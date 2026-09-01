<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
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
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class Button extends AbstractField
{

    /**
     * Field options
     * @var array
     */
    protected array $options = [];

    /**
     * Whether this specific widget is checked - independent of getValue()/
     * setValue(), which carry the export/on-state name, not the checked
     * state. A radio group can have every option carrying its own distinct
     * value while only one of them is actually checked.
     * @var bool
     */
    protected bool $checked = false;

    /**
     * Set checked
     *
     * @param  bool $checked
     * @return Button
     */
    public function setChecked(bool $checked = true): Button
    {
        $this->checked = $checked;
        return $this;
    }

    /**
     * Is checked
     *
     * @return bool
     */
    public function isChecked(): bool
    {
        return $this->checked;
    }

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
     * @param  ?array  $appearance
     * @param  ?int    $parentIndex
     * @return string
     */
    public function getStream(
        int $i, int $pageIndex, ?string $fontReference, int $x, int $y,
        ?array $appearance = null, ?int $parentIndex = null
    ): string
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
            $text          = '    /DA(' . $this->encryptLiteral($fontReference . ' ' . $this->size . ' Tf ' . $color) . ')';
        }

        $name   = (($parentIndex === null) && ($this->name !== null)) ? '    /T(' . $this->encryptLiteral($this->name) . ')/TU(' . $this->encryptLiteral($this->name) .
            ')/TM(' . $this->encryptLiteral($this->name) . ')' : '';
        $flags  = (($parentIndex === null) && (count($this->flagBits) > 0)) ? "\n    /Ff " . $this->getFlags() . "\n" : null;
        $parent = ($parentIndex !== null) ? "    /Parent {$parentIndex} 0 R\n" : '';
        // /V and /DV are bare PDF Names here (no parens/escaping), not
        // string literals - left untouched by encryptLiteral() on purpose.
        $default = ($this->defaultValue !== null) ? "\n    /DV " . $this->defaultValue . "\n" : null;

        if (count($this->options) > 0) {
            $options = "    /Opt [ ";
            foreach ($this->options as $option) {
                $options .= '(' . $this->encryptLiteral($option['option']) . ') ';
            }
            $options .= "]\n";
        }

        $ap = '';
        $as = '';
        $stateName = null;
        if ($appearance !== null) {
            $stateName = ($appearance['checked']) ? $appearance['onName'] : 'Off';
            $ap = "    /AP << /N << /" . $appearance['onName'] . " " . $appearance['onRef'] . " /Off " . $appearance['offRef'] . " >> >>\n";
            $as = "    /AS /" . $stateName . "\n";
        }

        // For checkboxes/radios, /V must be the same sanitized Name /AS uses
        // so the widget's value agrees with its appearance state. Push
        // buttons (no $appearance) keep the original bare /V behavior.
        $value = ($appearance !== null)
            ? "\n    /V /" . $stateName . "\n"
            : (($this->value !== null) ? "\n    /V " . $this->value . "\n" : null);

        // Return the stream
        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Widget\n    /FT /Btn\n    /Rect [{$x} {$y} " .
            ($this->width + $x) . " " . ($this->height + $y) . "]{$value}{$default}\n    /P {$pageIndex} 0 R\n{$parent}" .
            "    \n{$text}\n{$name}\n{$flags}\n{$options}{$ap}{$as}" .
            $this->getAppearanceCharacteristics() . $this->getBorderStyle() . ">>\nendobj\n\n";
    }

    /**
     * Get the shared parent field stream for a radio group - has no /Rect
     * since it is not itself a widget annotation, only the field the group's
     * widgets point back to via their own /Parent
     *
     * @param  int     $i
     * @param  ?string $checkedExportName
     * @return string
     */
    public function getParentFieldStream(int $i, ?string $checkedExportName = null): string
    {
        $name  = ($this->name !== null) ? '    /T(' . $this->encryptLiteral($this->name) . ')' : '';
        $flags = "    /Ff " . $this->getFlags() . "\n";
        $value = ($checkedExportName !== null) ? "    /V /" . $checkedExportName . "\n" : '';

        return "{$i} 0 obj\n<<\n    /FT /Btn\n{$name}\n{$flags}{$value}>>\nendobj\n\n";
    }

}
