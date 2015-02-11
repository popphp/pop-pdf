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
namespace Pop\Pdf\Document\Page\Annotation;

use Pop\Pdf\Document\Page\Color;

/**
 * Pdf page text field class
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
class TextField extends AbstractAnnotation
{

    /**
     * Field name
     * @var string
     */
    protected $name = null;

    /**
     * Field font
     * @var string
     */
    protected $font = 'MF1';

    /**
     * Field font size
     * @var string
     */
    protected $fontSize = 12;

    /**
     * Field font color
     * @var Color\ColorInterface
     */
    protected $fontColor = null;

    /**
     * Constructor
     *
     * Instantiate a PDF Text Field annotation object.
     *
     * @param  int    $width
     * @param  int    $height
     * @param  string $name
     * @return TextField
     */
    public function __construct($width, $height, $name = null)
    {
        parent::__construct($width, $height);
        if (null !== $name) {
            $this->setName($name);
        }
    }

    /**
     * Set the name
     *
     * @param  string $name
     * @return TextField
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the font
     *
     * @param  string $font
     * @return TextField
     */
    public function setFont($font)
    {
        $this->font = $font;
        return $this;
    }

    /**
     * Set the font size
     *
     * @param  int $size
     * @return TextField
     */
    public function setFontSize($size)
    {
        $this->fotnSize = (int)$size;
        return $this;
    }

    /**
     * Set the font color
     *
     * @param  Color\ColorInterface $color
     * @return TextField
     */
    public function setFontColor(Color\ColorInterface $color)
    {
        $this->fontColor = $color;
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
     * Get the field font
     *
     * @return string
     */
    public function getFont()
    {
        return $this->font;
    }

    /**
     * Get the field font size
     *
     * @return int
     */
    public function getFontSize()
    {
        return $this->fontSize;
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
     * Get the annotation stream
     *
     * @param  int $i
     * @param  int $pageIndex
     * @param  int $x
     * @param  int $y
     * @return string
     */
    public function getStream($i, $pageIndex, $x, $y)
    {
        // Assemble the border parameters
        $border = $this->hRadius . ' ' . $this->vRadius . ' ' . $this->borderWidth;
        if (($this->dashGap != 0) && ($this->dashLength != 0)) {
            $border .= ' [' . $this->dashGap . ' ' . $this->dashLength . ']';
        }

        $name = (null !== $this->name) ? '    /T(' . $this->name . ')/TU(' . $this->name . ')' : '';
        $color = '0 g';

        if (null !== $this->fontColor) {
            if ($this->fontColor instanceof Color\Rgb) {
                $color = $this->fontColor . " rg";
            } else if ($this->fontColor instanceof Color\Cmyk) {
                $color = $this->fontColor . " k";
            } else if ($this->fontColor instanceof Color\Gray) {
                $color = $this->fontColor . " g";
            }
        }

        $text = '/DA(/' . $this->font . ' ' . $this->fontSize . ' Tf ' . $color . ')';

        // Return the stream
        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Widget\n    /FT /Tx\n    /Rect [{$x} {$y} " .
            ($this->width + $x) . " " . ($this->height + $y) . "]\n    /P {$pageIndex} 0 R\n    {$text}\n{$name}\n>>\nendobj\n\n";
    }

}