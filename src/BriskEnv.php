<?php

final class BriskEnv {
    //四种渲染模式
    public static $mode_normal = 'normal';
    public static $mode_bigpipe = 'bigpipe';
    public static $mode_ajaxpipe = 'ajaxpipe';
    //以下两种针对widget
    public static $mode_bigrender = 'bigrender';
    public static $mode_lazyrender = 'lazyrender';
}