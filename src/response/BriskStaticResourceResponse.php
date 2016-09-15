<?php

/**
 * Tracks and resolves dependencies the page declares with
 * @{function:require_static_resource}, and then builds appropriate HTML or
 * Ajax responses.
 */
class BriskStaticResourceResponse extends Phobject {
    //动态设置cdn
    protected $cdn = '';

    //默认打印全部资源表
    protected $printType = 3;

    //收集所有打印的外链资源唯一路径
    protected $symbols = array();
    //记录打印的内联资源唯一id
    protected $inlined = array();
    //是否需要对收集的资源进行解析
    protected $needsResolve = true;
    //命名空间划分,记录外链引用的资源
    protected $packaged;

    //记录一些元数据
    protected $metadata = array();

    //页面初始化需要加载的框架
    protected $behaviors = array();

    //记录是否被渲染过
    protected $hasRendered = array();

    protected $postprocessorKey;

    public function __construct() {}

    final function addMetadata($metadata) {
        $id = count($this->metadata);
        $this->metadata[$id] = $metadata;
        return $this;
    }

    final function getMetadata() {
        return $this->metadata;
    }

    final function setPostprocessorKey($postprocessor_key) {
        $this->postprocessorKey = $postprocessor_key;
        return $this;
    }

    final function getPostprocessorKey() {
        return $this->postprocessorKey;
    }

    final function setCDN($cdn) {
        $this->cdn = $cdn;
    }

    final function getCDN() {
        return $this->cdn;
    }

    /**
     * 设置资源表打印类型
     * @param integer $type
     */
    final function setPrintType($type) {
        $this->printType = $type;
    }

    final function getPrintType() {
        return $this->printType;
    }

    /**
     * todo
     * Register a behavior for initialization.
     *
     * NOTE: If `$config` is empty, a behavior will execute only once even if it
     * is initialized multiple times. If `$config` is nonempty, the behavior will
     * be invoked once for each configuration.
     */
    final function initBehavior($behavior, array $config = array(), $source_name = null) {
        $this->requireResource($behavior, $source_name);
        if (empty($this->behaviors[$behavior])) {
            $this->behaviors[$behavior] = array();
        }
        if ($config) {
            $this->behaviors[$behavior][] = $config;
        }
        return $this;
    }

    final function getBehavior() {
        return $this->behaviors;
    }

    /**
     * 根据资源名获取线上路径
     * @param BriskResourceMap $map
     * @param $name
     * @return string
     */
    final function getURI(BriskResourceMap $map, $name) {
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
    public function requireResource($name, $source_name = 'brisk') {
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

        //之前渲染过,不区分外链还是内联
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
     * @return PhutilSafeHTML|string
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

        //之前已经内联渲染过
        if (isset($this->inlined[$source_name][$resource_type][$name])) {
            return '';
        }

        //立即渲染,不优化输出位置
        $fileContent = $map->getResourceDataForName($name, $source_name);
        $this->inlined[$source_name][$resource_type][$name] = $fileContent;
        if ($resource_type === 'js') {
            return BriskUtils::renderInlineScript($fileContent);
        } else if ($resource_type === 'css') {
            return BriskUtils::renderInlineStyle($fileContent);
        }

        return '';
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

    //单独渲染一个外链资源
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
     * @return PhutilSafeHTML
     * @throws Exception
     */
    public function renderResourcesOfType($type) {
        //更新$this->packaged
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
        }

        return phutil_implode_html('', $result);
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

    //渲染整个资源
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

    //渲染单个资源
    protected function renderResource(BriskResourceMap $map, $name) {
        $symbol = $map->getNameMap()[$name];
        $uri = $this->getURI($map, $name);
        $type = $map->getResourceTypeForName($name);
//        $multimeter = MultimeterControl::getInstance();
//        if ($multimeter) {
//            $event_type = MultimeterEvent::TYPE_STATIC_RESOURCE;
//            $multimeter->newEvent($event_type, 'rsrc.'.$name, 1);
//        }
        switch ($type) {
            case 'css':
                return phutil_tag(
                    'link',
                    array(
                        'rel'   => 'stylesheet',
                        'type'  => 'text/css',
                        'href'  => $uri,
                        'data-modux-hash' => $symbol
                    ));
            case 'js':
                return phutil_tag(
                    'script',
                    array(
                        'type'  => 'text/javascript',
                        'src'   => $uri,
                        'data-modux-hash' => $symbol
                    ));
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

        switch ($this->getPrintType()) {
            case BriskPrintType::$ALL_RES:
                $this->buildAllRes($res);
                $code = BriskUtils::renderInlineScript(
                    'var kerneljs = ' . json_encode($res) . ';'
                );
                array_unshift($result, $code);
                break;
            case BriskPrintType::$ONLY_ASYNC:
                $this->buildAsyncRes($res);
                $code = BriskUtils::renderInlineScript(
                    'var kerneljs = ' . json_encode($res) . ';'
                );
                array_unshift($result, $code);
                break;
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
                foreach ($js['asyncLoaded'] as $required_symbol) {
                    $this->addJsRes($required_symbol, $symbolMap, $res);
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