<?php

/**
 * @file 提供全局的应用程序接口
 * @author AceMood
 * @email zmike86@gmail.com
 */

//-------------

// 最重要的api. 根据资源名称引用需要的CSS或JS. 这个接口记录当前页面需要的资源依赖,
// 当返回请求时, 所有被以来的资源都会计算输出. 可以在任何地方调用这个函数, 一般推荐
// 在组件内部`require_static`该组件依赖的文件, 这样保证组件是内聚的, 容易扩展和维护.
/**
 * @param string $name 资源路径名
 * @param string $source_name 项目命名空间
 * @return void
 */
function require_static($name, $source_name) {
  $response = BriskAPI::staticResourceResponse();
  $response->requireResource($name, $source_name);
}

/**
 * @param string $name 资源路径名
 * @param string $source_name 项目命名空间
 * @return string 将一个资源数据内联式立即输出
 */
function inline_static($name, $source_name) {
  $response = BriskAPI::staticResourceResponse();
  return $response->inlineResource($name, $source_name);
}

/**
 * 输出所有外链css
 */
function render_css_block() {
  $response = BriskAPI::staticResourceResponse();
  $content = $response->renderResourcesOfType('css');
  echo $content->getHTMLContent();
}

/**
 * 输出所有外链js
 */
function render_js_block() {
  $response = BriskAPI::staticResourceResponse();
  $content = $response->renderResourcesOfType('js');
  echo $content->getHTMLContent();
}

/**
 * Get the versioned URI for a raw resource, like an image.
 * @param   string  $resource Path to the raw image.
 * @return  string  Versioned path to the image, if one is available.
 */
function get_resource_uri($resource, $source_name = 'brisk') {
  $resource = ltrim($resource, '/');
  $map = BriskResourceMap::getNamedInstance($source_name);
  $response = BriskAPI::staticResourceResponse();
  return $response->getURI($map, $resource);
}