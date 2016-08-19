<?php

/**
 * Class UtilsTest
 * @stable
 */

require_once 'src/Utils.php';

class UtilsTest extends PHPUnit_Framework_TestCase
{
    public function testAjaxify()
    {


        $this->assertClassHasAttribute('mode_normal', 'BriskEnv');
        $this->assertClassHasAttribute('mode_quickling', 'BriskEnv');
        $this->assertClassHasAttribute('mode_bigpipe', 'BriskEnv');
        $this->assertClassHasAttribute('mode_bigrender', 'BriskEnv');
    }
}