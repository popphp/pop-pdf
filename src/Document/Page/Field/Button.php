<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document\Page\Field;

use Pop\Color\Color;

/**
 * Pdf page button field class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.1
 */
class Button extends AbstractField
{

    /**
     * Field options
     * @var array
     */
    protected array $options = [];

    /**
     * Add an option
     *
     * @param  string $option
     * @param  int    $xOffset
     * @param  int    $yOffset
     * @return Button
     */
    public function addOption(string $option, int $xOffset = 0, int $yOffset = 0): Button
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
     * @return bool
     */
    public function hasOptions(): bool
    {
        return (count($this->options) > 0);
    }

    /**
     * Set no toggle to off
     *
     * @return Button
     */
    public function setNoToggleToOff(): Button
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
    public function setRadio(): Button
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
    public function setPushButton(): Button
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
    public function setRadiosInUnison(): Button
    {
        if (!in_array(26, $this->flagBits)) {
            $this->flagBits[] = 26;
        }
        return $this;
    }

    /**
     * Is radio
     *
     * @return bool
     */
    public function isRadio(): bool
    {
        return in_array(16, $this->flagBits);
    }

    /**
     * Is push button
     *
     * @return bool
     */
    public function isPushButton(): bool
    {
        return in_array(17, $this->flagBits);
    }

    /**
     * Get the field stream
     *
     * @param  int     $i
     * @param  int     $pageIndex
     * @param  ?string $fontReference
     * @param  int     $x
     * @param  int     $y
     * @return string
     */
    public function getStream(int $i, int $pageIndex, ?string $fontReference, int $x, int $y): string
    {
        $text    = null;
        $options = null;
        $color   = '0 g';

        if ($this->fontColor !== null) {
            if ($this->fontColor instanceof Color\Rgb) {
                $color = $this->fontColor->render(Color\Rgb::PERCENT) . " rg";
            } else if ($this->fontColor instanceof Color\Cmyk) {
                $color = $this->fontColor->render(Color\Cmyk::PERCENT) . " k";
            } else if ($this->fontColor instanceof Color\Grayscale) {
                $color = $this->fontColor->render(Color\Grayscale::PERCENT) . " g";
            }
        }

        if ($fontReference !== null) {
            $fontReference = substr($fontReference, 0, strpos($fontReference, ' '));
            $text          = '    /DA(' . $fontReference . ' ' . $this->size . ' Tf ' . $color . ')';
        }

        $name    = ($this->name !== null) ? '    /T(' . $this->name . ')/TU(' . $this->name . ')/TM(' . $this->name . ')' : '';
        $flags   = (count($this->flagBits) > 0) ? "\n    /Ff " . $this->getFlags() . "\n" : null;
        $value   = ($this->value !== null) ? "\n    /V " . $this->value . "\n" : null;
        $default = ($this->defaultValue !== null) ? "\n    /DV " . $this->defaultValue . "\n" : null;

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
