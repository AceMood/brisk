<?php


abstract class BriskWidgetView extends BriskView {

    public function __construct($path, $id, $mode) {
        parent::__construct($id, $mode);
    }

    abstract protected function renderHTML();

    public function render() {
        $html = '';
        switch ($this->mode) {
            case BriskEnv::$mode_normal:
                $html = $this->renderHTML();
                break;
            case BriskEnv::$mode_ajaxpipe:
                $html = $this->renderHTML();
                break;
            case BriskEnv::$mode_bigrender:
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
            case BriskEnv::$mode_lazyrender:
                $html = phutil_tag(
                    'textarea',
                    array(
                        'class' => 'g_soi_lazyrender',
                        'style' => 'display:none;'
                    ),
                    'BigPipe.asyncLoad({id: "' . $this->id . '"});'
                );
                $html->appendHTML(phutil_tag(
                    'div',
                    array(
                        'id' => $this->id
                    )
                ));
                break;
            case BriskEnv::$mode_bigpipe:
                //todo
                break;
        }

        return $html;
    }

}