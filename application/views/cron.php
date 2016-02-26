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

if(count($output > 0))
{
    $pre_replace = '<pre style="color:#fefefe;background:linear-gradient(rgba(0,0,0,1),rgba(0,0,0,0.8));padding:8px">';
    foreach($output as $i => $o) {
        echo str_replace('<pre>', $pre_replace, $o)
            .'<br/>';
    }
}
else echo('No output.');

echo '</body></html>';