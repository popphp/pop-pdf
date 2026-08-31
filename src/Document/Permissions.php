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

namespace Pop\Pdf\Document;

/**
 * Pdf permissions class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Permissions
{
    protected bool $printing                    = true;
    protected bool $highResPrinting              = true;
    protected bool $modifying                    = true;
    protected bool $copying                      = true;
    protected bool $annotating                   = true;
    protected bool $fillingForms                 = true;
    protected bool $extractingForAccessibility   = true;
    protected bool $assembling                   = true;

    public function allowPrinting(bool $allow = true): Permissions
    {
        $this->printing = $allow;
        return $this;
    }

    public function isPrintingAllowed(): bool
    {
        return $this->printing;
    }

    public function allowHighResPrinting(bool $allow = true): Permissions
    {
        $this->highResPrinting = $allow;
        return $this;
    }

    public function isHighResPrintingAllowed(): bool
    {
        return $this->highResPrinting;
    }

    public function allowModifying(bool $allow = true): Permissions
    {
        $this->modifying = $allow;
        return $this;
    }

    public function isModifyingAllowed(): bool
    {
        return $this->modifying;
    }

    public function allowCopying(bool $allow = true): Permissions
    {
        $this->copying = $allow;
        return $this;
    }

    public function isCopyingAllowed(): bool
    {
        return $this->copying;
    }

    public function allowAnnotating(bool $allow = true): Permissions
    {
        $this->annotating = $allow;
        return $this;
    }

    public function isAnnotatingAllowed(): bool
    {
        return $this->annotating;
    }

    public function allowFillingForms(bool $allow = true): Permissions
    {
        $this->fillingForms = $allow;
        return $this;
    }

    public function isFillingFormsAllowed(): bool
    {
        return $this->fillingForms;
    }

    public function allowExtractingForAccessibility(bool $allow = true): Permissions
    {
        $this->extractingForAccessibility = $allow;
        return $this;
    }

    public function isExtractingForAccessibilityAllowed(): bool
    {
        return $this->extractingForAccessibility;
    }

    public function allowAssembling(bool $allow = true): Permissions
    {
        $this->assembling = $allow;
        return $this;
    }

    public function isAssemblingAllowed(): bool
    {
        return $this->assembling;
    }

    /**
     * Pack the permission flags into ISO 32000-1 Table 22's signed 32-bit
     * /P value. Every bit from 7 upward that Table 22 does not assign a
     * meaning is reserved and must read as 1, so this starts from all bits
     * set and clears only the specific bit for each permission that is
     * denied - it never sets bits from 0 upward, which would risk leaving a
     * reserved bit incorrectly cleared.
     *
     * Bits 1 and 2 (combined value 3) are the exception: Table 22 reserves
     * them explicitly as "must be 0", so they are cleared up front.
     *
     * @return int
     */
    public function toPValue(): int
    {
        $p = -1 & ~3;

        if (!$this->printing) {
            $p &= ~4;
        }
        if (!$this->modifying) {
            $p &= ~8;
        }
        if (!$this->copying) {
            $p &= ~16;
        }
        if (!$this->annotating) {
            $p &= ~32;
        }
        if (!$this->fillingForms) {
            $p &= ~256;
        }
        if (!$this->extractingForAccessibility) {
            $p &= ~512;
        }
        if (!$this->assembling) {
            $p &= ~1024;
        }
        if (!$this->highResPrinting) {
            $p &= ~2048;
        }

        return $p;
    }
}
