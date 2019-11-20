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
namespace Pop\Pdf\Build\PdfObject;

/**
 * Pdf root object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class RootObject extends AbstractObject
{

    /**
     * PDF version
     * @var float
     */
    protected $version = 1.7;

    /**
     * PDF root object index
     * @var int
     */
    protected $index = 1;

    /**
     * PDF root parent index
     * @var int
     */
    protected $parent = 2;

    /**
     * Constructor
     *
     * Instantiate a PDF root object.
     *
     * @param  int $index
     */
    public function __construct($index = 1)
    {
        $this->setIndex($index);
        $this->setData("[{root_index}] 0 obj\n<<[{form_index}]/Pages [{parent_index}] 0 R/Type/Catalog>>\nendobj\n");
    }

    /**
     * Parse a root object from a string
     *
     * @param  string $stream
     * @return RootObject
     */
    public static function parse($stream)
    {
        $root = new self();

        // Else, parse out any metadata and determine the root and parent object indices.
        $root->setIndex(substr($stream, 0, strpos($stream, ' ')));
        $stream = str_replace($root->getIndex() . ' 0 obj', '[{root_index}] 0 obj', $stream);

        // Strip away any metadata
        if (strpos($stream, '/Metadata') !== false) {
            $metadata = substr($stream, strpos($stream, 'Metadata'));
            $metadata = '/' . substr($metadata, 0, strpos($metadata, '/'));
            $stream = str_replace($metadata, '', $stream);
        }

        // Determine the parent index.
        $parent = substr($stream, (strpos($stream, '/Pages') + 6));
        $parent = trim(substr($parent, 0, strpos($parent, '0 R')));
        $root->setParentIndex($parent);
        $stream = str_replace('Pages ' . $root->getParentIndex() . ' 0 R', 'Pages [{parent_index}] 0 R', $stream);

        // Set the root object parent index and the data.
        $root->setData($stream . "\n");

        return $root;
    }

    /**
     * Set the root object version
     *
     * @param  float $version
     * @return RootObject
     */
    public function setVersion($version)
    {
        $this->version = (float)$version;
        return $this;
    }

    /**
     * Set the root object parent index
     *
     * @param  int $p
     * @return RootObject
     */
    public function setParentIndex($p)
    {
        $this->parent = (int)$p;
        return $this;
    }

    /**
     * Set the root object form index
     *
     * @param  string $forms
     * @return RootObject
     */
    public function setFormReferences($forms)
    {
        $data = str_replace('[{form_index}]', '/AcroForm [' . $forms . ']', $this->data);
        $this->setData($data);
        return $this;
    }

    /**
     * Get the root object version
     *
     * @return float
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get the root object parent index
     *
     * @return int
     */
    public function getParentIndex()
    {
        return $this->parent;
    }

    /**
     * Get the root object form index
     *
     * @return int
     */
    public function getFormIndex()
    {
        return $this->form;
    }

    /**
     * Method to print the root object.
     *
     * @return string
     */
    public function __toString()
    {
        $root = '%PDF-' . $this->version . "\n" .
            str_replace(['[{root_index}]', '[{parent_index}]', '[{form_index}]'], [$this->index, $this->parent, ''], $this->data);

        return $root;
    }

}