<?php defined('BASEPATH') OR exit('*');
/**
 * Created by PhpStorm.
 * User: Smith
 * Date: 2016.01.18.
 * Time: 16:11
 */
# */5 * * * * wget -O /dev/null http://bigfathead.eu/kukorica/cron

class Cron extends CI_Controller {
    private $api_key = '4a1fe498a141725bb546cc9fd0a1a9e9';
    private $language = 'hu';
    private $movie_limit = NULL;
    private $call_limit = 30;
    private $time_interval = 10;

    public function __construct()
    {
        parent::__construct();

        if($this->uri->segment(1) == 'cron')
        {
            switch($this->uri->segment(2))
            {
                case 'elozetesek':
                    break;
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

            flock($_lock, LOCK_UN);
        }
        else log_message('info', 'cron blocked by flock()');
        fclose($_lock);
    }
    
    private function elozetesek()
    {
        die('tentebaba');
        $this->load->model('kukorica');
        $this->load->helper('api_helper');

        $movies = $this->kukorica->get_movies_without_trailer(10);

        if(count($movies) > 0)
        {
            log_message('debug', 'elozetesek start. movies.length = '.count($movies));
            foreach($movies as $m) {
                $u = 'http://ajax.googleapis.com/ajax/services/search/web?v=1.0&q='
                    . urlencode('"'.$m['title'].'" ')
                    . $m['year']
                    . urlencode(' magyar szinkronos előzetes ')
                    . 'site:youtube.com';

                $resp = json_decode(scrape_url($u), TRUE);

                if(count($resp['responseData']['results']) > 0)
                {
                    $yt = $resp['responseData']['results'][0]['unescapedUrl'];
                    $yt_trailer_code = get_yt_id($yt);

                    if($yt_trailer_code != '')
                    {
                        $this->kukorica->update_movie($m['imdb_id'], array('yt_trailer_code'=>$yt_trailer_code));
                    }
                    else log_message('debug', 'Nincs yt id: '.$u);
                }
                //else TODO: locked 2
                else log_message('debug', 'Nincs yt előzetes: '.$u);

                sleep(rand(1,2));//iggy talan nem blokkol
            }
            log_message('debug', 'elozetesek finish.');
        }
    }
    
    private function tmdb_scrape()
    {
        $this->load->model('kukorica');

            $movie_ids = $this->kukorica->get_unlocked_movie_ids($this->movie_limit);

            if (count($movie_ids) > 0) {
                log_message('info', 'cron started for ' . count($movie_ids) . ' item @ ' . date('H:i:s'));



                $tmdb = new TMDB($this->api_key, $this->language, false);
                $ac = 0;
                $timer = time();

                $tmdb_config = $tmdb->getConfig();
                $img_config = $tmdb_config['images'];

                foreach ($movie_ids as $i => $movie_id) {
                    $imdb_id = 'tt' . str_pad($movie_id['imdb_id'], 7, '0', STR_PAD_LEFT);
                    
                    if ($ac >= $this->call_limit) {
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
                        log_message('error', 'cron error: $movie === true');
                        $this->kukorica->update_movie($imdb_id, array('locked' => 1));
                    }
                    else {
                        $movie->setAPI($tmdb);
                        
                        if ($ac >= $this->call_limit) {
                            $this->go_sleep($timer, $ac);
                        }
                        $movie->loadTrailer();
                        $ac++;
                        
                        //if($movie->getTrailer()=='')//külön kron az üres trailereknek (tmdb) id-t tárolni kellene, locked flaget bővíteni :-/
                        //$tmdb->setLang('en');
                        //$movie->loadTrailer();
                        //$tmdb->setLang('hu');

                        $movie_data = array(
                            'locked' => 1,
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