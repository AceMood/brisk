<?php

class BriskAjaxResponse extends BriskStaticResourceResponse {

    /**
     * 输出外链资源类型的json格式, 如 ['base-style', 'dialog-style']
     * @param $type
     * @return array
     */
    public function renderResourcesOfType($type) {
        //更新$this->packaged
        $this->resolveResources();
        $result = array();

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
     * 输出行内的javascript
     * @return array
     */
    public function produceScript() {
        //更新$this->packaged
        $this->resolveResources();
        $result = array();
        $print = array(
            'resourceMap' => array(
                'js' => array(),
                'css' => array()
            )
        );

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

            $print['resourceMap'] = $symbolMap;
        }

        $result[] = 'kerneljs.setResourceMap(' . json_encode($print) . ');';

        if (!empty($this->inlined)) {
            $inlined = $this->inlined;
            $this->inlined = array();
            foreach ($inlined as $script) {
                $result[] = '~function(){'.$script.'}();';
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
        if (!empty($this->inlined)) {
            $inlined = $this->inlined;
            $this->inlined = array();
            foreach ($inlined as $style) {
                $result[] = $style;
            }
        }
        return $result;
    }
}