<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2017 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Font;

/**
 * TrueType font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2017 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class TrueType extends AbstractFont
{

    /**
     * Allowed properties
     * @var array
     */
    protected $allowed = [
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
     * @param  string $font
     */
    public function __construct($font)
    {
        parent::__construct($font);

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

        $this->allowed['ttfHeader'] = new \ArrayObject($ttfHeader, \ArrayObject::ARRAY_AS_PROPS);
        $this->allowed['ttfTable']  = new \ArrayObject($ttfTable, \ArrayObject::ARRAY_AS_PROPS);

        $nameByteOffset = 28;
        $tableByteOffset = 32;

        for ($i = 0; $i < $this->allowed['ttfHeader']['numberOfTables']; $i++) {
            $ttfTableName = $this->read($nameByteOffset, 4);
            $ttfTable = unpack(
                'Nchecksum/' .
                'Noffset/' .
                'Nlength', $this->read($tableByteOffset, 12)
            );

            $this->allowed['tableInfo'][trim($ttfTableName)] = new \ArrayObject($ttfTable, \ArrayObject::ARRAY_AS_PROPS);

            $nameByteOffset = $tableByteOffset + 12;
            $tableByteOffset = $nameByteOffset + 4;
        }

        $this->ttfHeader = $this->allowed['ttfHeader'];
        $this->ttfTable  = $this->allowed['ttfTable'];
        $this->tableInfo = $this->allowed['tableInfo'];
    }

    /**
     * Method to parse the TTF info of the TrueType font file from the name table.
     *
     * @return void
     */
    protected function parseName()
    {
        if (isset($this->allowed['tableInfo']['name'])) {
            $this->allowed['tables']['name'] = new TrueType\Table\Name($this);
            $this->allowed['info'] = $this->allowed['tables']['name'];
            if ((stripos($this->allowed['tables']['name']['fontFamily'], 'bold') !== false) ||
                (stripos($this->allowed['tables']['name']['fullName'], 'bold') !== false) ||
                (stripos($this->allowed['tables']['name']['postscriptName'], 'bold') !== false)) {
                $this->allowed['stemV'] = 120;
            } else {
                $this->allowed['stemV'] = 70;
            }

            $this->tables = $this->allowed['tables'];
            $this->stemV  = $this->allowed['stemV'];
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
        if (isset($this->allowed['tableInfo']['head'])) {
            $this->allowed['tables']['head'] = new TrueType\Table\Head($this);

            $this->allowed['unitsPerEm'] = $this->allowed['tables']['head']['unitsPerEm'];

            $this->allowed['tables']['head']['xMin'] = $this->toEmSpace($this->allowed['tables']['head']['xMin']);
            $this->allowed['tables']['head']['yMin'] = $this->toEmSpace($this->allowed['tables']['head']['yMin']);
            $this->allowed['tables']['head']['xMax'] = $this->toEmSpace($this->allowed['tables']['head']['xMax']);
            $this->allowed['tables']['head']['yMax'] = $this->toEmSpace($this->allowed['tables']['head']['yMax']);

            $this->allowed['bBox'] = new \ArrayObject([
                'xMin' => $this->allowed['tables']['head']['xMin'],
                'yMin' => $this->allowed['tables']['head']['yMin'],
                'xMax' => $this->allowed['tables']['head']['xMax'],
                'yMax' => $this->allowed['tables']['head']['yMax']
            ], \ArrayObject::ARRAY_AS_PROPS);

            $this->allowed['header'] = $this->allowed['tables']['head'];

            $this->tables     = $this->allowed['tables'];
            $this->header     = $this->allowed['header'];
            $this->unitsPerEm = $this->allowed['unitsPerEm'];
            $this->bBox       = $this->allowed['bBox'];
        }

        // hhea
        if (isset($this->allowed['tableInfo']['hhea'])) {
            $this->allowed['tables']['hhea'] = new TrueType\Table\Hhea($this);
            $this->allowed['ascent']           = $this->allowed['tables']['hhea']['ascent'];
            $this->allowed['descent']          = $this->allowed['tables']['hhea']['descent'];
            $this->allowed['capHeight']        = $this->allowed['ascent'] + $this->allowed['descent'];
            $this->allowed['numberOfHMetrics'] = $this->allowed['tables']['hhea']['numberOfHMetrics'];

            $this->tables           = $this->allowed['tables'];
            $this->ascent           = $this->allowed['ascent'];
            $this->descent          = $this->allowed['descent'];
            $this->capHeight        = $this->allowed['capHeight'];
            $this->numberOfHMetrics = $this->allowed['numberOfHMetrics'];
        }

        // maxp
        if (isset($this->allowed['tableInfo']['maxp'])) {
            $this->allowed['tables']['maxp'] = new TrueType\Table\Maxp($this);
            $this->allowed['numberOfGlyphs'] = $this->allowed['tables']['maxp']['numberOfGlyphs'];
            $this->tables         = $this->allowed['tables'];
            $this->numberOfGlyphs = $this->allowed['numberOfGlyphs'];
        }

        // post
        if (isset($this->allowed['tableInfo']['post'])) {
            $this->allowed['tables']['post'] = new TrueType\Table\Post($this);

            if ($this->allowed['tables']['post']['italicAngle'] != 0) {
                $this->allowed['flags']['isItalic'] = true;
                $this->allowed['italicAngle'] = $this->allowed['tables']['post']['italicAngle'];
            }

            if ($this->allowed['tables']['post']['fixed'] != 0) {
                $this->allowed['flags']['isFixedPitch'] = true;
            }

            $this->tables      = $this->allowed['tables'];
            $this->flags       = $this->allowed['flags'];
            $this->italicAngle = $this->allowed['italicAngle'];
        }

        // hmtx
        if (isset($this->allowed['tableInfo']['hmtx'])) {
            $this->allowed['tables']['hmtx'] = new TrueType\Table\Hmtx($this);
            $this->allowed['glyphWidths'] = $this->allowed['tables']['hmtx']['glyphWidths'];
            if (isset($this->allowed['glyphWidths'][0])) {
                $this->allowed['missingWidth'] = round((1000 / $this->allowed['unitsPerEm']) * $this->allowed['glyphWidths'][0]);
            }
            foreach ($this->allowed['glyphWidths'] as $key => $value) {
                $this->allowed['glyphWidths'][$key] = round((1000 / $this->allowed['unitsPerEm']) * $value);
            }

            $this->tables       = $this->allowed['tables'];
            $this->glyphWidths  = $this->allowed['glyphWidths'];
            $this->missingWidth = $this->allowed['missingWidth'];
        }

        // cmap
        if (isset($this->allowed['tableInfo']['cmap'])) {
            $this->allowed['tables']['cmap'] = new TrueType\Table\Cmap($this);
            $this->tables = $this->allowed['tables'];
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
        if (isset($this->allowed['tableInfo']['loca'])) {
            $this->allowed['tables']['loca'] = new TrueType\Table\Loca($this);
            $this->tables = $this->allowed['tables'];
        }

        // glyf
        if (isset($this->allowed['tableInfo']['glyf'])) {
            $this->allowed['tables']['glyf'] = new TrueType\Table\Glyf($this);
            $this->tables = $this->allowed['tables'];
        }

        // OS/2 (Optional in a TTF font file)
        if (isset($this->allowed['tableInfo']['OS/2'])) {
            $this->allowed['tables']['OS/2']         = new TrueType\Table\Os2($this);
            $this->allowed['flags']['isSerif']       = $this->allowed['tables']['OS/2']['flags']['isSerif'];
            $this->allowed['flags']['isScript']      = $this->allowed['tables']['OS/2']['flags']['isScript'];
            $this->allowed['flags']['isSymbolic']    = $this->allowed['tables']['OS/2']['flags']['isSymbolic'];
            $this->allowed['flags']['isNonSymbolic'] = $this->allowed['tables']['OS/2']['flags']['isNonSymbolic'];
            $this->allowed['embeddable']             = $this->allowed['tables']['OS/2']['embeddable'];

            $this->tables     = $this->allowed['tables'];
            $this->flags      = $this->allowed['flags'];
            $this->embeddable = $this->allowed['embeddable'];
        }
    }

}
