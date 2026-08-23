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
namespace Pop\Pdf\Extract\Font;

use Pop\Pdf\Extract\Content\TextRun;
use Pop\Pdf\Extract\Document;

/**
 * Pdf extract font resolver class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Resolver
{

    /**
     * Decode a text run's raw bytes into Unicode text, never throwing
     *
     * @param  TextRun  $run
     * @param  Document $doc
     * @return string
     */
    public static function decodeRun(TextRun $run, Document $doc): string
    {
        if ($run->decodedText !== null) {
            return $run->decodedText;
        }

        if ($run->rawBytes === null) {
            return '';
        }

        if ($run->font === null) {
            return $run->rawBytes;
        }

        try {
            // Interpreter computes this once per Tf (font activation) and
            // carries it on the run - falling back to computing it here
            // only protects callers that construct a TextRun directly
            // without going through Interpreter (e.g. tests), since hashing
            // the full resolved font dict per RUN rather than per Tf can be
            // an O(runs x dict-size) cost on a page with many runs sharing
            // one font.
            $key  = $run->fontCacheKey ?? md5(serialize($run->font));
            $info = $doc->getOrResolveFontInfo($key, fn() => FontInfo::resolve($doc, $run->font));

            if ($info === null) {
                return $run->rawBytes;
            }

            return $info->isType0
                ? CidDecoder::decode($run->rawBytes, $info)
                : SimpleDecoder::decode($run->rawBytes, $info);
        } catch (\Throwable $e) {
            return $run->rawBytes;
        }
    }

}
