<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document\Page;

/**
 * Pdf page form class
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
class Form
{

    /**
     * Form fields
     * @var array
     */
    protected $fields = [];

    /**
     * Constructor
     *
     * Instantiate a PDF form object.
     *
     * @return Form
     */
    public function __construct()
    {

    }

    /**
     * Add field
     *
     * @param  Field\AbstractField $field
     * @return Form
     */
    public function addField(Field\AbstractField $field)
    {
        $this->fields[] = $field;
        return $this;
    }

    /**
     * Get fields
     *
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
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
        $stream = "{$i} 0 obj\n<</DA(/MF1 0 Tf 0 g)/DR<</Font<</MF1 4 0 R>>>>/Fields[";

        $fields = '';
        foreach ($this->fields as $key => $value) {
            $i++;
            $fields .= $i . ' 0 R ';
        }
        $fields = substr($fields, 0, -1);

        $stream .= $fields . "]>>\nendobj\n\n";

        return $stream;
    }

}