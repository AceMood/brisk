<?php

require_once('BriskResourceCollector.php');
require_once('BriskPage.php');
require_once('BriskConfig.php');

/**
 * 页面初始化.
 * @param {string} $framework 设置加载器的模块id. 后端加载框架会自动包含这个
 *                            js文件. 建议使用provides指令显式声明js id.
 * @param {?string} $mode 渲染模式
 * @return void
 */
function brisk_page_init($framework = null, $mode = null) {
    // 加载基础库
    if (isset($framework)) {
        BriskResourceCollector::setFramework(
            BriskResourceCollector::load(BriskConfig::JS, $framework)
        );
    }
    BriskPage::init($mode);
}

/**
 * 设置资源表文件(默认是resource.json)所在目录, 和smarty方案共用时默认是configDir
 */
function brisk_set_map_dir($dir) {
    BriskResourceCollector::setMapDir($dir);
}

/**
 * 获取资源表文件(默认是resource.json)所在目录
 * @return {string}
 */
function brisk_get_map_dir() {
    return BriskResourceCollector::getMapDir();
}

/**
 * 设置资源的cdn域名
 */
function brisk_set_cdn($domain) {
    BriskPage::setCDN($domain);
}

/**
 * 获取资源的cdn域名
 * @return {string}
 */
function brisk_get_cdn() {
    return BriskPage::getCDN();
}

/**
 * 根据资源类型和唯一名确定资源
 * @param {string} $type
 * @param {string} $symbol
 * @return mixed
 */
function brisk_get_resource($type, $symbol) {
    return BriskResourceCollector::getResource($type, $symbol);
}

/**
 * Output proper html content. Use RegExp to replace.
 * @param {string} $content html content
 * @return {mixed|string}
 */
function brisk_render_response($content) {
    return BriskPage::render($content);
}

/**
 *
 * @param {string} $pageletId
 * @param {?string} $mode
 * @param {?string} $group
 * @return {bool}
 */
function brisk_widget_start($pageletId, $mode, $group) {
    return BriskPage::widgetStart($pageletId, $mode, $group);
}

function brisk_widget_end($pageletId) {
    return BriskPage::widgetEnd($pageletId);
}