<?php
/**
 * Created by PhpStorm.
 * User: baidu
 * Date: 16/8/17
 * Time: 下午12:38
 */

//echo DIRECTORY_SEPARATOR;
//
//echo __DIR__;
//
//echo __FILE__;

$path = __DIR__ . DIRECTORY_SEPARATOR . 'example/resource.json';
$content = file_get_contents($path);

$arr = json_decode($content);

var_dump($arr);