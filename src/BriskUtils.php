<?php

/**
 * @class utilize functions
 */

final class BriskUtils {

    public static function isAjaxify() {
        return $_GET['ajaxify'] == 1;
    }
    
}