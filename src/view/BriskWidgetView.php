<?php

/**
 * Class BriskWidgetView
 * 所有页面分片部件的基类.
 * 同一个部件类的不同实例可在多个页面通过id,以及mode区分
 * WidgetView对不用渲染模式需要提供两个方法进行渲染,
 * 1. 顶级页面正常渲染, 部件提供renderAsHTML方法,
 *    依据初始化时指定的模式渲染, normal, bigrender 或者lazyrender
 * 2. 顶级页面通过quickling渲染, 部件提供renderAsJSON方法
 */
abstract class BriskWidgetView extends Phobject {

    private static $mode_bigrender = 'bigrender';
    private static $mode_lazyrender = 'lazyrender';
    private static $mode_normal = 'normal';

    //当前部件的id, 用于替换页面中同样id的div
    private $id = '';
    //当前部件的渲染模式
    private $mode = null;
    //当前部件的父级视图
    private $parentView = null;
    //当前部件包含的子部件
    private $widgets = array();

    public function __construct($id = '', $mode = null) {
        if (empty($id)) {
            $id = BriskUtils::generateUniqueId();
        }

        $this->setId($id)->setMode($mode);
    }

    final function setMode($mode) {
        if (in_array($mode, array(
            self::$mode_lazyrender,
            self::$mode_bigrender
        ))) {
            $this->mode = $mode;
        } else {
            $this->mode = self::$mode_normal;
        }
        return $this;
    }

    final function getMode() {
        return $this->mode;
    }

    final function setId($id) {
        $this->id = phutil_escape_html($id);
        return $this;
    }

    final function getId() {
        return $this->id;
    }

    final function isPage() {
        return false;
    }

    final function isWidget() {
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

    //设置当前部件的父级视图
    final function setParentView($parent) {
        $this->parentView = $parent;
    }

    final function getParentView() {
        return $this->parentView;
    }

    /**
     * 部件中加载静态资源
     * @param string $name
     * @param string|null $source_name
     * @throws Exception
     */
    final function requireResource($name, $source_name = 'brisk') {
        if (!isset($this->parentView)) {
            throw new Exception(pht(
                'Could not invoke requireResource with no parentView set. %s',
                __CLASS__
            ));
        }

        //直接记录在最顶层的page view中
        $topView = $this->getTopLevelView();
        if (isset($topView)) {
            $topView->requireResource($name, $source_name);
        }
    }

    /**
     * 部件中内联静态资源
     * @param string $name
     * @param string|null $source_name
     * @throws Exception
     */
    final function inlineResource($name, $source_name = 'brisk') {
        if (!isset($this->parentView)) {
            throw new Exception(pht(
                'Could not invoke requireResource with no parentView set. %s',
                __CLASS__
            ));
        }

        //直接记录在最顶层的page view中
        $topView = $this->getTopLevelView();
        if (isset($topView)) {
            $topView->inlineResource($name, $source_name);
        }
    }

    /**
     * 获取顶层的pageview对象
     * @return BriskPageView
     */
    final function getTopLevelView() {
        $parent = $this->getParentView();
        while (isset($parent) && !($parent->isPage())) {
            $parent = $parent->getParentView();
        }
        return $parent;
    }

    /**
     * 渲染本视图
     * @return string
     */
    public function renderAsHTML() {
        $html = '';
        $this->willRender();
        switch ($this->mode) {
            case self::$mode_normal:
                $html = $this->produceHTML();
                break;
            case self::$mode_bigrender:
                $html = phutil_tag(
                    'textarea',
                    array(
                        'class' => 'g_soi_bigrender',
                        'style' => 'display:none;',
                        'data-bigrender' => 1,
                        'data-pageletId' => $this->id
                    ),
                    $this->produceHTML()
                );
                $html->appendHTML(phutil_tag(
                    'div',
                    array(
                        'id' => $this->id
                    )
                ));
                break;
            case self::$mode_lazyrender:
                $html = phutil_tag(
                    'textarea',
                    array(
                        'class' => 'g_soi_lazyrender',
                        'style' => 'display:none;'
                    ),
                    hsprintf(
                        'BigPipe.asyncLoad({id: "%s"});',
                        $this->id
                    )
                );
                $html->appendHTML(phutil_tag(
                    'div',
                    array(
                        'id' => $this->id
                    )
                ));
                break;
        }

        return $html;
    }

    /**
     * 渲染部件视图为json
     * @return array
     * @throws Exception
     */
    public function renderAsJSON() {
        if (!isset($this->parentView)) {
            throw new Exception(pht(
                'Could not invoke requireResource with no parentView set. %s',
                __CLASS__
            ));
        }

        $this->willRender();
        return $this->produceHTML();
    }

    /**
     * 生成html部分, 此方法可在子类重写
     * @return string
     */
    protected function produceHTML() {
        return (string)hsprintf(
            new PhutilSafeHTML($this->getTemplateString())
        );
    }

    //渲染前触发, 子类可重写
    protected function willRender() {}

    /**
     * 返回部件的模版字符串, 各子类具体实现
     * @return string
     */
    abstract function getTemplateString();
}