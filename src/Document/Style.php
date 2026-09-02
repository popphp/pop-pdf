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

use Pop\Color\Color\ColorInterface;

/**
 * Pdf style class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
 */
class Style
{

    /**
     * Style name
     * @var ?string
     */
    protected ?string $name = null;

    /**
     * Style font
     * @var ?string
     */
    protected ?string $font = null;

    /**
     * Style size
     * @var int|float|null
     */
    protected int|float|null $size = null;

    /**
     * Style color
     * @var ?ColorInterface
     */
    protected ?ColorInterface $color = null;

    /**
     * Constructor
     *
     * Instantiate a PDF style.
     *
     * @param string          $name
     * @param ?string         $font
     * @param int|float|null  $size
     * @param ?ColorInterface $color
     */
    public function __construct(
        string $name, ?string $font = null, int|float|null $size = null, ?ColorInterface $color = null
    )
    {
        $this->setName($name);
        if ($font !== null) {
            $this->setFont($font);
        }
        if ($size !== null) {
            $this->setSize($size);
        }
        if ($color !== null) {
            $this->setColor($color);
        }
    }

    /**
     * Create style object
     *
     * @param string          $name
     * @param ?string         $font
     * @param int|float|null  $size
     * @param ?ColorInterface $color
     * @return Style
     */
    public static function create(
        string $name, ?string $font = null, int|float|null $size = null, ?ColorInterface $color = null
    ): Style
    {
        return new self($name, $font, $size, $color);
    }

    /**
     * Set name
     *
     * @param  string $name
     * @return Style
     */
    public function setName(string $name): Style
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Has name
     *
     * @return bool
     */
    public function hasName(): bool
    {
        return ($this->name !== null);
    }

    /**
     * Set font
     *
     * @param  string $font
     * @return Style
     */
    public function setFont(string $font): Style
    {
        $this->font = $font;
        return $this;
    }

    /**
     * Get font
     *
     * @return ?string
     */
    public function getFont(): ?string
    {
        return $this->font;
    }

    /**
     * Has font
     *
     * @return bool
     */
    public function hasFont(): bool
    {
        return ($this->font !== null);
    }

    /**
     * Set size
     *
     * @param  int|float $size
     * @return Style
     */
    public function setSize(int|float $size): Style
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Get size
     *
     * @return int|float|null
     */
    public function getSize(): int|float|null
    {
        return $this->size;
    }

    /**
     * Has size
     *
     * @return bool
     */
    public function hasSize(): bool
    {
        return ($this->size !== null);
    }

    /**
     * Set color
     *
     * @param  ColorInterface $color
     * @return Style
     */
    public function setColor(ColorInterface $color): Style
    {
        $this->color = $color;
        return $this;
    }

    /**
     * Get color
     *
     * @return ?ColorInterface
     */
    public function getColor(): ?ColorInterface
    {
        return $this->color;
    }

    /**
     * Has color
     *
     * @return bool
     */
    public function hasColor(): bool
    {
        return ($this->color !== null);
    }

}
