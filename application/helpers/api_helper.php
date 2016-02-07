<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Created by PhpStorm.
 * User: Smith
 * Date: 2016.01.16.
 * Time: 14:15
 */

if ( ! function_exists('scrape_url')) {
    function scrape_url($url, $cookies = '')
    {
        //$agent = 'Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0';
        $agent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:25.8) Gecko/20151126 Firefox/31.9 PaleMoon/25.8.1 ';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_URL, $url);

        $raw = curl_exec($ch);
        curl_close($ch);

        return $raw;
    }
}

if ( ! function_exists('slugify')) {
    setlocale(LC_ALL, 'en_US.UTF8');
    function slugify($str, $replace = array(), $delimiter = '-')
    {
        if (!empty($replace)) {
            $str = str_replace((array)$replace, ' ', $str);
        }

        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
        $clean = strtolower(trim($clean, '-'));

        return $clean;
    }
}

if ( ! function_exists('get_url_query')) {
    function get_url_query($params = array())
    {
        $re = '';
        foreach ($params as $i => $v) {
            if ($re != '') $re .= '&';
            $re .= $i . '=' . $v;
        }

        return $re;
    }
}

if ( ! function_exists('get_yt_id')) {
    function get_yt_id($url)
    {
        $video_id = '';
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
            $video_id = $match[1];
        }
        return $video_id;
    }
}
if ( ! function_exists('check_yt_exists')) {
    function check_yt_exists($videoID) {
        $theURL = "http://www.youtube.com/oembed?url=http://www.youtube.com/watch?v=$videoID&format=json";
        $headers = get_headers($theURL);

        if (strpos($headers[0], '404') === false) {
            return true;
        } else {
            return false;
        }
    }
}

if ( ! function_exists('parse_url_for_params')) {
# https://gist.github.com/astockwell/11055104
    /*
    * Find the first matching parameter value in a url from the passed params array.
         *
         * @access private
         *
         * @param string $url The url
    * @param array $target_params Any parameter keys that may contain the id
    * @return null|string Null on failure to match a target param, the url's id on success
         */
    function parse_url_for_params($url, $target_params)
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $my_array_of_params);
        foreach ($target_params as $target) {
            if (array_key_exists($target, $my_array_of_params)) {
                return $my_array_of_params[$target];
            }
        }
        return null;
    }
}

if ( ! function_exists('parse_url_for_last_element')) {
/**
 * Find the last element in a url, without any trailing parameters
 *
 * @access private
 *
 * @param string $url The url
 * @return string The last element of the url
 */
    function parse_url_for_last_element($url)
    {
        $url_parts = explode("/", $url);
        $prospect = end($url_parts);
        $prospect_and_params = preg_split("/(\?|\=|\&)/", $prospect);
        if ($prospect_and_params) {
            return $prospect_and_params[0];
        } else {
            return $prospect;
        }
        return $url;
    }
}

if ( ! function_exists('clear_all_cache')) {
/**
 * Clears all cache from the cache directory
 */
    function clear_all_cache()
    {
        $CI =& get_instance();
        $path = $CI->config->item('cache_path');

        $cache_path = ($path == '') ? APPPATH.'cache/' : $path;

        $handle = opendir($cache_path);
        while (($file = readdir($handle)) !== FALSE)
        {
            //Leave the directory protection alone
            if ($file != '.htaccess' && $file != 'index.html')
            {
                @unlink($cache_path.'/'.$file);
            }
        }
        closedir($handle);
    }
/*
$CI =& get_instance();
$wildcard = 'latest';
$all_cache = $CI->cache->cache_info();
foreach ($all_cache as $cache_id => $cache) :
    if (strpos($cache_id, $wildcard) !== false) :
        $CI->cache->delete($cache_id);
    endif;
endforeach;
*/
}