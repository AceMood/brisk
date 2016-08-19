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
        $this->resources = new SantaResources();
        $map = $this->resources->loadMap();
        $this->symbolMap = idx($map, 'symbols', array());
        $this->packageMap = idx($map, 'packages', array());
        $this->nameMap = idx($map, 'paths', array());
        $this->componentMap = array();
        foreach ($this->packageMap as $package_name => $symbols) {
            foreach ($symbols as $symbol) {
                $this->componentMap[$symbol] = $package_name;
            }
        }
    }
    
    /**
     * get and return resourceMap instance through unique name
     * @static
     */
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

    //给一个资源id返回所在的包资源id
    public function getPackagedNamesForSymbols(array $symbols) {
        $resolved = $this->resolveResources($symbols);
        return $this->packageResources($resolved);
    }

    //根据资源名取得资源内容
    public function getResourceDataForName($name) {
        return $this->resources->getResourceData($name);
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

    /**
     * Return the absolute URI for the resource associated with a symbol. This
     * method is fairly low-level and ignores packaging.
     *
     * @param string Resource symbol to lookup.
     * @return string|null Resource URI, or null if the symbol is unknown.
     */
    public function getURIForSymbol($symbol) {
        $resource = idx($this->symbolMap, $symbol);
        return $resource['uri'];
    }

    /**
     * Return the absolute URI for the resource associated with a resource name.
     * This method is fairly low-level and ignores packaging.
     *
     * @param string Resource name to lookup.
     * @return string|null  Resource URI, or null if the name is unknown.
     */
    public function getURIForName($name) {
        $symbol = idx($this->nameMap, $name);
        return $this->getURIForSymbol($symbol);
    }

    /**
     * Return the resource symbols required by a named resource.
     *
     * @param string Resource name to lookup.
     * @return array<array>|null  List of required symbols, or null if the name
     *                            is unknown.
     */
    public function getRequiredSymbolsForName($name) {
        $symbol = idx($this->nameMap, $name);
        if ($symbol === null) {
            return null;
        }
        $resource = idx($this->symbolMap, $symbol, array());
        return array(
            'js' => $resource['deps'],
            'css' => $resource['css']
        );
    }

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

    //是否该资源名的资源为包资源
    public function isPackageResource($name) {
        return isset($this->packageMap[$name]);
    }

    //获取资源类型
    public function getResourceTypeForName($name) {
        return $this->resources->getResourceType($name);
    }

    //给一组资源id,返回所有需要打包的资源数组
    private function resolveResources(array $symbols) {
        $map = array();
        foreach ($symbols as $symbol) {
            if (!empty($map[$symbol])) {
                continue;
            }
            $this->resolveResource($map, $symbol);
        }
        return $map;
    }

    //给一个资源id,查询所有依赖并存入一个map结构
    private function resolveResource(array &$map, $symbol) {
        if (empty($this->symbolMap[$symbol])) {
            throw new Exception(pht(
                'Attempting to resolve unknown resource, "%s".',
                $symbol
            ));
        }

        $resource = $this->symbolMap[$symbol];
        if (isset($resource['deps'])) {
            $requires = $resource['deps'];
        } else {
            $requires = array();
        }

        $map[$symbol] = $requires;
        foreach ($requires as $required_symbol) {
            if (!empty($map[$required_symbol])) {
                continue;
            }
            $this->resolveResource($map, $required_symbol);
        }
    }

    // include all resources and packages they live in
    private function packageResources(array $resolved_map) {
        $packaged = array();
        $handled = array();

        foreach ($resolved_map as $symbol => $requires) {
            if (isset($handled[$symbol])) {
                continue;
            }

            if (empty($this->componentMap[$symbol])) {
                $packaged[] = $symbol;
            } else {
                $package_name = $this->componentMap[$symbol];
                $packaged[] = $package_name;
                $package_symbols = $this->packageMap[$package_name];
                foreach ($package_symbols as $package_symbol) {
                    $handled[$package_symbol] = true;
                }
            }
        }

        return $packaged;
    }
}