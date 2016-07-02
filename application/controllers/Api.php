<?php defined('BASEPATH') OR exit('*');
/**
 * Created by PhpStorm.
 * User: Smith
 * Date: 2016.01.16.
 * Time: 13:40
 */
define('_CACHETIME', 12);

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

        //TODO: also look for default PT segments uri->segment(1);#api/v2/movies_list.json??? etc.
        if($this->agent->agent_string() == ''
        && (
                $this->input->get('cat') == 'Film/Hun/SD'//--BT v1.0.4/1.0.8a
            ||  $this->input->get('cat') == 'Film/Eng/SD'//--BT v1.0.4/1.0.8a
            ||  $this->input->get('cat') == 'Sorozat/Hun/SD'//--BT v1.0.8a
            ||  $this->input->get('cat') == 'Sorozat/Eng/SD'//--BT v1.0.8a
            ||  $this->input->get('cat') == 'Hun'
            ||  $this->input->get('cat') == 'Eng'
          ))
        {
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

        $this->runScraper($this->lib);

        $DATA = array(
            'movie_count'=>$this->movie_count,
            'limit'=>$this->limit,
            'page_number'=>$this->pagenum,
            'movies'=>$this->MOVIES);

        //TODO: error handling? ^^
        $RESPONSE = array('status'=>'ok', 'status_message'=>'Query was successful', 'data'=>$DATA);

        //$this->output->enable_profiler(TRUE);
        $this->output->cache(_CACHETIME);
        $this->output->set_header("Content-Type: application/json");

        $this->load->view('json', array('json'=>$RESPONSE));
    }

    private function runScraper($lib)
    {
        $lib = (string) $lib;
        $this->load->library($lib);

        if($this->load->is_loaded(ucfirst($lib))) {

            // Nem használt bejövő paraméterek:
            //&limit=50 # rogzitett BT beallitas; 
            //&with_rt_ratings=true # nincs kuldve BT v1.0.4 ota; 
            //&lang=hu # BT nyelv beallitas
            //&quality=1080p # BT-ben a quality valto kapcsolja, beallitasokban ki van kapcsolva a mezo; 1080p az alap BT v1.0.6 ota; 
            //&order_by=asc # van utalas a valtasra PT helpben (dblclick a filteren), de ugy tunik nem valthato; 

            // FONTOS! Cache miatt, minden új elemet fel kell venni ami megváltoztatja a response-ot!!!
            // ITT: app/config/config.php [cache_query_string]
            // TODO protip: eleve abbol a konfigbol jojjon a lista, és itt csak a kivételekkel foglalkozzunk
            $this->{$lib}->parseReq($this->input->get(array(
            #/kukorica/api/v2/list_movies.json?sort_by=date_added&limit=50&page=25&lang=hu&cat=Eng
            #/kukorica/api/v2/list_movies.json?sort_by=date_added&limit=50&page=1&query_term=popcorn&lang=hu&cat=Eng
                'cat',
                'page',
                'sort_by',
                'genre',
                'query_term'
            ), TRUE));

            $this->pagenum = $this->{$lib}->getPagenum();
            $this->limit = $this->{$lib}->getLimit();

            #$this->benchmark->mark('scrape_start');
            $SCRAPED_DATA = scrape_url($this->{$lib}->getScrapeUrl(), $this->{$lib}->getScrapeCookies());
            #$this->benchmark->mark('scrape_end');

            if ($this->{$lib}->loadContent($SCRAPED_DATA)) {
                #$this->benchmark->mark('parse_start');
                $this->{$lib}->parseMovies();
                #$this->benchmark->mark('parse_end');

                $this->movie_count = $this->{$lib}->getMovieCount();

                $site_movies = $this->{$lib}->getMovies();
                $torrent_ids = $this->{$lib}->getTorrentIds();
                $imdb_ids = $this->{$lib}->getImdbIds();

                if(is_array($site_movies) && count($site_movies) > 0) {
                    $this->MOVIES = array_values($this->parseMovieData($site_movies, $torrent_ids, $imdb_ids));
                }
                else log_message('debug', 'Empty result from URL: '.$this->{$lib}->getScrapeUrl().' $site_movies = '.print_r($site_movies,true));
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
            'imdb_id,year,title,synopsis,yt_trailer_code,locked,rating,'
           .'background_image,small_cover_image,medium_cover_image,large_cover_image');

        $new_torrents = array();
        $upd_torrents = array();
        $new_movies = array();

        $op_log = array();
        foreach($site_movies as $i => $movie)
        {
            $qualities = array(
                '1080p'=>'1080p',
                '720p' =>'720p',
                '480p' =>'480p',
                'hdrip'=>'HDRip'
            );

            foreach($movie['torrents'] as $j => $torrent)
            {
                //TODO: ha nincs imdb_id vagy nulla, ellenőrizze, hogy itt megvan-e, ill. lekérni az adatokat torrent_id alapján

                if($imdb_ids[$i] == 0) {
                    //TODO: fake imdb_code ha lehetséges, h megjelenjen mindenféleképpen
                    //PROTIP: 1 000 000 000 tartomanyban is lehetnek id-k
                }
                else
                {
                    if( ! isset( $op_log[ $imdb_ids[$i] ] ))
                    {
                        $op_log[ $imdb_ids[$i] ] = $i;

                        if(count($qualities) > 0)
                        {
                            if ( ! isset($qualities[ strtolower($torrent['quality']) ]))
                            {
                                $torrent['quality'] = array_shift($qualities);

                                $T =& $site_movies[$i]['torrents'];
                                $T[$j]['quality'] = $torrent['quality'];
                            }
                            else
                            {
                                unset($qualities[ strtolower($torrent['quality']) ]);
                            }

                            if ( ! isset($db_movies[$imdb_ids[$i]])) // ! movies
                            {
                                $movie['imdb_id'] = $imdb_ids[$i];
                                $movie['genres'] = implode(',', $movie['genres']);
                                $movie['cast'] = implode(',', $movie['cast']);
                                $movie['directors'] = implode(',', $movie['directors']);

                                $new_movies[] = $movie;
                            }
                            else
                            {
                                $M =& $db_movies[$imdb_ids[$i]];

                                if ($M['locked'] >= 2)///stupid, stupid stoopid locked!!!
                                {
                                    if($M['year']) $site_movies[$i]['year'] = $M['year'];

                                    if($site_movies[$i]['title'] == '' && $M['title']) {
                                        $site_movies[$i]['title'] = $M['title'];
                                    }
                                    if($M['synopsis']) $site_movies[$i]['synopsis'] = $M['synopsis'];

                                    if($M['background_image']) $site_movies[$i]['background_image'] = $M['background_image'];
                                    if($M['small_cover_image']) $site_movies[$i]['small_cover_image'] = $M['small_cover_image'];
                                    //csak ha nincs az oldalon, akkor hasznalja a tarolt boritot
                                    if(#$site_movies[$i]['medium_cover_image'] == '' && // eleg sok kep nem tolt be cdn-rol :/ (503/521)
                                        $M['medium_cover_image']) {
                                        $site_movies[$i]['medium_cover_image'] = $M['medium_cover_image'];
                                    }
                                    if($M['large_cover_image']) $site_movies[$i]['large_cover_image'] = $M['large_cover_image'];

                                    if($M['rating']) $site_movies[$i]['rating'] = round($M['rating'], 2);
                                    if($M['yt_trailer_code']) $site_movies[$i]['yt_trailer_code'] = $M['yt_trailer_code'];
                                }
                            }
                        }
                    }
                    else
                    {
                        $T =& $site_movies[$op_log[ $imdb_ids[$i] ]]['torrents'];

                        //TODO: BT v1.1.x a quality switch helyett rls lista
                        foreach($T as $ti => $torrent_item)
                        {
                            if(isset($qualities[ strtolower($torrent_item['quality']) ]))
                            {
                                unset($qualities[ strtolower($torrent_item['quality']) ]);
                            }
                        }

                        if(count($qualities) > 0) {
                            if ( ! isset($qualities[ strtolower($torrent['quality']) ]))
                            {
                                $torrent['quality'] = array_shift($qualities);
                            }
                            else
                            {
                                unset($qualities[ strtolower($torrent['quality']) ]);
                            }

                            $T[] = $torrent;
                        }

                        if(isset($site_movies[$i])) unset($site_movies[$i]);
                    }

                    if( ! isset( $db_torrents[ $torrent_ids[$i] ] )) {
                        $torrent['torrent_id']  = $torrent_ids[$i];//TODO: e helyett Bithu lib adatoknál torrent_id-t rögzíteni
                        $torrent['imdb_id']     = $imdb_ids[$i];

                        $new_torrents[] = $torrent;
                    }
                    #else if($db_torrents[ $torrent_ids[$i] ]['imdb_id'] == 0) {
                    #    $upd_torrents[ $torrent_ids[$i] ] = array('imdb_id' => $imdb_ids[$i]);
                    #}
                    //TODO: else { peers, seeds befrissítése ha rég volt updatelve }
                }
            }

            if(isset($site_movies[$i]) && $site_movies[$i]['title'] == '') {
                $title_tmp = explode('.', $site_movies[$i]['title_long']);
                array_pop($title_tmp);
                $site_movies[$i]['title'] = trim(implode(' ', $title_tmp));
            }
        }

        if(count($new_torrents) > 0) $this->kukorica->save_torrents($new_torrents);
        if(count($upd_torrents) > 0) foreach($upd_torrents as $tid => $data) { $this->kukorica->update_torrent($tid, $data); }
        if(count($new_movies) > 0) $this->kukorica->save_movies($new_movies);

        return $site_movies;
    }
}