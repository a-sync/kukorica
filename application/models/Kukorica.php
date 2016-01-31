<?php defined('BASEPATH') OR exit('*');
/**
 * Created by PhpStorm.
 * User: Smith
 * Date: 2016.01.16.
 * Time: 20:55
 */

class Kukorica extends CI_Model {

    private $site_id = 0;

    private $torrents_fields = array(
        'site_id',
        'torrent_id',
        'imdb_id',
        'url',
        'hash',
        'quality',
        'seeds',
        'peers',
        'size',
        'size_bytes',
        'date_uploaded',
        'date_uploaded_unix'
    );

    private $movies_fields = array(
          'imdb_id'
        , 'url'
        , 'title'
        , 'title_long'
        , 'slug'
        , 'year'
        , 'rating'
        , 'runtime'
        , 'genres'
        , 'cast'
        , 'directors'
        , 'language'
        , 'mpa_rating'
        , 'synopsis'
        , 'yt_trailer_code'
        , 'google_video'
        , 'background_image'
        , 'small_cover_image'
        , 'medium_cover_image'
        , 'large_cover_image'
        , 'state'
        , 'date_uploaded'
        , 'date_uploaded_unix'
    );

    public function __construct()
    {
        parent::__construct();

        $this->load->database();
    }

    public function setSiteId($site_id)
    {
        $this->site_id = $site_id;
        return $this;
    }

    public function get_torrents_by_ids($torrent_ids, $select = 'torrent_id')
    {
        $re = array();

        if(is_array($torrent_ids) && count($torrent_ids) > 0) {
            $query = $this->db
                ->select($select)
                ->from('torrents')
                ->where('site_id', $this->site_id)
                ->where_in('torrent_id', array_filter($torrent_ids))
                ->get();

            foreach ($query->result_array() as $r) {
                $re[$r['torrent_id']] = $r;
            }
        }

        return $re;
    }

    public function get_movies_by_ids($imdb_ids, $select = 'imdb_id')
    {
        $re = array();

        if(is_array($imdb_ids) && count($imdb_ids) > 0) {
            $query = $this->db
                ->select($select)
                ->from('movies')
                ->where_in('imdb_id', array_filter($imdb_ids))
                ->get();

            foreach ($query->result_array() as $r) {
                $re[$r['imdb_id']] = $r;
            }
        }

        return $re;
    }

    public function save_torrent($torrent_data)
    {
        $data = $this->clean_fields($torrent_data, $this->torrents_fields);

        return $this->db->insert('torrents', $data);
    }

    public function save_torrents($torrents)
    {
        foreach($torrents as $i => $torrent_data) {
            $torrents[$i] = $this->clean_fields($torrent_data, $this->torrents_fields);
        }

        return $this->db->insert_batch('torrents', $torrents);
    }

    public function save_movie($movie_data)
    {
        $data = $this->clean_fields($movie_data, $this->movies_fields);

        return $this->db->insert('movies', $data);
    }

    public function save_movies($movies)
    {
        foreach($movies as $i => $movie_data) {
            $movies[$i] = $this->clean_fields($movie_data, $this->movies_fields);
        }

        return $this->db->insert_batch('movies', $movies);
    }

    public function get_unlocked_movie_ids($limit = NULL)
    {
        $query = $this->db
            ->select('imdb_id')
            ->from('movies')
            ->where('imdb_id !=', 0)
            ->where('locked', 0)
            ->limit($limit)
            ->get()
        ;

        return $query->result_array();
    }

    public function get_locked_movies_without_trailer($limit = 0)
    {
        $query = $this->db
            ->select('imdb_id, year, title')
            ->from('movies')
            ->where('year !=', 0)
            ->where_in('locked', 2)
            ->where('yt_trailer_code', '')
            ->order_by('updated', 'ASC')
            ->limit($limit)
            ->get()
        ;

        return $query->result_array();
    }

    public function update_movie($imdb_id, $data)
    {
        $imdb_id = intval(trim($imdb_id, 't'));
        if($imdb_id) {
            return $this->db
                ->where('imdb_id', $imdb_id)
                ->update('movies', $data);
        }

        return FALSE;
    }

    private function clean_fields($data, $valid_fields)
    {
        foreach($data as $key => $v)
        {
            if( ! in_array($key, $valid_fields)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /*
    public function poke_movie($imdb_id) //TODO: Y U NO WORK?
    {
        $imdb_id = intval(trim($imdb_id, 't'));
        if($imdb_id) {
            return $this->db
                ->where('imdb_id', $imdb_id)
                ->update('movies', array('updated'=>'NOW()'));
        }

        return FALSE;
    }
    */
}