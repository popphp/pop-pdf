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
 * CMAP table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Cmap extends AbstractTable
{

    /**
     * Font table properties
     * @var array
     */
    protected $properties = [
        'header'    => null,
        'subTables' => []
    ];

    /**
     * Constructor
     *
     * Instantiate a TTF 'cmap' table object.
     *
     * @param  \Pop\Pdf\Build\Font\TrueType $font
     */
    public function __construct(\Pop\Pdf\Build\Font\TrueType $font)
    {
        $bytePos = $font->tableInfo['cmap']->offset;

        // Get the CMAP header data.
        $cmapTableHeader = unpack(
            'ntableVersion/' .
            'nnumberOfTables', $font->read($bytePos, 4)
        );

        $this->properties['header'] = new \ArrayObject($cmapTableHeader, \ArrayObject::ARRAY_AS_PROPS);
        $this->parseSubTables($font);
    }

    /**
     * Method to parse the CMAP sub-tables.
     *
     * @param  \Pop\Pdf\Build\Font\TrueType $font
     * @return void
     */
    protected function parseSubTables(\Pop\Pdf\Build\Font\TrueType $font)
    {
        $bytePos = $font->tableInfo['cmap']->offset + 4;

        // Get each of the sub-table's data.
        for ($i = 0; $i < $this->properties['header']->numberOfTables; $i++) {
            $ary = unpack(
                'nplatformId/' .
                'nencodingId/' .
                'Noffset', $font->read($bytePos, 8)
            );
            if (($ary['platformId'] == 0) && ($ary['encodingId'] == 0)) {
                $ary['encoding'] = 'Unicode 2.0';
            } else if (($ary['platformId'] == 0) && ($ary['encodingId'] == 3)) {
                $ary['encoding'] = 'Unicode';
            } else if (($ary['platformId'] == 3) && ($ary['encodingId'] == 1)) {
                $ary['encoding'] = 'Microsoft Unicode';
            } else if (($ary['platformId'] == 1) && ($ary['encodingId'] == 0)) {
                $ary['encoding'] = 'Mac Roman';
            } else {
                $ary['encoding'] = 'Unknown';
            }
            $this->properties['subTables'][] = new \ArrayObject($ary, \ArrayObject::ARRAY_AS_PROPS);
            $bytePos += 8;
        }

        // Parse each of the sub-table's data.
        foreach ($this->properties['subTables'] as $key => $subTable) {
            $bytePos = $font->tableInfo['cmap']->offset + $subTable->offset;
            $ary = unpack(
                'nformat/' .
                'nlength/' .
                'nlanguage', $font->read($bytePos, 6)
            );
            $this->properties['subTables'][$key]->format = $ary['format'];
            $this->properties['subTables'][$key]->length = $ary['length'];
            $this->properties['subTables'][$key]->language = $ary['language'];
            $bytePos += 6;
            $this->properties['subTables'][$key]->data = $font->read($bytePos, $ary['length'] - 6);
            switch ($this->properties['subTables'][$key]->format) {
                case 0:
                    $this->properties['subTables'][$key]->parsed = Cmap\ByteEncoding::parseData($this->properties['subTables'][$key]->data);
                    break;
                case 4:
                    $this->properties['subTables'][$key]->parsed = Cmap\SegmentToDelta::parseData($this->properties['subTables'][$key]->data);
                    break;
                case 6:
                    $this->properties['subTables'][$key]->parsed = Cmap\TrimmedTable::parseData($this->properties['subTables'][$key]->data);
                    break;
            }
        }
    }

}
