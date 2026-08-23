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
namespace Pop\Pdf\Build\Font\TrueType\Table;

/**
 * GLYF table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Glyf extends AbstractTable
{

    /**
     * Font table properties
     * @var array
     */
    protected array $properties = [
        'glyphs'      => [],
        'glyphWidths' => []
    ];

    /**
     * Constructor
     *
     * Instantiate a TTF 'glyf' table object.
     *
     * @param  \Pop\Pdf\Build\Font\TrueType $font
     */
    public function __construct(\Pop\Pdf\Build\Font\TrueType $font)
    {
        $locaLength = count($font->tables['loca']->offsets);
        $j = 0;

        foreach ($font->tables['loca']->offsets as $offset) {
            $bytePos = $font->tableInfo['glyf']->offset + $offset;
            $ary = unpack(
                'nnumberOfContours/' .
                'nxMin/' .
                'nyMin/' .
                'nxMax/' .
                'nyMax', $font->read($bytePos, 10)
            );
            $ary = $font->shiftToSigned($ary);
            $ary['xMin']  = $font->toEmSpace($ary['xMin']);
            $ary['yMin']  = $font->toEmSpace($ary['yMin']);
            $ary['xMax']  = $font->toEmSpace($ary['xMax']);
            $ary['yMax']  = $font->toEmSpace($ary['yMax']);
            $ary['width'] = $ary['xMin'] + $ary['xMax'];
            $this->properties['glyphWidths'][] = $ary['width'];

            $bytePos += 10;
            $ary['endPtsOfContours'] = [];
            $ary['instructionLength'] = null;
            $ary['instructions'] = null;
            $ary['flags'] = null;

            // The simple and composite glyph descriptions may not be necessary.
            // If simple glyph.
            if ($ary['numberOfContours'] > 0) {
                $endPtsLength = $ary['numberOfContours'] * 2;
                $endPts       = array_values(unpack('n*', $font->read($bytePos, $endPtsLength)));
                for ($i = 0; $i < $ary['numberOfContours']; $i++) {
                    $ary['endPtsOfContours'][$i] = $endPts[$i];
                }
                $bytePos += $endPtsLength;

                $ar = unpack('ninstructionLength', $font->read($bytePos, 2));
                $ary['instructionLength'] = $ar['instructionLength'];
                $bytePos += 2;
                if ($ary['instructionLength'] > 0) {
                    $instructionData = $font->read($bytePos, $ary['instructionLength']);
                    $readLength      = ($instructionData !== null) ? strlen($instructionData) : 0;
                    $instructionBytes = ($readLength > 0) ? array_values(unpack('C*', $instructionData)) : [];
                    for ($i = 0; $i < $ary['instructionLength']; $i++) {
                        if ($i < $readLength) {
                            $ary['instructions'][$i] = $instructionBytes[$i];
                        } else {
                            $ary['instructions'][$i] = null;
                        }
                    }
                    $bytePos += $readLength;
                }
                $bytePos++;
                $byte = $font->read($bytePos, 1);
                if (strlen($byte) != 0) {
                    $ar = unpack('Cflags', $byte);
                    $ary['flags'] = $ar['flags'];
                } else {
                    $ary['flags'] = 0;
                }
                if ($j < ($locaLength - 1)) {
                    $this->properties['glyphs'][] = $ary;
                }
            // Stopped here. Still need to get the x & y coordinates of the simple glyph.
            // Else, if composite glyph.
            } else {
                if ($j < ($locaLength - 1)) {
                    // Composite glyph goes here.
                }
            }
            $j++;
        }
    }

}
