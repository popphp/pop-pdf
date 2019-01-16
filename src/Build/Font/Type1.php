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
namespace Pop\Pdf\Build\Font;

/**
 * Type1 font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Type1 extends AbstractFont
{

    /**
     * Font properties
     * @var array
     */
    protected $properties = [
        'info'             => null,
        'bBox'             => null,
        'ascent'           => 0,
        'descent'          => 0,
        'numberOfGlyphs'   => 0,
        'glyphWidths'      => [],
        'missingWidth'     => 0,
        'numberOfHMetrics' => 0,
        'italicAngle'      => 0,
        'capHeight'        => 0,
        'stemH'            => 0,
        'stemV'            => 0,
        'unitsPerEm'       => 1000,
        'flags'            => null,
        'embeddable'       => true,
        'dict'             => null,
        'data'             => null,
        'hex'              => null,
        'encoding'         => null,
        'length1'          => null,
        'length2'          => null,
        'fontData'         => null,
        'pfbPath'          => null,
        'afmPath'          => null,
    ];

    /**
     * Constructor
     *
     * Instantiate a Type1 font file object based on a pre-existing font file on disk.
     *
     * @param  string $font
     */
    public function __construct($font)
    {
        parent::__construct($font);

        $dir = realpath($this->dir);

        if (strtolower($this->extension) == 'pfb') {
            $this->properties['pfbPath'] = $this->fullpath;
            $this->parsePfb($this->fullpath);
            if (file_exists($dir . DIRECTORY_SEPARATOR . $this->filename . '.afm')) {
                $this->properties['afmPath'] = $dir . DIRECTORY_SEPARATOR . $this->filename . '.afm';
            } else if (file_exists($dir . DIRECTORY_SEPARATOR . $this->filename . '.AFM')) {
                $this->properties['afmPath'] = $dir . DIRECTORY_SEPARATOR . $this->filename . '.AFM';
            }
            if (null !== $this->properties['afmPath']) {
                $this->parseAfm($this->properties['afmPath']);
            }
        } else if (strtolower($this->extension) == 'afm') {
            $this->properties['afmPath'] = $this->fullpath;
            $this->parseAfm($this->properties['afmPath']);
            if (file_exists($dir . DIRECTORY_SEPARATOR . $this->filename . '.pfb')) {
                $this->properties['pfbPath'] = $dir . DIRECTORY_SEPARATOR . $this->filename . '.pfb';
            } else if (file_exists($dir . DIRECTORY_SEPARATOR . $this->filename . '.PFB')) {
                $this->properties['pfbPath'] = $dir . DIRECTORY_SEPARATOR . $this->filename . '.PFB';
            }
            if (null !== $this->properties['pfbPath']) {
                $this->parsePfb($this->properties['pfbPath']);
            }
        }
    }

    /**
     * Method to parse the Type1 PFB file.
     *
     * @param  string $pfb
     * @return void
     */
    protected function parsePfb($pfb)
    {
        $data = file_get_contents($pfb);

        // Get lengths and data
        $f = fopen($pfb, 'rb');
        $a = unpack('Cmarker/Ctype/Vsize', fread($f,6));
        $this->properties['length1'] = $a['size'];
        $this->properties['fontData'] = fread($f, $this->properties['length1']);
        $a = unpack('Cmarker/Ctype/Vsize', fread($f,6));
        $this->properties['length2'] = $a['size'];
        $this->properties['fontData'] .= fread($f, $this->properties['length2']);

        $info = [];
        $this->properties['dict'] = substr($data, stripos($data, 'FontDirectory'));
        $this->properties['dict'] = substr($this->properties['dict'], 0, stripos($this->properties['dict'], 'currentdict end'));

        $this->properties['data'] = substr($data, (stripos($data, 'currentfile eexec') + 18));
        $this->properties['data'] = substr(
            $this->properties['data'], 0,
            (stripos($this->properties['data'], '0000000000000000000000000000000000000000000000000000000000000000') - 1)
        );

        $this->convertToHex();

        if (stripos($this->properties['dict'], '/FullName') !== false) {
            $name = substr($this->properties['dict'], (stripos($this->properties['dict'], '/FullName ') + 10));
            $name = trim(substr($name, 0, stripos($name, 'readonly def')));
            $info['fullName'] = $this->strip($name);
        }

        if (stripos($this->properties['dict'], '/FamilyName') !== false) {
            $family = substr($this->properties['dict'], (stripos($this->properties['dict'], '/FamilyName ') + 12));
            $family = trim(substr($family, 0, stripos($family, 'readonly def')));
            $info['fontFamily'] = $this->strip($family);
        }

        if (stripos($this->properties['dict'], '/FontName') !== false) {
            $font = substr($this->properties['dict'], (stripos($this->properties['dict'], '/FontName ') + 10));
            $font = trim(substr($font, 0, stripos($font, 'def')));
            $info['postscriptName'] = $this->strip($font);
        }

        if (stripos($this->properties['dict'], '/version') !== false) {
            $version = substr($this->properties['dict'], (stripos($this->properties['dict'], '/version ') + 9));
            $version = trim(substr($version, 0, stripos($version, 'readonly def')));
            $info['version'] = $this->strip($version);
        }

        if (stripos($this->properties['dict'], '/UniqueId') !== false) {
            $matches = [];
            preg_match('/UniqueID\s\d/', $this->properties['dict'], $matches, PREG_OFFSET_CAPTURE);
            $id = substr($this->properties['dict'], ($matches[0][1] + 9));
            $id = trim(substr($id, 0, stripos($id, 'def')));
            $info['uniqueId'] = $this->strip($id);
        }

        if (stripos($this->properties['dict'], '/Notice') !== false) {
            $copyright = substr($this->properties['dict'], (stripos($this->properties['dict'], '/Notice ') + 8));
            $copyright = substr($copyright, 0, stripos($copyright, 'readonly def'));
            $copyright = str_replace('\\(', '(', $copyright);
            $copyright = trim(str_replace('\\)', ')', $copyright));
            $info['copyright'] = $this->strip($copyright);
        }

        $this->properties['info'] = new \ArrayObject($info, \ArrayObject::ARRAY_AS_PROPS);

        if (stripos($this->properties['dict'], '/FontBBox') !== false) {
            $bBox = substr($this->properties['dict'], (stripos($this->properties['dict'], '/FontBBox') + 9));
            $bBox = substr($bBox, 0, stripos($bBox, 'readonly def'));
            $bBox = trim($this->strip($bBox));
            $bBoxAry = explode(' ', $bBox);
            $this->properties['bBox'] = new \ArrayObject([
                'xMin' => str_replace('{', '', $bBoxAry[0]),
                'yMin' => $bBoxAry[1],
                'xMax' => $bBoxAry[2],
                'yMax' => str_replace('}', '', $bBoxAry[3])
            ], \ArrayObject::ARRAY_AS_PROPS);
        }

        if (stripos($this->properties['dict'], '/Ascent') !== false) {
            $ascent = substr($this->properties['dict'], (stripos($this->properties['dict'], '/ascent ') + 8));
            $this->properties['ascent'] = trim(substr($ascent, 0, stripos($ascent, 'def')));
        }

        if (stripos($this->properties['dict'], '/Descent') !== false) {
            $descent = substr($this->properties['dict'], (stripos($this->properties['dict'], '/descent ') + 9));
            $this->properties['descent'] = trim(substr($descent, 0, stripos($descent, 'def')));
        }

        if (stripos($this->properties['dict'], '/ItalicAngle') !== false) {
            $italic = substr($this->properties['dict'], (stripos($this->properties['dict'], '/ItalicAngle ') + 13));
            $this->properties['italicAngle'] = trim(substr($italic, 0, stripos($italic, 'def')));
            if ($this->properties['italicAngle'] != 0) {
                $this->properties['flags']->isItalic = true;
            }
        }

        if (stripos($this->properties['dict'], '/em') !== false) {
            $units = substr($this->properties['dict'], (stripos($this->properties['dict'], '/em ') + 4));
            $this->properties['unitsPerEm'] = trim(substr($units, 0, stripos($units, 'def')));
        }

        if (stripos($this->properties['dict'], '/isFixedPitch') !== false) {
            $fixed = substr($this->properties['dict'], (stripos($this->properties['dict'], '/isFixedPitch ') + 14));
            $fixed = trim(substr($fixed, 0, stripos($fixed, 'def')));
            $this->properties['flags']->isFixedPitch = ($fixed == 'true') ? true : false;
        }

        if (stripos($this->properties['dict'], '/ForceBold') !== false) {
            $force = substr($this->properties['dict'], (stripos($this->properties['dict'], '/ForceBold ') + 11));
            $force = trim(substr($force, 0, stripos($force, 'def')));
            $this->properties['flags']->isForceBold = ($force == 'true') ? true : false;
        }

        if (stripos($this->properties['dict'], '/Encoding') !== false) {
            $enc = substr($this->properties['dict'], (stripos($this->properties['dict'], '/Encoding ') + 10));
            $this->properties['encoding'] = trim(substr($enc, 0, stripos($enc, 'def')));
        }
    }

    /**
     * Method to parse the Type1 Adobe Font Metrics file
     *
     * @param  string $afm
     * @return void
     */
    protected function parseAfm($afm)
    {
        $data = file_get_contents($afm);

        if (stripos($data, 'FontBBox') !== false) {
            $bBox = substr($data, (stripos($data, 'FontBBox') + 8));
            $bBox = substr($bBox, 0, stripos($bBox, "\n"));
            $bBox = trim($bBox);
            $bBoxAry = explode(' ', $bBox);
            $this->properties['bBox'] = new \ArrayObject([
                'xMin' => $bBoxAry[0],
                'yMin' => $bBoxAry[1],
                'xMax' => $bBoxAry[2],
                'yMax' => $bBoxAry[3]
            ], \ArrayObject::ARRAY_AS_PROPS);
        }

        if (stripos($data, 'ItalicAngle') !== false) {
            $ital = substr($data, (stripos($data, 'ItalicAngle ') + 11));
            $this->properties['italicAngle'] = trim(substr($ital, 0, stripos($ital, "\n")));
            if ($this->properties['italicAngle'] != 0) {
                $this->properties['flags']->isItalic = true;
            }
        }

        if (stripos($data, 'IsFixedPitch') !== false) {
            $fixed = substr($data, (stripos($data, 'IsFixedPitch ') + 13));
            $fixed = strtolower(trim(substr($fixed, 0, stripos($fixed, "\n"))));
            if ($fixed == 'true') {
                $this->properties['flags']->isFixedPitch = true;
            }
        }

        if (stripos($data, 'CapHeight') !== false) {
            $cap = substr($data, (stripos($data, 'CapHeight ') + 10));
            $this->properties['capHeight'] = trim(substr($cap, 0, stripos($cap, "\n")));
        }

        if (stripos($data, 'Ascender') !== false) {
            $asc = substr($data, (stripos($data, 'Ascender ') + 9));
            $this->properties['ascent'] = trim(substr($asc, 0, stripos($asc, "\n")));
        }

        if (stripos($data, 'Descender') !== false) {
            $desc = substr($data, (stripos($data, 'Descender ') + 10));
            $this->properties['descent'] = trim(substr($desc, 0, stripos($desc, "\n")));
        }

        if (stripos($data, 'StartCharMetrics') !== false) {
            $num = substr($data, (stripos($data, 'StartCharMetrics ') + 17));
            $this->properties['numberOfGlyphs'] = trim(substr($num, 0, stripos($num, "\n")));
            $chars = substr($data, (stripos($data, 'StartCharMetrics ') + 17 + strlen($this->properties['numberOfGlyphs'])));
            $chars = trim(substr($chars, 0, stripos($chars, 'EndCharMetrics')));
            $glyphs = explode("\n", $chars);
            $widths = [];
            foreach ($glyphs as $glyph) {
                $w = substr($glyph, (stripos($glyph, 'WX ') + 3));
                $w = substr($w, 0, strpos($w, ' ;'));
                $widths[] = $w;
            }
            $this->properties['glyphWidths'] = $widths;
        }
    }

    /**
     * Method to convert the data string to hex.
     *
     * @return void
     */
    protected function convertToHex()
    {
        $ary = str_split($this->properties['data']);
        $length = count($ary);

        for ($i = 0; $i < $length; $i++) {
            $this->properties['hex'] .= bin2hex($ary[$i]);
        }
    }

    /**
     * Method to strip parentheses et al from a string.
     *
     * @param  string $str
     * @return string
     */
    protected function strip($str)
    {
        // Strip parentheses
        if (substr($str, 0, 1) == '(') {
            $str = substr($str, 1);
        }
        if (substr($str, -1) == ')') {
            $str = substr($str, 0, -1);
        }
        // Strip curly brackets
        if (substr($str, 0, 1) == '{') {
            $str = substr($str, 1);
        }
        if (substr($str, -1) == '}') {
            $str = substr($str, 0, -1);
        }
        // Strip leading slash
        if (substr($str, 0, 1) == '/') {
            $str = substr($str, 1);
        }

        return $str;
    }

}
