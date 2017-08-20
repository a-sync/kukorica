<?php defined('BASEPATH') OR exit('*');
/**
 * Created by PhpStorm.
 * User: Smith
 * Date: 2017.07.08.
 * Time: 10:44
 */

# ncore user #
define('_NICK', 'GecMan');
define('_PASS', 'f9b4fd9ef9d9b30e1fdcf71d5102b053');
define('_LIMIT', 50);
# /ncore user #

define('_DOMAIN', 'http://ncore.cc');
define('_URL', _DOMAIN.'/torrents.php?');
define('_PASSKEY', '{PASSKEY}');
define('_TORRENT', _DOMAIN.'/torrents.php?action=download&id={ID}&key={PASSKEY}');

require_once( APPPATH . 'third_party/abstract/Scraper.abstract.php' );

class Ncore extends Scraper {
    private $params;
    private $torrenttable;
    private $x;

    protected $pagenum;
    protected $limit = _LIMIT;//felhasználó beállítástól függ (1-100)

    protected $movie_count = 10000;
    protected $movies;
    protected $torrent_ids;
    protected $imdb_ids;

    protected function getParams($key = FALSE)
    {
        if($key !== FALSE) return $this->params[$key];
        else return $this->params;
    }

    public function getScrapeUrl()
    {
        $scrape_query = get_url_query($this->getParams());
        return _URL.$scrape_query;
    }

    public function getScrapeCookies()
    {
        return 'nick='._NICK.'; pass='._PASS.'; nyelv=hu ';
    }

    public function parseReq($INPUT)
    {
        if(is_numeric($INPUT['page'])){
            $this->pagenum = intval($INPUT['page']);
        } else {
            $this->pagenum = 1;
        }

        $params = array(
            'oldal' => $this->pagenum,
            'hogyan' => 'DESC'
        );

        switch($INPUT['cat'])
        {
            case 'Eng':
                $params['tipus'] = 'xvid';
                break;
            default:
                $params['tipus'] = 'xvid_hun';
        }

        switch($INPUT['sort_by'])
        {
            case 'views':
            case 'popularity':
            case 'download_count': # megtekintések
                $params['miszerint'] = 'times_completed';
                break;
            case 'trending': # legaktívabbak
            case 'trending_score':
                $params['miszerint'] = 'leechers';
                break;
            case 'seeds': # seedek
                $params['miszerint'] = 'seeders';
                break;
            default://date_added # utoljára hozzáadva
                $params['miszerint'] = 'fid';
        }

        switch(strtolower($INPUT['genre']))
        {
            case 'action':      $params['tags'] = 'akció'; break;
            case 'adventure':   $params['tags'] = 'kaland'; break;
            case 'animation':   $params['tags'] = 'animáció'; break;
            case 'biography':   $params['tags'] = 'életrajz'; break;
            case 'comedy':      $params['tags'] = 'vígjáték'; break;
            case 'crime':       $params['tags'] = 'bűnügyi'; break;
            case 'documentary': $params['tags'] = 'dokumentumfilm,ismeretterjesztő'; break;
            case 'drama':       $params['tags'] = 'dráma'; break;
            case 'family':      $params['tags'] = 'családi'; break;
            case 'fantasy':     $params['tags'] = 'fantasy'; break;
            //case 'film-noir':
            //case 'filmnoir':    $params['tags'] = ''; break;
            //case 'gameshow':    $params['tags'] = ''; break;
            case 'history':     $params['tags'] = 'történelmi'; break;
            case 'horror':      $params['tags'] = 'horror'; break;
            case 'music':       $params['tags'] = 'zene'; break;
            case 'musical':     $params['tags'] = 'musical'; break;
            case 'mystery':     $params['tags'] = 'misztikus'; break;
            //case 'news':        $params['tags'] = ''; break;
            case 'reality':     $params['tags'] = 'valóságshow'; break;
            case 'romance':     $params['tags'] = 'romantikus'; break;
            case 'sci-fi':
            case 'scifi':       $params['tags'] = 'sci-fi'; break;
            case 'short':       $params['tags'] = 'rövidfilm'; break;
            case 'sport':       $params['tags'] = 'sport'; break;
            //case 'talkshow':    $params['tags'] = ''; break;
            case 'thriller':    $params['tags'] = 'thriller'; break;
            case 'war':         $params['tags'] = 'háborús'; break;
            case 'western':     $params['tags'] = 'western'; break;
        }

        if($INPUT['query_term'])
        {
            ////$qs = mb_convert_encoding($INPUT['query_term'], 'ISO-8859-2');// bH <3

            $params['mire'] = urlencode($INPUT['query_term']);
            //.x, .y form vals!!
            $params['miben'] = 'name';
        }

        $this->params = $params;

        return $this;
    }

    public function loadContent($SCRAPED_DATA)
    {
        ////$SCRAPED_DATA = mb_convert_encoding($SCRAPED_DATA, 'HTML-ENTITIES', 'ISO-8859-2');

        $dom = new DOMDocument;
        $dom->strictErrorChecking = FALSE;
        //$dom->validateOnParse = true;
        $previous_errors = libxml_use_internal_errors(TRUE);

        if($dom->loadHTML($SCRAPED_DATA)) {

            libxml_clear_errors();
            libxml_use_internal_errors($previous_errors);
            //$this->torrenttable = $dom->getElementById('torrenttable');

            $this->x = new DOMXPath($dom);
            $el = $this->x->query('//div[@class="box_torrent_all"]');

            $this->torrenttable = $el->item(0);
            if(is_object($this->torrenttable))
            {
                return TRUE;
            }
        }
        else $this->torrenttable = NULL;

        $this->movies = NULL;
        $this->torrent_ids = NULL;
        $this->imdb_ids = NULL;

        return FALSE;
    }

    public function parseMovies()
    {
        $this->movies = array();
        $this->torrent_ids = array();
        $this->imdb_ids = array();

        $rows = $this->x->query('.//div[starts-with(@class,"box_nagy")]', $this->torrenttable);

        if ($rows === false) {
            log_message('error', 'ncore request parsing failed');
            $rows = [];
        }

        foreach($rows as $i => $node) {
            $cover_image = '';
            $imdb_id = '';
            $imdb_rating = '';
            $info_url = '';
            $genres = array();

            $cols = $this->x->query('.//div[starts-with(@class,"box_")]', $node);

            $torrent_link = $cols->item(0)->getElementsByTagName('a')->item(0);

            $id = intval(substr($torrent_link->getAttribute('href'), 31));

            $title_long = $torrent_link->getAttribute('title');
            $title = $title_long;

            $info_ico = $this->x->query('.//img[@class="infobar_ico"]', $cols->item(0));
            if ($info_ico->length > 0) {
                $info_ico = $info_ico->item(0);

                if ($info_ico->hasAttribute('onmouseover')) {
                    $cover_image = $this->parse_poster($info_ico->getAttribute('onmouseover'));
                }
            }

            $info_siterank = $this->x->query('.//div[@class="siterank"]', $cols->item(0));
            if ($info_siterank->length > 0) {
                $info_siterank = $info_siterank->item(0);

                $info_title = $info_siterank->getElementsByTagName('span');
                if ($info_title->length > 0) {
                    $info_title = $info_title->item(0);

                    if ($info_title->hasAttribute('title')) {
                        $title = $info_title->getAttribute('title');
                    }
                    else {
                        $title = $info_title->textContent;
                    }
                }

                $info_link = $this->x->query('.//a[@class="infolink"]', $info_siterank);
                if ($info_link->length > 0) {
                    $info_link = $info_link->item(0);

                    $info_href = $info_link->getAttribute('href');
                    if (strpos($info_href, 'imdb.com/title/') !== false) {
                        $tmp = substr($info_href, strpos($info_href, 'imdb.com/title/') + 15);
                        $imdb_id = strstr($tmp, '/', true);
                        $imdb_rating = str_replace(array('[imdb: ', ']'), '', $info_link->textContent);
                        $imdb_rating = floatval($imdb_rating);
                    }

                    $info_url = trim(str_replace('https://dereferer.me/?', '', $info_href));
                    if(stripos($info_url, 'http') !== 0) $info_url = '';
                }
            }

            $title_slug = slugify($title);

            $date_unix = $this->parse_date($cols->item(1)->textContent);
            $date = date('Y-m-d H:i:s', $date_unix);

            $size_values = $this->parse_size($cols->item(2)->textContent);
            $size = $size_values['text'];
            $size_bytes = $size_values['bytes'];

            $seeds = intval(trim($cols->item(4)->textContent));
            $peers = intval(trim($cols->item(5)->textContent));

            $quality = '720p';
            if( stripos($title_long, '.x264.') || stripos($title_long, '.x264-'))
            {
                $quality = '1080p';
            }
            else if( stripos($title_long, '.xvid.') || stripos($title_long, '.xvid-'))
            {
                $quality = '720p';
            }

            // KIZÁRÁSOK
            if($seeds == 0 || !$this->is_playable($size_bytes)) continue;

            // DATA MODEL TODO: külön absztrakt osztály adja vissza az alap modeleket db-vel szinkronba hozva
            $TORRENT = array(
                'url' => str_replace(array('{PASSKEY}', '{ID}'), array(_PASSKEY, $id), _TORRENT),
                'hash' => "",
                'quality' => $quality,
                'seeds' => $seeds,
                'peers' => $peers,
                'size' => $size,
                'size_bytes' => (string) $size_bytes,
                'date_uploaded' => $date,
                'date_uploaded_unix' => $date_unix
                //TODO: torrent_id-t rögzíteni itt, és Api controllerben kiiktatni a felülírást
            );

            $MOVIE = array(
              'id' => $id
            , 'url' => $info_url
            , 'imdb_code' => $imdb_id
            , 'title' => $title
            , 'title_long' => $title_long
            , 'slug' => $title_slug
            , 'year' => 0
            , 'rating' => $imdb_rating
            , 'runtime' => 0
            , 'genres' => $genres
            , 'cast' => array()
            , 'directors' => array()
            , 'language' => 'Hungarian'
            , 'mpa_rating' => 'E'
            , 'synopsis' => ''
            , 'yt_trailer_code' => ''
            , 'google_video' => ''
            , 'background_image' => ''
            , 'small_cover_image' => $cover_image
            , 'medium_cover_image' => $cover_image
            , 'large_cover_image' => $cover_image
            , 'state' => 'ok'
            , 'torrents' => array($TORRENT)
            , 'date_uploaded' => $date
            , 'date_uploaded_unix' => $date_unix

            );

            $this->movies[] = $MOVIE;
            $this->torrent_ids[] = $id;//TODO torrenthez tárolandó
            $this->imdb_ids[] = ($imdb_id == '') ? 0 : intval(trim($imdb_id, 't'));
        }

        return $this;
    }

    /** HELPERS **/
    private function is_playable($size_bytes)
    {
        if($size_bytes < 52428800) return FALSE;//50MB

        return TRUE;
    }

    private function parse_date($str)
    {
        $tmp = substr_replace($str, ' ', 10, 0);

        return strtotime($tmp);
    }

    private function parse_size($str)
    {
        $bytes = 0;
        $tmp = explode(' ', strtolower($str));
        switch ($tmp[1]) {
            case 'gb':
                $bytes = floatval($tmp[0]) * 1073741824;
                break;
            case 'mb':
                $bytes = floatval($tmp[0]) * 1048576;
                break;
        }

        return array('text' => $str, 'bytes' => round($bytes));
    }

    private function parse_poster($str)
    {
        $pic_url = strstr(substr(strstr($str, "'"), 1), "'", true);

        return $pic_url;
    }
}
