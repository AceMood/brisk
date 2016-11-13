<?php

/**
 * @file Dom相关的操作函数
 * @author AceMood
 * @email zmike86@gmail.com
 */

//----------------

final class BriskDomProxy {

  // 输出html元素的标签和内部内容, 默认认为其内部内容不安全, 需要进行转义.
  //
  // Tag rendering has some special logic which implements security features:
  //
  //   - 对于`<a>`标签, 如果没指定`rel`属性, 浏览器会当做`rel="noreferrer"`.
  //   - When rendering `<a>` tags, the `href` attribute may not begin with
  //     `javascript:`.
  //
  // These special cases can not be disabled.
  //
  // IMPORTANT: The `$tag` attribute and the keys of the `$attributes` array are
  // trusted blindly, and not escaped. You should not pass user data in these
  // parameters.
  /**
   * @param string $tag 要创建的dom标签.
   * @param map<string, string> 一个包含dom属性的哈希结构.
   * @param wild $content 标签内所包含的内容.
   * @param bool $escape 是否对内容进行转义
   * @return BriskSafeHTML Tag对象.
   */
  public static function tag(
    $tag,
    array $attributes = array(),
    $content = null,
    $escape = true
  ) {
    // If the `href` attribute is present:
    //   - make sure it is not a "javascript:" URI. We never permit these.
    //   - if the tag is an `<a>` and the link is to some foreign resource,
    //     add `rel="nofollow"` by default.
    if (!empty($attributes['href'])) {

      // This might be a URI object, so cast it to a string.
      $href = (string)$attributes['href'];

      if (isset($href[0])) {
        $is_anchor_href = ($href[0] == '#');

        // Is this a link to a resource on the same domain? The second part of
        // this excludes "///evil.com/" protocol-relative hrefs.
        $is_domain_href = ($href[0] == '/') &&
          (!isset($href[1]) || $href[1] != '/');

        // If the `rel` attribute is not specified, fill in `rel="noreferrer"`.
        // Effectively, this serves to make the default behavior for offsite
        // links "do not send a  referrer", which is broadly desirable. Specifying
        // some non-null `rel` will skip this.
        if (!isset($attributes['rel'])) {
          if (!$is_anchor_href && !$is_domain_href) {
            if ($tag == 'a') {
              $attributes['rel'] = 'noreferrer';
            }
          }
        }

        // Block 'javascript:' hrefs at the tag level: no well-designed
        // application should ever use them, and they are a potent attack vector.

        // This function is deep in the core and performance sensitive, so we're
        // doing a cheap version of this test first to avoid calling preg_match()
        // on URIs which begin with '/' or `#`. These cover essentially all URIs
        // in Phabricator.
        if (!$is_anchor_href && !$is_domain_href) {
          // Chrome 33 and IE 11 both interpret "javascript\n:" as a Javascript
          // URI, and all browsers interpret "  javascript:" as a Javascript URI,
          // so be aggressive about looking for "javascript:" in the initial
          // section of the string.

          $normalized_href = preg_replace('([^a-z0-9/:]+)i', '', $href);
          if (preg_match('/^javascript:/i', $normalized_href)) {
            throw new Exception(
              pht(
                "Attempting to render a tag with an '%s' attribute that begins ".
                "with '%s'. This is either a serious security concern or a ".
                "serious architecture concern. Seek urgent remedy.",
                'href',
                'javascript:'));
          }
        }
      }
    }

    // For tags which can't self-close, treat null as the empty string -- for
    // example, always render `<div></div>`, never `<div />`.
    static $self_closing_tags = array(
      'area'    => true,
      'base'    => true,
      'br'      => true,
      'col'     => true,
      'command' => true,
      'embed'   => true,
      'frame'   => true,
      'hr'      => true,
      'img'     => true,
      'input'   => true,
      'keygen'  => true,
      'link'    => true,
      'meta'    => true,
      'param'   => true,
      'source'  => true,
      'track'   => true,
      'wbr'     => true,
    );

    $attr_string = '';
    foreach ($attributes as $k => $v) {
      if ($v === null) {
        continue;
      }
      $v = self::escapeHtml($v);
      $attr_string .= ' '.$k.'="'.$v.'"';
    }

    if ($content === null) {
      if (isset($self_closing_tags[$tag])) {
        return new PhutilSafeHTML('<'.$tag.$attr_string.' />');
      } else {
        $content = '';
      }
    } else {
      if ($escape) {
        $content = self::escapeHtml($content);
      }
    }

    return new BriskSafeHTML('<'.$tag.$attr_string.'>'.$content.'</'.$tag.'>');
  }

  // 原类库的`phutil_safe_html`, 整合后调用`BriskDomProxy::safeHtml`完成同样的操作.
  // 将字符串封装为安全字符串对象返回, 可直接用在html中.
  /**
   * @param mixed $string
   * @return BriskSafeHTML|string
   */
  public static function safeHtml($string) {
    if ($string == '') {
      return $string;
    } else if ($string instanceof BriskSafeHTML) {
      return $string;
    } else {
      return new BriskSafeHTML($string);
    }
  }

  // 原类库的`phutil_escape_html`方法, 整合后调用`BriskDomProxy::escapeHtml`
  // 完成同样的操作. 对字符串进行html编码, 返回安全的html对象
  public static function escapeHtml($string) {
    // 本身是安全的html对象则直接返回
    if ($string instanceof BriskSafeHTML) {
      return $string;
    }

    if ($string instanceof BriskSafeHTMLProducerInterface) {
      $result = $string->produceBriskSafeHTML();
      if ($result instanceof BriskSafeHTML) {
        return self::escapeHtml($result);
      } else if (is_array($result)) {
        return self::escapeHtml($result);
      } else if ($result instanceof BriskSafeHTMLProducerInterface) {
        return self::escapeHtml($result);
      } else {
        try {
          return self::escapeHtml((string)$result);
        } catch (Exception $ex) {
          throw new Exception(
            pht(
              "Object (of class '%s') implements %s but did not return anything ".
              "renderable from %s.",
              get_class($string),
              'BriskSafeHTMLProducerInterface',
              'produceBriskSafeHTML()'));
        }
      }
    }

    if (is_array($string)) {
      $result = '';
      foreach ($string as $item) {
        $result .= self::escapeHtml($item);
      }
      return $result;
    }

    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
  }

  /**
   * 加强了原生`implode()`函数, 对html进行转义.
   */
  public static function implodeHtml($glue, array $pieces) {
    $glue = self::escapeHtml($glue);

    foreach ($pieces as $k => $piece) {
      $pieces[$k] = self::escapeHtml($piece);
    }

    return self::safeHtml(implode($glue, $pieces));
  }
}
