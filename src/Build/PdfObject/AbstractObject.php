<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\PdfObject;

/**
 * Pdf abstract object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.7
 */
abstract class AbstractObject implements ObjectInterface
{

    /**
     * PDF object index
     * @var ?int
     */
    protected ?int $index = null;

    /**
     * PDF object data
     * @var ?string
     */
    protected ?string $data = null;

    /**
     * Imported flag
     * @var bool
     */
    protected bool $isImported = false;

    /**
     * Set the object index
     *
     * @param  int $i
     * @return AbstractObject
     */
    public function setIndex(int $i): AbstractObject
    {
        $this->index = $i;
        return $this;
    }

    /**
     * Set the object data
     *
     * @param  string $data
     * @return AbstractObject
     */
    public function setData(string $data): AbstractObject
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set whether the object is imported
     *
     * @param  bool $imported
     * @return AbstractObject
     */
    public function setImported(bool $imported)
    {
        $this->isImported = $imported;
        return $this;
    }

    /**
     * Get the object index
     *
     * @return ?int
     */
    public function getIndex(): ?int
    {
        return $this->index;
    }

    /**
     * Get the object stream
     *
     * @return ?string
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    /**
     * Determine if the object is imported
     *
     * @return bool
     */
    public function isImported(): bool
    {
        return $this->isImported;
    }

    /**
     * Get the integer references within a dictionary stream
     *
     * @param  string $dictionary
     * @return array
     */
    public function getDictionaryReferences(string $dictionary): array
    {
        $dictionary = trim($dictionary);

        if (str_starts_with($dictionary, '[')) {
            $dictionary = substr($dictionary, 0, strpos($dictionary, ']'));
            $dictionary = trim(str_replace(['[', '0 R', '1 R', ' '], ['', '|', '|', ''], $dictionary));
            if (str_ends_with($dictionary, '|')) {
                $dictionary = substr($dictionary, 0, -1);
            }
            $references = explode('|', $dictionary);
        } else {
            $references = [substr($dictionary, 0, strpos($dictionary, ' '))];
        }

        return $references;
    }

    /**
     * Method to print the object
     *
     * @return string
     */
    abstract public function __toString(): string;

}
