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
namespace Pop\Pdf\Build\Font\Standard;

/**
 * Pdf abstract standard font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.0
 */
abstract class AbstractStandard
{

    /**
     * Font units per em
     * @var int
     */
    protected int $unitsPerEm = 1000;

    /**
     * Font glyph widths
     * @var array
     */
    protected array $glyphWidths = [];

    /**
     * Font character map
     * @var array
     */
    protected array $cmap = [];

    /**
     * Get units per em
     *
     * @return int
     */
    public function getUnitsPerEm(): int
    {
        return $this->unitsPerEm;
    }

    /**
     * Get character glyph width
     *
     * @param  int $code
     * @return mixed
     */
    public function getGlyphWidth(int $code): mixed
    {
        if (isset($this->cmap[$code]) && isset($this->glyphWidths[$this->cmap[$code]])) {
            return $this->glyphWidths[$this->cmap[$code]];
        } else {
            return 0;
        }
    }

}
