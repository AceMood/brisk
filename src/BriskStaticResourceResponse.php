<?php

/**
 * Tracks and resolves dependencies the page declares with
 * @{function:require_static_resource}, and then builds appropriate HTML or
 * Ajax responses.
 */
final class BriskStaticResourceResponse extends Phobject {
    //当前请求的渲染模式
    private $mode;

    //动态设置cdn
    private $cdn = '';

    //收集所有打印的外链资源唯一id
    private $symbols = array();

    //记录打印的内联资源唯一id
    private $inlined = array();

    private $needsResolve = true;

    //命名空间划分,记录引用的资源
    private $packaged;

    private $metadata = array();

    private $metadataBlock = 0;

    //页面初始化需要加载的框架
    private $behaviors = array();

    private $hasRendered = array();

    private $postprocessorKey;

    public function __construct($mode = null) {
        if (isset($_REQUEST['__metablock__'])) {
            $this->metadataBlock = (int)$_REQUEST['__metablock__'];
        }

        $this->mode = BriskEnv::$mode_normal;
        if (isset($mode)) {
            $this->mode = $mode;
        }
        
        if (BriskUtils::isAjaxify()) {
            $this->mode = BriskEnv::$mode_quickling;
        }
    }

    public function addMetadata($metadata) {
        $id = count($this->metadata);
        $this->metadata[$id] = $metadata;
        return $this->metadataBlock . '_' . $id;
    }

    public function getMetadataBlock() {
        return $this->metadataBlock;
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

    //记录请求依赖的外链资源
    /**
     * @param string $name 工程目录资源路径
     * @param string $source_name 空间
     * @return mixed $this
     * @throws Exception
     */
    public function requireResource($name, $source_name) {
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

        //之前渲染过,不区分外链还是内联
        if (isset($this->symbols[$source_name][$symbol]) ||
            isset($this->inlined[$source_name][$symbol])) {
            return $this;
        }

        $this->symbols[$source_name][$symbol] = true;
        $this->needsResolve = true;

        return $this;
    }

    //资源内联
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
        if (isset($this->inlined[$source_name][$symbol])) {
            return '';
        }

        //立即渲染,不优化输出位置
        $fileContent = $map->getResourceDataForName($name, $source_name);
        $this->inlined[$source_name][$symbol] = $fileContent;

        $type = $map->getResourceTypeForName($name);
        if ($type === 'js') {
            return self::renderInlineScript($fileContent);
        } else if ($type === 'css') {
            return self::renderInlineStyle($fileContent);
        }

        return '';
    }

    //资源内联
    public function inlineImage($name, $source_name) {
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
        $packaged = $map->getPackagedNamesForSymbols(array($symbol));
        return $this->renderPackagedResources($map, $packaged);
    }

    //渲染输出一种资源类型的html片段
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
        return phutil_implode_html('', $result);
    }

    //
    public function renderHTMLFooter() {
        $data = array();
        if ($this->metadata) {
            $json_metadata = AphrontResponse::encodeJSONForHTTPResponse(
                $this->metadata);
            $this->metadata = array();
        } else {
            $json_metadata = '{}';
        }

        // Even if there is no metadata on the page, Javelin uses the mergeData()
        // call to start dispatching the event queue.
        $data[] = 'JX.Stratcom.mergeData('.$this->metadataBlock.', '.
            $json_metadata.');';

        $onload = array();
        if ($this->behaviors) {
            $behaviors = $this->behaviors;
            $this->behaviors = array();
            $higher_priority_names = array(
                'refresh-csrf',
                'aphront-basic-tokenizer',
                'dark-console',
                'history-install',
            );

            $higher_priority_behaviors = array_select_keys(
                $behaviors,
                $higher_priority_names);

            foreach ($higher_priority_names as $name) {
                unset($behaviors[$name]);
            }

            $behavior_groups = array(
                $higher_priority_behaviors,
                $behaviors,
            );

            foreach ($behavior_groups as $group) {
                if (!$group) {
                    continue;
                }
                $group_json = AphrontResponse::encodeJSONForHTTPResponse(
                    $group);
                $onload[] = 'JX.initBehaviors('.$group_json.')';
            }
        }

        if ($onload) {
            foreach ($onload as $func) {
                $data[] = 'JX.onload(function(){'.$func.'});';
            }
        }

        if ($data) {
            $data = implode("\n", $data);
            return self::renderInlineScript($data);
        } else {
            return '';
        }
    }

    //
    public function buildAjaxResponse($payload, $error = null) {
        $response = array(
            'error'   => $error,
            'payload' => $payload,
        );

        if ($this->metadata) {
            $response['javelin_metadata'] = $this->metadata;
            $this->metadata = array();
        }

        if ($this->behaviors) {
            $response['javelin_behaviors'] = $this->behaviors;
            $this->behaviors = array();
        }

        //更新$this->packaged
        $this->resolveResources();
        $resources = array();
        foreach ($this->packaged as $source_name => $resource_names) {
            $map = BriskResourceMap::getNamedInstance($source_name);
            foreach ($resource_names as $resource_name) {
                $resources[] = $this->getURI($map, $resource_name);
            }
        }

        if ($resources) {
            $response['javelin_resources'] = $resources;
        }

        return $response;
    }

    //根据资源名获取线上路径
    public function getURI(BriskResourceMap $map, $name) {
        $uri = $map->getURIForName($name);
        // If we have a postprocessor selected, add it to the URI.
        $postprocessor_key = $this->getPostprocessorKey();
        if ($postprocessor_key) {
            $uri = preg_replace('@^/res/@', '/res/' . $postprocessor_key . 'X/', $uri);
        }
        // In developer mode, we dump file modification times into the URI. When a
        // page is reloaded in the browser, any resources brought in by Ajax calls
        // do not trigger revalidation, so without this it's very difficult to get
        // changes to Ajaxed-in CSS to work (you must clear your cache or rerun
        // the map script). In production, we can assume the map script gets run
        // after changes, and safely skip this.
        if (BriskEnv::$devmode) {
            $mtime = $map->getModifiedTimeForName($name);
            $uri = preg_replace('@^/res/@', '/res/' . $mtime . 'T/', $uri);
        }

        return $this->cdn . $uri;
    }

    //更新$this->packaged,$this->needsResolve标示false
    /**
     * @return $this
     * @throws Exception
     */
    private function resolveResources() {
        if ($this->needsResolve) {
            $this->packaged = array();
            foreach ($this->symbols as $source_name => $symbols_map) {
                $symbols = array_keys($symbols_map);
                $map = BriskResourceMap::getNamedInstance($source_name);
                $packaged = $map->getPackagedNamesForSymbols($symbols);
                $this->packaged[$source_name] = $packaged;
            }
            $this->needsResolve = false;
        }
        return $this;
    }

    //
    private function renderPackagedResources(BriskResourceMap $map, array $resources) {
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
    private function renderResource(BriskResourceMap $map, $name) {
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
    private static function renderInlineStyle($data) {
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
    private static function renderInlineScript($data) {
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