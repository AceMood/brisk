<?php

/**
 * Interface to the static resource map, which is a graph of available
 * resources, resource dependencies, and packaging information. You generally do
 * not need to invoke it directly; instead, you call higher-level Brisk APIs
 * and it uses the resource map to satisfy your requests.
 */
final class BriskResourceMap extends Phobject {
    //根据空间存储资源表
    private static $instances = array();

    // resources array
    private $resources;

    // symbol => resource
    private $symbolMap;

    // package symbol => resource
    private $packageMap;

    // path => symbol
    private $nameMap;

    // symbol => package symbol
    private $componentMap;

    public function __construct() {
        $this->resources = new BriskSantaResources();
        $map = $this->resources->loadMap();

        $this->symbolMap = idx($map, 'resource', array());
        $this->packageMap = idx($map, 'packages', array());
        $this->nameMap = idx($map, 'paths', array());
        $this->componentMap = array();

        foreach ($this->packageMap as $package_name => $symbols) {
            foreach ($symbols as $symbol) {
                $this->componentMap[$symbol] = $package_name;
            }
        }
    }
    
    //获取指定名称的资源表
    public static function getNamedInstance($source_name) {
        if (empty(self::$instances[$source_name])) {
            $instance = new BriskResourceMap();
            self::$instances[$source_name] = $instance;
        }

        return self::$instances[$source_name];
    }

    //path为主键的资源表
    public function getNameMap() {
        return $this->nameMap;
    }

    //id为主键的资源表
    public function getSymbolMap() {
        return $this->symbolMap;
    }

    //打包资源信息
    public function getPackageMap() {
        return $this->packageMap;
    }

    //===========================//
    //======= 以下方法传id =======//
    //===========================//

    /**
     * 根据资源id获取资源名, 否则返回null
     * @param string $type
     * @param string $symbol Resource symbol to lookup.
     * @return string|null Resource name, or null if the symbol is unknown.
     * @throws Exception
     */
    public function getResourceNameForSymbol($type, $symbol) {
        $resource = $this->symbolMap[$type][$symbol];
        if (!isset($resource)) {
            throw new Exception(pht(
                'No resource with type "%s" exists symbol is "%s"!',
                $type,
                $symbol
            ));
        }
        if (!isset($resource['path'])) {
            return null;
        }
        return $resource['path'];
    }

    /**
     * 根据资源类型和id获取线上uri
     * @param string Resource symbol to lookup.
     * @return string|null Resource URI, or null if the symbol is unknown.
     */
    public function getURIForSymbol($type, $symbol) {
        $resource = idx($this->symbolMap[$type], $symbol);
        return $resource['uri'];
    }

    //给一个包资源名,获取包含的所有资源名
    public function getResourceNamesForPackageName($package_name) {
        $package = idx($this->packageMap, $package_name);
        if (!$package) {
            return null;
        }

        if (isset($package['has'])) {
            $resource_symbols = $package['has'];
        } else {
            $resource_symbols = array();
        }

        $resource_names = array();
        foreach ($resource_symbols as $symbol) {
            $resource_names[] = $this->getResourceNameForSymbol($package['type'], $symbol);
        }

        return $resource_names;
    }

    //============================//
    //======= 以下方法传路径 =======//
    //============================//

    //是否该资源名的资源为包资源
    public function isPackageResource($name) {
        return isset($this->packageMap[$name]);
    }

    /**
     * 根据图片资源名获取内联dateUri数据
     * @param string  Resource name to attempt to generate a data URI for.
     * @return string|null Data URI, or null if we declined to generate one.
     */
    public function generateDataURI($resource_name) {
        $type = $this->getResourceTypeForName($resource_name);
        switch ($type) {
            case 'png':
                $type = 'image/png';
                break;
            case 'gif':
                $type = 'image/gif';
                break;
            case 'jpg':
                $type = 'image/jpeg';
                break;
            default:
                return null;
        }

        // In IE8, 32KB is the maximum supported URI length.
        $maximum_data_size = (1024 * 32);

        $data = $this->getResourceDataForName($resource_name);
        if (strlen($data) >= $maximum_data_size) {
            // If the data is already too large on its own, just bail before
            // encoding it.
            return null;
        }

        $uri = 'data:' . $type . ';base64,' . base64_encode($data);
        if (strlen($uri) >= $maximum_data_size) {
            return null;
        }

        return $uri;
    }

    //获取资源类型
    public function getResourceTypeForName($name) {
        return $this->resources->getResourceType($name);
    }

    //根据资源名取得资源内容
    public function getResourceDataForName($name) {
        return $this->resources->getResourceData($name);
    }

    //给定资源名,返回线上路径
    public function getURIForName($name) {
        $type = $this->getResourceTypeForName($name);
        $symbol = idx($this->nameMap, $name);
        return $this->getURIForSymbol($type, $symbol);
    }

    //传一个资源名返回依赖的所有资源id
    public function getRequiredSymbolsForName($name) {
        $symbol = idx($this->nameMap, $name);
        if ($symbol === null) {
            return null;
        }
        $type = $this->getResourceTypeForName($name);
        $resource = idx($this->symbolMap[$type], $symbol, array());

        $arrJs = isset($resource['deps']) ? $resource['deps'] : array();
        $arrCss = isset($resource['css']) ? $resource['css'] : array();
        return array(
            'js' => $arrJs,
            'css' => $arrCss
        );
    }

    //给资源路径数组,返回所在的包资源名数组
    public function getPackagedNamesForNames(array $names) {
        $resolved = $this->resolveResources($names);
        return $this->packageResources($resolved, $names);
    }

    //==========================//
    //======= 以下私有方法 =======//
    //==========================//

    //给一组有顺序的资源路径, 返回所有需要打包的有序资源数组
    private function resolveResources(array $names) {
        $map = array();
        foreach ($names as $name) {
            if (isset($map[$name])) {
                continue;
            }
            $this->resolveResource($map, $name);
        }
        return $map;
    }

    //给一个资源名, 查询所有递归的依赖, 并存入一个map结构
    private function resolveResource(array &$map, $name) {
        if (!isset($this->nameMap[$name])) {
            throw new Exception(pht(
                'Attempting to resolve unknown resource, "%s".',
                $name
            ));
        }

        // array(js => array(), css => array());
        $requires = $this->getRequiredSymbolsForName($name);

        $map[$name] = array();
        foreach ($requires as $type => $required_symbols) {
            foreach ($required_symbols as $required_symbol) {
                $required_name = $this->getResourceNameForSymbol($type, $required_symbol);
                //map中记录依赖项
                $map[$name][] = $required_name;
                if (isset($map[$required_name])) {
                    continue;
                }
                $this->resolveResource($map, $required_name);
            }
        }
    }

    // include all resources and packages they live in
    private function packageResources(array $resolved_map, $names) {
        //记录需要引入的有序资源列表
        $packaged = array();
        //记录处理过的资源
        $handled = array();

        $names = array_reverse($names);
        foreach ($names as $name) {
            $this->loop($resolved_map, $packaged, $handled, $name);
        }

        return array_reverse($packaged);
    }

    private function loop(&$resolved_map, &$packaged, &$handled, $name) {
        $symbol = $this->nameMap[$name];
        //并未打包
        if (empty($this->componentMap[$symbol])) {
            $packaged[] = $name;
            foreach ($resolved_map[$name] as $require) {
                if (isset($handled[$require])) {
                    continue;
                }
                $this->loop($resolved_map, $packaged, $handled, $require);
            }
        } else {
            $package_name = $this->componentMap[$symbol];
            $packaged[] = $package_name;
            $package_symbols = $this->packageMap[$package_name]['has'];
            $package_type = $this->packageMap[$package_name]['type'];
            foreach ($package_symbols as $resource_symbol) {
                $resource_name = $this->getResourceNameForSymbol($package_type, $resource_symbol);
                $handled[$resource_name] = true;
                foreach ($resolved_map[$resource_name] as $require) {
                    if (isset($handled[$require])) {
                        continue;
                    }
                    $this->loop($resolved_map, $packaged, $handled, $require);
                }
            }
        }
    }
}