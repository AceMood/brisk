<?php

final class BriskEnv {
    //四种渲染模式
    public static $mode_normal = 'normal';
    public static $mode_bigpipe = 'bigpipe';
    public static $mode_quickling = 'quickling';
    public static $mode_bigrender = 'bigrender';

    //文件后缀类型映射
    public static $typeMap = array(
        'jsx' => 'js',
        'js' => 'js',
        'coffee' => 'js',
        'ts' => 'js',
        'less' => 'css',
        'css' => 'css',
        'scss' => 'css'
    );
}