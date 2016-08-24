<?php


abstract class BriskWidgetView extends BriskStaticResourceResponse {

    private static $mode_bigrender = 'bigrender';
    private static $mode_lazyrender = 'lazyrender';
    private static $mode_normal = 'normal';

    //当前部件的id, 用于替换页面中同样id的div
    protected $id = '';
    protected $mode = null;

    public function __construct() {
        parent::__construct();
    }

    public function setMode($mode) {
        if (in_array($mode, array(self::$mode_lazyrender, self::$mode_bigrender))) {
            $this->mode = $mode;
        } else {
            $this->mode = self::$mode_normal;
        }
    }

    public function getMode() {
        return $this->mode;
    }

    public function setId($id) {
        $this->id = phutil_escape_html($id);
    }

    public function getId() {
        return $this->id;
    }

    abstract protected function renderHTML();

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
                        'data-bigrender' => $this->id
                    ),
                    $this->renderHTML()
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

}