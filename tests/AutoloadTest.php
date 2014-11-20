<?php

class AutoloadTest extends PHPUnit_Framework_TestCase
{
    public function testLoadClass()
    {
        $autocoder = new \PHPAutocoder\Autocoder();
        $this->assertInstanceOf('\PHPAutocoder\Autocoder', $autocoder);
    }

    public function testChainedLoader()
    {
        // try to load something that the autoloader can't find
        try
        {
            class_exists('PHPAutocoder\\AutoLoaderTestClass');
            $this->fail("No autoloader defined that knows where this class is, it should not be exist");
        }
        catch(Exception $e)
        {
            $this->assertInstanceOf('PhpAutocoder\Exception', $e);
        }


        // create an autoloader function and add it as an external
        $function = function($className){
            if($className == 'PHPAutocoder\\AutoLoaderTestClass')
            {
                require_once(realpath(dirname(__FILE__))."/build/AutoLoaderTestClass.php");
                return true;
            }
            return false;
        };

        \PHPAutocoder\Autoload::addExternalAutoloaderFunction('test', $function);

        // see if the new external loader actually gets called correctly
        $this->assertTrue(class_exists('PHPAutocoder\\AutoLoaderTestClass'));
        // make sure that something we expect to exist and be autoloadable still is
        $this->assertTrue(class_exists('PHPAutocoder\\Helpers\\Objects\\StatefulArray'));

        // make sure that the expected exception is still thrown on something that does not exist
        try
        {
            class_exists('PHPAutocoder\\SomeNonexistentClass');
            $this->fail("No autoloader defined that knows where this class is, it should not be known");
        }
        catch(Exception $e)
        {
            $this->assertInstanceOf('PhpAutocoder\Exception', $e);
        }


    }

}
