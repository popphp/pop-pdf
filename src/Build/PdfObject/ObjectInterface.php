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
namespace Pop\Pdf\Build\PdfObject;

/**
 * Pdf object interface
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.1
 */
interface ObjectInterface
{

    /**
     * Set the object index
     *
     * @param  int $i
     * @return ObjectInterface
     */
    public function setIndex(int $i): ObjectInterface;

    /**
     * Set the object data
     *
     * @param  string $data
     * @return ObjectInterface
     */
    public function setData(string $data): ObjectInterface;

    /**
     * Get the object index
     *
     * @return ?int
     */
    public function getIndex(): ?int;

    /**
     * Get the object data
     *
     * @return ?string
     */
    public function getData(): ?string;

    /**
     * Method to print the object
     *
     * @return string
     */
    public function __toString(): string;

}
