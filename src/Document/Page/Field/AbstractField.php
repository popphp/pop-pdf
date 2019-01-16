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
namespace Pop\Pdf\Document\Page\Field;

use Pop\Pdf\Document\Page\Color;

/**
 * Pdf abstract form field class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
abstract class AbstractField implements FieldInterface
{

    /**
     * Field name
     * @var string
     */
    protected $name = null;

    /**
     * Text field width
     * @var int
     */
    protected $width = null;

    /**
     * Text field height
     * @var int
     */
    protected $height = null;

    /**
     * Field value
     * @var string
     */
    protected $value = null;

    /**
     * Field default value
     * @var string
     */
    protected $defaultValue = null;

    /**
     * Text field font
     * @var string
     */
    protected $font = null;

    /**
     * Text field font size
     * @var int
     */
    protected $size = 12;

    /**
     * Field font color
     * @var Color\ColorInterface
     */
    protected $fontColor = null;

    /**
     * Field flag bits
     * @var array
     */
    protected $flagBits = [];

    /**
     * Constructor
     *
     * Instantiate a PDF text field object.
     *
     * @param  string $name
     * @param  string $font
     * @param  int    $size
     */
    public function __construct($name, $font = null, $size = 12)
    {
        $this->setName($name);
        $this->setSize($size);
        if (null !== $font) {
            $this->setFont($font);
        }
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
     * Set the field value
     *
     * @param  string $value
     * @return AbstractField
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Set the field default value
     *
     * @param  string $value
     * @return AbstractField
     */
    public function setDefaultValue($value)
    {
        $this->defaultValue = $value;
        return $this;
    }

    /**
     * Set the font
     *
     * @param  string $font
     * @return Text
     */
    public function setFont($font)
    {
        $this->font = $font;
        return $this;
    }

    /**
     * Get the font
     *
     * @return string
     */
    public function getFont()
    {
        return $this->font;
    }

    /**
     * Set the font size
     *
     * @param  int $size
     * @return Text
     */
    public function setSize($size)
    {
        $this->size = (int)$size;
        return $this;
    }

    /**
     * Get the font size
     *
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Set the font color
     *
     * @param  Color\ColorInterface $color
     * @return Text
     */
    public function setFontColor(Color\ColorInterface $color)
    {
        $this->fontColor = $color;
        return $this;
    }

    /**
     * Get the field font color
     *
     * @return Color\ColorInterface
     */
    public function getFontColor()
    {
        return $this->fontColor;
    }

    /**
     * Set read-only
     *
     * @return AbstractField
     */
    public function setReadOnly()
    {
        if (!in_array(1, $this->flagBits)) {
            $this->flagBits[] = 1;
        }
        return $this;
    }

    /**
     * Set required
     *
     * @return AbstractField
     */
    public function setRequired()
    {
        if (!in_array(2, $this->flagBits)) {
            $this->flagBits[] = 2;
        }
        return $this;
    }

    /**
     * Set no export
     *
     * @return AbstractField
     */
    public function setNoExport()
    {
        if (!in_array(3, $this->flagBits)) {
            $this->flagBits[] = 3;
        }
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

    /**
     * Get the field value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Get the field default value
     *
     * @return string
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * Set the field width
     *
     * @param  int $width
     * @return Text
     */
    public function setWidth($width)
    {
        $this->width = (int)$width;
        return $this;
    }

    /**
     * Get the field width
     *
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Set the field height
     *
     * @param  int $height
     * @return Text
     */
    public function setHeight($height)
    {
        $this->height = (int)$height;
        return $this;
    }

    /**
     * Get the field height
     *
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Get the field name
     *
     * @return int
     */
    protected function getFlags()
    {
        $flags = '';

        for ($i = 1; $i <= 32; $i++) {
            $flags = ((in_array($i, $this->flagBits)) ? '1' : '0') . $flags;
        }

        return bindec($flags);
    }

}
