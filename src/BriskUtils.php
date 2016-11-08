<?php

/**
 * @class 提供类库的一些常用函数
 * @author AceMood
 * @email zmike86@gmail.com
 */

//-------------

final class BriskUtils {

  // 判断当前请求是否ajaxpipe模式或者正常模式.
  // 不同模式会影响PageView的渲染输出.
  public static function isAjaxPipe() {
    return isset($_GET['ajaxpipe']) && ($_GET['ajaxpipe'] === '1');
  }

  /**
   * todo
   * 为dom节点生成唯一id, 保证该dom的id在当前页中是永远唯一的.
   * Generate a node ID which is guaranteed to be unique for the current page,
   * even across Ajax requests.
   * @return string
   */
  public static function generateUniqueId() {
    static $uniqueIdCounter = 0;
    $t = 'node';
    return 'brisk_' . $t . '_' . ($uniqueIdCounter++);
  }

  /**
   * 根据内容渲染内联style标签, 返回安全的html封装对象
   * @param string $data css的内容
   * @param array|null $attributes dom上内联的属性
   * @return BriskSafeHTML
   * @throws Exception
   */
  public static function renderInlineStyle($data, $attributes = array()) {
    if (stripos($data, '</style>') !== false) {
      throw new Exception(pht(
        'Literal %s is not allowed inside inline style.',
        '</style>'));
    }
    if (strpos($data, '<!') !== false) {
      throw new Exception(pht(
        'Literal %s is not allowed inside inline style.',
        '<!'));
    }
    // We don't use <![CDATA[ ]]> because it is ignored by HTML parsers. We
    // would need to send the document with XHTML content type.
    return BriskDomProxy::tag('style', $attributes, BriskDomProxy::safeHtml($data));
  }

  /**
   * 根据内容渲染内联script
   * @param string $data script的内容
   * @param array|null $attributes dom上内联的属性
   * @return BriskSafeHTML
   * @throws Exception
   */
  public static function renderInlineScript($data, $attributes = array()) {
    if (stripos($data, '</script>') !== false) {
      throw new Exception(pht(
        'Literal %s is not allowed inside inline script.',
        '</script>'));
    }
    if (strpos($data, '<!') !== false) {
      throw new Exception(pht(
        'Literal %s is not allowed inside inline script.',
        '<!'));
    }

    $attributes = array_merge($attributes, array('type' => 'text/javascript'));

    // We don't use <![CDATA[ ]]> because it is ignored by HTML parsers. We
    // would need to send the document with XHTML content type.
    return BriskDomProxy::tag('script', $attributes, BriskDomProxy::safeHtml($data));
  }
}

