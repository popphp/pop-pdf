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

use Pop\Pdf\Build\Font;
use Pop\Utils\ArrayObject as Data;

/**
 * NAME table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Name extends AbstractTable
{

    /**
     * Font table properties
     * @var array
     */
    protected $properties = [];

    /**
     * TrueType font info names
     * @var array
     */
    protected $names = [
        0  => 'copyright',
        1  => 'fontFamily',
        2  => 'fontSubFamily',
        3  => 'uniqueId',
        4  => 'fullName',
        5  => 'version',
        6  => 'postscriptName',
        7  => 'trademark',
        8  => 'manufacturer',
        9  => 'designer',
        10 => 'description',
        11 => 'vendorUrl',
        12 => 'designerUrl',
        13 => 'license',
        14 => 'licenseUrl',
        16 => 'preferredFamily',
        17 => 'preferredSubFamily',
        18 => 'compatibleFull',
        19 => 'sampleText'
    ];

    /**
     * Constructor
     *
     * Instantiate a TTF 'name' table object.
     *
     * @param  Font\TrueType $font
     */
    public function __construct(Font\TrueType $font)
    {
        $tableInfo = $font->tableInfo;

        if (!isset($tableInfo['name'])) {
            $tableInfo['name'] = [];
        }

        $tableInfo['name']['header'] = new Data(
            unpack(
                'nformatSelector/' .
                'nnameRecordsCount/' .
                'nstorageOffset', $font->read($font->tableInfo['name']->offset, 6)
            )
        );

        $font->tableInfo = $tableInfo;

        $bytePos = $font->tableInfo['name']->offset + 6;

        for ($j = 0; $j < $font->tableInfo['name']->header->nameRecordsCount; $j++) {
            $ttfRecord = unpack(
                'nplatformId/' .
                'nencodingId/' .
                'nlanguageId/' .
                'nnameId/' .
                'nlength/' .
                'noffset', $font->read($bytePos, 12)
            );

            $ttfRecordOffset = $bytePos + 12;
            $nextBytePos = $font->tableInfo['name']->offset + $font->tableInfo['name']->header->storageOffset + $ttfRecord['offset'];

            $ttfValue = $font->read($nextBytePos, $ttfRecord['length']);

            if ($ttfRecord['platformId'] != 1) {
                $ttfValue = @iconv('UTF-16be', 'UTF-8//TRANSLIT', $ttfValue);
            }
            if (($ttfValue != '') && isset($ttfRecord['nameId']) && isset($this->names[$ttfRecord['nameId']])) {
                $this->properties[$this->names[$ttfRecord['nameId']]] = $ttfValue;
            }

            $bytePos = $ttfRecordOffset;
        }
    }

}
