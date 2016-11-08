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

  //
  public function requireResource($name, $source_name);

  //
  public function setDataSource($data);

  public function getDataSource();

  //
  public function render();

}