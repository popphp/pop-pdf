<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
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
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.2.0
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
     * Get the object stream type
     *
     * @param  string $stream
     * @return string
     */
    protected function getStreamType(string $stream): string
    {
        if ((str_contains($stream, '/Catalog')) && (str_contains($stream, '/Pages'))) {
            $type = 'root';
        } else if ((str_contains($stream, '/Count')) && (str_contains($stream, '/Kids'))) {
            $type = 'parent';
        } else if ((str_contains($stream, '/Parent')) && (str_contains($stream, '/MediaBox'))) {
            $type = 'page';
        } else if ((str_contains($stream, '/Creator')) || (str_contains($stream, '/CreationDate')) ||
            (str_contains($stream, '/ModDate')) || (str_contains($stream, '/Author')) ||
            (str_contains($stream, '/Title')) || (str_contains($stream, '/Subject')) ||
            (str_contains($stream, '/Producer'))) {
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
     * @return AbstractDocument
     */
    abstract public function parse(mixed $pages = null): AbstractDocument;

}
