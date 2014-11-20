<?php
namespace PHPAutocoder;

class Autoload
{
    protected static $basePath = null;
    protected static $autoloaders = array();

    /**
     * This function is here to provide a nicely managed approach to adding your own custom autoloader to override or add
     * to the classes on the \PHPAutocoder namespace only, autoloaders added here will be processed before the standard internal autoloader
     *
     * @param string   $name     - identifier for the autoloader (allows you to dynamically add/remove autoloaders at runtime)
     * @param callable $function - a callable function that takes one parameter (the classname) and returns true if it loads the requested class and false if it does not
     *
     * @throws Exception
     */
    public static function addExternalAutoloaderFunction($name, callable $function)
    {
        if (isset(self::$autoloaders[$name]))
            throw new Exception("Autoloader $name already defined");
        self::$autoloaders = array($name => $function) + self::$autoloaders;
    }


    /**
     * Function to remove external autoloaders for PHPAutocoder that have been added
     *
     * @param string $name
     *
     * @throws Exception
     */
    public static function removeExternalAutoloaderFunction($name)
    {
        if ($name == '__PHPAutocoder__')
            throw new Exception("Cannot remove internal autoloader function");
        unset(self::$autoloaders[$name]);
    }

    /**
     * Main autoloading function that uses the defined external autoloaders as well as internal
     *
     * @param string $className
     *
     * @return bool
     */
    public static function loadClass($className)
    {
        $parts = explode('\\', $className);
        if ($parts[0] == 'PHPAutocoder')
        {
            foreach (self::$autoloaders as $name => $function)
            {
                $success = call_user_func_array($function, array($className));
                if ($success)
                    return true;
            }
        }

        return false;
    }


    /**
     * This is the implementation of the internal autoloader function
     *
     * @param string $className
     *
     * @return bool
     * @throws Exception
     */
    protected static function internalAutoloaderFunction($className)
    {
        $parts = explode('\\', $className);
        $count = count($parts);
        unset($parts[0]);
        $path     = str_replace(array("\\", strtolower($parts[$count - 1])), array('/', $parts[$count - 1]), strtolower(implode('/', $parts)));
        $fullPath = self::$basePath . '/' . $path . '.php';
        if (!file_exists($fullPath))
            throw new Exception("Unable to load $className from $fullPath - file not found");
        require_once($fullPath);

        return true;
    }


    /**
     * Initializer function for the Autoloader, called at the end of this file
     */
    public static function init()
    {
        if (self::$basePath === null)
        {
            self::$basePath                        = realpath(dirname(__FILE__));
            self::$autoloaders['__PHPAutocoder__'] = __NAMESPACE__ . '\Autoload::internalAutoloaderFunction';
            spl_autoload_register(__NAMESPACE__ . '\Autoload::loadClass');
        }
    }

}

Autoload::init();
