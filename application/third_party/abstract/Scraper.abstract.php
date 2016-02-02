<?php defined('BASEPATH') OR exit('*');
/** iScraper interface & Scraper abstract with methods declared in call order.
 *
 * Created by PhpStorm.
 * User: Smith
 * Date: 2016.02.02.
 * Time: 19:10
 */

require_once( APPPATH . 'third_party/abstract/iScraper.interface.php' );

abstract class Scraper implements iScraper
{
    protected $pagenum;
    protected $limit;

    protected $movie_count;
    protected $movies;
    protected $torrent_ids;
    protected $imdb_ids;

    public final function getPagenum()
    {
        return $this->pagenum;
    }

    public final function getLimit()
    {
        return $this->limit;
    }

    public final function getMovieCount()
    {
        return $this->movie_count;
    }

    public final function getMovies()
    {
        return $this->movies;
    }

    public final function getTorrentIds()
    {
        return $this->torrent_ids;
    }

    public final function getImdbIds()
    {
        return $this->imdb_ids;
    }
}
