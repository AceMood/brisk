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
}