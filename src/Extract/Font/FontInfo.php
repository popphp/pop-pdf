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

use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Filter\Registry;
use Pop\Pdf\Extract\Value;

/**
 * Pdf extract font info class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
 */
class FontInfo
{

    /**
     * Constructor
     *
     * Instantiate a resolved font info value object.
     *
     * @param bool    $isType0           Whether this is a Type0/CID composite font
     * @param mixed   $encoding          Name, Differences dict, or parsed embedded CMap array
     * @param ?array  $toUnicodeCMap     Parsed /ToUnicode CMap, null if absent
     * @param mixed   $cidToGidMap       'Identity' marker string, or raw CIDToGIDMap stream bytes
     * @param ?string $embeddedFontBytes Decoded /FontFile2 bytes, null if not embedded
     */
    public function __construct(
        public readonly bool $isType0,
        public readonly mixed $encoding,
        public readonly ?array $toUnicodeCMap,
        public readonly mixed $cidToGidMap,
        public readonly ?string $embeddedFontBytes
    ) {
    }

    /**
     * Resolve a font dict (or reference to one) into a FontInfo value object
     *
     * @param  Document $doc
     * @param  mixed    $fontRef
     * @return ?self
     */
    public static function resolve(Document $doc, mixed $fontRef): ?self
    {
        $fontDict = ($fontRef instanceof Value\Reference) ? $doc->resolve($fontRef) : $fontRef;

        if (!is_array($fontDict)) {
            return null;
        }

        $subtype = $fontDict['Subtype'] ?? null;
        $isType0 = ($subtype instanceof Value\Name) && ($subtype->name === 'Type0');

        $toUnicodeCMap = self::parseCMapStream($doc, $doc->resolve($fontDict['ToUnicode'] ?? null));

        if ($isType0) {
            return self::resolveType0($doc, $fontDict, $toUnicodeCMap);
        }

        return self::resolveSimple($doc, $fontDict, $toUnicodeCMap);
    }

    /**
     * Resolve a Type0/CID composite font dict
     *
     * @param  Document $doc
     * @param  array    $fontDict
     * @param  ?array   $toUnicodeCMap
     * @return self
     */
    protected static function resolveType0(Document $doc, array $fontDict, ?array $toUnicodeCMap): self
    {
        $encoding = $fontDict['Encoding'] ?? null;
        if ($encoding instanceof Value\Reference) {
            $encoding = $doc->resolve($encoding);
        }
        if ($encoding instanceof Value\Stream) {
            $encoding = self::parseCMapStream($doc, $encoding);
        }

        $descendants = $doc->resolve($fontDict['DescendantFonts'] ?? null);
        $cidFontDict = null;
        if (is_array($descendants) && isset($descendants[0])) {
            $cidFontDict = $doc->resolve($descendants[0]);
        }

        $cidToGidMap       = 'Identity';
        $embeddedFontBytes = null;

        if (is_array($cidFontDict)) {
            $c2g = $doc->resolve($cidFontDict['CIDToGIDMap'] ?? null);
            if ($c2g instanceof Value\Stream) {
                $cidToGidMap = Registry::decode(
                    $c2g->raw, $c2g->dict['Filter'] ?? null, $c2g->dict['DecodeParms'] ?? null, $doc->getDecodeBudget()
                );
            }

            $embeddedFontBytes = self::extractFontFile2($doc, $doc->resolve($cidFontDict['FontDescriptor'] ?? null));
        }

        return new self(true, $encoding, $toUnicodeCMap, $cidToGidMap, $embeddedFontBytes);
    }

    /**
     * Resolve a simple (non-Type0) font dict
     *
     * @param  Document $doc
     * @param  array    $fontDict
     * @param  ?array   $toUnicodeCMap
     * @return self
     */
    protected static function resolveSimple(Document $doc, array $fontDict, ?array $toUnicodeCMap): self
    {
        $encoding = $fontDict['Encoding'] ?? null;
        if ($encoding instanceof Value\Reference) {
            $encoding = $doc->resolve($encoding);
        }

        $embeddedFontBytes = self::extractFontFile2($doc, $doc->resolve($fontDict['FontDescriptor'] ?? null));

        return new self(false, $encoding, $toUnicodeCMap, null, $embeddedFontBytes);
    }

    /**
     * Extract and decode a font descriptor's /FontFile2 embedded TrueType program
     *
     * @param  Document $doc
     * @param  mixed    $descriptor
     * @return ?string
     */
    protected static function extractFontFile2(Document $doc, mixed $descriptor): ?string
    {
        if (!is_array($descriptor)) {
            return null;
        }

        $fontFile2 = $doc->resolve($descriptor['FontFile2'] ?? null);

        if (!($fontFile2 instanceof Value\Stream)) {
            return null;
        }

        return Registry::decode(
            $fontFile2->raw, $fontFile2->dict['Filter'] ?? null, $fontFile2->dict['DecodeParms'] ?? null, $doc->getDecodeBudget()
        );
    }

    /**
     * Decode and parse a CMap stream (e.g. /ToUnicode or an embedded /Encoding CMap)
     *
     * @param  Document $doc
     * @param  mixed    $stream
     * @return ?array
     */
    protected static function parseCMapStream(Document $doc, mixed $stream): ?array
    {
        if (!($stream instanceof Value\Stream)) {
            return null;
        }

        $decoded = Registry::decode($stream->raw, $stream->dict['Filter'] ?? null, $stream->dict['DecodeParms'] ?? null, $doc->getDecodeBudget());

        return CMapParser::parse($decoded);
    }

}
