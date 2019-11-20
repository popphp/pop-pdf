<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Font\TrueType\Table;

/**
 * Abstract table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
abstract class AbstractTable implements \ArrayAccess, TableInterface
{

    /**
     * Font table properties
     * @var array
     */
    protected $properties = [];

    /**
     * Read-only properties
     * @var array
     */
    protected $readOnly = [];

    /**
     * Offset set method
     *
     * @param  string $name
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($name, $value)
    {
        $this->properties[$name] = $value;
    }

    /**
     * Offset get method
     *
     * @param  string $name
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public function offsetGet($name)
    {
        return (isset($this->properties[$name])) ? $this->properties[$name] : null;
    }

    /**
     * Offset exists method
     *
     * @param  mixed $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->properties[$offset]);
    }

    /**
     * Offset unset method
     *
     * @param  mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        if (isset($this->properties[$offset])) {
            unset($this->properties[$offset]);
        }
    }

    /**
     * Set method
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->offsetSet($name, $value);
    }

    /**
     * Get method
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->offsetGet($name);
    }
    /**
     * Isset method
     *
     * @param  string $name
     * @return boolean
     */
    public function __isset($name)
    {
        return $this->offsetExists($name);
    }
    /**
     * Unset fields[$name]
     *
     * @param  string $name
     * @return void
     */
    public function __unset($name)
    {
        $this->offsetUnset($name);
    }

}
