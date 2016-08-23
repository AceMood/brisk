<?php

abstract class BriskStandardView extends Phobject {

    private $mode;

    function __construct($mode) {
        $this->mode = BriskEnv::$mode_normal;
        if (isset($mode)) {
            $this->mode = $mode;
        }

        if (BriskUtils::isAjaxify()) {
            $this->mode = BriskEnv::$mode_quickling;
        }
    }

    abstract function render();


}