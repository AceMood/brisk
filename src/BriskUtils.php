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
     * Generate a node ID which is guaranteed to be unique for the current page,
     * even across Ajax requests. You should use this method to generate IDs for
     * nodes which require a uniqueness guarantee.
     * @return string
     */
    public static function generateUniqueId() {
        static $uniqueIdCounter = 0;
        $t = 'node';
        return 'brisk_' . $t . '_' . ($uniqueIdCounter++);
    }
    
}