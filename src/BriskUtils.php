<?php

/**
 * @class utilize functions
 */

final class BriskUtils {

    public static function isAjaxPipe() {
        return $_GET['ajaxpipe'] == 1;
    }

    /**
     * 为dom节点生成唯一id
     * @return string
     */
    public static function generateUniqueId() {
        static $uniqueIdCounter = 0;
        $t = 'node';
        return 'brisk_' . $t . '_' . ($uniqueIdCounter++);
    }
    
}