<?php

class BriskAjaxResponse extends BriskStaticResourceResponse {

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