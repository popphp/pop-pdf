<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Extract\Content;

/**
 * Pdf extract content text run value object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
 */
class TextRun
{

    /**
     * Separator constants
     */
    public const SEPARATOR_NONE    = 'none';
    public const SEPARATOR_SPACE   = 'space';
    public const SEPARATOR_TAB     = 'tab';
    public const SEPARATOR_NEWLINE = 'newline';

    /**
     * Constructor
     *
     * Instantiate a text run value object.
     *
     * @param ?string $fontResourceName The font resource name active for this run (e.g. "F1"), null for ActualText-substituted runs
     * @param ?string $rawBytes         Raw undecoded bytes shown by Tj/TJ, null for ActualText-substituted runs
     * @param ?string $decodedText      Pre-decoded ActualText replacement, null for raw-byte runs
     * @param float   $x                Text-space x position
     * @param float   $y                Text-space y position
     * @param string  $separator        One of the SEPARATOR_* constants describing the gap before this run
     * @param bool    $reversed         Whether this run is inside /ReversedChars marked content
     * @param mixed   $font             Resolved font dict for this run, null if unresolved
     * @param ?string $fontCacheKey     Precomputed font cache key, null to compute on demand
     */
    public function __construct(
        public readonly ?string $fontResourceName,
        public readonly ?string $rawBytes,
        public readonly ?string $decodedText,
        public readonly float $x,
        public readonly float $y,
        public readonly string $separator,
        public readonly bool $reversed = false,
        public readonly mixed $font = null,
        public readonly ?string $fontCacheKey = null
    ) {
    }

}
