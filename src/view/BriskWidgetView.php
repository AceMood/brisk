<?php


class BriskWidgetView extends BriskStandardView {

    public static function load($path, $mode) {
        switch ($mode) {
            case BriskEnv::$mode_normal:
                include $path;
                break;
            case BriskEnv::$mode_bigrender:

                break;
        }
    }

    public function render() {

    }

}