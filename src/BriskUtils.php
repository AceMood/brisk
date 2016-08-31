<?php

/**
 * @class utilize functions
 */

final class BriskUtils {

    public static function isAjaxPipe() {
        return isset($_GET['ajaxpipe']) && ($_GET['ajaxpipe'] == 1);
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

    /**
     * 根据内容渲染内联style
     * @param $data
     * @return PhutilSafeHTML
     * @throws Exception
     */
    public static function renderInlineStyle($data) {
        if (stripos($data, '</style>') !== false) {
            throw new Exception(pht(
                'Literal %s is not allowed inside inline style.',
                '</style>'));
        }
        if (strpos($data, '<!') !== false) {
            throw new Exception(pht(
                'Literal %s is not allowed inside inline style.',
                '<!'));
        }
        // We don't use <![CDATA[ ]]> because it is ignored by HTML parsers. We
        // would need to send the document with XHTML content type.
        return phutil_tag(
            'style',
            array(),
            phutil_safe_html($data));
    }

    /**
     * 根据内容渲染内联script
     * @param $data
     * @return PhutilSafeHTML
     * @throws Exception
     */
    public static function renderInlineScript($data) {
        if (stripos($data, '</script>') !== false) {
            throw new Exception(pht(
                'Literal %s is not allowed inside inline script.',
                '</script>'));
        }
        if (strpos($data, '<!') !== false) {
            throw new Exception(pht(
                'Literal %s is not allowed inside inline script.',
                '<!'));
        }
        // We don't use <![CDATA[ ]]> because it is ignored by HTML parsers. We
        // would need to send the document with XHTML content type.
        return phutil_tag(
            'script',
            array('type' => 'text/javascript'),
            phutil_safe_html($data));
    }
}