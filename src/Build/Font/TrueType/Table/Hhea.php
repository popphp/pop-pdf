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
 * HHEA table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.0
 */
class Hhea extends AbstractTable
{

    /**
     * Font table properties
     * @var array
     */
    protected array $properties = [
        'ascent'           => 0,
        'descent'          => 0,
        'numberOfHMetrics' => 0
    ];

    /**
     * Constructor
     *
     * Instantiate a TTF 'hhea' table object.
     *
     * @param  \Pop\Pdf\Build\Font\TrueType $font
     */
    public function __construct(\Pop\Pdf\Build\Font\TrueType $font)
    {
        $bytePos = $font->tableInfo['hhea']->offset + 4;

        $ary = unpack(
            'nascent/' .
            'ndescent', $font->read($bytePos, 4)
        );

        $ary = $font->shiftToSigned($ary);
        $this->properties['ascent']  = $font->toEmSpace($ary['ascent']);
        $this->properties['descent'] = $font->toEmSpace($ary['descent']);

        $bytePos = $font->tableInfo['hhea']->offset + 34;
        $ary = unpack('nnumberOfHMetrics/', $font->read($bytePos, 2));
        $this->properties['numberOfHMetrics'] = $ary['numberOfHMetrics'];
    }

}
