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
 * Pdf page button field class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Text extends AbstractField
{

    /**
     * Set multiline
     *
     * @return Text
     */
    public function setMultiline()
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
    public function setPassword()
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
    public function setFileSelect()
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
    public function setDoNotSpellCheck()
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
    public function setDoNotScroll()
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
    public function setComb()
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
    public function setRichText()
    {
        if (!in_array(26, $this->flagBits)) {
            $this->flagBits[] = 26;
        }
        return $this;
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

        if (null !== $fontReference) {
            $fontReference = substr($fontReference, 0, strpos($fontReference, ' '));
            $text          = '    /DA(' . $fontReference . ' ' . $this->size . ' Tf ' . $color . ')';
        } else {
            $text = null;
        }

        $name    = (null !== $this->name) ? '    /T(' . $this->name . ')/TU(' . $this->name . ')/TM(' . $this->name . ')' : '';
        $flags   = (count($this->flagBits) > 0) ? "\n    /Ff " . $this->getFlags() . "\n" : null;
        $value   = (null !== $this->value) ? "\n    /V " . $this->value . "\n" : null;
        $default = (null !== $this->defaultValue) ? "\n    /DV " . $this->defaultValue . "\n" : null;

        // Return the stream
        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Widget\n    /FT /Tx\n    /Rect [{$x} {$y} " .
            ($this->width + $x) . " " . ($this->height + $y) . "]{$value}{$default}\n    /P {$pageIndex} 0 R\n{$text}\n{$name}\n{$flags}>>\nendobj\n\n";
    }

}
