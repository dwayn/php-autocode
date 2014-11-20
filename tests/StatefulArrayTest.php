<?php
use PHPAutocoder\Helpers\Objects\StatefulArray;
use PHPAutocoder\Helpers\Objects\StatefulArrayException;


class StatefulArrayTest extends PHPUnit_Framework_TestCase
{
    public function testBasics()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulArray($initialData);

        foreach($initialData as $key => $value)
            $this->assertEquals($sa[$key], $value);

        foreach($sa as $key => $value)
        {
            $this->assertArrayHasKey($key, $initialData);
            $this->assertEquals($initialData[$key], $value);
        }

    }

    public function testAccessControlsAllowNone()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulArray($initialData, StatefulArray::ALLOW_NONE);
        try
        {
            $sa['foo'] = 'new foo';
            $this->fail("ALLOW_NONE should not allow write");
        }
        catch(StatefulArrayException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulArrayException', $e);
        }

        try
        {
            unset($sa['foo']);
            $this->fail("ALLOW_NONE should not allow unset");
        }
        catch(StatefulArrayException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulArrayException', $e);
        }

        try
        {
            $sa['foo_new'] = 'new foo';
            $this->fail("ALLOW_NONE should not allow append");
        }
        catch(StatefulArrayException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulArrayException', $e);
        }


    }

    public function testAccessControlsAllowWrite()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulArray($initialData, StatefulArray::ALLOW_WRITE);
        $sa['foo'] = 'new foo';
        $this->assertEquals('new foo', $sa['foo']);

        try
        {
            unset($sa['foo']);
            $this->fail("ALLOW_WRITE should not allow unset");
        }
        catch(StatefulArrayException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulArrayException', $e);
        }

        try
        {
            $sa['foo_new'] = 'new foo';
            $this->fail("ALLOW_WRITE should not allow append");
        }
        catch(StatefulArrayException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulArrayException', $e);
        }


    }


    public function testAccessControlsAllowWriteAppend()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulArray($initialData, StatefulArray::ALLOW_WRITE | StatefulArray::ALLOW_APPEND);
        $sa['foo'] = 'new foo';
        $this->assertEquals('new foo', $sa['foo']);

        try
        {
            unset($sa['foo']);
            $this->fail("ALLOW_WRITE + ALLOW_APPEND should not allow unset");
        }
        catch(StatefulArrayException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulArrayException', $e);
        }

        $sa['foo_new'] = 'new foo';
        $this->assertEquals('new foo', $sa['foo_new']);


    }


    public function testAccessControlsAllowWriteAppendUnset()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulArray($initialData, StatefulArray::ALLOW_WRITE + StatefulArray::ALLOW_APPEND + StatefulArray::ALLOW_UNSET);
        $sa['foo'] = 'new foo';
        $this->assertEquals('new foo', $sa['foo']);

        unset($sa['foo']);
        $this->assertFalse(isset($sa['foo']));

        $sa['foo_new'] = 'new foo';
        $this->assertEquals('new foo', $sa['foo_new']);


    }


    public function testAccessControlsAllowAll()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulArray($initialData, StatefulArray::ALLOW_ALL);
        $sa['foo'] = 'new foo';
        $this->assertEquals('new foo', $sa['foo']);

        unset($sa['foo']);
        $this->assertFalse(isset($sa['foo']));

        $sa['foo_new'] = 'new foo';
        $this->assertEquals('new foo', $sa['foo_new']);


    }


}
