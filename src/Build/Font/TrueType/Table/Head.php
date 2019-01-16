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
namespace Pop\Pdf\Build\Font\TrueType\Table;

/**
 * HEAD table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Head extends AbstractTable
{

    /**
     * Font table properties
     * @var array
     */
    protected $properties = [];

    /**
     * Constructor
     *
     * Instantiate a TTF 'head' table object.
     *
     * @param  \Pop\Pdf\Build\Font\TrueType $font
     */
    public function __construct(\Pop\Pdf\Build\Font\TrueType $font)
    {
        $bytePos = $font->tableInfo['head']->offset;

        $tableVersionNumberBytes = $font->read($bytePos, 4);
        $tableVersionNumber      = $font->readFixed(16, 16, $tableVersionNumberBytes);

        $bytePos += 4;

        $fontRevisionBytes = $font->read($bytePos, 4);
        $fontRevision      = $font->readFixed(16, 16, $fontRevisionBytes);

        $versionArray = [
            'tableVersionNumber' => $tableVersionNumber,
            'fontRevision'       => $fontRevision
        ];

        $bytePos += 4;

        $headerArray = unpack(
            'NcheckSumAdjustment/' .
            'NmagicNumber/' .
            'nflags/' .
            'nunitsPerEm', $font->read($bytePos, 12)
        );

        $bytePos += 28;
        $bBox = unpack(
            'nxMin/' .
            'nyMin/' .
            'nxMax/' .
            'nyMax', $font->read($bytePos, 8)
        );
        $bBox = $font->shiftToSigned($bBox);

        $bytePos += 14;
        $indexToLocFormat = unpack('nindexToLocFormat', $font->read($bytePos, 2));
        $headerArray['indexToLocFormat'] = $font->shiftToSigned($indexToLocFormat['indexToLocFormat']);

        $this->properties = array_merge($versionArray, $headerArray, $bBox);
    }

}
