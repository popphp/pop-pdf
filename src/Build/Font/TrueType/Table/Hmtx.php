<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Font\TrueType\Table;

/**
 * HMTX table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Hmtx extends AbstractTable
{

    /**
     * Font table properties
     * @var array
     */
    protected array $properties = [
        'glyphWidths' => []
    ];

    /**
     * Constructor
     *
     * Instantiate a TTF 'hmtx' table object.
     *
     * @param  \Pop\Pdf\Build\Font\TrueType $font
     */
    public function __construct(\Pop\Pdf\Build\Font\TrueType $font)
    {
        $bytePos = $font->tableInfo['hmtx']->offset;

        $count      = $font->numberOfHMetrics;
        $totalBytes = $count * 4;
        $values     = array_values(unpack('n*', $font->read($bytePos, $totalBytes)));

        for ($i = 0; $i < $count; $i++) {
            $this->properties['glyphWidths'][$i] = $font->shiftToSigned($values[$i * 2]);
        }

        while (count($this->properties['glyphWidths']) < $font->numberOfGlyphs) {
            $this->properties['glyphWidths'][] = end($this->properties['glyphWidths']);
        }
    }

}
