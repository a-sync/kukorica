<?php defined('BASEPATH') OR exit('*');
/**
 * Created by PhpStorm.
 * User: Smith
 * Date: 2016.02.02.
 * Time: 21:22
 */

interface iScraper
{
    /** Parse the request of BT to create matching query on the
     *  scraped site and setup pagenum and limit properties.
     *
     */
    public function parseReq($INPUT);


    /** @return int the current page number (starting at 1)
     */
    public function getPagenum();

    /** @return int the current limit of requested results
     */
    public function getLimit();


    /** @return string the full URL query to be scraped
     */
    public function getScrapeUrl();

    /** @return string the cookie string to be sent with the query
     */
    public function getScrapeCookies();


    /** Try to parse the served content, usually HTML.
     *  You could possibly setup movie_count property here.
     *  If the content is not the expected result, return false.
     *
     * @param string scraped content
     * @return boolean true if the source was successfully parsed
     */
    public function loadContent($SCRAPED_DATA);

    /** Parse the content and setup movies, torrent_ids and
     *  imdb_ids arrays.
     */
    public function parseMovies();


    /** @return int the overall number of results
     */
    public function getMovieCount();

    /** Return the parsed results with the torrents info included
     *  in the movie. The api takes care of sorting and marshalling
     *  the matching movies together if necessary.
     *
     * @return array the parsed results in BT format
     */
    public function getMovies();

    /** Return the parsed torrent_id-s (unique on the site) in an
     *  array, matching with the indexes returned by getMovies().
     *
     * @return array torrent_id values
     */
    public function getTorrentIds();

    /** Return the parsed imdb_id-s in an array, matching with the
     *  indexes returned by getMovies().
     *
     * @return array torrent_id values
     */
    public function getImdbIds();
}
