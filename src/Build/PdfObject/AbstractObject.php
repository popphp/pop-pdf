<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
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
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
abstract class AbstractObject implements ObjectInterface
{

    /**
     * PDF object index
     * @var int
     */
    protected $index = null;

    /**
     * PDF object data
     * @var string
     */
    protected $data = null;

    /**
     * Imported flag
     * @var string
     */
    protected $isImported = false;

    /**
     * Set the object index
     *
     * @param  int $i
     * @return AbstractObject
     */
    public function setIndex($i)
    {
        $this->index = (int)$i;
        return $this;
    }

    /**
     * Set the object data
     *
     * @param  string $data
     * @return AbstractObject
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set whether the object is imported
     *
     * @param  boolean $imported
     * @return AbstractObject
     */
    public function setImported($imported)
    {
        $this->isImported = (bool)$imported;
        return $this;
    }

    /**
     * Get the object index
     *
     * @return int
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * Get the object stream
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Determine if the object is imported
     *
     * @return boolean
     */
    public function isImported()
    {
        return $this->isImported;
    }

    /**
     * Get the integer references within a dictionary stream
     *
     * @param  string $dictionary
     * @return array
     */
    public function getDictionaryReferences($dictionary)
    {
        $dictionary = trim($dictionary);

        if (substr($dictionary, 0, 1) == '[') {
            $dictionary = substr($dictionary, 0, strpos($dictionary, ']'));
            $dictionary = trim(str_replace(['[', '0 R', '1 R', ' '], ['', '|', '|', ''], $dictionary));
            if (substr($dictionary, -1) == '|') {
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
    abstract public function __toString();

}