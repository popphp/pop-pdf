<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Font\TrueType\Table;

/**
 * MAXP table class
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Maxp extends AbstractTable
{

    /**
     * Allowed properties
     * @var array
     */
    protected $allowed = [
        'numberOfGlyphs' => 0
    ];

    /**
     * Constructor
     *
     * Instantiate a TTF 'maxp' table object.
     *
     * @param  \Pop\Pdf\Build\Font\TrueType $font
     * @return Maxp
     */
    public function __construct(\Pop\Pdf\Build\Font\TrueType $font)
    {
        parent::__construct($this->allowed);

        $bytePos = $font->tableInfo['maxp']->offset + 4;
        $ary     = unpack('nnumberOfGlyphs/', $font->read($bytePos, 2));
        $this->numberOfGlyphs = $ary['numberOfGlyphs'];
    }

}
