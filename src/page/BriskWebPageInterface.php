<?php
/**
 * @file 描述web page的接口, 抽象类`BriskWebPage`继承了此接口.
 * @author AceMood
 * @email zmike86@gmail.com
 */

interface BriskWebPageInterface {

  // 设置页面的渲染模式, 一般来说WebPage对象会根据`$_GET`参数识别是否`ajaxpipe`
  // 渲染模式. 提供这个方法是方便手动设置, 为今后的`bigpipe`模式做准备.
  public function setMode($mode);

  // 获取页面的渲染模式
  public function getMode();

  // 设置页面title
  public function setTitle($title);

  // 取得页面title
  public function getTitle();

  // 设置分片id数组
  public function setPageletIds($ids);

  // 取得分片id数组
  public function getPageletIds();

  // 设置分片的dom属性
  public function setDomAttributes($attributes);

  // 获取分片的dom属性
  public function getDomAttributes();

  // 渲染当前页面分片
  public function produceHTML();

  // 获取当前分片的css
  public function getDependentCss();

  // 获取当前分片的js
  public function getDependentJs();

  // 记录当前部件需要的静态资源
  public function requireResource($name, $source_name);

  // 部件主动获取需要的数据对象
  public function fetchDataSource();

  // 设置本部件需要的数据对象
  public function setDataSource($data);

  // 获取本部件需要的数据对象
  public function getDataSource();

  // 以html的方式进行输出
  public function renderAsHTML();
}