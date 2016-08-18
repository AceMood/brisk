<?php

/**
 * Defines tpldev's static resources.
 */
final class SantaResources extends BriskResourcesOnDisk {

  // return project name
  public function getName() {
    return 'santa';
  }

  // get the top-level directory of all static resources
  public function getPathToResources() {
    return $this->getPhabricatorPath('webroot/');
  }

  // get map.php path
  public function getPathToMap() {
    return $this->getPhabricatorPath('map/resource.json');
  }

  // get file in current 'phabricator' directory
  private function getPhabricatorPath($to_file) {
    return dirname(phutil_get_library_root('phabricator')) . '/' . $to_file;
  }

  // get all packages info
  public function getResourcePackages() {
    return include $this->getPhabricatorPath('resources/celerity/packages.php');
  }

}
