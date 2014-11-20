<?php
namespace PHPAutocoder\Helpers\Objects;

/**
 * class that acts like an array but retains _prev state
 *
 * @author dwayn
 */
class StatefulArray implements \Iterator, \ArrayAccess, \Countable
{
    protected $_prev = array();
    protected $_data = array();

    // useful for controlling what rights the end user code has in modifying the array
    // to disable use not operator, ie. StatefulArray::ALLOW_ALL & ~StatefulArray::ALLOW_APPEND to disable append abilities
    // permissions can be combined by ORing or adding them together, eg: ALLOW_APPEND | ALLOW_WRITE  or  ALLOW_APPEND + ALLOW_WRITE
    const ALLOW_NONE = 0x00; // turn off all access to the array (make it read only beyond the initial instantiation)
    const ALLOW_APPEND = 0x01; // allow adding of previously (from StatefulArray instantiation) unset values
    const ALLOW_UNSET = 0x02; // allow to unset keys that are set in the array
    const ALLOW_WRITE = 0x04; // allow the fields in the array to be overwritten
    const ALLOW_ALL = 0xFF; // allow full access - truly act like it is a plain array

    protected $_workingFlags;

    public function __construct($array, $workingFlags = StatefulArray::ALLOW_ALL)
    {
        foreach ($array as $key => $value)
            $this->_data[$key] = $value;
        $this->_prev         = array();
        $this->_workingFlags = $workingFlags;
    }

    /**
     * clears the previous array effectively resetting the state of the
     * StatefulArray so that the current state is now accepted as the original state
     * used in cases where the current state of the StateAray is now the persistent
     * version (after the data has been written to the database inside of a DAO)
     *
     */
    public function clearPrev()
    {
        $this->_prev = array();
    }

    public function getPrevArray()
    {
        return $this->_prev;
    }

    public function getDataArray()
    {
        return $this->_data;
    }

    public function hasChanged($offset)
    {
        return isset($this->_prev[$offset]);
    }

    // Countable function
    public function count()
    {
        return count($this->_data);
    }


    //Iterator functions
    public function rewind()
    {
        reset($this->_data);
    }

    public function valid()
    {
        if (!is_null(key($this->_data)))
            return true;

        return false;
    }

    public function next()
    {
        return next($this->_data);
    }

    public function key()
    {
        return key($this->_data);
    }

    public function current()
    {
        return current($this->_data);
    }


    // ArrayAccess functions
    public function offsetExists($offset)
    {
        return isset($this->_data[$offset]);
    }

    public function offsetGet($offset)
    {
        if (isset($this->_data[$offset]))
        {
            return $this->_data[$offset];
        }
    }

    public function offsetSet($offset, $value)
    {
        if (!($this->_workingFlags & StatefulArray::ALLOW_WRITE))
        {
            throw new StatefulArrayException("write is not allowed in this StatefulArray");
        }
        if (!isset($this->_data[$offset]) && !($this->_workingFlags & StatefulArray::ALLOW_APPEND))
        {
            throw new StatefulArrayException("append is not allowed in this StatefulArray");
        }
        if (isset($this->_data[$offset]))
        {
            if (!isset($this->_prev[$offset]))
            {
                $this->_prev[$offset] = $this->_data[$offset];
            }
            else
            {
                // if the _prev value is restored to original, we can remove the
                // _prev reference because it effectively has not net
                if ($this->_prev[$offset] == $value)
                {
                    unset($this->_prev[$offset]);
                }
            }
            $this->_data[$offset] = $value;
        }
        else
        {
            $this->_data[$offset] = $value;
            // set an _prev value for the field so that when we pull the
            // previous value array it shows up as a updated field
            $this->_prev[$offset] = null;
        }
    }

    public function offsetUnset($offset)
    {
        if ($this->_workingFlags & StatefulArray::ALLOW_UNSET)
        {
            if (isset($this->_data[$offset]))
            {
                unset($this->_data[$offset]);
            }
            if (isset($this->_prev[$offset]))
            {
                unset($this->_prev[$offset]);
            }
        }
        else
        {
            throw new StatefulArrayException("unset not allowed on this StatefulArray");
        }
    }


    // magic methods for isset and unset functionality
    public function __unset($name)
    {
        $this->offsetUnset($name);
    }

    public function __isset($name)
    {
        return $this->offsetExists($name);
    }


    // magic getter and setter so you can treat it like an object if you like
    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    public function __set($name, $value)
    {
        return $this->offsetSet($name, $value);
    }


}


