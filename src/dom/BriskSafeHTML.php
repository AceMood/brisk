<?php

/**
 * @file BriskSafeHTML对于安全的html进行封装
 */

//---------------

final class BriskSafeHTML {

  private $content;

  public function __construct($content) {
    $this->content = (string)$content;
  }

  public function __toString() {
    return $this->content;
  }

  public function getHTMLContent() {
    return $this->content;
  }

  public function appendHTML($html /* , ... */) {
    foreach (func_get_args() as $html) {
      $this->content .= BriskDomProxy::escapeHtml($html);
    }
    return $this;
  }

  public static function applyFunction($function, $string /* , ... */) {
    $args = func_get_args();
    array_shift($args);
    // 需要对参数进行安全编码
    $arr_args = array();
    foreach ($args as $arg) {
      $arr_args[] = BriskDomProxy::escapeHtml($arg);
    }
    return new BriskSafeHTML(call_user_func_array($function, $arr_args));
  }

// Requires http://pecl.php.net/operator.

  public function __concat($html) {
    $clone = clone $this;
    return $clone->appendHTML($html);
  }

  public function __assign_concat($html) {
    return $this->appendHTML($html);
  }

}
