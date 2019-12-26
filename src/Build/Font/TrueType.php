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
namespace Pop\Pdf\Build\Font;

use Pop\Utils\ArrayObject as Data;

/**
 * TrueType font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class TrueType extends AbstractFont
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
        'rawGlyphWidths'   => [],
        'cmap'             => ['glyphIndexArray' => [], 'glyphNumbers' => []],
        'missingWidth'     => 0,
        'numberOfHMetrics' => 0,
        'italicAngle'      => 0,
        'capHeight'        => 0,
        'stemH'            => 0,
        'stemV'            => 0,
        'unitsPerEm'       => 1000,
        'flags'            => null,
        'embeddable'       => true,
        'header'           => null,
        'ttfHeader'        => null,
        'ttfTable'         => null,
        'tables'           => [],
        'tableInfo'        => []
    ];

    /**
     * Constructor
     *
     * Instantiate a TrueType font file object based on a pre-existing font file on disk.
     *
     * @param  string $fontFile
     * @param  string $fontStream
     */
    public function __construct($fontFile = null, $fontStream = null)
    {
        parent::__construct($fontFile, $fontStream);

        $this->parseTtfTable();
        $this->parseName();
        $this->parseCommonTables();
        $this->parseRequiredTables();
    }

    /**
     * Method to parse the TTF header and table of the TrueType font file.
     *
     * @return void
     */
    protected function parseTtfTable()
    {
        $ttfHeader = unpack(
            'nmajorVersion/' .
            'nminorVersion/' .
            'nnumberOfTables/' .
            'nsearchRange/' .
            'nentrySelector/' .
            'nrangeShift', $this->read(0, 12)
        );

        $tableName = $this->read(12, 4);

        $ttfTable = unpack(
            'Nchecksum/' .
            'Noffset/' .
            'Nlength', $this->read(16, 12)
        );

        $ttfTable['name'] = $tableName;

        $this->properties['ttfHeader'] = new Data($ttfHeader);
        $this->properties['ttfTable']  = new Data($ttfTable);

        $nameByteOffset  = 28;
        $tableByteOffset = 32;

        for ($i = 0; $i < $this->properties['ttfHeader']['numberOfTables']; $i++) {
            $ttfTableName = $this->read($nameByteOffset, 4);
            $ttfTable     = unpack(
                'Nchecksum/' .
                'Noffset/' .
                'Nlength', $this->read($tableByteOffset, 12)
            );

            $this->properties['tableInfo'][trim($ttfTableName)] = new Data($ttfTable);

            $nameByteOffset = $tableByteOffset + 12;
            $tableByteOffset = $nameByteOffset + 4;
        }
    }

    /**
     * Method to parse the TTF info of the TrueType font file from the name table.
     *
     * @return void
     */
    protected function parseName()
    {
        if (isset($this->properties['tableInfo']['name'])) {
            $this->properties['tables']['name'] = new TrueType\Table\Name($this);
            $this->properties['info'] = $this->properties['tables']['name'];
            if ((stripos($this->properties['tables']['name']['fontFamily'], 'bold') !== false) ||
                (stripos($this->properties['tables']['name']['fullName'], 'bold') !== false) ||
                (stripos($this->properties['tables']['name']['postscriptName'], 'bold') !== false)) {
                $this->properties['stemV'] = 120;
            } else {
                $this->properties['stemV'] = 70;
            }
        }
    }

    /**
     * Method to parse the common tables of the TrueType font file.
     *
     * @return void
     */
    protected function parseCommonTables()
    {
        // head
        if (isset($this->properties['tableInfo']['head'])) {
            $this->properties['tables']['head'] = new TrueType\Table\Head($this);

            $this->properties['unitsPerEm'] = $this->properties['tables']['head']['unitsPerEm'];

            $this->properties['tables']['head']['xMin'] = $this->toEmSpace($this->properties['tables']['head']['xMin']);
            $this->properties['tables']['head']['yMin'] = $this->toEmSpace($this->properties['tables']['head']['yMin']);
            $this->properties['tables']['head']['xMax'] = $this->toEmSpace($this->properties['tables']['head']['xMax']);
            $this->properties['tables']['head']['yMax'] = $this->toEmSpace($this->properties['tables']['head']['yMax']);

            $this->properties['bBox'] = new Data([
                'xMin' => $this->properties['tables']['head']['xMin'],
                'yMin' => $this->properties['tables']['head']['yMin'],
                'xMax' => $this->properties['tables']['head']['xMax'],
                'yMax' => $this->properties['tables']['head']['yMax']
            ]);

            $this->properties['header'] = $this->properties['tables']['head'];
        }

        // hhea
        if (isset($this->properties['tableInfo']['hhea'])) {
            $this->properties['tables']['hhea'] = new TrueType\Table\Hhea($this);
            $this->properties['ascent']           = $this->properties['tables']['hhea']['ascent'];
            $this->properties['descent']          = $this->properties['tables']['hhea']['descent'];
            $this->properties['capHeight']        = $this->properties['ascent'] + $this->properties['descent'];
            $this->properties['numberOfHMetrics'] = $this->properties['tables']['hhea']['numberOfHMetrics'];
        }

        // maxp
        if (isset($this->properties['tableInfo']['maxp'])) {
            $this->properties['tables']['maxp'] = new TrueType\Table\Maxp($this);
            $this->properties['numberOfGlyphs'] = $this->properties['tables']['maxp']['numberOfGlyphs'];
        }

        // post
        if (isset($this->properties['tableInfo']['post'])) {
            $this->properties['tables']['post'] = new TrueType\Table\Post($this);

            if ($this->properties['tables']['post']['italicAngle'] != 0) {
                $this->properties['flags']['isItalic'] = true;
                $this->properties['italicAngle'] = $this->properties['tables']['post']['italicAngle'];
            }

            if ($this->properties['tables']['post']['fixed'] != 0) {
                $this->properties['flags']['isFixedPitch'] = true;
            }
        }

        // hmtx
        if (isset($this->properties['tableInfo']['hmtx'])) {
            $this->properties['tables']['hmtx'] = new TrueType\Table\Hmtx($this);
            $this->properties['glyphWidths'] = $this->properties['tables']['hmtx']['glyphWidths'];
            if (isset($this->properties['glyphWidths'][0])) {
                $this->properties['missingWidth'] = round((1000 / $this->properties['unitsPerEm']) * $this->properties['glyphWidths'][0]);
            }

            foreach ($this->properties['glyphWidths'] as $key => $value) {
                $this->properties['rawGlyphWidths'][$key] = $value;
                $this->properties['glyphWidths'][$key]    = round((1000 / $this->properties['unitsPerEm']) * $value);
            }
        }

        // cmap
        if (isset($this->properties['tableInfo']['cmap'])) {
            $this->properties['tables']['cmap'] = new TrueType\Table\Cmap($this);
            if (isset($this->properties['tables']['cmap']['subTables']) && isset($this->properties['tables']['cmap']['subTables'][0]) &&
                isset($this->properties['tables']['cmap']['subTables'][0]['parsed'])) {
                if (isset($this->properties['tables']['cmap']['subTables'][0]['parsed']['glyphIndexArray'])) {
                    $this->properties['cmap']['glyphIndexArray'] = $this->properties['tables']['cmap']['subTables'][0]['parsed']['glyphIndexArray'];
                }
                if (isset($this->properties['tables']['cmap']['subTables'][0]['parsed']['glyphNumbers'])) {
                    $this->properties['cmap']['glyphNumbers'] = $this->properties['tables']['cmap']['subTables'][0]['parsed']['glyphNumbers'];
                }
            }
        }
    }

    /**
     * Method to parse the required tables of the TrueType font file.
     *
     * @return void
     */
    protected function parseRequiredTables()
    {
        // loca
        if (isset($this->properties['tableInfo']['loca'])) {
            $this->properties['tables']['loca'] = new TrueType\Table\Loca($this);
        }

        // glyf
        if (isset($this->properties['tableInfo']['glyf'])) {
            $this->properties['tables']['glyf'] = new TrueType\Table\Glyf($this);
        }

        // OS/2 (Optional in a TTF font file)
        if (isset($this->properties['tableInfo']['OS/2'])) {
            $this->properties['tables']['OS/2']         = new TrueType\Table\Os2($this);
            $this->properties['flags']['isSerif']       = $this->properties['tables']['OS/2']['flags']['isSerif'];
            $this->properties['flags']['isScript']      = $this->properties['tables']['OS/2']['flags']['isScript'];
            $this->properties['flags']['isSymbolic']    = $this->properties['tables']['OS/2']['flags']['isSymbolic'];
            $this->properties['flags']['isNonSymbolic'] = $this->properties['tables']['OS/2']['flags']['isNonSymbolic'];
            $this->properties['embeddable']             = $this->properties['tables']['OS/2']['embeddable'];
        }
    }

}
