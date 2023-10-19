<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
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
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Choice extends AbstractField
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
     * @return Choice
     */
    public function addOption(string $option): Choice
    {
        $this->options[] = $option;
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
     * Set combo
     *
     * @return Choice
     */
    public function setCombo(): Choice
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
    public function setEdit(): Choice
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
    public function setSort(): Choice
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
    public function setMultiSelect(): Choice
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
    public function setDoNotSpellCheck(): Choice
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
    public function setCommitOnSelChange(): Choice
    {
        if (!in_array(27, $this->flagBits)) {
            $this->flagBits[] = 27;
        }
        return $this;
    }

    /**
     * Is combo
     *
     * @return bool
     */
    public function isCombo(): bool
    {
        return in_array(18, $this->flagBits);
    }

    /**
     * Is multi-select
     *
     * @return bool
     */
    public function isMultiSelect(): bool
    {
        return in_array(22, $this->flagBits);
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
                $color = $this->fontColor . " rg";
            } else if ($this->fontColor instanceof Color\Cmyk) {
                $color = $this->fontColor . " k";
            } else if ($this->fontColor instanceof Color\Grayscale) {
                $color = $this->fontColor . " g";
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
