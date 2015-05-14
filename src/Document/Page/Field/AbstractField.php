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
namespace Pop\Pdf\Document\Page\Field;

/**
 * Pdf abstract form field class
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
abstract class AbstractField implements FieldInterface
{

    /**
     * Field name
     * @var string
     */
    protected $name = null;

    /**
     * Constructor
     *
     * Instantiate a PDF field object.
     *
     * @param  string $name
     * @return AbstractField
     */
    public function __construct($name)
    {
        $this->setName($name);
    }

    /**
     * Set the field name
     *
     * @param  string $name
     * @return AbstractField
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the field name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

}
