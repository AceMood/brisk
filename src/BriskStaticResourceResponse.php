<?php

/**
 * @file Tracks and resolves dependencies the page declares with `require_static`
 * and then builds appropriate HTML or Ajax responses.
 * @author AceMood
 * @email zmike86@gmail.com
 */

//-------------

class BriskStaticResourceResponse {
  // SR cdn
  protected $cdn = '';

  // Default only print asyncLoaded resources
  protected $printType = MAP_ASYNC;

  // device type
  protected $deviceType = DEVICE_MOBILE;

  // external linked resource's ids
  protected $symbols = array();

  // inlined resource's ids
  protected $inlined = array();

  // need to analyze resources again
  protected $needsResolve = true;

  // Separated by namespace, record each resource
  protected $packaged;

  // add metadata
  protected $metadata = array();

  // todo add come behaviours when page being init
  protected $behaviors = array();

  // resources have been rendered
  protected $hasRendered = array();

  protected $postprocessorKey;

  public function addMetadata($metadata) {
    $id = count($this->metadata);
    $this->metadata[$id] = $metadata;
    return $this;
  }

  public function getMetadata() {
    return $this->metadata;
  }

  public function setPostprocessorKey($postprocessor_key) {
    $this->postprocessorKey = $postprocessor_key;
    return $this;
  }

  public function getPostprocessorKey() {
    return $this->postprocessorKey;
  }

  public function setCDN($cdn) {
    $this->cdn = $cdn;
    return $this;
  }

  public function getCDN() {
    return $this->cdn;
  }

  public function setPrintType($type) {
    $this->printType = $type;
  }

  public function getPrintType() {
    return $this->printType;
  }

  public function setDeviceType($device) {
    if (in_array($device, array(DEVICE_PC, DEVICE_MOBILE))) {
      $this->deviceType = $device;
    }
    return $this;
  }

  /**
   * todo
   * Register a behavior for initialization.
   *
   * NOTE: If `$config` is empty, a behavior will execute only once even if it
   * is initialized multiple times. If `$config` is nonempty, the behavior will
   * be invoked once for each configuration.
   */
  public function initBehavior($behavior, array $config = array(), $source_name = null) {
    $this->requireResource($behavior, $source_name);
    if (empty($this->behaviors[$behavior])) {
      $this->behaviors[$behavior] = array();
    }
    if ($config) {
      $this->behaviors[$behavior][] = $config;
    }
    return $this;
  }

  public function getBehavior() {
    return $this->behaviors;
  }

  /**
   * Get uri online
   * @param BriskResourceMap $map
   * @param $name
   * @return string
   */
  public function getURI(BriskResourceMap $map, $name) {
    $uri = $map->getURIForName($name);

    // If we have a postprocessor selected, add it to the URI.
    $postprocessor_key = $this->getPostprocessorKey();
    if ($postprocessor_key) {
      $uri = preg_replace('@^/res/@', '/res/' . $postprocessor_key . 'X/', $uri);
    }

    return $this->cdn . $uri;
  }

  /**
   * Record the resource required
   * @param string $name engineering path
   * @param string $source_name project namespace
   * @return mixed $this
   * @throws Exception
   */
  public function requireResource($name, $source_name) {
    // confirm exist
    $map = BriskResourceMap::getNamedInstance($source_name);
    $symbol = $map->getNameMap()[$name];

    if ($symbol === null) {
      throw new Exception(pht(
        'No resource with name "%s" exists in source "%s"!',
        $name,
        $source_name
      ));
    }

    if (!array_key_exists($source_name, $this->symbols)) {
      $this->symbols[$source_name] = array();
    }

    $symbols = $this->symbols[$source_name];
    $resource_type = $map->getResourceTypeForName($name);

    // have been rendered, not distinguish external linked or inline
    if (array_search($name, $symbols, true) > -1 ||
      isset($this->inlined[$source_name][$resource_type][$name])) {
      return $this;
    }

    $this->symbols[$source_name][] = $name;
    $this->needsResolve = true;

    return $this;
  }

  /**
   * Inline a resource
   * @param string $name engineering path
   * @param string $source_name project namespace
   * @return BriskSafeHTML|string
   * @throws Exception
   */
  public function inlineResource($name, $source_name) {
    // confirm exist first
    $map = BriskResourceMap::getNamedInstance($source_name);
    $symbol = $map->getNameMap()[$name];
    if ($symbol === null) {
      throw new Exception(pht(
        'No resource with name "%s" exists in source "%s"!',
        $name,
        $source_name
      ));
    }

    $resource_type = $map->getResourceTypeForName($name);

    // have been inline-rendered
    if (isset($this->inlined[$source_name][$resource_type][$name])) {
      return '';
    }

    $fileContent = $map->getResourceDataForName($name, $source_name);
    $this->inlined[$source_name][$resource_type][$name] = $fileContent;

    if (BriskUtils::isAjaxPipe()) {
      return $this;
    } else {
      // render immediately, whatever current page position now
      if ($resource_type === 'js') {
        return BriskUtils::renderInlineScript($fileContent);
      } else if ($resource_type === 'css') {
        return BriskUtils::renderInlineStyle($fileContent);
      }

      return '';
    }
  }

  /**
   * inline an image using data uri
   * @param string $name resource name
   * @param string $source_name project namespace
   * @return mixed
   * @throws Exception
   */
  public function generateDataURI($name, $source_name = 'brisk') {
    $map = BriskResourceMap::getNamedInstance($source_name);
    $symbol = $map->getNameMap()[$name];
    if ($symbol === null) {
      throw new Exception(pht(
        'No resource with name "%s" exists in source "%s"!',
        $name,
        $source_name
      ));
    }

    return $map->generateDataURI($name);
  }

  // Render a single resource
  public function renderSingleResource($name, $source_name) {
    $map = BriskResourceMap::getNamedInstance($source_name);
    $symbol = $map->getNameMap()[$name];
    if ($symbol === null) {
      throw new Exception(pht(
        'No resource with name "%s" exists in source "%s"!',
        $name,
        $source_name
      ));
    }
    $packaged = $map->getPackagedNamesForNames(array($name));
    return $this->renderPackagedResources($map, $packaged);
  }

  /**
   * Output resources of specific type, html fragment.
   * For full-page requests.
   * @param string $type resource type, `js` or `css`
   * @return BriskSafeHTML
   * @throws Exception
   */
  public function renderResourcesOfType($type) {
    // update $this->packaged
    $this->resolveResources();
    $result = array();

    foreach ($this->packaged as $source_name => $resource_names) {
      $map = BriskResourceMap::getNamedInstance($source_name);
      $resources_of_type = array();
      foreach ($resource_names as $resource_name) {
        $resource_type = $map->getResourceTypeForName($resource_name);
        if ($resource_type == $type) {
          $resources_of_type[] = $resource_name;
        }
      }

      $result[] = $this->renderPackagedResources($map, $resources_of_type);
    }

    if ($type === 'js') {
      $this->printResourceMap($result);
      // modux.js prepend to all js resources
      $name = id($map->getSymbolMap())['js']['modux']['path'];
      if (!isset($this->hasRendered[$name])) {
        array_unshift($result, $this->renderResource($map, $name));
      }
    }

    return BriskDomProxy::implodeHtml('', $result);
  }

  /**
   * Output resources of specific type, html fragment.
   * For ajaxpipe requests.
   * @param string $type resource type, `js` or `css`
   * @return array
   * @throws Exception
   */
  public function renderAjaxResponseResourcesOfType($type) {
    // update $this->packaged
    $this->resolveResources();
    $result = array();

    // In mobile device mode, we should inline all resources.
    // But it conflict with a fact that, we do not know what
    // resources have been included from previous request and
    // older requests, which means the same resource may be
    // included more than once. We called it a `Wasted Bytes`.
    // There are two solution for this question:
    // I.   We return all the resource id needed and related
    //      ResourceMap object. Module loader decide how to load
    //      the resource and remove duplicated ones. Further more,
    //      loader can combo to one request through server interface.
    // II.  We use cookie to record which resource have been
    //      loaded, but the cookie is not safe, we can not depend
    //      on it completely. At present, we do not implement
    //      resource cookie recorder for user first visit.

    // ajaxpipe  eg: ['base-style', 'dialog-style']
    foreach ($this->packaged as $source_name => $resource_names) {
      $map = BriskResourceMap::getNamedInstance($source_name);
      foreach ($resource_names as $resource_name) {
        $resource_type = $map->getResourceTypeForName($resource_name);
        if ($resource_type === $type) {
          $resource_symbol = $map->getNameMap()[$resource_name];
          $result[] = $resource_symbol;
        }
      }
    }

    return array_values(array_unique($result));
  }

  /**
   * Output all inline javascript content for ajaxpipe request
   * @return array
   */
  public function produceAjaxScript() {
    // update $this->packaged
    $this->resolveResources();
    $result = array();
    $res = array(
      'resourceMap' => array(
        'js' => array(),
        'css' => array()
      )
    );

    if ($this->getPrintType() === MAP_ALL || BriskUtils::isAjaxPipe()) {
      $this->buildAllRes($res);
      $result[] = 'require.setResourceMap(' .
        json_encode($res['resourceMap']) . ');';
    } else if ($this->getPrintType() === MAP_ASYNC) {
      $this->buildAsyncRes($res);
      $result[] = 'require.setResourceMap(' .
        json_encode($res['resourceMap']) . ');';
    }

    foreach ($this->inlined as $source_name => $inlineScripts) {
      if (!empty($inlineScripts['js'])) {
        $scripts = $inlineScripts['js'];
        foreach ($scripts as $script) {
          $result[] = '(function(){' . $script . '}());';
        }
      }
    }
    return $result;
  }

  /**
   * Output all inline css content for ajaxpipe request
   * @return array
   */
  public function produceAjaxStyle() {
    $result = array();
    foreach ($this->inlined as $source_name => $inlineStyles) {
      if (!empty($inlineStyles['css'])) {
        $styles = $inlineStyles['css'];
        foreach ($styles as $style) {
          $result[] = $style;
        }
      }
    }
    return $result;
  }

  /**
   * update $this->packaged, $this->needsResolve set to false
   * @return $this
   * @throws Exception
   */
  protected function resolveResources() {
    if ($this->needsResolve) {
      $this->packaged = array();
      foreach ($this->symbols as $source_name => $names) {
        $map = BriskResourceMap::getNamedInstance($source_name);
        $packaged = $map->getPackagedNamesForNames($names);
        $this->packaged[$source_name] = $packaged;
      }
      $this->needsResolve = false;
    }
    return $this;
  }

  /**
   * 渲染全部需要的资源
   * @param BriskResourceMap $map
   * @param array $resources
   * @return array
   * @throws Exception
   */
  protected function renderPackagedResources(BriskResourceMap $map, array $resources) {
    $output = array();
    foreach ($resources as $name) {
      if (isset($this->hasRendered[$name])) {
        continue;
      }
      $this->hasRendered[$name] = true;
      $output[] = $this->renderResource($map, $name);
    }
    return $output;
  }

  // Render a single static resource
  protected function renderResource(BriskResourceMap $map, $name) {
    if ($map->isPackageResource($name)) {
      $package_info = $map->getPackageMap()[$name];
      $symbol = implode('|', $package_info['has']);
    } else {
      $symbol = $map->getNameMap()[$name];
    }

    $type = $map->getResourceTypeForName($name);
    $version = $map->getResourceVersionForName($name);
//        $multimeter = MultimeterControl::getInstance();
//        if ($multimeter) {
//            $event_type = MultimeterEvent::TYPE_STATIC_RESOURCE;
//            $multimeter->newEvent($event_type, 'rsrc.'.$name, 1);
//        }
    switch ($type) {
      case 'css':
        if ($this->deviceType === DEVICE_PC) {
          $uri = $this->getURI($map, $name);
          return BriskDomProxy::tag(
            'link',
            array(
              'rel'   => 'stylesheet',
              'type'  => 'text/css',
              'href'  => $uri,
              'data-modux-hash' => $symbol,
              'data-modux-version' => $version
            )
          );
        } else {
          return BriskDomProxy::tag(
            'style',
            array(
              'data-modux-hash' => $symbol,
              'data-modux-version' => $version
            ),
            $map->getResourceDataForName($name),
            false
          );
        }

      case 'js':
        if ($this->deviceType === DEVICE_PC) {
          $uri = $this->getURI($map, $name);
          return BriskDomProxy::tag(
            'script',
            array(
              'type' => 'text/javascript',
              'src' => $uri,
              'data-modux-hash' => $symbol,
              'data-modux-version' => $version
            )
          );
        } else {
          return BriskDomProxy::tag(
            'script',
            array(
              'data-modux-hash' => $symbol,
              'data-modux-version' => $version
            ),
            $map->getResourceDataForName($name),
            false
          );
        }
    }

    throw new Exception(pht(
      'Unable to render resource "%s", which has unknown type "%s".',
      $name,
      $type
    ));
  }

  /**
   * Print Resource Map to web page
   * @param array $result
   * @throws Exception
   */
  protected function printResourceMap(&$result) {
    $res = array(
      'resourceMap' => array(
        'js' => array(),
        'css' => array()
      )
    );

    $print_type = $this->getPrintType();

    if ($print_type === MAP_NO) {
      return;
    }

    if ($print_type === MAP_ALL) {
      $this->buildAllRes($res);
    } else if ($print_type === MAP_ASYNC) {
      $this->buildAsyncRes($res);
    }

    $json = json_encode($res['resourceMap']);
    if (!empty($res['resourceMap']['js'] || !empty($res['resourceMap']['css']))) {
      $code = BriskUtils::renderInlineScript(
        'if ("undefined" !== typeof require) {require.setResourceMap('
        . $json . ')}'
      );
      array_unshift($result, $code);
    }
  }

  protected function buildAllRes(&$res) {
    foreach ($this->packaged as $source_name => $resource_names) {
      $map = BriskResourceMap::getNamedInstance($source_name);
      $symbolMap = $map->getSymbolMap();

      foreach ($symbolMap['js'] as $symbol => $js) {
        unset($js['path']);
        unset($js['within']);
        $js['uri'] = self::getCDN() . $js['uri'];
      }

      foreach ($symbolMap['css'] as $symbol => $css) {
        unset($css['path']);
        unset($css['within']);
        $css['uri'] = self::getCDN() . $css['uri'];
      }

      $res['resourceMap']['js'] = array_merge(
        $res['resourceMap']['js'],
        $symbolMap['js']
      );
      $res['resourceMap']['css'] = array_merge(
        $res['resourceMap']['css'],
        $symbolMap['css']
      );
    }
  }

  protected function buildAsyncRes(&$res) {
    foreach ($this->packaged as $source_name => $resource_names) {
      $map = BriskResourceMap::getNamedInstance($source_name);
      $symbolMap = $map->getSymbolMap();

      foreach ($resource_names as $resource_name) {
        $js= $map->getResourceByName($resource_name);
        // only javascript can require.async
        if (isset($js['asyncLoaded'])) {
          foreach ($js['asyncLoaded'] as $required_symbol) {
            $this->addJsRes($required_symbol, $symbolMap, $res);
          }
        }
      }
    }
  }

  protected function addJsRes($required_symbol, $map, &$res) {
    if (!isset($res['resourceMap']['js'][$required_symbol])) {
      $required_js = $map['js'][$required_symbol];
      $required_css = array();
      $deps = array();

      if (isset($required_js['css'])) {
        $required_css = $required_js['css'];
      }
      if (isset($required_js['deps'])) {
        $deps = $required_js['deps'];
      }

      $res['resourceMap']['js'][$required_symbol] = array(
        'type' => 'js',
        'uri' => self::getCDN() . $required_js['uri'],
        'deps' => $deps,
        'css' => $required_css
      );

      foreach ($required_css as $required_css_symbol) {
        $this->addCssRes($required_css_symbol, $map, $res);
      }

      foreach ($deps as $required_js_symbol) {
        $this->addJsRes($required_js_symbol, $map, $res);
      }

      if (isset($required_js['asyncLoaded'])) {
        foreach ($required_js['asyncLoaded'] as $required_js_symbol) {
          $this->addJsRes($required_js_symbol, $map, $res);
        }
      }
    }
  }

  protected function addCssRes($required_symbol, $map, &$res) {
    if (!isset($res['resourceMap']['css'][$required_symbol])) {
      $required_css = $map['css'][$required_symbol];
      $res['resourceMap']['css'][$required_symbol] = array(
        'type' => 'css',
        'uri' => self::getCDN() . $required_css['uri'],
        'css' => $required_css['css']
      );
      // 加载$required_css的依赖
      foreach ($required_css['css'] as $required_css_symbol) {
        $this->addCssRes($required_css_symbol, $map, $res);
      }
    }
  }
}