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
namespace Pop\Pdf\Build;

use Pop\Pdf\Document\AbstractDocument;

/**
 * Parser interface
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.0
 */
interface ParserInterface
{

    /**
     * Get the file
     *
     * @return string
     */
    public function getFile(): string;

    /**
     * Get the data stream
     *
     * @return string
     */
    public function getData(): string;

    /**
     * Parse the pdf data
     *
     * @param  mixed  $pages
     * @return AbstractDocument
     */
    public function parse(mixed $pages = null): AbstractDocument;

}
