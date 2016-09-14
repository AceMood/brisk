<?php

/**
 * Class BriskAjaxResponse
 * 
 */
class BriskAjaxResponse extends BriskStaticResourceResponse {

    /**
     * 输出外链资源类型的json格式, 如 ['base-style', 'dialog-style']
     * @param string $type 资源类型
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
        $res = array(
            'resourceMap' => array(
                'js' => array(),
                'css' => array()
            )
        );

        switch ($this->getPrintType()) {
            case BriskPrintType::$ALL_RES:
                $this->buildAllRes($res);
                $result[] = 'kerneljs.setResourceMap(' . json_encode($res) . ');';
                break;
            case BriskPrintType::$ONLY_ASYNC:
                $this->buildAsyncRes($res);
                $result[] = 'kerneljs.setResourceMap(' . json_encode($res) . ');';
                break;
        }

        foreach ($this->inlined as $source_name => $inlineScripts) {
            if (!empty($inlineScripts['js'])) {
                $scripts = $inlineScripts['js'];
                foreach ($scripts as $script) {
                    $result[] = '~function(){' . $script . '}();';
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
     * 资源内联
     * @param string $name
     * @param string $source_name
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

        $fileContent = $map->getResourceDataForName($name, $source_name);
        $this->inlined[$source_name][$resource_type][$name] = $fileContent;
        return $this;
    }
}