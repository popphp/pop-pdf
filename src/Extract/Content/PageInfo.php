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
namespace Pop\Pdf\Extract\Content;

/**
 * Pdf extract content page info value object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class PageInfo
{

    /**
     * Constructor
     *
     * Instantiate a page info value object.
     *
     * @param array  $page      The page's own (unresolved) dictionary
     * @param array  $resources The page's resolved /Resources dict (inherited if not set directly)
     * @param mixed  $mediaBox  The page's resolved /MediaBox (inherited if not set directly)
     * @param mixed  $rotate    The page's resolved /Rotate (inherited if not set directly)
     * @param string $content   The page's resolved/decoded content stream text
     */
    public function __construct(
        public readonly array $page,
        public readonly array $resources,
        public readonly mixed $mediaBox,
        public readonly mixed $rotate,
        public readonly string $content
    ) {
    }

}
