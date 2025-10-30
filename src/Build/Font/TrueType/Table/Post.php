<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Font\TrueType\Table;

/**
 * POST table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.3
 */
class Post extends AbstractTable
{

    /**
     * Font table properties
     * @var array
     */
    protected array $properties = [
        'italicAngle' => 0,
        'fixed'       => 0
    ];

    /**
     * Constructor
     *
     * Instantiate a TTF 'post' table object.
     *
     * @param  \Pop\Pdf\Build\Font\TrueType $font
     */
    public function __construct(\Pop\Pdf\Build\Font\TrueType $font)
    {
        $bytePos = $font->tableInfo['post']->offset + 4;

        $italicBytes  = $font->read($bytePos, 4);
        $this->properties['italicAngle'] = $font->readFixed(16, 16, $italicBytes);

        $bytePos += 8;

        $ary = unpack('nfixed/', $font->read($bytePos, 2));
        $ary = $font->shiftToSigned($ary);
        $this->properties['fixed'] = $ary['fixed'];
    }

}
