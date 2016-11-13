<?php

/**
 * @class 项目的静态资源
 * @author AceMood
 * @email zmike86@gmail.com
 */

//-------------

final class BriskStaticResources extends BriskResourcesOnDisk {

  // 存放资源表文件的目录
  private $mapDirectory = '';

  // 编译后资源的存放目录, 对应编译时工具设置的`dir`属性.
  private $compileDirectory = '';

  // 这个方法在原类库中取的是源码文件的目录, 请求时动态用一个php的工具对代码做压缩.
  // 觉得这样比较低效, 编译时产出了压缩后的代码, 所以这里应该加载最终代码的所处目录.
  public function getPathToResources() {
    if (empty($this->compileDirectory)) {
      // 期望在程序中主动设置过`BRISK_COMPILE_DIRECTORY`这个常量, 作为加载文件
      // 的根目录. 如果没有设置过则读取编译工具的`root`属性, 但是此属性是编译时在
      // 编译机上的目录和最终线上的目录不一定对应, 所以可能引起找不到文件的错误.
      if (defined('BRISK_COMPILE_DIRECTORY')) {
        $this->compileDirectory = BRISK_COMPILE_DIRECTORY;
      } else {
        $map = $this->loadResourceMap();
        if (isset($map['root'])) {
          $this->compileDirectory = $map['root'];
        } else {
          throw new Exception(pht(
            'Resource Map have no root property.'
          ));
        }
      }
    }
    return $this->compileDirectory;
  }

  // 项目名用作命名空间
  public function getProjectName() {
    return 'brisk';
  }

  // 获取resource.json路径
  public function getPathToResourceMap() {
    $dir = $this->getProjectMapPath();
    return $dir . DIRECTORY_SEPARATOR . 'resource.json';
  }

  // 获取packages.json路径
  public function getPathToPackageMap() {
    $dir = $this->getProjectMapPath();
    return $dir . DIRECTORY_SEPARATOR . 'packages.json';
  }

  // 获取工程目录下文件的路径
  private function getProjectMapPath() {
    // 期望在程序中主动设置过`BRISK_MAP_DIRECTORY`这个常量, 作为加载资源表的根目录
    if (defined('BRISK_MAP_DIRECTORY')) {
      $this->mapDirectory = BRISK_MAP_DIRECTORY;
    } else {
      throw new Exception(pht(
        'Not found global BRISK_MAP_DIRECTORY value.'
      ));
    }
    return $this->mapDirectory;
  }
}
