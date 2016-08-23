<?php

/**
 * @class utilize functions
 */

final class BriskUtils {

    public static function isAjaxPipe() {
        return $_GET['ajaxpipe'] == 1;
    }
    
}