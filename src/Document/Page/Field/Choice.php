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
class Choice extends AbstractField
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
     * @return Choice
     */
    public function addOption($option)
    {
        $this->options[] = $option;
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
     * Set combo
     *
     * @return Choice
     */
    public function setCombo()
    {
        if (!in_array(18, $this->flagBits)) {
            $this->flagBits[] = 18;
        }
        return $this;
    }

    /**
     * Set edit
     *
     * @return Choice
     */
    public function setEdit()
    {
        if (!in_array(19, $this->flagBits)) {
            $this->flagBits[] = 19;
        }
        return $this;
    }

    /**
     * Set sort
     *
     * @return Choice
     */
    public function setSort()
    {
        if (!in_array(20, $this->flagBits)) {
            $this->flagBits[] = 20;
        }
        return $this;
    }

    /**
     * Set multi-select
     *
     * @return Choice
     */
    public function setMultiSelect()
    {
        if (!in_array(22, $this->flagBits)) {
            $this->flagBits[] = 22;
        }
        return $this;
    }

    /**
     * Set do not spell check
     *
     * @return Choice
     */
    public function setDoNotSpellCheck()
    {
        if (!in_array(23, $this->flagBits)) {
            $this->flagBits[] = 23;
        }
        return $this;
    }

    /**
     * Set commit on select change
     *
     * @return Choice
     */
    public function setCommitOnSelChange()
    {
        if (!in_array(27, $this->flagBits)) {
            $this->flagBits[] = 27;
        }
        return $this;
    }

    /**
     * Is combo
     *
     * @return boolean
     */
    public function isCombo()
    {
        return in_array(18, $this->flagBits);
    }

    /**
     * Is multi-select
     *
     * @return boolean
     */
    public function isMultiSelect()
    {
        return in_array(22, $this->flagBits);
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
                $options .= '(' . $option . ') ';
            }
            $options .= "]\n";
        }

        // Return the stream
        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Widget\n    /FT /Ch\n    /Rect [{$x} {$y} " .
            ($this->width + $x) . " " . ($this->height + $y) . "]{$value}{$default}\n    /P {$pageIndex} 0 R\n" .
            "    \n{$text}\n{$name}\n{$flags}\n{$options}>>\nendobj\n\n";
    }

}
