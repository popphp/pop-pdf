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
namespace Pop\Pdf\Build\Font\TrueType\Table;

/**
 * LOCA table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Loca extends AbstractTable
{

    /**
     * Font table properties
     * @var array
     */
    protected $properties = [
        'offsets' => []
    ];

    /**
     * Constructor
     *
     * Instantiate a TTF 'loca' table object.
     *
     * @param  \Pop\Pdf\Build\Font\TrueType $font
     */
    public function __construct(\Pop\Pdf\Build\Font\TrueType $font)
    {
        $bytePos    = $font->tableInfo['loca']->offset;
        $format     = ($font->header->indexToLocFormat == 1) ? 'N' : 'n';
        $byteLength = ($font->header->indexToLocFormat == 1) ? 4 : 2;
        $multiplier = ($font->header->indexToLocFormat == 1) ? 1 : 2;

        for ($i = 0; $i < ($font->numberOfGlyphs + 1); $i++) {
            $ary = unpack($format . 'offset', $font->read($bytePos, $byteLength));
            $this->properties['offsets'][$i] = $ary['offset'] * $multiplier;
            $bytePos += $byteLength;
        }
    }

}
