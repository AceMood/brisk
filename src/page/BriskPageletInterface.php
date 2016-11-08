<?php
/**
 * @file 描述pagelet的接口, 抽象类`BriskPagelet`继承了此接口.
 * @author AceMood
 * @email zmike86@gmail.com
 */

interface BriskPageletInterface {

  public function setMode($mode);

  public function getMode();

  public function render();

  public function setDataSource();

  public function getDataSource();

}