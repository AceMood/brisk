<?php

/**
 * Defines the location of static resources.
 */
abstract class BriskResources extends Brisk {
    // get project's name
    abstract public function getName();

    // get file content
    abstract public function getResourceData($name);

    // get file's mtime, default is 0
    public function getResourceModifiedTime($name) {
        return 0;
    }

    // get hash of a resource
    public function getBriskHash($data) {
        $tail = PhabricatorEnv::getEnvConfig('celerity.resource-hash');
        $hash = PhabricatorHash::digest($data, $tail);
        return substr($hash, 0, 8);
    }

    // get suffix name
    public function getResourceType($path) {
        return BriskResourceTransformer::getResourceType($path);
    }

    // get the online url
    public function getResourceURI($hash, $name) {
        // return 'phabricator'
        $resources = $this->getName();
        return "/res/{$resources}/{$hash}/{$name}";
    }

    // get the whole package infos
    public function getResourcePackages() {
        return array();
    }

    // load resource map
    public function loadMap() {
        return array();
    }
}