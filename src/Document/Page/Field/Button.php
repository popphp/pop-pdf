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
namespace Pop\Pdf\Document\Page\Field;

use Pop\Pdf\Document\Page\Color;

/**
 * Pdf page button field class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Button extends AbstractField
{

    /**
     * Field options
     * @var array
     */
    protected $options = [];

    /**
     * Add an option
     *
     * @param  string $option
     * @param  int    $xOffset
     * @param  int    $yOffset
     * @return Button
     */
    public function addOption($option, $xOffset = 0, $yOffset = 0)
    {
        $this->options[] = [
            'option'  => $option,
            'xOffset' => $xOffset,
            'yOffset' => $yOffset
        ];

        return $this;
    }

    /**
     * Has options
     *
     * @return boolean
     */
    public function hasOptions()
    {
        return (count($this->options) > 0);
    }

    /**
     * Set no toggle to off
     *
     * @return Button
     */
    public function setNoToggleToOff()
    {
        if (!in_array(15, $this->flagBits)) {
            $this->flagBits[] = 15;
        }
        return $this;
    }

    /**
     * Set radio
     *
     * @return Button
     */
    public function setRadio()
    {
        if (!in_array(16, $this->flagBits)) {
            $this->flagBits[] = 16;
        }
        return $this;
    }

    /**
     * Set push button
     *
     * @return Button
     */
    public function setPushButton()
    {
        if (!in_array(17, $this->flagBits)) {
            $this->flagBits[] = 17;
        }
        return $this;
    }

    /**
     * Set radios in unison
     *
     * @return Button
     */
    public function setRadiosInUnison()
    {
        if (!in_array(26, $this->flagBits)) {
            $this->flagBits[] = 26;
        }
        return $this;
    }

    /**
     * Is radio
     *
     * @return boolean
     */
    public function isRadio()
    {
        return in_array(16, $this->flagBits);
    }

    /**
     * Is push button
     *
     * @return Button
     */
    public function isPushButton()
    {
        return in_array(17, $this->flagBits);
    }

    /**
     * Get the field stream
     *
     * @param  int    $i
     * @param  int    $pageIndex
     * @param  string $fontReference
     * @param  int    $x
     * @param  int    $y
     * @return string
     */
    public function getStream($i, $pageIndex, $fontReference, $x, $y)
    {
        $text    = null;
        $options = null;
        $color   = '0 g';

        if (null !== $this->fontColor) {
            if ($this->fontColor instanceof Color\Rgb) {
                $color = $this->fontColor . " rg";
            } else if ($this->fontColor instanceof Color\Cmyk) {
                $color = $this->fontColor . " k";
            } else if ($this->fontColor instanceof Color\Gray) {
                $color = $this->fontColor . " g";
            }
        }

        if (null !== $fontReference) {
            $fontReference = substr($fontReference, 0, strpos($fontReference, ' '));
            $text          = '    /DA(' . $fontReference . ' ' . $this->size . ' Tf ' . $color . ')';
        }

        $name    = (null !== $this->name) ? '    /T(' . $this->name . ')/TU(' . $this->name . ')/TM(' . $this->name . ')' : '';
        $flags   = (count($this->flagBits) > 0) ? "\n    /Ff " . $this->getFlags() . "\n" : null;
        $value   = (null !== $this->value) ? "\n    /V " . $this->value . "\n" : null;
        $default = (null !== $this->defaultValue) ? "\n    /DV " . $this->defaultValue . "\n" : null;

        if (count($this->options) > 0) {
            $options = "    /Opt [ ";
            foreach ($this->options as $option) {
                $options .= '(' . $option['option'] . ') ';
            }
            $options .= "]\n";
        }

        // Return the stream
        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Widget\n    /FT /Btn\n    /Rect [{$x} {$y} " .
            ($this->width + $x) . " " . ($this->height + $y) . "]{$value}{$default}\n    /P {$pageIndex} 0 R\n" .
            "    \n{$text}\n{$name}\n{$flags}\n{$options}>>\nendobj\n\n";
    }

}
