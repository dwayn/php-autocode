<?php
use PHPAutocoder\Helpers\Objects\StatefulObject;
use PHPAutocoder\Helpers\Objects\StatefulObjectException;


class StatefulObjectTest extends PHPUnit_Framework_TestCase
{
    public function testBasics()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulObject($initialData);

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


        $sa = new StatefulObject($initialData, StatefulObject::ALLOW_NONE);
        try
        {
            $sa->foo = 'new foo';
            $this->fail("ALLOW_NONE should not allow write");
        }
        catch(StatefulObjectException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulObjectException', $e);
        }

        try
        {
            unset($sa->foo);
            $this->fail("ALLOW_NONE should not allow unset");
        }
        catch(StatefulObjectException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulObjectException', $e);
        }

        try
        {
            $sa->foo_new = 'new foo';
            $this->fail("ALLOW_NONE should not allow append");
        }
        catch(StatefulObjectException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulObjectException', $e);
        }


    }

    public function testAccessControlsAllowWrite()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulObject($initialData, StatefulObject::ALLOW_WRITE);
        $sa->foo = 'new foo';
        $this->assertEquals('new foo', $sa->foo);

        try
        {
            unset($sa->foo);
            $this->fail("ALLOW_WRITE should not allow unset");
        }
        catch(StatefulObjectException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulObjectException', $e);
        }

        try
        {
            $sa->foo_new = 'new foo';
            $this->fail("ALLOW_WRITE should not allow append");
        }
        catch(StatefulObjectException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulObjectException', $e);
        }


    }


    public function testAccessControlsAllowWriteAppend()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulObject($initialData, StatefulObject::ALLOW_WRITE | StatefulObject::ALLOW_APPEND);
        $sa->foo = 'new foo';
        $this->assertEquals('new foo', $sa->foo);

        try
        {
            unset($sa->foo);
            $this->fail("ALLOW_WRITE + ALLOW_APPEND should not allow unset");
        }
        catch(StatefulObjectException $e)
        {
            $this->assertInstanceOf('PHPAutocoder\Helpers\Objects\StatefulObjectException', $e);
        }

        $sa->foo_new = 'new foo';
        $this->assertEquals('new foo', $sa->foo_new);


    }


    public function testAccessControlsAllowWriteAppendUnset()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulObject($initialData, StatefulObject::ALLOW_WRITE + StatefulObject::ALLOW_APPEND + StatefulObject::ALLOW_UNSET);
        $sa->foo = 'new foo';
        $this->assertEquals('new foo', $sa->foo);

        unset($sa->foo);
        $this->assertFalse(isset($sa->foo));

        $sa->foo_new = 'new foo';
        $this->assertEquals('new foo', $sa->foo_new);


    }


    public function testAccessControlsAllowAll()
    {
        $initialData = array(
            'foo' => 'foo_val',
            'bar' => 'bar_val',
            'baz' => 'baz_val'
        );


        $sa = new StatefulObject($initialData, StatefulObject::ALLOW_ALL);
        $sa->foo = 'new foo';
        $this->assertEquals('new foo', $sa->foo);

        unset($sa->foo);
        $this->assertFalse(isset($sa->foo));

        $sa->foo_new = 'new foo';
        $this->assertEquals('new foo', $sa->foo_new);


    }


}
