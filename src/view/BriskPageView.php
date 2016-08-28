<?php

/**
 * Class BriskPageView
 * 渲染页面的抽象类
 * 
 */
abstract class BriskPageView extends Phobject {

    private static $mode_ajaxpipe = 'ajaxpipe';
    private static $mode_normal = 'normal';

    //页面标题
    private $title = '';
    //页面渲染模式
    private $mode = null;
    //页面需然渲染的分片id
    private $pagelets = array();
    //页面分片的部件
    private $widgets = array();

    //当前请求页面关联的response对象
    private $response = null;

    function __construct($title = '') {
        $this->setTitle($title);
        if (BriskUtils::isAjaxPipe()) {
            $this->mode = self::$mode_ajaxpipe;
            $this->setPagelets($_GET['pagelets']);
            $this->response = new BriskAjaxResponse();
        } else {
            $this->mode = self::$mode_normal;
            $this->response = new BriskStaticResourceResponse();
        }
    }

    final function setTitle($title) {
        $this->title = $title;
    }

    final function getTitle() {
        return $this->title;
    }

    final function setMode($mode) {
        if ($mode === self::$mode_ajaxpipe) {
            $this->mode = $mode;
        } else {
            $this->mode = self::$mode_normal;
        }
    }

    final function getMode() {
        return $this->mode;
    }

    /**
     * 设置当前页面的pagelets
     * @param {array|string} $pagelets
     */
    final function setPagelets($pagelets) {
        if (!is_array($pagelets)) {
            $pagelets = array($pagelets);
        }
        foreach ($pagelets as $id) {
            $this->pagelets[$id] = true;
        }
    }

    final function getPagelets() {
        return $this->pagelets;
    }

    final function setCDN($cdn) {
        $this->response->setCDN($cdn);
    }

    final function getCDN() {
        return $this->response->getCDN();
    }

    final function isPage() {
        return true;
    }

    /**
     * 渲染期间加载对应的部件
     * @param BriskWidgetView $widget
     * @return PhutilSafeHTML|$this
     */
    final function loadWidget($widget) {
        $widget->setParentView($this);
        //正常渲染则直接输出部件html内容
        if ($this->mode === self::$mode_normal) {
            return $widget->renderAsHTML();
        }
        //否则记录页面部件
        else {
            $this->widgets[] = $widget;
            return $this;
        }
    }

    final function getWidgets() {
        return $this->widgets;
    }

    /**
     * 记录请求依赖的外链资源
     * @param string $name 工程目录资源路径
     * @param string $source_name 空间
     * @return mixed $this
     * @throws Exception
     */
    final function requireResource($name, $source_name = 'brisk') {
        return $this->response->requireResource($name, $source_name);
    }

    /**
     * 将一张图片内联为dataUri的方式
     * @param $name
     * @param $source_name
     * @return mixed
     * @throws Exception
     */
    final function inlineImage($name, $source_name = 'brisk') {
        return $this->response->requireResource($name, $source_name);
    }

    final function renderResourcesOfType($type) {
        return $this->response->renderResourcesOfType($type);
    }

    /**
     * 渲染本视图
     * @return string
     */
    final function render() {
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

    /**
     * 渲染页面成html
     * @return string
     * @throws Exception
     */
    protected function renderAsHTML() {
        return (string)hsprintf(
            $this->getTemplateString(),
            phutil_escape_html($this->title),
            $this->response->renderResourcesOfType('css'),
            new PhutilSafeHTML(''),
            $this->response->renderResourcesOfType('js'));
    }

    /**
     * 渲染页面成json
     * @return array
     * @throws Exception
     */
    protected function renderAsJSON() {
        $res = array(
            'payload' => array(),
            'js' => array(),
            'css' => array(),
            'script' => array(),
            'style' => array()
        );

        //挑选需要渲染的部件
        foreach ($this->pagelets as $pageletId) {
            $widget = $this->widgets[$pageletId];
            if (isset($widget)) {
                throw new Exception(pht(
                    'No widget with id %s found in %s',
                    $pageletId,
                    __CLASS__
                ));
            }

            $res['payload'][$pageletId] = $widget->renderAsJSON();
            $res['js'] = $this->response->renderResourcesOfType('js');
            $res['css'] = $this->response->renderResourcesOfType('css');
            $res['script'] = $this->response->produceScript();
        }

        if ($this->metadata) {
            $res['metadata'] = $this->metadata;
            $this->metadata = array();
        }

        if ($this->behaviors) {
            $res['behaviors'] = $this->behaviors;
            $this->behaviors = array();
        }

        return json_encode($res);
    }

    /**
     * 获取默认的页面模板,可在子类复写
     * @return string
     */
    protected function getTemplateString() {
        return
<<<EOTEMPLATE
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8" />
        <title>%s</title>
        %s
    </head>
    <body>%s</body>
    %s
    </html>
EOTEMPLATE;
    }

}