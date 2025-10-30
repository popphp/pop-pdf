<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Font\TrueType;

use Pop\Pdf\Build\Font\Exception;

/**
 * OpenType font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.7
 */
class OpenType extends \Pop\Pdf\Build\Font\TrueType
{

    /**
     * Constructor
     *
     * Instantiate a OpenType font file object based on a pre-existing font file on disk.
     *
     * @param  ?string $fontFile
     * @param  ?string $fontStream
     * @throws Exception|\Pop\Utils\Exception
     */
    public function __construct(?string $fontFile = null, ?string $fontStream = null)
    {
        parent::__construct($fontFile, $fontStream);
    }

    /**
     * Method to parse the required tables of the OpenType font file.
     *
     * @return void
     */
    protected function parseRequiredTables(): void
    {
        // OS/2
        if (isset($this->tableInfo['OS/2'])) {
            $this->properties['tables']['OS/2'] = new Table\Os2($this);

            $this->properties['flags']['isSerif']       = $this->properties['tables']['OS/2']['flags']['isSerif'];
            $this->properties['flags']['isScript']      = $this->properties['tables']['OS/2']['flags']['isScript'];
            $this->properties['flags']['isSymbolic']    = $this->properties['tables']['OS/2']['flags']['isSymbolic'];
            $this->properties['flags']['isNonSymbolic'] = $this->properties['tables']['OS/2']['flags']['isNonSymbolic'];
            $this->properties['capHeight']              = $this->properties['tables']['OS/2']['capHeight'];
        }
    }

}
