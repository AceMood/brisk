<?php

/**
 * Indirection layer which provisions for a terrifying future where we need to
 * build multiple resource responses per page.
 */
final class BriskAPI extends Phobject {
    private static $pageResponse;
    private static $ajaxResponse;

    public static function getStaticResourceResponse() {
        if (BriskUtils::isAjaxify()) {
            if (empty(self::$ajaxResponse)) {
                self::$ajaxResponse = new BriskAjaxResponse();
            }
            return self::$ajaxResponse;
        } else {
            if (empty(self::$pageResponse)) {
                self::$pageResponse = new BriskPageResponse();
            }
            return self::$pageResponse;
        }
    }
}