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
namespace Pop\Pdf\Build\Font\Standard;

/**
 * Pdf abstract standard font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
abstract class AbstractStandard
{

    /**
     * Font units per em
     * @var int
     */
    protected $unitsPerEm = 1000;

    /**
     * Font glyph widths
     * @var array
     */
    protected $glyphWidths = [];

    /**
     * Font character map
     * @var array
     */
    protected $cmap = [];

    /**
     * Get units per em
     *
     * @return int
     */
    public function getUnitsPerEm()
    {
        return $this->unitsPerEm;
    }

    /**
     * Get character glyph width
     *
     * @param  int $code
     * @throws Exception
     * @return mixed
     */
    public function getGlyphWidth($code)
    {
        if (isset($this->cmap[$code]) && isset($this->glyphWidths[$this->cmap[$code]])) {
            return $this->glyphWidths[$this->cmap[$code]];
        } else {
            return 0;
        }
    }

}