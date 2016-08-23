<?php

/**
 * Class BriskWidgetFactory
 */
final class BriskWidgetFactory {

    //缓存对象
    private static $cached = array();

    /**
     * 加载组件并渲染
     * @param string $path
     * @param string|null $id
     * @param string|null $mode
     * @return BriskWidgetView
     */
    public static function load($path, $id = null, $mode = null) {
        $real = realpath($path);

        if (!$real) {
            throw new Exception(pht(
                'No widget has path "%s" exists in directory "%s"!',
                $path,
                __DIR__
            ));
        }

        if (!isset($id)) {
            $id = BriskUtils::generateUniqueId();
        }

        if (!isset($mode)) {
            $mode = BriskEnv::$mode_normal;
        }

        if (isset(self::$cached[$real])) {
            if (!isset(self::$cached[$real][$mode])) {
                self::$cached[$real][$mode] = new BriskWidgetView($real, $id, $mode);
            }
            return self::$cached[$real][$mode];
        }

        self::$cached[$real] = array(
            $mode => new BriskWidgetView($real, $id, $mode)
        );

        return self::$cached[$real][$mode];
    }
    
}