<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Created by PhpStorm.
 * User: Smith
 * Date: 2016.01.23.
 * Time: 20:05
 */
//header('Content-Type: text/plain');
//echo (count($output > 0)) ? implode("\r\n", $output) : 'No output.';

echo '<html><head><meta charset="utf-8"></head><body>';

echo (count($output > 0)) ? implode("<br/>", $output) : 'No output.';

echo '</body></html>';