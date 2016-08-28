<?php

class BriskAjaxResponse extends BriskStaticResourceResponse {

    //渲染输出一种资源类型的json
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

        return $result;
    }

    //渲染输出行内的javascript
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

        return $result;
    }

//
//    public function renderHTMLFooter() {
//        $data = array();
//        if ($this->metadata) {
//            $json_metadata = AphrontResponse::encodeJSONForHTTPResponse(
//                $this->metadata);
//            $this->metadata = array();
//        } else {
//            $json_metadata = '{}';
//        }
//
//        // Even if there is no metadata on the page, Javelin uses the mergeData()
//        // call to start dispatching the event queue.
//        $data[] = 'JX.Stratcom.mergeData('.$this->metadataBlock.', '.
//            $json_metadata.');';
//
//        $onload = array();
//        if ($this->behaviors) {
//            $behaviors = $this->behaviors;
//            $this->behaviors = array();
//            $higher_priority_names = array(
//                'refresh-csrf',
//                'aphront-basic-tokenizer',
//                'dark-console',
//                'history-install',
//            );
//
//            $higher_priority_behaviors = array_select_keys(
//                $behaviors,
//                $higher_priority_names);
//
//            foreach ($higher_priority_names as $name) {
//                unset($behaviors[$name]);
//            }
//
//            $behavior_groups = array(
//                $higher_priority_behaviors,
//                $behaviors,
//            );
//
//            foreach ($behavior_groups as $group) {
//                if (!$group) {
//                    continue;
//                }
//                $group_json = AphrontResponse::encodeJSONForHTTPResponse(
//                    $group);
//                $onload[] = 'JX.initBehaviors('.$group_json.')';
//            }
//        }
//
//        if ($onload) {
//            foreach ($onload as $func) {
//                $data[] = 'JX.onload(function(){'.$func.'});';
//            }
//        }
//
//        if ($data) {
//            $data = implode("\n", $data);
//            return self::renderInlineScript($data);
//        } else {
//            return '';
//        }
//    }
}