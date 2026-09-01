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

    /**
     * Allow printing
     * @var bool
     */
    protected bool $printing = true;

    /**
     * Allow high (full) resolution printing
     * @var bool
     */
    protected bool $highResPrinting = true;

    /**
     * Allow modifying the document
     * @var bool
     */
    protected bool $modifying = true;

    /**
     * Allow copying/extracting text and graphics
     * @var bool
     */
    protected bool $copying = true;

    /**
     * Allow adding or modifying text annotations
     * @var bool
     */
    protected bool $annotating = true;

    /**
     * Allow filling in existing interactive form fields
     * @var bool
     */
    protected bool $fillingForms = true;

    /**
     * Allow extracting text/graphics for accessibility purposes
     * @var bool
     */
    protected bool $extractingForAccessibility = true;

    /**
     * Allow document assembly (insert/delete/rotate pages, bookmarks)
     * @var bool
     */
    protected bool $assembling = true;

    /**
     * Set whether printing is allowed
     *
     * @param  bool $allow
     * @return Permissions
     */
    public function allowPrinting(bool $allow = true): Permissions
    {
        $this->printing = $allow;
        return $this;
    }

    /**
     * Determine whether printing is allowed
     *
     * @return bool
     */
    public function isPrintingAllowed(): bool
    {
        return $this->printing;
    }

    /**
     * Set whether high (full) resolution printing is allowed
     *
     * @param  bool $allow
     * @return Permissions
     */
    public function allowHighResPrinting(bool $allow = true): Permissions
    {
        $this->highResPrinting = $allow;
        return $this;
    }

    /**
     * Determine whether high (full) resolution printing is allowed
     *
     * @return bool
     */
    public function isHighResPrintingAllowed(): bool
    {
        return $this->highResPrinting;
    }

    /**
     * Set whether modifying the document is allowed
     *
     * @param  bool $allow
     * @return Permissions
     */
    public function allowModifying(bool $allow = true): Permissions
    {
        $this->modifying = $allow;
        return $this;
    }

    /**
     * Determine whether modifying the document is allowed
     *
     * @return bool
     */
    public function isModifyingAllowed(): bool
    {
        return $this->modifying;
    }

    /**
     * Set whether copying/extracting text and graphics is allowed
     *
     * @param  bool $allow
     * @return Permissions
     */
    public function allowCopying(bool $allow = true): Permissions
    {
        $this->copying = $allow;
        return $this;
    }

    /**
     * Determine whether copying/extracting text and graphics is allowed
     *
     * @return bool
     */
    public function isCopyingAllowed(): bool
    {
        return $this->copying;
    }

    /**
     * Set whether adding or modifying text annotations is allowed
     *
     * @param  bool $allow
     * @return Permissions
     */
    public function allowAnnotating(bool $allow = true): Permissions
    {
        $this->annotating = $allow;
        return $this;
    }

    /**
     * Determine whether adding or modifying text annotations is allowed
     *
     * @return bool
     */
    public function isAnnotatingAllowed(): bool
    {
        return $this->annotating;
    }

    /**
     * Set whether filling in existing interactive form fields is allowed
     *
     * @param  bool $allow
     * @return Permissions
     */
    public function allowFillingForms(bool $allow = true): Permissions
    {
        $this->fillingForms = $allow;
        return $this;
    }

    /**
     * Determine whether filling in existing interactive form fields is allowed
     *
     * @return bool
     */
    public function isFillingFormsAllowed(): bool
    {
        return $this->fillingForms;
    }

    /**
     * Set whether extracting text/graphics for accessibility purposes is allowed
     *
     * @param  bool $allow
     * @return Permissions
     */
    public function allowExtractingForAccessibility(bool $allow = true): Permissions
    {
        $this->extractingForAccessibility = $allow;
        return $this;
    }

    /**
     * Determine whether extracting text/graphics for accessibility purposes is allowed
     *
     * @return bool
     */
    public function isExtractingForAccessibilityAllowed(): bool
    {
        return $this->extractingForAccessibility;
    }

    /**
     * Set whether document assembly (insert/delete/rotate pages, bookmarks) is allowed
     *
     * @param  bool $allow
     * @return Permissions
     */
    public function allowAssembling(bool $allow = true): Permissions
    {
        $this->assembling = $allow;
        return $this;
    }

    /**
     * Determine whether document assembly (insert/delete/rotate pages, bookmarks) is allowed
     *
     * @return bool
     */
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
