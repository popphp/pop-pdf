<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build;

/**
 * Parser interface
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
interface ParserInterface
{

    /**
     * Get the file
     *
     * @return string
     */
    public function getFile();

    /**
     * Get the data stream
     *
     * @return string
     */
    public function getData();

    /**
     * Parse the data stream
     *
     * @param  string $file
     * @param  mixed  $pages
     * @return \Pop\Pdf\AbstractDocument
     */
    public function parse($file, $pages = null);

}