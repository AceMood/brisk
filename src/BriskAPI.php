<?php

/**
 * @file 提供了间接操作`StaticResourceResponse`类的层. 一般情况下不需要直接`new`新建
 *       `StaticResourceResponse`的对象. （未来要支持一个页面的渲染多次发出response）
 * @author AceMood
 * @email zmike86@gmail.com
 */

//-------------

final class BriskAPI {
  private static $response;

  public static function staticResourceResponse() {
    if (empty(self::$response)) {
      self::$response = new BriskStaticResourceResponse();
    }
    return self::$response;
  }
}