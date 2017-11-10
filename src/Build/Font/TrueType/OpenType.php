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
namespace Pop\Pdf\Build\Font\TrueType;

/**
 * OpenType font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2017 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class OpenType extends \Pop\Pdf\Build\Font\TrueType
{

    /**
     * Constructor
     *
     * Instantiate a OpenType font file object based on a pre-existing font file on disk.
     *
     * @param  string $font
     */
    public function __construct($font)
    {
        parent::__construct($font);
    }

    /**
     * Method to parse the required tables of the OpenType font file.
     *
     * @return void
     */
    protected function parseRequiredTables()
    {
        // OS/2
        if (isset($this->tableInfo['OS/2'])) {
            $this->allowed['tables']['OS/2'] = new Table\Os2($this);

            $this->allowed['flags']['isSerif']       = $this->allowed['tables']['OS/2']['flags']['isSerif'];
            $this->allowed['flags']['isScript']      = $this->allowed['tables']['OS/2']['flags']['isScript'];
            $this->allowed['flags']['isSymbolic']    = $this->allowed['tables']['OS/2']['flags']['isSymbolic'];
            $this->allowed['flags']['isNonSymbolic'] = $this->allowed['tables']['OS/2']['flags']['isNonSymbolic'];
            $this->allowed['capHeight']              = $this->allowed['tables']['OS/2']['capHeight'];

            $this->tables    = $this->allowed['tables'];
            $this->flags     = $this->allowed['flags'];
            $this->capHeight = $this->allowed['capHeight'];
        }
    }

}
