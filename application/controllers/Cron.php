<?php defined('BASEPATH') OR exit('*');
/**
 * Created by PhpStorm.
 * User: Smith
 * Date: 2016.01.18.
 * Time: 16:11
 */
# */5 * * * * wget -O /dev/null http://bigfathead.eu/kukorica/cron

class Cron extends CI_Controller {
    private $tmdb_api_key = '4a1fe498a141725bb546cc9fd0a1a9e9';
    private $tmdb_language = 'hu';
    private $tmdb_movie_limit = NULL;
    private $tmdb_call_limit = 30;
    private $time_interval = 10;

    private $cron_output = array();

    public function __construct()
    {
        parent::__construct();

        if($this->uri->segment(1) == 'cron')
        {
            switch($this->uri->segment(2))
            {
                case 'elozetesek':
                    break;
                #case 'tmdb':
                default:
                    require_once( APPPATH . 'third_party/tmdb/tmdb-api.php' );
            }
        }
        else show_404();
    }

    public function index()
    {
        $_lock = fopen('cron.lock', 'w');
        if(flock($_lock, LOCK_EX|LOCK_NB)) {

            set_time_limit(0);
            ignore_user_abort(true);
            //log_message('info', 'cron started @ '.date('H:i:s'));

            switch($this->uri->segment(2))
            {
                case 'elozetesek':
                    $this->elozetesek();
                    break;
                default:
                    $this->tmdb_scrape();
            }

            if(count($this->cron_output) > 0) $this->load->view('cron', array('output'=>$this->cron_output));

            flock($_lock, LOCK_UN);
        }
        else log_message('info', 'cron blocked by flock()');
        fclose($_lock);
    }

    private function o($str)
    {
        $this->cron_output[] = '['.str_pad(microtime(true), 15, '0', STR_PAD_RIGHT).'] '.$str;
    }

    private function elozetesek()
    {
        $this->load->model('kukorica');
        $this->load->helper('api_helper');

        $movies = $this->kukorica->get_locked_movies_without_trailer(10);

        if(count($movies) > 0)
        {
            log_message('debug', 'elozetesek start. movies.length = '.count($movies));
            $this->o('elozetesek start. movies.length = '.count($movies)."\r\n");

            foreach($movies as $m) {
                $imdb_id = 'tt' . str_pad($m['imdb_id'], 7, '0', STR_PAD_LEFT);

                $u = 'http://ajax.googleapis.com/ajax/services/search/web?v=1.0&q='
                    . urlencode('"'.$m['title'].'" ')
                    . $m['year']
                    . urlencode(' magyar szinkronos előzetes ')
                    . 'site:youtube.com';
                $this->o('call & decode: /'.$imdb_id.'/ '.$m['title'].' ('.$m['year'].')');

                $resp = json_decode(scrape_url($u), TRUE);
                log_message('debug', 'responseStatus '.$resp['responseStatus']);
                $this->o('responseStatus '.$resp['responseStatus']);

                if(intval($resp['responseStatus']) >= '400')
                {
                    log_message('debug', 'terminating... ');
                    $this->o('terminating ');
                    break;
                }

                $res_num = 0;
                if( isset($resp['responseData'])
                    && isset($resp['responseData']['results'])
                    && is_array($resp['responseData']['results'])) {
                    $res_num = count($resp['responseData']['results']);
                }
                $this->o('resp.num = '.$res_num);

                $locked = 2;
                if($res_num > 0)
                {
                    $yt_trailer_code = '';

                    foreach($resp['responseData']['results'] as $search_result) {
                        $ueUrl = $search_result['unescapedUrl'];
                        $yt_trailer_code = get_yt_id($ueUrl);

                        $this->o('url: '.$search_result['title'].' (code: '.$yt_trailer_code.')');

                        if ($yt_trailer_code != '') {
                            $this->o('IMDB_ID: '.$imdb_id.' updated... <br/>'
                            .'<img src="http://img.youtube.com/vi/'.$yt_trailer_code.'/default.jpg" alt="" style=""/> '
                            .'<img src="http://img.youtube.com/vi/'.$yt_trailer_code.'/1.jpg" alt="" style=""/> '
                            .'<img src="http://img.youtube.com/vi/'.$yt_trailer_code.'/2.jpg" alt="" style=""/> '
                            .'<img src="http://img.youtube.com/vi/'.$yt_trailer_code.'/3.jpg" alt="" style=""/> '
                            .'<a href="'.$search_result['url'].'">'.$yt_trailer_code.'</a> <br style="clear:both;"/><br/>');

                            $locked = 5;

                            break;
                        }
                        else
                        {
                            log_message('debug', 'Nincs yt id találat ('.$imdb_id.'): ' . $u);
                            $this->o('Nincs yt id találat ('.$imdb_id.'): '.$u.'<br/>');

                            $locked = 4;
                        }
                    }
                }
                else
                {
                    log_message('debug', 'Nincs yt előzetes találat ('.$imdb_id.'): '.$u);
                    $this->o('Nincs yt előzetes találat ('.$imdb_id.'): '.$u.'<br/>');

                    $locked = 4;
                }

                $this->kukorica->update_movie($m['imdb_id'], array('yt_trailer_code' => $yt_trailer_code, 'locked'=>$locked));

                $r = rand(2,5);//igy talan nem blokkol egybol
                $this->o('sleep: '.$r);
                sleep($r);
                $this->o('continue.'."\r\n");
            }
            log_message('debug', 'elozetesek finish.');

            $this->o('elozetesek finish. ');
        }
    }

    private function tmdb_scrape()
    {
        $this->load->model('kukorica');

            $movie_ids = $this->kukorica->get_unlocked_movie_ids($this->tmdb_movie_limit);

            if (count($movie_ids) > 0) {
                log_message('info', 'cron started for ' . count($movie_ids) . ' item @ ' . date('H:i:s'));

                $tmdb = new TMDB($this->tmdb_api_key, $this->tmdb_language, false);
                $ac = 0;
                $timer = time();

                $tmdb_config = $tmdb->getConfig();
                $img_config = $tmdb_config['images'];

                foreach ($movie_ids as $i => $movie_id) {
                    $imdb_id = 'tt' . str_pad($movie_id['imdb_id'], 7, '0', STR_PAD_LEFT);
                    
                    if ($ac >= $this->tmdb_call_limit) {
                        $this->go_sleep($timer, $ac);
                    }
                    $movie = $tmdb->findMovie($imdb_id);
                    $ac++;

                    if ($movie === FALSE || $tmdb->getLastHttpCode() >= 400) //érvénytelen válasz
                    {
                        log_message('error', 'cron error: '.$tmdb->getLastHttpCode().' > 400');
                    }
                    elseif ($movie === TRUE) //nem található film ezzel az id-vel
                    {
                        log_message('error', 'cron error: $movie === true @ '.$imdb_id);
                        $this->kukorica->update_movie($imdb_id, array('locked' => 1));
                    }
                    else {
                        $movie->setAPI($tmdb);

                        $locked = 2;

                        if ($ac >= $this->tmdb_call_limit) {
                            $this->go_sleep($timer, $ac);
                        }
                        $movie->loadTrailer();
                        $ac++;

                        //TODO: (tmdb) id-t tárolni kellene, locked helyett flagelni az infókat :-/
                        if($movie->getTrailer() != '')
                        {
                            $locked = 3;
                        }
                        else
                        {
                            $tmdb->setLang('en');

                            if ($ac >= $this->tmdb_call_limit) {
                                $this->go_sleep($timer, $ac);
                            }
                            $movie->loadTrailer();
                            $ac++;

                            $tmdb->setLang($this->tmdb_language);
                        }

                        $movie_data = array(
                            'locked' => $locked,
                            'background_image' => $img_config['base_url'] . $img_config['backdrop_sizes'][2] . $movie->getBackdrop(),
                            'synopsis' => $movie->get('overview'),
                            'year' => strstr($movie->get('release_date'), '-', true),
                            'small_cover_image' => $img_config['base_url'] . $img_config['poster_sizes'][2] . $movie->getPoster(),
                            'medium_cover_image' => $img_config['base_url'] . $img_config['poster_sizes'][3] . $movie->getPoster(),
                            'large_cover_image' => $img_config['base_url'] . $img_config['poster_sizes'][5] . $movie->getPoster(),
                            'title' => $movie->getTitle(),
                            //'rating' => $movie->get('vote_average')
                            'yt_trailer_code' => $movie->getTrailer()
                        );

                        $this->kukorica->update_movie($imdb_id, $movie_data);
                    }
                }

                log_message('info', 'cron finished @ ' . date('H:i:s'));
            }

    }
    
    private function go_sleep(&$timer, &$ac)
    {
        if ($this->time_interval > 0) {
            $sleep_time = $this->time_interval - (time() - $timer);
            if ($sleep_time < 1) $sleep_time = 1;

            log_message('info', 'cron sleep for: ' . $sleep_time . ' @ ' . date('H:i:s'));
            sleep($sleep_time);
            log_message('info', 'cron continued @ ' . date('H:i:s'));
            $ac = 0;
            $timer = time();
        }
    }
}