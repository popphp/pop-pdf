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
namespace Pop\Pdf\Build;

/**
 * Abstract Pdf parser class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
abstract class AbstractParser implements ParserInterface
{

    /**
     * Imported PDF file
     * @var string
     */
    protected $file = null;

    /**
     * Imported PDF data stream
     * @var string
     */
    protected $data = null;

    /**
     * Get the file
     *
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Get the data stream
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the object stream type
     *
     * @param  string $stream
     * @return string
     */
    protected function getStreamType($stream)
    {
        if ((strpos($stream, '/Catalog') !== false) && (strpos($stream, '/Pages') !== false)) {
            $type = 'root';
        } else if ((strpos($stream, '/Count') !== false) && (strpos($stream, '/Kids') !== false)) {
            $type = 'parent';
        } else if ((strpos($stream, '/Parent') !== false) && (strpos($stream, '/MediaBox') !== false)) {
            $type = 'page';
        } else if ((strpos($stream, '/Creator') !== false) || (strpos($stream, '/CreationDate') !== false) ||
            (strpos($stream, '/ModDate') !== false) || (strpos($stream, '/Author') !== false) ||
            (strpos($stream, '/Title') !== false) || (strpos($stream, '/Subject') !== false) ||
            (strpos($stream, '/Producer') !== false)) {
            $type = 'info';
        } else {
            $type = 'stream';
        }

        return $type;
    }

    /**
     * Parse the PDF data
     *
     * @param  mixed  $pages
     * @return \Pop\Pdf\AbstractDocument
     */
    abstract public function parse($pages = null);

}