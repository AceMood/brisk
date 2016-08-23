<?php


abstract class BriskPageView extends BriskView {

    protected $title = '';

    public function __construct($path, $id, $mode) {
        parent::__construct($id, $mode);
    }

    abstract protected function renderHTML();

    public function render() {
        $html = '';
        switch ($this->mode) {
            case BriskEnv::$mode_ajaxpipe:
                $html = $this->renderHTML();
                break;
            default:
                $html = $this->renderHTML();
                break;
        }

        return $html;
    }

}