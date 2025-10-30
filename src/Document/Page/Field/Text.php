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
namespace Pop\Pdf\Document\Page\Field;

use Pop\Color\Color;

/**
 * Pdf page button field class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.7
 */
class Text extends AbstractField
{

    /**
     * Set multiline
     *
     * @return Text
     */
    public function setMultiline(): Text
    {
        if (!in_array(13, $this->flagBits)) {
            $this->flagBits[] = 13;
        }
        return $this;
    }

    /**
     * Set password
     *
     * @return Text
     */
    public function setPassword(): Text
    {
        if (!in_array(14, $this->flagBits)) {
            $this->flagBits[] = 14;
        }
        return $this;
    }

    /**
     * Set file select
     *
     * @return Text
     */
    public function setFileSelect(): Text
    {
        if (!in_array(21, $this->flagBits)) {
            $this->flagBits[] = 21;
        }
        return $this;
    }

    /**
     * Set do not spell check
     *
     * @return Text
     */
    public function setDoNotSpellCheck(): Text
    {
        if (!in_array(23, $this->flagBits)) {
            $this->flagBits[] = 23;
        }
        return $this;
    }

    /**
     * Set do not scroll
     *
     * @return Text
     */
    public function setDoNotScroll(): Text
    {
        if (!in_array(24, $this->flagBits)) {
            $this->flagBits[] = 24;
        }
        return $this;
    }

    /**
     * Set comb
     *
     * @return Text
     */
    public function setComb(): Text
    {
        if (!in_array(25, $this->flagBits)) {
            $this->flagBits[] = 25;
        }
        return $this;
    }

    /**
     * Set rich text
     *
     * @return Text
     */
    public function setRichText(): Text
    {
        if (!in_array(26, $this->flagBits)) {
            $this->flagBits[] = 26;
        }
        return $this;
    }

    /**
     * Is multiline
     *
     * @return bool
     */
    public function isMultiline(): bool
    {
        return in_array(13, $this->flagBits);
    }

    /**
     * Is password
     *
     * @return bool
     */
    public function isPassword(): bool
    {
        return in_array(14, $this->flagBits);
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
        $color = '0 g';
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
        } else {
            $text = null;
        }

        $name    = ($this->name !== null) ? '    /T(' . $this->name . ')/TU(' . $this->name . ')/TM(' . $this->name . ')' : '';
        $flags   = (count($this->flagBits) > 0) ? "\n    /Ff " . $this->getFlags() . "\n" : null;
        $value   = ($this->value !== null) ? "\n    /V " . $this->value . "\n" : null;
        $default = ($this->defaultValue !== null) ? "\n    /DV " . $this->defaultValue . "\n" : null;

        // Return the stream
        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Widget\n    /FT /Tx\n    /Rect [{$x} {$y} " .
            ($this->width + $x) . " " . ($this->height + $y) . "]{$value}{$default}\n    /P {$pageIndex} 0 R\n{$text}\n{$name}\n{$flags}>>\nendobj\n\n";
    }

}
