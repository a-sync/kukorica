<?php defined('BASEPATH') OR exit('*');
/**
 * Created by PhpStorm.
 * User: Smith
 * Date: 2016.01.16.
 * Time: 15:13
 */

define('_UID', '55830');
define('_PASS', 'a628e51fe55b66e4932dad014dc71bbe');
define('_LIMIT', 50);

define('_DOMAIN', 'http://bithumen.be');
define('_URL', _DOMAIN.'/browse.php?');
define('_PASSKEY', '{PASSKEY}');
define('_TORRENT', _DOMAIN.'/download/{PASSKEY}/{ID}.torrent');

require_once( APPPATH . 'third_party/abstract/Scraper.abstract.php' );

class Bithu extends Scraper {
    private $params;
    private $torrenttable;

    protected $pagenum;
    protected $limit = _LIMIT;//felhasználó beállítástól függ (1-100)

    protected $movie_count = 10000;//TODO: pager div utolsó elemének utolsó száma
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
        return 'uid='._UID.'; pass='._PASS;
    }

    //TODO: <input type="hidden" value="589f7" name="vxx"> előző küldése
    public function parseReq($INPUT)
    {
        if(is_numeric($INPUT['page'])){
            $this->pagenum = intval($INPUT['page']);
        } else {
            $this->pagenum = 1;
        }

        $params = array(
            'page' => $this->pagenum - 1,
            'd' => 'DESC'//order_by=asc
        );

        switch($INPUT['cat'])
        {
            case 'Sorozat/Eng/SD':
                $params['cat'] = '26';
                break;
            case 'Sorozat/Hun/SD':
                $params['cat'] = '7';
                break;
            case 'Film/Eng/SD':
            case 'Eng':
                $params['cat'] = '19';
                break;
            /*case 'Film/Hun/SD':
            case 'Hun':
                $params['cat'] = '23';
                break;*/
            default:
                $params['cat'] = '23';
        }

        switch($INPUT['sort_by'])
        {
            case 'views':
            case 'popularity':
            case 'download_count': # megtekintések
                $params['sort'] = 'times_completed';
                break;
            case 'trending': # legaktívabbak
                $params['sort'] = 'activity';
                break;
            case 'seeds': # seedek
                $params['sort'] = 'seeders';
                break;
            case 'rating': # értékelés
                $params['sort'] = 'rating';
                break;
            default://date_added # utoljára hozzáadva
                $params['sort'] = 'added';
        }

        switch($INPUT['genre'])
        {
            //case 'All': $params['genre'] = 0; break;
            case 'Action': $params['genre'] = 1; break;
            case 'Adventure': $params['genre'] = 2; break;
            case 'Animation': $params['genre'] = 3; break;
            case 'Biography': $params['genre'] = 4; break;
            case 'Comedy': $params['genre'] = 5; break;
            case 'Crime': $params['genre'] = 6; break;
            case 'Documentary': $params['genre'] = 7; break;
            case 'Drama': $params['genre'] = 8; break;
            case 'Family': $params['genre'] = 9; break;
            case 'Fantasy': $params['genre'] = 10; break;
            case 'Film-Noir': $params['genre'] = 11; break;
            case 'Gameshow': $params['genre'] = 12; break;
            case 'History': $params['genre'] = 13; break;
            case 'Horror': $params['genre'] = 14; break;
            case 'Music': $params['genre'] = 15; break;
            case 'Musical': $params['genre'] = 16; break;
            case 'Mystery': $params['genre'] = 17; break;
            case 'News': $params['genre'] = 18; break;
            case 'Reality': $params['genre'] = 19; break;
            case 'Romance': $params['genre'] = 20; break;
            case 'Sci-Fi': $params['genre'] = 21; break;
            case 'Short': $params['genre'] = 22; break;
            case 'Sport': $params['genre'] = 23; break;
            case 'Talkshow': $params['genre'] = 24; break;
            case 'Thriller': $params['genre'] = 25; break;
            case 'War': $params['genre'] = 26; break;
            case 'Western': $params['genre'] = 27; break;
        }

        if($INPUT['query_term'])
        {
            $qs = mb_convert_encoding($INPUT['query_term'], 'ISO-8859-2');

            $params['search'] = urlencode($qs);
        }

        $this->params = $params;

        return $this;
    }

    //TODO: <input type="hidden" value="589f7" name="vxx"> berögzítése
    public function loadContent($SCRAPED_DATA)
    {
        $dom = new DOMDocument;
        $dom->strictErrorChecking = FALSE;
        //$dom->validateOnParse = true;
        $previous_errors = libxml_use_internal_errors(TRUE);

        if($dom->loadHTML($SCRAPED_DATA)) {

            libxml_clear_errors();
            libxml_use_internal_errors($previous_errors);
            //return $dom->getElementById('torrenttable');

            $x = new DOMXPath($dom);
            $el = $x->query("//table[@id='torrenttable']");

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

        $rows = $this->torrenttable->getElementsByTagName('tr');
        foreach($rows as $i => $node) {
            if ($i == 0) continue;
            $cols = $node->getElementsByTagName('td');

            $id = 0;
            $title = '';
            $title_long = '';
            $title_slug = '';
            $imdb_id = '';
            $imdb_rating = '';
            $info_url = '';
            $genres = array();
            $small_cover_image = '';
            $medium_cover_image = '';

            $spans = $cols->item(1)->getElementsByTagName('span');
            foreach ($spans as $k => $span) {
                $genre_links = $span->getElementsByTagName('a');
                if ($genre_links->length > 0) {
                    foreach ($genre_links as $l => $genre) {
                        $genres[] = $genre->textContent;
                    }
                } else {
                    $title = $span->textContent;
                    $title_slug = slugify($title);
                }
            }

            $links = $cols->item(1)->getElementsByTagName('a');
            foreach ($links as $j => $link) {
                $href = $link->getAttribute('href');
                //if($id==0 && strpos($href, 'details.php?id=')===0) {
                if ($j == 0) {
                    $id = intval(substr($href, 15));
                    $title_long = $link->textContent;
                    if ($title == '') {
                        $title = explode('.', $title_long);
                        array_pop($title);
                        $title = implode(' ', $title);
                        $title_slug = slugify($title);
                    }
                } elseif (strpos($href, 'imdb.com/title/') !== false) {
                    $tmp = substr($href, strpos($href, 'imdb.com/title/') + 15);
                    $imdb_id = strstr($tmp, '/', true);
                    $imdb_rating = str_replace(array('[imdb: ', ']'), '', $link->textContent);
                    $imdb_rating = floatval($imdb_rating);
                } elseif ($link->hasAttribute('alt') && $link->getAttribute('alt') == 'info') {
                    $info_url = str_replace('http://anonym.to/?', '', $href);
                    if ($link->hasAttribute('onmouseover')) {
                        $mo = $this->parse_poster($link->getAttribute('onmouseover'));
                        $small_cover_image = $mo['small'];
                        $medium_cover_image = $mo['orig'];
                    }
                }
            }

            $filenum = intval($cols->item(2)->textContent);

            $date_unix = $this->parse_date($cols->item(4)->textContent);
            $date = date('Y-m-d H:i:s', $date_unix);

            $size_values = $this->parse_size($cols->item(5)->textContent);
            $size = $size_values['text'];
            $size_bytes = $size_values['bytes'];

            $seeds = intval(trim($cols->item(7)->textContent));
            $peers = explode(' / ', $cols->item(8)->textContent);
            $peers = intval($peers[1]);

            $title_cut = explode('-', $title_long);
            $releaser = array_pop($title_cut);
            $title_cut = implode('-', $title_cut);//torrent név releaser nélkül

            $quality = '720p';
            if( stripos($title_long, '.x264.')
             || stripos($title_long, '.x264-'))
            {
                $quality = '1080p';
            }
            else
            #if( stripos($title_long, '.xvid.')
            # || stripos($title_long, '.xvid-'))
            {
                $quality = '720p';
            }

            // KIZÁRÁSOK
            if($size_bytes < 52428800) continue;//50MB
            elseif($seeds == 0) continue;

            $filenum_treshold = ($this->params['cat'] == '19') ? 12 : 15;//Eng = 12; Hun = 15
            if($filenum >= $filenum_treshold)
            {
                // HA:
                //      több mint $filenum_treshold fájl
                // AKKOR:

                if(
                    (
                       ($filenum <= 50  && $size_bytes > 2147483648 )//2GB //itt azert rezeg a lec TODO: ha tárolva lesz, lekérni a gyanús fájlszámú / méretű torrenteket
                    || ($filenum <= 100 && $size_bytes > 10737418240)//10GB
                    || ($filenum <= 200 && $size_bytes > 21474836480)//20GB
                    )
                &&  (
                       stripos($title_cut, '.collection.') !== false
                    || stripos($title_cut, '.pack.') !== false
                    || stripos($title_cut, '.osszes.') !== false
                    || stripos($title_cut, '.gyujtemeny.') !== false
                    || stripos($title_cut, '.gyujtemenye.') !== false
                    || stripos($title_cut, '.boxset.') !== false
                    || stripos($title_cut, '.i-') !== false
                    || stripos($title_cut, '.1-') !== false
                    || stripos($title_cut, '.1.2.') !== false
                    || stripos($title_cut, 'logy.') !== false
                    || stripos($title_cut, 'logia.') !== false
                    )
                )
                {
                    //   HA:
                    //        max. 50  fájl ÉS nagyobb mint 2GB
                    //        VAGY
                    //        max. 100 fájl ÉS nagyobb mint 10GB
                    //        VAGY
                    //        max. 200 fájl ÉS nagyobb mint 20GB
                    //      ÉS
                    //        tartalmazza valemelyik kulcsszót
                    //   AKKOR:
                    //      (valószínűleg nem becsomagolt filmgyűjtemény)
                }
                else {
                    //   KÜLÖNBEN:
                    //      átugorjuk ennek a rögzítését (csomagolt állományok a torrentben)
                    continue;
                }
            }

            // DATA MODEL TODO: külön absztrakt osztály adja vissza az alap modeleket db-vel szinkronba hozva
            $TORRENT = array(
                'url' => str_replace(array('{PASSKEY}', '{ID}'), array(_PASSKEY, $id), _TORRENT),
                'hash' => "",
                'quality' => $quality,
                'seeds' => $seeds,
                'peers' => $peers,
                'size' => $size,
                'size_bytes' => (string)$size_bytes,
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
            , 'small_cover_image' => $small_cover_image
            , 'medium_cover_image' => $medium_cover_image
            , 'large_cover_image' => $medium_cover_image
            , 'state' => 'ok'
            , 'torrents' => array($TORRENT)
            , 'date_uploaded' => $date
            , 'date_uploaded_unix' => $date_unix

            );

            $this->movies[] = $MOVIE;
            $this->torrent_ids[] = $id;//TODO torrenthez tárolandó
            $this->imdb_ids[] = ($imdb_id == '') ? 0 : intval(trim($imdb_id, 't'));
        }

        //if(intval($this->movie_count) <= 0 || count($this->movies) < _LIMIT) {
        //    $this->movie_count = count($this->movies) + (($this->pagenum - 1) * _LIMIT);
        //}

        return $this;
    }

    /** HELPERS **/
    private function parse_date($str)
    {
        if (stripos($str, ' ×') === false) $tmp1 = trim($str);
        else $tmp1 = trim(strstr($str, ' ×', true));

        $tmp2 = str_replace(array('ma', 'tegnap'), array(date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))), $tmp1);

        if ($tmp2 == $tmp1 && strlen($tmp2) == 12) $tmp2 = date('Y-') . str_replace(array('. ', '.'), array(' ', '-'), $tmp2);

        return strtotime($tmp2);
    }

    private function parse_size($str)
    {
        $tmp1 = str_replace(array('G', 'M', '  '), array(' G', ' M', ' '), trim($str));

        if (stripos($tmp1, ' ×') === false) $tmp2 = trim($tmp1);
        else $tmp2 = trim(strstr($tmp1, ' ×', true));

        $bytes = 0;
        $tmp = explode(' ', strtolower($tmp2));
        switch ($tmp[1]) {
            case 'gib':
                $bytes = $tmp[0] * 1073741824;
                break;
            case 'mib':
                $bytes = $tmp[0] * 1048576;
                break;
        }

        return array('text' => $tmp2, 'bytes' => round($bytes));
    }

    private function parse_poster($str)
    {
        $small = strstr(substr(strstr($str, '"'), 1), '"', true);

        $lastslash = strrpos($small, '/');
        if (substr_count($small, '_', $lastslash) == 4) {
            $file = explode('_', substr($small, $lastslash + 1));
            unset($file[1]);
            unset($file[2]);
            $orig = substr($small, 0, $lastslash + 1) . implode('_', $file);
        } else $orig = '';

        return array('small' => $small, 'orig' => $orig);
    }

}
