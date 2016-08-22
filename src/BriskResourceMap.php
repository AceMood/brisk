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

    public function getNameMap() {
        return $this->nameMap;
    }

    public function getSymbolMap() {
        return $this->symbolMap;
    }

    public function getPackageMap() {
        return $this->packageMap;
    }

    //===========================//
    //======= 以下方法传id =======//
    //===========================//

    /**
     * Return the resource name for a given symbol.
     *
     * @param string Resource symbol to lookup.
     * @return string|null Resource name, or null if the symbol is unknown.
     */
    public function getResourceNameForSymbol($symbol) {
        $name = array_search($symbol, $this->nameMap, true);
        return $name;
    }

    /**
     * Return the absolute URI for the resource associated with a symbol. This
     * method is fairly low-level and ignores packaging.
     *
     * @param string Resource symbol to lookup.
     * @return string|null Resource URI, or null if the symbol is unknown.
     */
    public function getURIForSymbol($type, $symbol) {
        $resource = idx($this->symbolMap[$type], $symbol);
        return $resource['uri'];
    }

    //给资源id数组返回所在的包资源名数组
    public function getPackagedNamesForSymbols(array $symbols) {
        $resolved = $this->resolveResources($symbols);

        var_dump($resolved);

        return $this->packageResources($resolved);
    }

    //给一个包资源名,获取包含的所有资源名
    public function getResourceNamesForPackageName($package_name) {
        $package_symbols = idx($this->packageMap, $package_name);
        if (!$package_symbols) {
            return null;
        }

        if (isset($package_symbols['has'])) {
            $resource_symbols = $package_symbols['has'];
        } else {
            $resource_symbols = array();
        }

        $resource_names = array();
        foreach ($resource_symbols as $symbol) {
            $resource_names[] = $this->getResourceNameForSymbol($symbol);
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

    //根据图片资源名获取内联dateUri数据
    /**
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

    /**
     * Get the epoch timestamp of the last modification time of a symbol.
     *
     * @param string Resource symbol to lookup.
     * @return int Epoch timestamp of last resource modification.
     */
    public function getModifiedTimeForName($name) {
        if ($this->isPackageResource($name)) {
            $names = array();
            foreach ($this->packageMap[$name] as $symbol) {
                $names[] = $this->getResourceNameForSymbol($symbol);
            }
        } else {
            $names = array($name);
        }
        $mtime = 0;
        foreach ($names as $name) {
            $mtime = max($mtime, $this->resources->getResourceModifiedTime($name));
        }
        return $mtime;
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

    //==========================//
    //======= 以下私有方法 =======//
    //==========================//

    //给一组有顺序的资源id,返回所有需要打包的有序资源数组
    private function resolveResources(array $symbols) {
        $map = array();
        foreach ($symbols as $symbol) {
            $name = $this->getResourceNameForSymbol($symbol);
            if (isset($map[$name])) {
                continue;
            }
            $this->resolveResource($map, $name);
        }
        return $map;
    }

    //给一个资源名,查询所有依赖并存入一个map结构
    private function resolveResource(array &$map, $name) {
        if (!isset($this->nameMap[$name])) {
            throw new Exception(pht(
                'Attempting to resolve unknown resource, "%s".',
                $name
            ));
        }

        $symbol = $this->nameMap[$name];
        $type = $this->getResourceTypeForName($name);
        //取得资源对象
        $resource = $this->symbolMap[$type][$symbol];

        if (isset($resource['deps'])) {
            $requires = $resource['deps'];
        } else {
            $requires = array();
        }

        $map[$name] = array();
        foreach ($requires as $required_symbol) {
            $required_name = $this->getResourceNameForSymbol($required_symbol);
            $map[$name][] = $required_name;
            if (isset($map[$required_name])) {
                continue;
            }
            $this->resolveResource($map, $required_name);
        }
    }

    // include all resources and packages they live in
    private function packageResources(array $resolved_map) {
        $packaged = array();
        $handled = array();

        foreach ($resolved_map as $name => $requires) {
            if (isset($handled[$name])) {
                continue;
            }

            $symbol = $this->nameMap[$name];
            //并未打包
            if (empty($this->componentMap[$symbol])) {
                $packaged[] = $name;
            } else {
                $package_name = $this->componentMap[$symbol];
                $packaged[] = $package_name;
                $package_symbols = $this->packageMap[$package_name];
                foreach ($package_symbols as $resource_symbol) {
                    $resource_name = $this->getResourceNameForSymbol($resource_symbol);
                    $handled[$resource_name] = true;
                }
            }
        }

        return $packaged;
    }
}