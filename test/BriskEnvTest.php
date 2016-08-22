<?php

require_once 'src/BriskEnv.php';

/**
 * Class EnvTest
 * @stable
 */
class EnvTest extends PHPUnit_Framework_TestCase {
    public function testRenderModeAttrs() {
        $this->assertClassHasAttribute('mode_normal', 'BriskEnv');
        $this->assertClassHasAttribute('mode_quickling', 'BriskEnv');
        $this->assertClassHasAttribute('mode_bigpipe', 'BriskEnv');
        $this->assertClassHasAttribute('mode_bigrender', 'BriskEnv');
    }

    public function testTypeMap() {

        $typeMap = BriskEnv::$typeMap;

        $this->assertEquals($typeMap['jsx'], 'js');
        $this->assertEquals($typeMap['js'], 'js');
        $this->assertEquals($typeMap['less'], 'css');
        $this->assertEquals($typeMap['scss'], 'css');
        $this->assertEquals($typeMap['css'], 'css');
    }
}