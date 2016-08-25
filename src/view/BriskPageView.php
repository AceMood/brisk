<?php

/**
 * Class BriskPageView
 * 渲染页面的抽象类
 */
abstract class BriskPageView extends BriskStaticResourceResponse {

    private static $mode_ajaxpipe = 'ajaxpipe';
    private static $mode_normal = 'normal';

    //页面标题
    protected $title = '';
    protected $mode = null;
    protected $pagelets = array();
    protected $widgets = array();

    public function __construct($title = '') {
        parent::__construct();
        $this->setTitle($title);
        if (BriskUtils::isAjaxPipe()) {
            $this->mode = self::$mode_ajaxpipe;
            $this->setPagelets($_GET['pagelets']);
        } else {
            $this->mode = self::$mode_normal;
        }
    }

    public function setTitle($title) {
        $this->title = $title;
    }

    public function getTitle() {
        return $this->title;
    }

    public function setMode($mode) {
        if ($mode === self::$mode_ajaxpipe) {
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

    /**
     * 渲染期间加载对应的部件
     * @param $widget
     * @return mixed
     */
    public function loadWidget($widget) {
        //正常渲染则直接输出部件html内容
        if ($this->mode === self::$mode_normal) {
            return $widget->render();
        //否则记录页面部件
        } else {
            $this->widgets[] = $widget;
            return null;
        }
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

    //渲染页面成html
    protected function renderAsHTML() {
        return (string)hsprintf(
            $this->getTemplateString(),
            $this->title,
            $this->renderResourcesOfType('css'),
            '',
            $this->renderResourcesOfType('js'));
    }

    protected function renderAsJSON() {
        $response = array(
            'html' => array(),
            'js' => array(),
            'css' => array(),
            'script' => array(),
            'style' => array()
        );

        //收集所有部件的html部分
        foreach ($this->pagelets as $pageletId) {
            $widget = $this->retrieveWidget($pageletId);
            $json = $widget->renderAsJSON();
            $response['html'][$pageletId] = $json['html'];
            $response['js'][] = $json['js'];
            $response['css'][] = $json['css'];
            $response['script'][] = $json['script'];
            $response['style'][] = $json['style'];
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

    //获取默认的页面模板,不可在子类复写
    protected function getTemplateString() {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8" />' .
        '<title>%s</title>%s</head><body>%s%s</body></html>';
    }

}