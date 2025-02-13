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
namespace Pop\Pdf\Build\Font\TrueType\Table;

/**
 * MAXP table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
 */
class Maxp extends AbstractTable
{

    /**
     * Font table properties
     * @var array
     */
    protected array $properties = [
        'numberOfGlyphs' => 0
    ];

    /**
     * Constructor
     *
     * Instantiate a TTF 'maxp' table object.
     *
     * @param  \Pop\Pdf\Build\Font\TrueType $font
     */
    public function __construct(\Pop\Pdf\Build\Font\TrueType $font)
    {
        $bytePos = $font->tableInfo['maxp']->offset + 4;
        $ary     = unpack('nnumberOfGlyphs/', $font->read($bytePos, 2));
        $this->properties['numberOfGlyphs'] = $ary['numberOfGlyphs'];
    }

}
