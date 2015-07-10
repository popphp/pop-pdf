<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Object;

/**
 * Pdf object interface
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
interface ObjectInterface
{

    /**
     * Set the object index
     *
     * @param  int $i
     * @return AbstractObject
     */
    public function setIndex($i);

    /**
     * Set the object data
     *
     * @param  string $data
     * @return ObjectInterface
     */
    public function setData($data);

    /**
     * Get the object index
     *
     * @return int
     */
    public function getIndex();

    /**
     * Get the object data
     *
     * @return string
     */
    public function getData();

    /**
     * Method to print the object
     *
     * @return string
     */
    public function __toString();

}