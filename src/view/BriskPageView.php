<?php

/**
 * Class BriskPageView
 * 渲染页面的抽象类
 */
abstract class BriskPageView extends BriskStaticResourceResponse {

    private static $mode_ajaxpipe = 'ajaxpipe';
    private static $mode_normal = 'normal';

    protected $title = '';
    protected $mode = null;
    protected $pagelets = array();
    protected $widgets = array();

    public function __construct() {
        parent::__construct();
        if (BriskUtils::isAjaxPipe()) {
            $this->mode = self::$mode_ajaxpipe;
            $this->setPagelets($_GET['pagelets']);
        } else {
            $this->mode = self::$mode_normal;
        }
    }

    public function setMode($mode) {
        if (in_array($mode, array(self::$mode_ajaxpipe, self::$mode_normal))) {
            $this->mode = $mode;
        } else {
            $this->mode = self::$mode_normal;
        }
    }

    public function getMode() {
        return $this->mode;
    }

    /**
     * 设置当前页面的pagelets
     * @param {array|string} $pagelets
     */
    public function setPagelets($pagelets) {
        if (!is_array($pagelets)) {
            $pagelets = array($pagelets);
        }
        foreach ($pagelets as $id) {
            $this->pagelets[$id] = true;
        }
    }

    public function getPagelets() {
        return $this->pagelets;
    }

    public function retrieveWidget($widgetId) {
        return $this->widgets[$widgetId];
    }

    public function loadWidget($widget) {
        $this->widgets[$widget->getId()] = $widget;
    }

    /**
     * 渲染本视图
     * @return mixed
     */
    public function render() {
        $html = '';
        switch ($this->mode) {
            case self::$mode_ajaxpipe:
                $html = $this->renderAsJSON();
                break;
            case self::$mode_normal:
                $html = $this->renderAsHTML();
                break;
        }

        return $html;
    }

    abstract protected function renderAsHTML();

    protected function renderAsJSON() {
        $response = array(
            'html' => array(),
            'js' => array(),
            'css' => array()
        );

        $payload = array();
        foreach ($this->pagelets as $pageletId) {
            $widget = $this->retrieveWidget($pageletId);
            $response['html'][$pageletId] = $widget->render();
        }



        if ($this->metadata) {
            $response['metadata'] = $this->metadata;
            $this->metadata = array();
        }

        if ($this->behaviors) {
            $response['behaviors'] = $this->behaviors;
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
            $response['resources'] = $resources;
        }

        return $response;
    }

}