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
namespace Pop\Pdf\Document;

/**
 * Pdf form class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Form
{

    /**
     * Form name
     * @var string
     */
    protected $name = null;

    /**
     * Form field indices
     * @var array
     */
    protected $fields = [];

    /**
     * Constructor
     *
     * Instantiate a PDF form object.
     *
     * @param  string $name
     */
    public function __construct($name)
    {
        $this->setName($name);
    }

    /**
     * Set form name
     *
     * @param  string $name
     * @return Form
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the form name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Add field index
     *
     * @param  int $i
     * @return Form
     */
    public function addFieldIndex($i)
    {
        $this->fields[] = (int)$i;
        return $this;
    }

    /**
     * Get field indices
     *
     * @return array
     */
    public function getFieldIndices()
    {
        return $this->fields;
    }

    /**
     * Get number of fields
     *
     * @return array
     */
    public function getNumberOfFields()
    {
        return count($this->fields);
    }

    /**
     * Get the form stream
     *
     * @param  int $i
     * @return string
     */
    public function getStream($i)
    {
        // Return the stream
        $stream = "{$i} 0 obj\n<</Fields[";

        $fields = '';
        foreach ($this->fields as $value) {
            $fields .= $value . ' 0 R ';
        }
        $fields = substr($fields, 0, -1);

        $stream .= $fields . "]>>\nendobj\n\n";

        return $stream;
    }

}