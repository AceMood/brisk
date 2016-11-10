<?php
/**
 * @file 描述web page的接口, 抽象类`BriskWebPage`继承了此接口.
 * @author AceMood
 * @email zmike86@gmail.com
 */

//----------------

interface BriskWebPageInterface {

  // 设置页面的渲染模式, 一般来说WebPage对象会根据`$_GET`参数识别是否`ajaxpipe`
  // 渲染模式. 提供这个方法是方便手动设置, 为今后的`bigpipe`模式做准备.
  public function setMode($mode);

  // 获取页面的渲染模式
  public function getMode();

  // 设置页面浏览设备类型
  public function setDevice($device);

  // 取得页面浏览设备类型
  public function getDevice();

  // 设置页面title
  public function setTitle($title);

  // 取得页面title
  public function getTitle();

  // 加载部件
  public function loadPagelet($pagelet);

  // 设置分片的dom属性
  public function setDomAttributes($attributes);

  // 获取分片的dom属性
  public function getDomAttributes();

  // 渲染指定类型的html
  public function renderResourcesOfType($type);

  // 以html的方式进行输出
  public function render();
}