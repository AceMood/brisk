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

    // id => resource
    private $symbolMap;

    // package symbol => array(symbols)
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
    public static function getNamedInstance($name) {
        if (empty(self::$instances[$name])) {
            $instance = new BriskResourceMap();
            self::$instances[$name] = $instance;
        }
        return self::$instances[$name];
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

    // give a symbol and get the package it lived in
    public function getPackagedNamesForSymbols(array $symbols) {
        $resolved = $this->resolveResources($symbols);
        return $this->packageResources($resolved);
    }

    // give a symbol collection, return all the resources needed (include all dependency)
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

    // give a symbol, then query the resourceMap and put all its dependencies (recursively)
    // into the map structure
    private function resolveResource(array &$map, $symbol) {
        if (empty($this->symbolMap[$symbol])) {
            throw new Exception(pht(
                'Attempting to resolve unknown resource, "%s".',
                $symbol));
        }
        $hash = $this->symbolMap[$symbol];
        $map[$symbol] = $hash;
        if (isset($this->requiresMap[$hash])) {
            $requires = $this->requiresMap[$hash];
        } else {
            $requires = array();
        }
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
        foreach ($resolved_map as $symbol => $hash) {
            if (isset($handled[$symbol])) {
                continue;
            }
            if (empty($this->componentMap[$symbol])) {
                $packaged[] = $this->hashMap[$hash];
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

    public function getResourceDataForName($name) {
        return $this->resources->getResourceData($name);
    }

    public function getResourceNamesForPackageName($package_name) {
        $package_symbols = idx($this->packageMap, $package_name);
        if (!$package_symbols) {
            return null;
        }

        $resource_names = array();
        foreach ($package_symbols as $symbol) {
            $resource_names[] = $this->hashMap[$this->symbolMap[$symbol]];
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
}