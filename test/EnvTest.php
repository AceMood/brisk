<?php

/**
 * Class EnvTest
 * @stable
 */
class EnvTest extends PHPUnit_Framework_TestCase
{
    public function testRenderModeAttrs()
    {
        require_once 'src/Env.php';

        $this->assertClassHasAttribute('mode_normal', 'BriskEnv');
        $this->assertClassHasAttribute('mode_quickling', 'BriskEnv');
        $this->assertClassHasAttribute('mode_bigpipe', 'BriskEnv');
        $this->assertClassHasAttribute('mode_bigrender', 'BriskEnv');
    }
}