<?php
/**
 * Created by PhpStorm.
 * User: baidu
 * Date: 16/11/7
 * Time: 下午7:03
 */
function add($num) {
  static $n = 0;
  $n += $num;
  return $n;
}

echo add(1);