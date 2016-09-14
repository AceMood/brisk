<?php

/**
 * Class BriskPageView
 * 渲染页面的抽象类
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

    final function addMetadata($metadata) {
        $this->response->addMetadata($metadata);
        return $this;
    }

    final function setTitle($title) {
        $this->title = $title;
        return $this;
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
        return $this;
    }

    final function getMode() {
        return $this->mode;
    }

    /**
     * 设置当前页面的pagelets
     * @param {array|string} $pagelets
     * @return mixed
     */
    final function setPagelets($pagelets) {
        if (!is_array($pagelets)) {
            $pagelets = array($pagelets);
        }
        foreach ($pagelets as $id) {
            $this->pagelets[] = $id;
        }
        return $this;
    }

    final function getPagelets() {
        return $this->pagelets;
    }

    final function setCDN($cdn) {
        $this->response->setCDN($cdn);
        return $this;
    }

    final function getCDN() {
        return $this->response->getCDN();
    }

    /**
     * 组件类型是页面
     * @return bool
     */
    final function isPage() {
        return true;
    }

    /**
     * 设置资源表打印类型
     * @param integer $type
     */
    final function setPrintType($type) {
        if (isset($this->response)) {
            $this->response->setPrintType($type);
        }
    }

    /**
     * 渲染期间加载对应的部件.
     * 正常渲染则直接输出部件html内容, 否则记录页面部件
     * @param BriskWidgetView $widget
     * @return PhutilSafeHTML|$this
     */
    final function loadWidget($widget) {
        $widget->setParentView($this);
        if ($this->mode === self::$mode_normal) {
            return $widget->renderAsHTML();
        } else {
            $this->widgets[$widget->getId()] = $widget;
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
     * 内联资源
     * @param string $name 工程目录资源路径
     * @param string $source_name 空间
     * @return mixed
     * @throws Exception
     */
    final function inlineResource($name, $source_name = 'brisk') {
        return $this->response->inlineResource($name, $source_name);
    }

    /**
     * 返回图片内联为dataUri的方式
     * @param $name
     * @param $source_name
     * @return mixed
     * @throws Exception
     */
    final function generateDataURI($name, $source_name = 'brisk') {
        return $this->response->generateDataURI($name, $source_name);
    }

    /**
     * 将一种类型的资源输出到页面
     * @param string $type 资源类型如js, css
     * @return PhutilSafeHTML
     */
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
                //这里不需要加载页面全局的资源, 不再调用loadGlobalResources
                $this->willRender();
                $html = $this->renderAsJSON();
                break;
            case self::$mode_normal:
                $this->willRender();
                $this->loadGlobalResources();
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
            $this->response->renderResourcesOfType('js')
        );
    }

    /**
     * 渲染页面成json
     * @return array
     * @throws Exception
     */
    protected function renderAsJSON() {
        $res = array(
            'payload' => array()
        );

        //挑选需要渲染的部件
        foreach ($this->pagelets as $pageletId) {
            if (!isset($this->getWidgets()[$pageletId])) {
                throw new Exception(pht(
                    'No widget with id %s found in %s',
                    $pageletId,
                    __CLASS__
                ));
            }

            $widget = $this->getWidgets()[$pageletId];

            $res['payload'][$pageletId] = $widget->renderAsJSON();
            $res['js'] = $this->response->renderResourcesOfType('js');
            $res['css'] = $this->response->renderResourcesOfType('css');
            $res['script'] = $this->response->produceScript();
            $res['style'] = $this->response->produceStyle();
        }

        // 需要元数据但不需要behavior
        $metadata = $this->response->getMetadata();
        if (!empty($metadata)) {
            $res['metadata'] = $metadata;
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

    //渲染前触发, 子类可重写
    protected function willRender() {}

    //全页面渲染的时候加载页面级别的资源
    abstract function loadGlobalResources();

}