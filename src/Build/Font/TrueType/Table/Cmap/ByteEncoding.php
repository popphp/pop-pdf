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
namespace Pop\Pdf\Build\Font\TrueType\Table\Cmap;

use Pop\Pdf\Build\Font;

use Pop\Utils\ArrayObject as Data;

/**
 * CMAP byte-encoding class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class ByteEncoding
{

    /**
     * Method to parse the Byte Encoding (Format 0) CMAP data
     *
     * @param  string $data
     * @return array
     */
    public static function parseData($data)
    {
        $ary = array();

        for ($i = 0; $i < strlen($data); $i++) {
            $ary[$i] = new Data(array(
                'hex'   => bin2hex($data[$i]),
                'ascii' => ord($data[$i]),
                'char'  => chr(ord($data[$i]))
            ));
        }

        return $ary;
    }

}