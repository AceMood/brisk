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

  // file io
  public function getResourceData($name) {
    return Filesystem::readFile($this->getPathToResource($name));
  }

  // find all binary files
  public function findBinaryResources() {
    return $this->findResourcesWithSuffixes($this->getBinaryFileSuffixes());
  }

  // find all text-based files
  public function findTextResources() {
    return $this->findResourcesWithSuffixes($this->getTextFileSuffixes());
  }

  public function getResourceModifiedTime($name) {
    return (int)filemtime($this->getPathToResource($name));
  }

  // get all suffixes for binary files
  protected function getBinaryFileSuffixes() {
    return array(
      'png',
      'jpg',
      'gif',
      'swf',
      'svg',
      'woff',
      'woff2',
      'ttf',
      'eot',
      'mp3',
    );
  }

  // get text-based resource type
  protected function getTextFileSuffixes() {
    return array(
      'js',
      'css',
    );
  }

  // load files with their specific suffixes and calculate its md5
  private function findResourcesWithSuffixes(array $suffixes) {
    $root = $this->getPathToResources();

    $finder = id(new FileFinder($root))
      ->withType('f')
      ->withFollowSymlinks(true)
      ->setGenerateChecksums(true);

    foreach ($suffixes as $suffix) {
      $finder->withSuffix($suffix);
    }

    $raw_files = $finder->find();

    $results = array();
    // $hash is the hash value of original file content calculated in md5
    foreach ($raw_files as $path => $hash) {
      // $readable equals with $path
      $readable = Filesystem::readablePath($path, $root);
      $results[$readable] = $hash;
    }

    return $results;
  }

}
