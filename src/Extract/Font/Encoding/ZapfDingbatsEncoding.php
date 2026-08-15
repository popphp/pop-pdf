<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Extract\Font\Encoding;

/**
 * Pdf extract ZapfDingbatsEncoding table class
 *
 * Minimal stub (space only) - dingbat/ornament glyphs are decorative, not
 * meaningful extracted text, and a full table is deferred.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class ZapfDingbatsEncoding
{

    /**
     * ZapfDingbatsEncoding byte-to-Unicode codepoint table
     */
    public const TABLE = [
        0x20 => 0x0020,
    ];

}
