<?php

require_once '__init_script_register__.php';

/**
 * Include a CSS or JS static resource by name. This function records a
 * dependency for the current page, so when a response is generated it can be
 * included. You can call this method from any context, and it is recommended
 * you invoke it as close to the actual dependency as possible so that page
 * dependencies are minimized.
 *
 * @param string $name 资源路径名
 * @param string $source_name 项目命名空间
 * @return void
 */
function require_static_resource($name, $source_name = 'brisk') {
    $response = BriskAPI::getStaticResourceResponse();
    $response->requireResource($name, $source_name);
}

//将一个资源数据内联式立即输出
function inline_static_resource($name, $source_name = 'brisk') {
    $response = BriskAPI::getStaticResourceResponse();
    return $response->inlineResource($name, $source_name);
}

//动态设置cdn
function set_cdn($cdn) {
    $response = BriskAPI::getStaticResourceResponse();
    $response->setCDN($cdn);
}

//获取cdn
function get_cdn() {
    $response = BriskAPI::getStaticResourceResponse();
    $response->getCDN();
}

//输出所有外链css
function render_css_block() {
    $response = BriskAPI::getStaticResourceResponse();
    $content = $response->renderResourcesOfType('css');
    echo $content->getHTMLContent();
}

//输出所有外链js
function render_js_block() {
    $response = BriskAPI::getStaticResourceResponse();
    $content = $response->renderResourcesOfType('js');
    echo $content->getHTMLContent();
}

//
function load_widget($path, $id = null, $mode = null) {
    BriskWidgetFactory::load($path, $id, $mode);
}

/**
 * Get the versioned URI for a raw resource, like an image.
 * @param   string  $resource Path to the raw image.
 * @return  string  Versioned path to the image, if one is available.
 */
function get_resource_uri($resource, $source_name = 'brisk') {
    $resource = ltrim($resource, '/');
    $map = BriskResourceMap::getNamedInstance($source_name);
    $response = BriskAPI::getStaticResourceResponse();
    return $response->getURI($map, $resource);
}