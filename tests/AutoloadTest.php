<?php

class AutoloadTest extends PHPUnit_Framework_TestCase
{
    public function testLoadClass()
    {
        $autocoder = new \PHPAutocoder\Autocoder();
        $this->assertInstanceOf('\PHPAutocoder\Autocoder', $autocoder);
    }

}
