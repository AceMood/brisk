<?php

/**
 * Tracks and resolves dependencies the page declares with
 * @{function:require_static}, and then builds appropriate HTML or
 * Ajax responses.
 * @file
 * @author AceMood
 * @email zmike86@gmail.com
 */

//-------------

class BriskStaticResourceResponse {
  // 动态设置cdn
  protected $cdn = '';

  // 默认打印全部资源表
  protected $printType = MAP_ASYNC;

  // 当前浏览的设备类型
  protected $deviceType = DEVICE_MOBILE;

  // 收集所有打印的外链资源唯一路径
  protected $symbols = array();

  // 记录打印的内联资源唯一id
  protected $inlined = array();

  // 是否需要对收集的资源进行解析
  protected $needsResolve = true;

  // 命名空间划分,记录外链引用的资源
  protected $packaged;

  // 记录一些元数据
  protected $metadata = array();

  // 页面初始化需要的行为
  protected $behaviors = array();

  // 记录是否被渲染过
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
    if (in_array($device, array(
      DEVICE_PC, DEVICE_MOBILE
    ))) {
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
   * 根据资源名获取线上路径
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
   * 记录请求依赖的外链资源
   * @param string $name 工程目录资源路径
   * @param string $source_name 空间
   * @return mixed $this
   * @throws Exception
   */
  public function requireResource($name, $source_name) {
    //首先确认资源表存在
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

    // 之前渲染过,不区分外链还是内联
    if (array_search($name, $symbols, true) > -1 ||
      isset($this->inlined[$source_name][$resource_type][$name])) {
      return $this;
    }

    $this->symbols[$source_name][] = $name;
    $this->needsResolve = true;

    return $this;
  }

  /**
   * 资源内联
   * @param string $name 资源在工程目录的路径
   * @param string $source_name 项目空间
   * @return BriskSafeHTML|string
   * @throws Exception
   */
  public function inlineResource($name, $source_name) {
    //首先确认资源存在
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

    // 之前已经内联渲染过
    if (isset($this->inlined[$source_name][$resource_type][$name])) {
      return '';
    }

    $fileContent = $map->getResourceDataForName($name, $source_name);
    $this->inlined[$source_name][$resource_type][$name] = $fileContent;

    if (BriskUtils::isAjaxPipe()) {
      return $this;
    } else {
      // 立即渲染,不优化输出位置
      if ($resource_type === 'js') {
        return BriskUtils::renderInlineScript($fileContent);
      } else if ($resource_type === 'css') {
        return BriskUtils::renderInlineStyle($fileContent);
      }

      return '';
    }
  }

  /**
   * 将图片内联为dataUri的方式
   * @param $name
   * @param $source_name
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

  // 单独渲染一个外链资源
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
   * 渲染输出一种资源类型的html片段
   * @param string $type 资源类型
   * @return BriskSafeHTML
   * @throws Exception
   */
  public function renderResourcesOfType($type) {
    // 更新$this->packaged
    $this->resolveResources();
    $result = array();

    // ajaxpipe方式输出外链资源名称的json格式,
    // 如 ['base-style', 'dialog-style']
    if (BriskUtils::isAjaxPipe()) {
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
    } else {
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
        // modux插入到最前面
        $name = id($map->getSymbolMap())['js']['modux']['path'];
        if (!isset($this->hasRendered[$name])) {
          array_unshift($result, $this->renderResource($map, $name));
        }
      }

      return BriskDomProxy::implodeHtml('', $result);
    }
  }

  /**
   * 输出行内的javascript
   * @return array
   */
  public function produceScript() {
    //更新$this->packaged
    $this->resolveResources();
    $result = array();
    $res = array(
      'resourceMap' => array(
        'js' => array(),
        'css' => array()
      )
    );

    switch ($this->getPrintType()) {
      case MAP_ALL:
        $this->buildAllRes($res);
        $result[] = 'require.setResourceMap('
          . json_encode($res['resourceMap']) . ');';
        break;
      case MAP_ASYNC:
        $this->buildAsyncRes($res);
        $result[] = 'require.setResourceMap('
          . json_encode($res['resourceMap']) . ');';
        break;
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
   * 输出内联css
   * @return array
   */
  public function produceStyle() {
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
   * 更新$this->packaged, $this->needsResolve标示false
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

  // 渲染单个资源
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
   * 输出打印资源表信息
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
      //记录到打印的资源表
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
      //记录到打印的资源表
      $symbolMap = $map->getSymbolMap();
      foreach ($symbolMap['js'] as $symbol => $js) {
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
      $res['resourceMap']['js'][$required_symbol] = array(
        'type' => 'js',
        'uri' => self::getCDN() . $required_js['uri'],
        'deps' => $required_js['deps'],
        'css' => $required_js['css']
      );
      // 加载$required_js的依赖
      foreach ($required_js['css'] as $required_css_symbol) {
        $this->addCssRes($required_css_symbol, $map, $res);
      }
      foreach ($required_js['asyncLoaded'] as $required_js_symbol) {
        $this->addJsRes($required_js_symbol, $map, $res);
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