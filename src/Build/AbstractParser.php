<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build;

use Pop\Pdf\Document\AbstractDocument;

/**
 * Abstract Pdf parser class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
abstract class AbstractParser implements ParserInterface
{

    /**
     * Imported PDF file
     * @var ?string
     */
    protected ?string $file = null;

    /**
     * Imported PDF data stream
     * @var ?string
     */
    protected ?string $data = null;

    /**
     * Get the file
     *
     * @return string
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Get the data stream
     *
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Parse the PDF data
     *
     * @param  mixed  $pages
     * @return AbstractDocument
     */
    abstract public function parse(mixed $pages = null): AbstractDocument;

}
