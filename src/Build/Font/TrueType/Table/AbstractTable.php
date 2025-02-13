<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Font\TrueType\Table;

use ReturnTypeWillChange;

/**
 * Abstract table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
 */
abstract class AbstractTable implements \ArrayAccess, TableInterface
{

    /**
     * Font table properties
     * @var array
     */
    protected array $properties = [];

    /**
     * Read-only properties
     * @var array
     */
    protected array $readOnly = [];

    /**
     * Offset set method
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->properties[$offset] = $value;
    }

    /**
     * Offset get method
     *
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->properties[$offset] ?? null;
    }

    /**
     * Offset exists method
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->properties[$offset]);
    }

    /**
     * Offset unset method
     *
     * @param  mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
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
    public function __set(string $name, mixed $value): void
    {
        $this->offsetSet($name, $value);
    }

    /**
     * Get method
     *
     * @param  string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->offsetGet($name);
    }
    /**
     * Isset method
     *
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->offsetExists($name);
    }
    /**
     * Unset fields[$name]
     *
     * @param  string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        $this->offsetUnset($name);
    }

}
