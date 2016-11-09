<?php
/**
 * @file 描述pagelet的接口, 抽象类`BriskPagelet`继承了此接口.
 * @author AceMood
 * @email zmike86@gmail.com
 */

interface BriskPageletInterface {

  // 设置分片的渲染模式
  public function setMode($mode);

  // 获取分片的渲染模式
  public function getMode();

  // 设置分片id
  public function setId($id);

  // 获取分片id
  public function getId();

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

  // 以html的方式进行输出, 用于一般的请求
  public function renderAsHTML();

  // 以json的方式进行输出, 用于ajaxpipe方式的请求中
  public function renderAsJSON();
}