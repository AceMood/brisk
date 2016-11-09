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
function require_static($name, $source_name = 'brisk') {
  $response = BriskAPI::staticResourceResponse();
  $response->requireResource($name, $source_name);
}

// 很方便的api. 根据资源名称渲染行内的CSS或JS. 不会计算依赖, 而是把这个资源直接输出.
// 适合一些统计脚本或者小片段的脚本, 并且这样的脚本也可以在编译时压缩, 样式表也完全适合.
// 对于要由服务端数据决定的脚本, 可以直接通过`BriskUtils::renderInlineStyle`和
// `BriskUtils::renderInlineScript`输出, 但是不会做压缩.
/**
 * @param string $name 资源路径名
 * @param string $source_name 项目命名空间
 * @return string 将一个资源数据内联式立即输出
 */
function inline_static($name, $source_name = 'brisk') {
  $response = BriskAPI::staticResourceResponse();
  echo $response->inlineResource($name, $source_name);
}

// 输出收集的css
function render_css_block() {
  $response = BriskAPI::staticResourceResponse();
  $content = $response->renderResourcesOfType('css');
  echo $content->getHTMLContent();
}

// 输出收集的js
function render_js_block() {
  $response = BriskAPI::staticResourceResponse();
  $content = $response->renderResourcesOfType('js');
  echo $content->getHTMLContent();
}

/**
 * 获取一个资源的线上路径
 * @param  string $name 资源名称.
 * @param  string $source_name 项目命名空间
 * @return string
 */
function get_resource_uri($name, $source_name = 'brisk') {
  $name = ltrim($name, '/');
  $map = BriskResourceMap::getNamedInstance($source_name);
  $response = BriskAPI::staticResourceResponse();
  return $response->getURI($map, $name);
}

// 这个函数直接从原类库拷贝, 原目的在于国际化的翻译文本. 在本库中暂且不考虑国际化的功能.
// 函数保留但简单返回最初的文本, 方便日后国际化时扩展.
/**
 * @param string $text Translation identifier with `sprintf()` placeholders.
 * @param mixed Value to select the variant from (e.g. singular or plural).
 * @param ... Next values referenced from $text.
 * @return string 返回翻译后的文本.
 */
function pht($text, $variant = null /* , ... */) {
  return $text;
/*
    $args = func_get_args();
    $translator = PhutilTranslator::getInstance();
    return call_user_func_array(array($translator, 'translate'), $args);
*/
}

// 同一函数. 返回参数的值.
// 有一些php语法会导致错误, 如下:
//   new Thing()->doStuff();
// 但是这样是可以的:
//   id(new Thing())->doStuff();
// 本函数就为此目的
/**
 * @param  wild $x 任何参数.
 * @return wild 无任何改变的形参.
 */
function id($x) {
  return $x;
}

/**
 * 取得数组在索引处的值, 不会发出警告.
 * @param  array  $array 取值的数组.
 * @param  scalar $key 取值的索引.
 * @param  wild   $default 没有找打返回此默认值.
 * @return wild   If `$array[$key]` exists, that value is returned. If not,
 *                $default is returned without raising a warning.
 */
function idx(array $array, $key, $default = null) {
  // isset() is a micro-optimization - it is fast but fails for null values.
  if (isset($array[$key])) {
    return $array[$key];
  }

  // Comparing $default is also a micro-optimization.
  if ($default === null || array_key_exists($key, $array)) {
    return null;
  }

  return $default;
}

/**
 * Format a HTML code. This function behaves like `sprintf()`, except that all
 * the normal conversions (like %s) will be properly escaped.
 */
function hsprintf($html /* , ... */) {
  $args = func_get_args();
  array_shift($args);
  // 需要对参数进行安全编码
  $arr_args = array();
  foreach ($args as $arg) {
    $arr_args[] = BriskDomProxy::escapeHtml($arg);
  }
  return new BriskSafeHTML(vsprintf($html, $arr_args));
}
