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

class Bithu {

    private $torrenttable;
    private $movies;
    private $torrent_ids;
    private $imdb_ids;
    private $params;
    private $pagenum;
    private $movie_count = 10000;//TODO

    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    public function getTable()
    {
        return $this->torrenttable;
    }

    public function getMovies()
    {
        return $this->movies;
    }

    public function getTorrentIds()
    {
        return $this->torrent_ids;
    }

    public function getImdbIds()
    {
        return $this->imdb_ids;
    }

    public function getParams($key = FALSE)
    {
        if($key != FALSE) return $this->params[$key];
        else return $this->params;
    }

    public function getPagenum()
    {
        return $this->pagenum;
    }

    public function getMovieCount()
    {
        return $this->movie_count;
    }

    public function getLimit()
    {
        return _LIMIT;
    }

    public function getScrapeURL()
    {
        $scrape_query = get_url_query($this->getParams());
        return _URL.$scrape_query;
    }

    public function getScrapeCookies()
    {
        return 'uid='._UID.'; pass='._PASS;
    }

    public function parseHTML($str)
    {
        $dom = new DOMDocument;
        $dom->strictErrorChecking = FALSE;
        //$dom->validateOnParse = true;
        $previous_errors = libxml_use_internal_errors(TRUE);

        if($dom->loadHTML($str)) {

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

    public function parseTable()
    {
        $this->movies = array();
        $this->torrent_ids = array();
        $this->imdb_ids = array();

        $rows = $this->torrenttable->getElementsByTagName('tr');
        foreach($rows as $i => $node) {
            if ($i == 0) continue;
            $cols = $node->getElementsByTagName('td');

            /*
            if($this->params['cat'] == '19')
            {
                //Film/Eng/SD esetén, ha legalább egy tucat fájl van, valószínűleg be van csomagolva :(
                if(intval($cols->item(2)->textContent) >= 12)
                {
                    continue;
                }
            }
            */

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
                    $tmp = substr($href, strpos($href, 'imdb.com/title/') + 15);//,9
                    $imdb_id = strstr($tmp, '/', true);
                    $imdb_rating = floatval(str_replace(array('[imdb: ', ']'), '', $link->textContent));
                } elseif ($link->hasAttribute('alt') && $link->getAttribute('alt') == 'info') {
                    $info_url = str_replace('http://anonym.to/?', '', $href);
                    if ($link->hasAttribute('onmouseover')) {
                        $mo = $this->parse_poster($link->getAttribute('onmouseover'));
                        $small_cover_image = $mo['small'];
                        $medium_cover_image = $mo['orig'];
                    }
                }
            }

            $date_unix = $this->parse_date($cols->item(4)->textContent);
            $date = date('Y-m-d H:i:s', $date_unix);

            $size = str_replace(array('G', 'M'), array(' G', ' M'), trim($cols->item(5)->textContent));
            $size_bytes = $this->parse_size($size);

            $seeds = intval(trim($cols->item(7)->textContent));
            $peers = explode(' / ', $cols->item(8)->textContent);
            $peers = intval($peers[1]);

            if($size_bytes < 52428800) continue;//50MB
            elseif($seeds == 0) continue;

            $filenum = intval($cols->item(2)->textContent);

            $filenum_treshold = ($this->params['cat'] == '19') ? 12 : 15;//Eng = 12; Hun = 15
            if($filenum >= $filenum_treshold)
            {
                $title_cut = explode('-', $title_long);
                $releaser = array_pop($title_cut);
                $title_cut = implode('-', $title_cut);//torrent név releaser nélkül

                if(
                    (
                       ($filenum <= 50  && $size_bytes > 2147483648 )//2GB
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
                    // HA:
                    //      több mint $filenum_treshold fájl
                    // AKKOR:
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
                    //      átugorjuk ennek a rögzítését
                    continue;
                }
            }

            $TORRENT = array(
                'url' => str_replace(array('{PASSKEY}', '{ID}'), array(_PASSKEY, $id), _TORRENT),
                'hash' => "",
                'quality' => "720p",
                'seeds' => $seeds,
                'peers' => $peers,
                'size' => $size,
                'size_bytes' => (string)$size_bytes,
                'date_uploaded' => $date,
                'date_uploaded_unix' => $date_unix
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
            $this->torrent_ids[] = $id;
            $this->imdb_ids[] = ($imdb_id == '') ? 0 : intval(trim($imdb_id, 't'));
        }

        //if(intval($this->movie_count) <= 0 || count($this->movies) < _LIMIT) {
        //    $this->movie_count = count($this->movies) + (($this->pagenum - 1) * _LIMIT);
        //}

        return $this;
    }

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
        $bytes = 0;
        $tmp = explode(' ', strtolower($str));
        switch ($tmp[1]) {
            case 'gib':
                $bytes = $tmp[0] * 1073741824;
                break;
            case 'mib':
                $bytes = $tmp[0] * 1048576;
                break;
        }

        return round($bytes);
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

    //&limit=50&with_rt_ratings=true&lang=hu
    //&quality=1080p&order_by=asc
    public function parse_req()
    {
        // Check! app/config/config.php [cache_query_string]

        if(is_numeric($this->CI->input->get('page'))){
            $this->pagenum = intval($this->CI->input->get('page'));
        } else {
            $this->pagenum = 1;
        }

        $params = array(
            'cat' => (strpos($this->CI->input->get('cat'), 'Eng') !== false)?'19':'23',#19:Film/Eng/SD #23:Film/Hun/SD;
            'page' => $this->pagenum - 1,
            'd' => 'DESC'//order_by=asc
        );

        switch($this->CI->input->get('sort_by'))
        {
            case 'views':
            case 'popularity':
            case 'download_count': # megtekintések
                $params['sort'] = 'times_completed';
                break;
            case 'trending':
            case 'seeds': # seedek
                $params['sort'] = 'seeders';
                break;
            case 'rating': # értékelés
                $params['sort'] = 'rating';
                break;
            default://date_added # utoljára hozzáadva
                $params['sort'] = 'added';
        }

        switch($this->CI->input->get('genre'))
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

        $qt = $this->CI->input->get('query_term', TRUE);
        if($qt)
        {
            $qs = mb_convert_encoding($qt, 'ISO-8859-2');

            $params['search'] = urlencode($qs);
        }

        $this->params = $params;

        return $this;
    }
}
