<?php
namespace PHPAutocoder;

class Autoload
{
    protected static $basePath = null;

    public static function loadClass($className)
    {
        $parts = explode('\\', $className);
        if($parts[0] == 'PHPAutocoder')
        {
            $count = count($parts);
            unset($parts[0]);
            $path = str_replace(array("\\", strtolower($parts[$count - 1])), array('/', $parts[$count - 1]), strtolower(implode('/', $parts)));
            require_once(self::$basePath.'/'.$path.'.php');
        }
    }

    public static function init()
    {
        if(self::$basePath === null)
        {
            self::$basePath = realpath(dirname(__FILE__));
            spl_autoload_register(__NAMESPACE__.'\Autoload::loadClass');
        }
    }


    protected static function buildPath($className)
    {

    }
}
Autoload::init();
