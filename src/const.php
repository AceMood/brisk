<?php

/**
 * @file 定义常用常量
 * @author AceMood
 * @email zmike86@gmail.com
 */

//---------------

// 浏览网页的设备类型, 针对pc端用户, 分析依赖的静态资源全部外链输出.
// 针对移动端用户, 目前采用inline的方式输出静态资源, 未来http2得到普及
// 则可以修改为外链
define('DEVICE_PC', 9800000);
define('DEVICE_MOBILE', 9800001);

// 打印资源表的输出类型.
//
define('MAP_NO', 9800002);
define('MAP_ASYNC', 9800003);
define('MAP_ALL', 9800004);
