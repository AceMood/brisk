<?php

/**
 * Defines the location of static resources on disk.
 */
abstract class BriskResourcesOnDisk extends BriskPhysicalResources {

  // return source code directory
  abstract public function getPathToResources();

  // according to name as 'static/a.js', get the real file-system path
  private function getPathToResource($name) {
    return $this->getPathToResources() . DIRECTORY_SEPARATOR . $name;
  }

  //读取文件内容
  public function getResourceData($name) {
    return Filesystem::readFile($this->getPathToResource($name));
  }

  public function getResourceModifiedTime($name) {
    return (int)filemtime($this->getPathToResource($name));
  }

}
