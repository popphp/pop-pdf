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
 * OS/2 table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Os2 extends AbstractTable
{

    /**
     * Font table properties
     * @var array
     */
    protected $properties = [
        'capHeight'  => 0,
        'embeddable' => true,
        'flags'      => null
    ];

    /**
     * Constructor
     *
     * Instantiate a OTF 'OS/2' table object.
     *
     * @param  Font\TrueType $font
     */
    public function __construct(Font\TrueType $font)
    {
        $this->properties['flags'] = new Data([
            'isFixedPitch'  => false,
            'isSerif'       => false,
            'isSymbolic'    => false,
            'isScript'      => false,
            'isNonSymbolic' => false,
            'isItalic'      => false,
            'isAllCap'      => false,
            'isSmallCap'    => false,
            'isForceBold'   => false
        ]);

        $bytePos = $font->tableInfo['OS/2']->offset + 8;
        $ary     = unpack("nfsType", $font->read($bytePos, 2));
        $this->properties['embeddable'] = (($ary['fsType'] != 2) && (($ary['fsType'] & 0x200) == 0));

        $bytePos = $font->tableInfo['OS/2']->offset + 30;
        $ary     = unpack("nfamily_class", $font->read($bytePos, 2));
        $familyClass = ($font->shiftToSigned($ary['family_class']) >> 8);

        if ((($familyClass >= 1) && ($familyClass <= 5)) || ($familyClass == 7)) {
            $this->properties['flags']['isSerif'] = true;
        } else if ($familyClass == 8) {
            $this->properties['flags']['isSerif'] = false;
        }
        if ($familyClass == 10) {
            $this->properties['flags']['isScript'] = true;
        }
        if ($familyClass == 12) {
            $this->properties['flags']['isSymbolic']    = true;
            $this->properties['flags']['isNonSymbolic'] = false;
        } else {
            $this->properties['flags']['isSymbolic']    = false;
            $this->properties['flags']['isNonSymbolic'] = true;
        }

        // Unicode bit-sniffing may not be necessary.
        $bytePos += 3;
        $ary = unpack(
            'NunicodeRange1/' .
            'NunicodeRange2/' .
            'NunicodeRange3/' .
            'NunicodeRange4', $font->read($bytePos, 16)
        );

        if (($ary['unicodeRange1'] == 1) && ($ary['unicodeRange2'] == 0) && ($ary['unicodeRange3'] == 0) && ($ary['unicodeRange4'] == 0)) {
            $this->properties['flags']['isSymbolic']    = false;
            $this->properties['flags']['isNonSymbolic'] = true;
        }

        $bytePos = $font->tableInfo['OS/2']->offset + 76;
        $ary = unpack("ncap/", $font->read($bytePos, 2));
        $this->properties['flags']['capHeight'] = $font->toEmSpace($font->shiftToSigned($ary['cap']));
    }

}
