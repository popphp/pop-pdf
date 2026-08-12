<?php
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
namespace Pop\Pdf\Extract\Value;

/**
 * Pdf extract stream value object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Stream
{

    /**
     * Constructor
     *
     * Instantiate a stream value object.
     *
     * @param array  $dict The stream's dictionary
     * @param string $raw  The stream's raw (still-encoded) bytes
     */
    public function __construct(
        public readonly array $dict,
        public readonly string $raw
    ) {
    }

}
