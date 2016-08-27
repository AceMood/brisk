<?php

/**
 * Class BriskWidgetView
 * 所有页面分片部件的基类.
 * 同一个部件类的不同实例可在多个页面通过id,以及mode区分
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

    public function __construct($id = '', $mode = null) {
        $this->setId($id);
        $this->setMode($mode);
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
    }

    final function getMode() {
        return $this->mode;
    }

    final function setId($id) {
        $this->id = phutil_escape_html($id);
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

    //设置当前部件的父级视图
    final function setParentView($parent) {
        $this->parentView = $parent;
    }

    final function getParentView() {
        return $this->parentView;
    }

    //部件中加载静态资源
    final function requireResource($name, $source_name) {
        if (!isset($this->parentView)) {
            throw new Exception(pht(
                'Could not invoke requireResource with no parentView set. %s',
                __CLASS__
            ));
        }

        //lazyrender或者
        if ($this->mode === self::$mode_lazyrender) {

        }
        //否则直接记录在最顶层的page view中
        else {
            $this->parentView->requireResource($name, $source_name);
        }
    }

    //
    final function getTopContainerView() {
        $parent = null;
        while (isset($this->parentView) && $this->parentView->isPage()) {
            $parent = $this->parentView;
        }
        return $parent;
    }

    /**
     * 渲染本视图
     * @return string
     */
    public function render() {
        $html = '';
        switch ($this->mode) {
            case self::$mode_normal:
                $html = $this->renderAsHTML();
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
                    $this->renderAsHTML()
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

        $response = array(
            'html' => array(),
            'js' => array(),
            'css' => array(),
            'script' => array(),
            'style' => array()
        );

        //更新$this->packaged
        $this->parentView->resolveResources();
        $resources = array();

        foreach ($this->packaged as $source_name => $resource_names) {
            $map = BriskResourceMap::getNamedInstance($source_name);
            foreach ($resource_names as $resource_name) {
                $resources[] = $this->parentView->getURI($map, $resource_name);
            }
        }

        if ($resources) {
            $response['resources'] = $resources;
        }

        return $response;
    }

    /**
     * 渲染部件的html
     * @return string
     * @throws Exception
     */
    abstract function renderAsHTML();

    //返回部件的模版字符串
    abstract function getTemplateString();
}