<?php defined('BASEPATH') OR exit('*');
/**
 * Created by PhpStorm.
 * User: Smith
 * Date: 2016.01.16.
 * Time: 13:40
 */

class Api extends CI_Controller {

    private $pagenum = 0;
    private $movie_count = 0;
    private $limit = 50;
    private $site_id = 0;
    private $lib = 'kukica';

    private $MOVIES = array();

    public function __construct()
    {
        parent::__construct();

        $this->load->library('user_agent');

        if($this->agent->agent_string() == ''
        && (
                $this->input->get('cat') == 'Film/Hun/SD'//--remove
            ||  $this->input->get('cat') == 'Film/Eng/SD'//--remove
            ||  $this->input->get('cat') == 'Hun'
            ||  $this->input->get('cat') == 'Eng'
          ))
        {
            $this->config->load('api');
            $this->load->helper('api_helper');
        }
        else show_404();
    }

    public function index()
    {
        if(count($_REQUEST)>0) log_message('debug', '('.$_SERVER['REMOTE_ADDR'].') '.$_SERVER['REQUEST_URI']);

        if(strpos($this->uri->segment(1), 'carpathians') === 0)
        {
            $this->site_id = 2;
            $this->lib = 'carpat';
        }
        else # if(strpos($this->uri->segment(1), 'bithumen') === 0)
        {
            $this->site_id = 1;
            $this->lib = 'bithu';
        }

        $this->runLib($this->lib);

        $DATA = array(
            'movie_count'=>$this->movie_count,
            'limit'=>$this->limit,
            'page_number'=>$this->pagenum,
            'movies'=>$this->MOVIES);

        //TODO: error handling?
        $RESPONSE = array('status'=>'ok', 'status_message'=>'Query was successful', 'data'=>$DATA);

        //$this->output->enable_profiler(TRUE);
        $this->output->cache(_CACHETIME);
        $this->output->set_header("Content-Type: application/json");

        $this->load->view('json', array('json'=>$RESPONSE));
    }

    private function runLib($lib)
    {
        $lib = (string) $lib;
        $this->load->library($lib);

        if($this->load->is_loaded(ucfirst($lib))) {

            $this->{$lib}->parse_req();
            $this->pagenum = $this->{$lib}->getPagenum();
            $this->limit = $this->{$lib}->getLimit();

            #$this->benchmark->mark('scrape_start');
            $HTML = scrape_url($this->{$lib}->getScrapeURL(), $this->{$lib}->getScrapeCookies());
            #$this->benchmark->mark('scrape_end');

            if ($this->{$lib}->parseHTML($HTML)) {
                #$this->benchmark->mark('parse_start');
                $this->{$lib}->parseTable();
                #$this->benchmark->mark('parse_end');

                $this->movie_count = $this->{$lib}->getMovieCount();

                $site_movies = $this->{$lib}->getMovies();
                $torrent_ids = $this->{$lib}->getTorrentIds();
                $imdb_ids = $this->{$lib}->getImdbIds();

                $this->MOVIES = $this->parseMovieData($site_movies, $torrent_ids, $imdb_ids);
            }
        }
        else log_message('error', ucfirst($lib).' library not loaded.');

        #echo 'scrape: '.$this->benchmark->elapsed_time('scrape_start', 'scrape_end')."\n";
        #echo 'parse: '.$this->benchmark->elapsed_time('parse_start', 'parse_end')."\n";
    }

    private function parseMovieData($site_movies, $torrent_ids, $imdb_ids)
    {
        $this->load->model('kukorica');
        $this->kukorica->setSiteId($this->site_id);

        $db_torrents = $this->kukorica->get_torrents_by_ids($torrent_ids);
        $db_movies = $this->kukorica->get_movies_by_ids($imdb_ids,
            'imdb_id,locked,background_image,synopsis,year,small_cover_image,medium_cover_image,large_cover_image,title,yt_trailer_code');//,rating

        $new_torrents = array();
        $new_movies = array();

        $op_log = array();
        foreach($site_movies as $i => $movie)
        {
            foreach($movie['torrents'] as $j => $torrent) {
                if( ! isset( $db_torrents[ $torrent_ids[$i] ] )) { // ! torrents
                    $torrent['site_id']     = $this->site_id;
                    $torrent['torrent_id']  = $torrent_ids[$i];
                    $torrent['imdb_id']     = $imdb_ids[$i];

                    $new_torrents[] = $torrent;
                }
                //TODO: else { peers, seeds befrissítése ha rég volt updatelve }
                //TODO: ha nincs imdb_id vagy nulla, ellenőrizze, hogy itt megvan-e, ill. lekérni az adatokat torrent_id alapján

                if($imdb_ids[$i] != 0) {
                    if(isset( $op_log[ $imdb_ids[$i] ] ))
                    {
                        # QUALITY 2 RLS HAX #
                        $qualities = array('720p', '1080p', '480p', 'HDRip');
                        $tnum = count($site_movies[$op_log[ $imdb_ids[$i] ]]['torrents']);
                        if(isset($qualities[$tnum])) {
                            //TODO: setup quality by release name (BDRip,DVDRip,TVRip,HDTV,DVBRip,Web-DL)
                            if($tnum == 1 && intval($torrent['size_bytes']) < intval($site_movies[$op_log[ $imdb_ids[$i] ]]['torrents'][0]['size_bytes'])) {
                                $torrent['quality'] = $qualities[0];
                                $site_movies[$op_log[ $imdb_ids[$i] ]]['torrents'][0]['quality'] = $qualities[1];
                            }//TODO: elseif($tnum>1)*prio by seeders xor BT RLS switcher*
                            else {
                                $torrent['quality'] = $qualities[$tnum];
                            }

                            $site_movies[$op_log[ $imdb_ids[$i] ]]['torrents'][] = $torrent;
                        }
                        # /QUALITY 2 RLS HAX #
                    }
                    else {
                        $op_log[ $imdb_ids[$i] ] = $i;

                        if (!isset($db_movies[ $imdb_ids[$i] ])) // ! movies
                        {
                            $movie['imdb_id'] = $imdb_ids[$i];
                            $movie['genres'] = implode(',', $movie['genres']);
                            $movie['cast'] = implode(',', $movie['cast']);
                            $movie['directors'] = implode(',', $movie['directors']);
                            //rating?
                            //yt_link

                            $new_movies[] = $movie;
                        } else {
                            $M =& $db_movies[ $imdb_ids[$i] ];

                            if ($M['locked'] >= 2) {

                                $site_movies[$i]['background_image'] = $M['background_image'];
                                $site_movies[$i]['synopsis'] = $M['synopsis'];
                                $site_movies[$i]['year'] = $M['year'];
                                $site_movies[$i]['small_cover_image'] = $M['small_cover_image'];
                                $site_movies[$i]['medium_cover_image'] = $M['medium_cover_image'];
                                $site_movies[$i]['large_cover_image'] = $M['large_cover_image'];
                                $site_movies[$i]['title'] = $M['title'];
                                //$site_movies[$i]['rating'] = $M['rating'];
                                $site_movies[$i]['yt_trailer_code'] = $M['yt_trailer_code'];
                            }
                        }
                    }
                }
            }
        }

        if(count($new_torrents) > 0) $this->kukorica->save_torrents($new_torrents);
        if(count($new_movies) > 0) $this->kukorica->save_movies($new_movies);

        return $site_movies;
    }
}