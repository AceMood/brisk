<?php

/**
 * Tracks and resolves dependencies the page declares with
 * @{function:require_static_resource}, and then builds appropriate HTML or
 * Ajax responses.
 */
class BriskStaticResourceResponse extends Phobject {
    //动态设置cdn
    protected $cdn = '';

    //收集所有打印的外链资源唯一路径
    protected $symbols = array();

    //记录打印的内联资源唯一id
    protected $inlined = array();
    protected $styles = array();
    protected $scripts = array();

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

    public function addMetadata($metadata) {
        $id = count($this->metadata);
        $this->metadata[$id] = $metadata;
        return $this;
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
    }

    public function getCDN() {
        return $this->cdn;
    }

    /**
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

        //之前渲染过,不区分外链还是内联
        if (array_search($name, $symbols, true) > -1 ||
            isset($this->inlined[$source_name][$name])) {
            return $this;
        }

        $this->symbols[$source_name][] = $name;
        $this->needsResolve = true;

        return $this;
    }

    /**
     * 资源内联 todo
     * @param $name
     * @param $source_name
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

        //之前已经内联渲染过
        if (isset($this->inlined[$source_name][$name])) {
            return '';
        }

        //立即渲染,不优化输出位置
        $fileContent = $map->getResourceDataForName($name, $source_name);
        $this->inlined[$source_name][$name] = true;

        $type = $map->getResourceTypeForName($name);
        if ($type === 'js') {
            return self::renderInlineScript($fileContent);
        } else if ($type === 'css') {
            return self::renderInlineStyle($fileContent);
        }

        return '';
    }

    /**
     * 将一张图片内联为dataUri的方式
     * @param $name
     * @param $source_name
     * @return mixed
     * @throws Exception
     */
    public function inlineImage($name, $source_name = 'brisk') {
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

    //渲染输出一种资源类型的html片段
    public function renderResourcesOfType($type) {
        //更新$this->packaged
        $this->resolveResources();
        $result = array();
        $print = array();

        if ($type === 'js') {
            $print = array(
                'resourceMap' => array(
                    'js' => array(),
                    'css' => array()
                )
            );
        }

        foreach ($this->packaged as $source_name => $resource_names) {
            $map = BriskResourceMap::getNamedInstance($source_name);

            //记录到打印的资源表
            if ($type === 'js') {
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

                $print['resourceMap'] = $symbolMap;
            }

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
            $mapCode = self::renderInlineScript('var kerneljs = ' . json_encode($print) . ';');
            array_unshift($result, $mapCode);
        }

        return phutil_implode_html('', $result);
    }

    //根据资源名获取线上路径
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
                    ));
            case 'js':
                return phutil_tag(
                    'script',
                    array(
                        'type'  => 'text/javascript',
                        'src'   => $uri,
                    ));
        }

        throw new Exception(pht(
            'Unable to render resource "%s", which has unknown type "%s".',
            $name,
            $type
        ));
    }

    //根据内容渲染内联style
    protected static function renderInlineStyle($data) {
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
        return phutil_tag(
            'style',
            array(),
            phutil_safe_html($data));
    }

    //根据内容渲染内联script
    protected static function renderInlineScript($data) {
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
        // We don't use <![CDATA[ ]]> because it is ignored by HTML parsers. We
        // would need to send the document with XHTML content type.
        return phutil_tag(
            'script',
            array('type' => 'text/javascript'),
            phutil_safe_html($data));
    }
}