<?php

require_once 'BriskResourceCollector.php';
require_once 'BriskConfig.php';
require_once 'BriskUtils.php';


/**
 * @file 构造pagelet的html以及所需要的静态资源json
 * @from FISP
 */
class BriskPage {

    const CSS_HOOK        = '<!--[CSS_HOOK]-->';
    const JS_HOOK         = '<!--[JS_HOOK]-->';
    const CSS_TAG         = '<link type="text/css" rel="stylesheet" href="@href" />';
    const JS_TAG          = '<script type="text/javascript" src="@src"></script>';

    const MODE_NOSCRIPT   = 0;
    const MODE_QUICKLING  = 1;
    const MODE_BIGPIPE    = 2;
    const MODE_BIGRENDER  = 3;

    private static $cdn;
    private static $title = '';

    // 渲染模式
    private static $mode  = null;
    // 默认渲染模式
    private static $defaultMode = null;
    // 保存本次请求的pagelets
    private static $filter;

    //
    private static $context = array();
    private static $contextMap = array();

    // 收集pagelet分组
    private static $pageletGroup = array();

    /**
     * 收集widget内部使用的静态资源
     * array(
     *  0: array(), 1: array(), 2: array()
     * )
     * @var array
     */
    protected static $inner_widget = array(
        array(),
        array(),
        array()
    );

    // widget时记录pagelet id
    protected static $pageletId = null;
    // 用于记录没有id的页面分片
    protected static $sessionId = 0;

    protected static $pageLets = array();
    // 某一个widget使用那种模式渲染
    protected static $widgetMode;

    /* 保存当前inline script或style的id */
    static $cp;

    /* 保存当前inline script或style的id数组 */
    static $embeded = array();

    /**
     * 用渲染模式初始化本次请求
     * @param {?string} $defaultMode set default render mode
     */
    public static function init($defaultMode) {
        $mode = self::parseMode($defaultMode);
        if (is_string($defaultMode) &&
            in_array(
                $mode,
                array(self::MODE_BIGPIPE, self::MODE_NOSCRIPT))
        ) {
            self::$defaultMode = $mode;
        } else {
            self::$defaultMode = self::MODE_NOSCRIPT;
        }

        $isAjax = BriskUtils::isAjax();
        if ($isAjax) {
            self::setMode(self::MODE_QUICKLING);
        } else {
            self::setMode(self::$defaultMode);
        }

        // 记录数组
        self::setFilter($_GET['pagelets']);
    }

    /**
     * 设置渲染模式, 为self::$mode赋值, 没提供则默认quickling模式
     * @param {?number} $mode
     */
    public static function setMode($mode) {
        self::$mode = isset($mode) ? intval($mode) : 1;
    }

    /**
     * 获取当前会话渲染模式
     * @return {?number}
     */
    public static function getMode() {
        return self::$mode;
    }

    /**
     * 返回渲染模式的整型表示.
     * 默认是 self::$mode.
     * @param  {string} $mode
     * @return {?number}
     */
    private static function parseMode($mode) {
        $m = self::$mode;
        $mode = strtoupper($mode);
        switch($mode) {
            case 'BIGPIPE':
                $m = self::MODE_BIGPIPE;
                break;
            case 'QUICKLING':
                $m = self::MODE_QUICKLING;
                break;
            case 'NOSCRIPT':
                $m = self::MODE_NOSCRIPT;
                break;
            case 'BIGRENDER':
                $m = self::MODE_BIGRENDER;
                break;
        }
        return $m;
    }

    /**
     * Set pagelets
     * @param {array|string} $ids
     */
    private static function setFilter($ids) {
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        foreach ($ids as $id) {
            self::$filter[$id] = true;
        }
    }

    // 设置页面title
    public static function setTitle($title) {
        self::$title = $title;
    }

    // 收集行内script
    public static function addScript($code) {
        if(self::$context['hit'] || self::$mode === self::$defaultMode) {
            BriskResourceCollector::addScript($code);
        }
    }

    // 收集行内style
    public static function addStyle($code) {
        if(self::$context['hit'] || self::$mode === self::$defaultMode) {
            BriskResourceCollector::addStyle($code);
        }
    }

    // Output css placeholder when render into page.
    public static function drawCSSHook() {
        echo self::CSS_HOOK;
    }

    // Output js placeholder when render into page.
    public static function drawJSHook() {
        echo self::JS_HOOK;
    }

    /**
     * 收集资源
     * @param {string} $type
     * @param {string} $symbol
     * @param {bool}   $async
     */
    public static function load($type, $symbol, $async = false) {
        if (self::$context['hit'] || self::$mode === self::$defaultMode) {
            BriskResourceCollector::load($type, $symbol, $async);
        }
    }

    // Set cdn domain
    public static function setCDN($cdn) {
        $cdn = trim($cdn);
        self::$cdn = $cdn;
    }

    // Get cdn domain
    public static function getCDN() {
        return self::$cdn;
    }

    // 构建需要打印在页面中的资源表, 这里全部打印
    protected static function buildResourceMap($map) {
        $ret = array(
            BriskConfig::CSS => array(),
            BriskConfig::JS => array()
        );
        foreach ($map[BriskConfig::JS] as $symbol) {
            if (preg_match('/^p(\d)+$/', $symbol) !== 1) {
                $js = BriskResourceCollector::getResource(BriskConfig::JS, $symbol);
                $ret[BriskConfig::JS][$symbol] = array(
                    BriskConfig::ATTR_URI => self::getCDN() . $js[BriskConfig::ATTR_URI],
                    BriskConfig::ATTR_DEP => $js[BriskConfig::ATTR_DEP],
                    BriskConfig::ATTR_CSS => $js[BriskConfig::ATTR_CSS]
                );
            }
        }

        foreach ($map[BriskConfig::CSS] as $symbol) {
            if (preg_match('/^p(\d)+$/', $symbol) !== 1) {
                $css = BriskResourceCollector::getResource(BriskConfig::CSS, $symbol);
                $ret[BriskConfig::CSS][$symbol] = array(
                    BriskConfig::ATTR_URI => self::getCDN() . $css[BriskConfig::ATTR_URI],
                    BriskConfig::ATTR_DEP => $css[BriskConfig::ATTR_DEP],
                    BriskConfig::ATTR_CSS => $css[BriskConfig::ATTR_CSS]
                );
            }
        }

        return array(
            BriskConfig::JS => array_merge(
                $ret[BriskConfig::JS],
                $map[BriskConfig::ATTR_ASYNCLOADED][BriskConfig::JS]
            ),
            BriskConfig::CSS => array_merge(
                $ret[BriskConfig::CSS],
                $map[BriskConfig::ATTR_ASYNCLOADED][BriskConfig::CSS]
            )
        );
    }

    /**
     * 根据资源表返回页面中的js片段
     * @param {array} $res
     * @return {string}
     */
    protected static function genJsFragment($res) {
        // 只有加载js时打印资源表才有意义
        $resourceMap = self::buildResourceMap($res);
        $loadModJs = BriskResourceCollector::getFramework();

        $code = '<!-- brisk render js start -->';

        if (!empty($resourceMap)) {
            $code .= '<script type="text/javascript">';
            $code .= 'var kerneljs = {resourceMap:' . json_encode($resourceMap).'};';
            $code .= '</script>';
        }

        if ($loadModJs) {
            $code .= str_replace(
                    '@src',
                    self::getCDN() . BriskResourceCollector::getFramework(),
                    self::JS_TAG
                ) . PHP_EOL;
        }

        foreach ($res[BriskConfig::JS] as $symbol) {
            if (preg_match('/^p(\d)+$/', $symbol) === 1) {
                $js = BriskResourceCollector::getPackage($symbol);
            } else {
                $js = BriskResourceCollector::getResource(BriskConfig::JS, $symbol);
            }

            if ($js[BriskConfig::ATTR_URI] === BriskResourceCollector::getFramework()) {
                continue;
            }

            $code .= str_replace(
                    '@src',
                    self::getCDN() . $js[BriskConfig::ATTR_URI],
                    self::JS_TAG
                ) . PHP_EOL;
        }

        if (!empty($res[BriskConfig::SCRIPT])) {
            $code .= '<script type="text/javascript">' . PHP_EOL;
            foreach ($res[BriskConfig::SCRIPT] as $inner_script) {
                $code .= '!function(){' . $inner_script . '}();' . PHP_EOL;
            }
            $code .= '</script>';
        }

        $code .= '<!-- brisk render js end -->';

        return $code;
    }

    /**
     * 根据资源表返回页面中的css片段
     * @param {array} $res
     * @return {string}
     */
    protected static function genCssFragment($res) {
        $code = '<!-- brisk render css start -->';

        if (!empty($res[BriskConfig::CSS])) {
            foreach ($res[BriskConfig::CSS] as $symbol) {
                if (preg_match('/^p(\d)+$/', $symbol) === 1) {
                    $css = BriskResourceCollector::getPackage($symbol);
                } else {
                    $css = BriskResourceCollector::getResource(BriskConfig::CSS, $symbol);
                }

                $code .= str_replace(
                        '@href',
                        self::getCDN() . $css[BriskConfig::ATTR_URI],
                        self::CSS_TAG
                    ) . PHP_EOL;
            }
        }
        if (!empty($res[BriskConfig::STYLE])) {
            $code .= '<style type="text/css">' . PHP_EOL;
            foreach ($res[BriskConfig::STYLE] as $inner_style) {
                $code .= $inner_style;
            }
            $code .= PHP_EOL . '</style>';
        }

        $code .= '<!-- brisk render css end -->';

        return $code;
    }

    /**
     * 将资源渲染到页面替换页面中的占位符
     * @param {string} $html html片段
     * @param {array}  $staticResource
     * @param {bool}   $cleanHook 是否把页面中的占位符清除
     * @return mixed
     */
    protected static function renderStatic($html, $staticResource, $cleanHook = false) {
        if (!empty($staticResource)) {
            $code = self::genJsFragment($staticResource);
            $html = str_replace(self::JS_HOOK, $code . self::JS_HOOK, $html);

            $code = self::genCssFragment($staticResource);
            $html = str_replace(self::CSS_HOOK, $code . self::CSS_HOOK, $html);
        }

        if ($cleanHook) {
            $html = str_replace(array(self::CSS_HOOK, self::JS_HOOK), '', $html);
        }

        return $html;
    }

    /**
     * BigRender模式下将惰性执行的代码放到textarea元素中
     * @param {string} $html html片段
     * @return mixed
     */
    protected static function insertPageletGroup($html) {
        // 无widget直接返回
        if (empty(self::$pageletGroup)) {
            return $html;
        }

        $search = array();
        $replace = array();

        foreach(self::$pageletGroup as $group => $ids) {
            $search[] = '<!--' . $group . '-->';
            $replace[] = '<textarea class="g_bigrender g_bigrender_'
                . $group . '" style="display: none">BigPipe.asyncLoad([{id: "'
                . implode('"},{id:"', $ids)
                .'"}])</textarea>';
        }

        return str_replace($search, $replace, $html);
    }

    /**
     * 合并两个资源表
     * @param {array} $arr
     * @param {array} $arr2
     * @return {array}
     */
    protected static function mergeResource(array $arr, array $arr2) {
        $res = array(
            BriskConfig::JS => array(),
            BriskConfig::CSS => array(),
            BriskConfig::SCRIPT => array(),
            BriskConfig::STYLE => array(),
            BriskConfig::ATTR_ASYNCLOADED => array(
                BriskConfig::JS => array(),
                BriskConfig::CSS => array(),
                BriskConfig::SCRIPT => array(),
                BriskConfig::STYLE => array()
            )
        );

        foreach ($res as $key => $val) {
            if (!is_array($arr[$key])) {
                $arr[$key] = $val;
            }

            if (!is_array($arr2[$key])) {
                $arr2[$key] = $val;
            }

            if ($key !== BriskConfig::ATTR_ASYNCLOADED) {
                $merged = array_merge($arr[$key], $arr2[$key]);
                $merged = array_merge(array_unique($merged));
            } else {
                $merged = array(
                    BriskConfig::JS => array_merge(
                        $arr[BriskConfig::ATTR_ASYNCLOADED][BriskConfig::JS],
                        $arr2[BriskConfig::ATTR_ASYNCLOADED][BriskConfig::JS]
                    ),
                    BriskConfig::CSS => array_merge(
                        $arr[BriskConfig::ATTR_ASYNCLOADED][BriskConfig::CSS],
                        $arr2[BriskConfig::ATTR_ASYNCLOADED][BriskConfig::CSS]
                    )
                );
            }

            $arr[$key] = $merged;
        }

        return $arr;
    }

    /**
     * 渲染静态页面部分
     * @param  {string} $html 全部要返回的html内容
     * @return {mixed|string}
     */
    public static function render($html) {
        // 处理bigrender模式下的一些内容替换
        $html = self::insertPageletGroup($html);

        $pagelets = self::$pageLets;
        $mode = self::$mode;

        // 打印到页面中的资源表
        $res = array();

        // 合并资源
        foreach (self::$inner_widget[$mode] as $widgetResources) {
            $res = self::mergeResource($res, $widgetResources);
        }

        // tpl信息没有必要打到页面
        switch($mode) {
            case self::MODE_NOSCRIPT:
                $all_static = BriskResourceCollector::getPageStaticResource();
                $all_static = self::mergeResource($all_static, $res);
                $html = self::renderStatic($html, $all_static, true);
                break;
            case self::MODE_QUICKLING:
                // 返回json
                header('Content-Type: text/json; charset=utf-8');
                $all_static = BriskResourceCollector::getPageStaticResource();
                $res = self::mergeResource($all_static, $res);

                foreach ($res[BriskConfig::JS] as $index => $symbol) {
                    if (preg_match('/^p(\d)+$/', $symbol) === 1) {
                        $js = BriskResourceCollector::getPackage($symbol);
                    } else {
                        $js = BriskResourceCollector::getResource(BriskConfig::JS, $symbol);
                    }

                    $js[BriskConfig::ATTR_URI] = self::getCDN() . $js[BriskConfig::ATTR_URI];
                    $js['id'] = $symbol;
                    $res[BriskConfig::JS][$index] = $js;
                }

                foreach ($res[BriskConfig::CSS] as $index => $symbol) {
                    if (preg_match('/^p(\d)+$/', $symbol) === 1) {
                        $css = BriskResourceCollector::getPackage($symbol);
                    } else {
                        $css = BriskResourceCollector::getResource(BriskConfig::CSS, $symbol);
                    }

                    $css[BriskConfig::ATTR_URI] = self::getCDN() . $css[BriskConfig::ATTR_URI];
                    $css['id'] = $symbol;
                    $res[BriskConfig::CSS][$index] = $css;
                }

                if ($res[BriskConfig::SCRIPT]) {
                    $res[BriskConfig::SCRIPT] = BriskUtils::convertToUtf8(
                        implode("\n", $res[BriskConfig::SCRIPT])
                    );
                }

                if ($res[BriskConfig::STYLE]) {
                    $res[BriskConfig::STYLE] = BriskUtils::convertToUtf8(
                        implode("\n", $res[BriskConfig::STYLE])
                    );
                }

                foreach ($pagelets as &$pagelet) {
                    $pagelet['html'] = BriskUtils::convertToUtf8(
                        self::insertPageletGroup($pagelet['html'])
                    );
                }

                unset($pagelet);
                $title = BriskUtils::convertToUtf8(self::$title);
                $html = json_encode(array(
                    'title' => $title,
                    'pagelets' => $pagelets,
                    'resourceMap' => $res
                ));
                break;
            case self::MODE_BIGPIPE:
//        $external = BriskResourceCollector::getPageStaticResource();
//        $page_script = $external['script'];
//        unset($external['script']);
//        $html = self::renderStatic(
//          $html,
//          $external,
//          true
//        );
//        $html .= "\n";
//        $html .= '<script type="text/javascript">';
//        $html .= 'BigPipe.onPageReady(function() {';
//        $html .= implode("\n", $page_script);
//        $html .= '});';
//        $html .= '</script>';
//        $html .= "\n";
//
//        if ($res['script']) {
//          $res['script'] = convertToUtf8(implode("\n", $res['script']));
//        }
//        if ($res['style']) {
//          $res['style'] = convertToUtf8(implode("\n", $res['style']));
//        }
//        $html .= "\n";
//        foreach($pagelets as $index => $pagelet){
//          $id = '__cnt_' . $index;
//          $html .= '<code style="display:none" id="' . $id . '"><!-- ';
//          $html .= str_replace(
//            array('\\', '-->'),
//            array('\\\\', '--\\>'),
//            self::insertPageletGroup($pagelet['html'])
//          );
//          unset($pagelet['html']);
//          $pagelet['html_id'] = $id;
//          $html .= ' --></code>';
//          $html .= "\n";
//          $html .= '<script type="text/javascript">';
//          $html .= "\n";
//          $html .= 'BigPipe.onPageletArrived(';
//          $html .= json_encode($pagelet);
//          $html .= ');';
//          $html .= "\n";
//          $html .= '</script>';
//          $html .= "\n";
//        }
//        $html .= '<script type="text/javascript">';
//        $html .= "\n";
//        $html .= 'BigPipe.register(';
//        if(empty($res)){
//          $html .= '{}';
//        } else {
//          $html .= json_encode($res);
//        }
//        $html .= ');';
//        $html .= "\n";
//        $html .= '</script>';
            break;
        }

        return $html;
    }

    /**
     * 解析参数, 收集widget所用到的静态资源
     * @param {?string} $id    分片id
     * @param {?string} $mode  渲染模式
     * @param {?string} $group 分组id
     * @return {bool}
     */
    public static function widgetStart($id, $mode = null, $group = null) {
        $hasParent = !empty(self::$context);
        // widget渲染模式
        if ($mode) {
            $widgetMode = self::parseMode($mode);
        } else {
            $widgetMode = self::$mode;
        }

        // 记录当前分片id
        self::$pageletId = $id;

        $parentId = $hasParent ? self::$context['id'] : '';
        $flag = (self::$mode === self::MODE_QUICKLING ? '_qk_' : '');

        // 没传id的自动生成一个
        $id = empty($id) ? '__elm_' . $parentId . '_' . $flag . self::$sessionId++ : $id;


        $parent = self::$context;
        $hasParent = !empty($parent);

        $hit = true;
        $context = array(
            'id'   => $id,          // widget id
            'mode' => $widgetMode,  // 当前widget的mode
            'hit'  => $hit          // 是否命中
        );

        if ($hasParent) {
            $context['parent_id'] = $parent['id'];
            self::$contextMap[$parent['id']] = $parent;
        }

        if ($widgetMode === self::MODE_NOSCRIPT) {
            // 只有指定pagelet_id的widget才嵌套一层div
            if (self::$pageletId) {
                echo '<div id="' . $id . '">';
            }
        } else {
            if ($widgetMode === self::MODE_BIGRENDER) {
                // widget为bigrender时，将内容渲染到html注释里面
                if (!$hasParent) {
                    echo '<div id="' . $id . '">';
                    echo '<code class="g_bigrender"><!--';
                }
            } else {
                echo '<div id="' . $id . '">';
            }

            if (self::$mode === self::MODE_QUICKLING) {
                $hit = self::$filter[$id];
                // 如果父widget被命中，则子widget设置为命中
                if ($hasParent && $parent['hit']) {
                    $hit = true;
                } else if ($hit) {
                    //指定获取一个子widget时，需要单独处理这个widget
                    $context['parent_id'] = null;
                    $hasParent = false;
                }
            } else if ($widgetMode === self::MODE_QUICKLING) {
                // 渲染模式不是quickling时，可以认为是首次渲染
                if (self::$pageletId && self::$mode !== self::MODE_QUICKLING) {
                    if (!$group) {
                        echo '<textarea class="g_bigrender" style="display:none;">'
                            .'BigPipe.asyncLoad({id: "'.$id.'"});'
                            .'</textarea>';
                    } else {
                        if (isset(self::$pageletGroup[$group])) {
                            self::$pageletGroup[$group][] = $id;
                        } else {
                            self::$pageletGroup[$group] = array($id);
                            echo "<!--" . $group . "-->";
                        }
                    }
                }
                // 不需要渲染这个widget
                $hit = false;
            }

            $context['hit'] = $hit;

            if ($hit) {
                if (!$hasParent) {
                    //获取widget内部的静态资源
                    BriskResourceCollector::widgetStart();
                }
                //start a buffer
                ob_start();
            }
        }

        // 设置当前处理context
        self::$context = $context;

        return $hit;
    }

    /**
     * WIDGET END
     * 收集html, 收集静态资源
     */
    public static function widgetEnd($id = null) {
        $ret = true;

        $context = self::$context;
        $widgetMode = $context['mode'];
        $hasParent = $context['parent_id'];

        if ($id) {
            self::$pageletId = $id;
        }

        if ($widgetMode === self::MODE_NOSCRIPT) {
            if (self::$pageletId) {
                echo '</div>';
            }
        } else {
            if ($context['hit']) {
                //close buffer
                $html = ob_get_clean();
                if (!$hasParent) {
                    $widget = BriskResourceCollector::widgetEnd();
                    // end
                    if ($widgetMode === self::MODE_BIGRENDER) {
                        $widget_style = $widget['style'];
                        $widget_script = $widget['script'];
                        // 内联css和script放到注释里面, 不需要收集
                        unset($widget['style']);
                        unset($widget['script']);

                        $out = '';
                        if ($widget_style) {
                            $out .= '<style type="text/css">'. implode('', $widget_style) . '</style>';
                        };

                        $out .= $html;
                        if ($widget_script) {
                            $out .= '<script type="text/javascript">' . implode('', $widget_script) . '</script>';
                        }
                        echo str_replace (
                            array('\\', '-->'),
                            array('\\\\', '--\\>'),
                            $out
                        );

                        echo '--></code></div>';

                        // 收集外链的js和css
                        self::$inner_widget[self::$mode][] = $widget;

                    } else {
                        $context['html'] = $html;
                        // 删除不需要的信息
                        unset($context['mode']);
                        unset($context['hit']);
                        // not parent
                        unset($context['parent_id']);
                        self::$pageLets[] = $context;
                        self::$inner_widget[$widgetMode][] = $widget;
                    }
                } else {
                    // end
                    if ($widgetMode === self::MODE_BIGRENDER) {
                        echo $html;
                    } else {
                        $context['html'] = $html;
                        // 删除不需要的信息
                        unset($context['mode']);
                        unset($context['hit']);
                        self::$pageLets[] = $context;
                    }
                }
            }

            if ($widgetMode !== self::MODE_BIGRENDER) {
                //echo '! MODE_BIGRENDER';
                echo '</div>';
            }
        }

        // 切换context
        self::$context = self::$contextMap[$context['parent_id']];
        unset(self::$contextMap[$context['parent_id']]);
        if (!$hasParent) {
            self::$context = null;
        }

        return $ret;
    }
}
