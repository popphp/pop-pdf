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
namespace Pop\Pdf\Build;

use Pop\Pdf\Document;
use Pop\Pdf\Document\Page\Text;
use Pop\Pdf\Document\Page\Field\Button;
use Pop\Pdf\Build\Security as PdfSecurity;

/**
 * Pdf compiler class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class Compiler extends AbstractCompiler
{

    /**
     * Set the document object
     *
     * @param  Document $document
     * @return Compiler
     */
    public function setDocument(Document $document): Compiler
    {
        $this->document = $document;

        foreach ($this->document->getPages() as $key => $page) {
            if (!in_array($page, $this->pages, true)) {
                $this->pages[$key] = $page;
            }
        }

        foreach ($this->document->getFonts() as $key => $font) {
            if (!in_array($font, $this->fonts, true)) {
                $this->fonts[$key] = $font;
            }
        }

        $this->compression = $this->document->isCompressed();

        if ($this->document->hasImportedObjects()) {
            foreach ($this->document->getImportObjects() as $i => $object) {
                if ($object instanceof PdfObject\RootObject) {
                    $this->setRoot($object);
                } else if ($object instanceof PdfObject\ParentObject) {
                    $this->setParent($object);
                } else if ($object instanceof PdfObject\InfoObject) {
                    $this->setInfo($object);
                } else {
                    $this->addObject($i, $object);
                }
            }
        }

        if ($this->root === null) {
            $this->setRoot(new PdfObject\RootObject());
        }
        if ($this->parent === null) {
            $this->setParent(new PdfObject\ParentObject());
        }
        if ($this->info === null) {
            $this->setInfo(new PdfObject\InfoObject());
        }

        $this->root->setVersion($this->document->getVersion());
        $this->info->setMetadata($this->document->getMetadata());

        return $this;
    }

    /**
     * Compile and finalize the PDF document
     *
     * @param  ?Document $document
     * @throws Exception
     * @return void
     */
    public function finalize(?Document $document = null): void
    {
        if ($document !== null) {
            $this->setDocument($document);
        }
        $this->prepareFonts();

        // Raw bytes of the file identifier - used both for the trailer's
        // /ID (hex-encoded further below) and, when the document is
        // encrypted, as key-derivation/checksum input for AES-128/revision 4
        // (revision 6 ignores it). Computed once so both uses agree on the
        // same value.
        $fileId = md5(uniqid((string)mt_rand(), true), true);

        // Computed here, before the page/annotation/field preparation passes
        // below, rather than immediately before serialization where this
        // block used to live - prepareAnnotations(), prepareFields(), and
        // font preparation each need $fileKey/$algorithm already resolved
        // so they can encrypt each string-bearing object's literal content
        // on the way in, instead of after the fact.
        $fileKey     = null;
        $encryptDict = null;
        $algorithm   = null;

        if ($document->hasSecurity()) {
            $security  = $document->getSecurity();
            $algorithm = $security->getAlgorithm();

            if (($algorithm !== Document\Security::AES_128) && ($algorithm !== Document\Security::AES_256)) {
                throw new PdfSecurity\Exception(
                    "Error: Invalid encryption algorithm '{$algorithm}'. Expected '" .
                    Document\Security::AES_128 . "' or '" . Document\Security::AES_256 . "'."
                );
            }

            $built = ($algorithm === Document\Security::AES_128)
                ? PdfSecurity\StandardSecurityHandler::buildRevision4($security, $fileId)
                : PdfSecurity\StandardSecurityHandler::buildRevision6($security, $fileId);

            $fileKey     = $built['fileKey'];
            $encryptDict = $built['dict'];
        }

        $pageObjects = [];

        foreach ($this->pages as $page) {
            if ($page->hasImportedPageObject()) {
                $pageObject = $page->getImportedPageObject();
                $pageObject->setCurrentContentIndex(null);
                $this->addObject($pageObject->getIndex(), $pageObject);
            } else {
                $page->setIndex($this->lastIndex() + 1);
                $pageObject = new PdfObject\PageObject($page->getWidth(), $page->getHeight(), $page->getIndex());
                $pageObject->setParentIndex($this->parent->getIndex());
                $this->addObject($pageObject->getIndex(), $pageObject);
                $this->parent->addKid($pageObject->getIndex());
            }

            foreach ($this->fontReferences as $fontReference) {
                $pageObject->addFontReference($fontReference);
            }

            // Prepare image objects
            if ($page->hasImages()) {
                $this->prepareImages($page->getImages(), $pageObject);
            }
            // Prepare path objects
            if ($page->hasPaths()) {
                $this->preparePaths($page->getPaths(), $pageObject);
            }
            // Prepare text objects
            if ($page->hasText()) {
                $this->prepareText($page->getText(), $pageObject);
            }
            // Prepare text objects
            if ($page->hasTextStreams()) {
                $this->prepareTextStreams($page->getTextStreams(), $pageObject);
            }
            // Prepare field objects
            if ($page->hasFields()) {
                $this->prepareFields($page->getFields(), $pageObject, $fileKey, $algorithm);
            }

            $pageObjects[$pageObject->getIndex()] = $pageObject;
        }

        // A merge's own source subtrees are deferred rather than included
        // directly in the page tree's kids up front, so that any pages the
        // target document already had (just processed above) land before
        // them instead of after - done before annotations are prepared,
        // since those resolve internal-link targets against the final kids.
        if ($this->parent->hasDeferredKids()) {
            foreach ($this->parent->getDeferredKids() as $kid) {
                $this->parent->addKid($kid);
            }
        }

        // Prepare annotation objects, after the pages have been set
        foreach ($this->pages as $page) {
            if ($page->hasAnnotations()) {
                $this->prepareAnnotations($page->getAnnotations(), $pageObjects[$page->getIndex()], $fileKey, $algorithm);
            }
        }

        // If the document has forms
        if ($document->hasForms()) {
            $this->prepareForms();
        }

        // Compute each object's byte offset keyed by its real object number
        // rather than assuming objects are inserted in dense, ascending,
        // gapless order - imported/merged documents commonly have gaps
        // (excluded source Root/Info numbers) and PHP array insertion order
        // need not match ascending numeric order. The classical xref table
        // requires row N to correspond exactly to object number N, so the
        // table is built from this offset map afterward, not inline during
        // emission.
        $offsets = [];

        // Intial Length is the length of the version string
        $this->byteLength = 9;
        $offsets[$this->root->getIndex()] = $this->byteLength;

        // New Length is the distance to the second object
        $rootString        = (string)$this->root;
        $this->byteLength  = $this->calculateByteLength($rootString);

        $this->output .= $rootString;

        // Per-object compression pass, run to completion before anything is
        // encrypted - encryption wraps whatever bytes the filter chain
        // actually produced, so it must never run before compression.
        foreach ($this->objects as $object) {
            if ($object->getIndex() != $this->root->getIndex()) {
                if (($object instanceof PdfObject\StreamObject) && ($this->compression) && (!$object->isPalette()) &&
                    (!$object->isEncoded() && !$object->isImported() && (stripos((string)$object->getDefinition(), '/length') === false))) {
                    $object->encode();
                }
            }
        }

        // Encryption pass - runs only when the document has security
        // configured, after compression, and before serialization, so every
        // stream's on-disk bytes (already run through whatever /Filter chain
        // applies) are what gets encrypted.
        if ($fileKey !== null) {
            foreach ($this->objects as $object) {
                if (($object->getIndex() == $this->root->getIndex()) || (!($object instanceof PdfObject\StreamObject))) {
                    continue;
                }
                $stream = $object->getStream();
                if (($stream !== null) && ($stream !== '')) {
                    // Objects built via StreamObject::parse() (images,
                    // embedded font files, palettes, and anything else with
                    // a pre-existing declared /Length) retain, as part of
                    // their stored stream, the leading end-of-line marker
                    // captured from the original "stream\n<data>" text
                    // alongside the real payload - a structural artifact of
                    // round-tripping, not real content. The object itself
                    // tracks exactly how many such bytes it is (regardless
                    // of whether /Length is a literal integer, an indirect
                    // reference, or absent - inferring it from the /Length
                    // text here would mis-measure indirect references, whose
                    // digits are an unrelated object number, not a byte
                    // count). Only the bytes after that leading padding are
                    // genuine payload; encrypting the padding too would
                    // return it, scrambled, as part of what a reader
                    // decrypts and then feeds to DCTDecode/FlateDecode/etc.
                    // as if it were real data. Ordinary content-stream
                    // objects (built via direct appendStream() calls, never
                    // parse()) report 0 here, so they fall through
                    // unchanged - their whole $stream is genuine content.
                    $leadingPadding = $object->getLeadingEolLength();
                    $payload        = (($leadingPadding > 0) && ($leadingPadding < strlen($stream)))
                        ? substr($stream, $leadingPadding) : $stream;

                    $encrypted = ($algorithm === Document\Security::AES_128)
                        ? PdfSecurity\ObjectCipher::encryptAes128($fileKey, $object->getIndex(), 0, $payload)
                        : PdfSecurity\ObjectCipher::encryptAes256($fileKey, $payload);
                    $object->setStream($encrypted);

                    // StreamObject::__toString() deliberately leaves /Length
                    // untouched for every object type here (Image and
                    // Length1/embedded-font-file objects specifically),
                    // since those declare it explicitly rather than having
                    // it recomputed from the stream. Encryption always
                    // changes the on-disk byte count (16-byte IV + PKCS#7
                    // padding), so the declared /Length needs correcting
                    // here explicitly - otherwise a reader is handed a
                    // stale, too-short length and a non-block-aligned
                    // ciphertext buffer. This must handle an INDIRECT
                    // /Length too (e.g. "6 0 R", common in imported/merged
                    // PDFs, since Build\Parser/Build\Merger leave a source's
                    // declared /Length untouched): replacing just the
                    // leading digit(s) of "N G R" the same way a literal
                    // integer is replaced would emit invalid syntax (an
                    // indirect reference needs all three of object number,
                    // generation, and "R"), so the WHOLE "N G R" span is
                    // matched and replaced with a fresh literal instead -
                    // the same pattern StreamObject::__toString() already
                    // uses for its own (literal-length-only) dynamic
                    // /Length recompute.
                    $definition = (string)$object->getDefinition();
                    if (preg_match('/\/Length\s+\d+(?:\s+\d+\s+R)?/', $definition) === 1) {
                        $object->setDefinition(
                            preg_replace('/\/Length\s+\d+(?:\s+\d+\s+R)?/', '/Length ' . strlen($encrypted), $definition, 1)
                            ?? $definition
                        );
                    }
                }
            }

            // /Info dictionary strings (title/author/subject/creator/
            // producer/dates) are encrypted here to match /StrF /StdCF -
            // see buildEncryptDictBody()'s docblock for why every literal
            // string must actually be encrypted once that's declared.
            // Annotation URLs, form-field strings, and embedded-font
            // /CIDSystemInfo strings are handled in their own dedicated
            // passes (prepareAnnotations(), prepareFields(), and
            // encryptEmbeddedFontStrings(), defined above in this file).
            $this->info->encryptWith($this->stringEncryptor($fileKey, $algorithm, $this->info->getIndex()));

            $this->encryptEmbeddedFontStrings($fileKey, $algorithm);
        }

        // Loop through the rest of the objects, calculate their size and length
        // for the xref table and add their data to the output.
        foreach ($this->objects as $object) {
            if ($object->getIndex() != $this->root->getIndex()) {
                $objectString  = (string)$object;

                // Encrypted stream content is opaque binary with no reason to
                // start with the end-of-line marker ISO 32000-1 7.3.8.1
                // requires immediately after the "stream" keyword - and
                // unlike StreamObject::encode()'s own leading "\n" for
                // FlateDecode's binary output, that EOL must NOT be part of
                // what AES-CBC decrypts (an extra byte there desyncs the
                // whole cipher, corrupting every block). So it is spliced in
                // here, into the already-rendered string, after /Length has
                // been computed from the untouched ciphertext - mirroring
                // how the template's trailing "\nendstream\n" is likewise
                // never counted in /Length.
                if (($fileKey !== null) && ($object instanceof PdfObject\StreamObject)) {
                    $streamContent = $object->getStream();
                    if (($streamContent !== null) && ($streamContent !== '')) {
                        $objectString = str_replace("stream" . $streamContent, "stream\n" . $streamContent, $objectString);
                    }
                }

                $offsets[$object->getIndex()] = $this->byteLength;
                $this->output     .= $objectString;
                $this->byteLength += $this->calculateByteLength($objectString);
            }
        }

        // Build and append the /Encrypt dictionary object, if the document
        // is encrypted, before the xref table is built below - it needs an
        // xref row of its own like any other object, so it must land in
        // $offsets (and bump the object count) ahead of that computation.
        $encryptIndex = null;

        if ($encryptDict !== null) {
            // Built as a raw object string rather than via PdfObject\StreamObject:
            // that class's __toString() unconditionally rewrites the first
            // "/Length N" it finds in the definition into the stream's own
            // byte length (there being no stream here, that clobbers the
            // dictionary's genuine /Length - the encryption key size in bits -
            // down to 0, which some readers reject outright for V4/AESV2).
            $encryptIndex  = $this->lastIndex() + 1;
            $encryptString = "{$encryptIndex} 0 obj\n" . self::buildEncryptDictBody($algorithm, $encryptDict) .
                "\nendobj\n\n";

            $offsets[$encryptIndex] = $this->byteLength;
            $this->output          .= $encryptString;
            $this->byteLength      += $this->calculateByteLength($encryptString);
        }

        $maxObjNum = max(array_keys($offsets));
        $numObjs   = $maxObjNum + 1;

        $this->trailer = "xref\n0 {$numObjs}\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObjNum; $i++) {
            $this->trailer .= isset($offsets[$i])
                ? $this->formatByteLength($offsets[$i]) . " 00000 n \n"
                : "0000000000 65535 f \n";
        }

        // Finalize the trailer.
        $idHex      = bin2hex($fileId);
        $encryptRef = ($encryptIndex !== null) ? "/Encrypt {$encryptIndex} 0 R" : '';

        $this->trailer .= "trailer\n<</Size {$numObjs}/Root " . $this->root->getIndex() . " 0 R/Info " .
            $this->info->getIndex() . " 0 R/ID[<{$idHex}><{$idHex}>]{$encryptRef}>>\nstartxref\n" . ($this->byteLength) . "\n%%EOF";

        // Append the trailer to the final output.
        $this->output .= $this->trailer;
    }

    /**
     * Build the /Encrypt dictionary body text from either revision's field
     * array produced by StandardSecurityHandler::buildRevision4()/buildRevision6().
     *
     * /StmF names the crypt filter for STREAMS and /StrF the one for literal
     * STRINGS - both /StdCF here, since every literal string this library
     * emits (Info metadata, annotation URLs, form-field strings, an embedded
     * font's /CIDSystemInfo) is actually encrypted to match. Declaring
     * /StdCF for strings while leaving any of them plaintext causes a
     * conforming reader to "decrypt" that plaintext anyway, corrupting it -
     * this dictionary must never be changed to /StrF /Identity (or have
     * /StrF omitted, which is spec-equivalent to /Identity) without also
     * removing every encryptWith() call site in prepareAnnotations(),
     * prepareFields(), encryptEmbeddedFontStrings(), and the /Info
     * encryption above, or real-world readers (confirmed: poppler-based
     * Linux viewers, Chrome) will misdetect the cipher entirely and fail to
     * open the document at all.
     *
     * @param  string $algorithm
     * @param  array  $dict
     * @return string
     */
    private static function buildEncryptDictBody(string $algorithm, array $dict): string
    {
        $hex = fn (string $s): string => '<' . bin2hex($s) . '>';

        if ($algorithm === Document\Security::AES_128) {
            return '<< /Filter /Standard /V 4 /R 4 /Length 128 ' .
                '/CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >> /StmF /StdCF /StrF /StdCF ' .
                "/O {$hex($dict['O'])} /U {$hex($dict['U'])} /P {$dict['P']} >>";
        }

        return '<< /Filter /Standard /V 5 /R 6 /Length 256 ' .
            '/CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >> /StmF /StdCF /StrF /StdCF ' .
            "/O {$hex($dict['O'])} /U {$hex($dict['U'])} /OE {$hex($dict['OE'])} /UE {$hex($dict['UE'])} " .
            "/P {$dict['P']} /Perms {$hex($dict['Perms'])} >>";
    }

    /**
     * Build a per-object string-encryptor closure for the current
     * document's encryption settings, or null if the document isn't
     * encrypted. Shared by every literal-string encryption call site
     * (annotations, form fields, embedded fonts) - each needs its own
     * closure bound to its own object index, since AES-128's per-object
     * key derivation depends on it.
     *
     * @param  ?string $fileKey
     * @param  ?string $algorithm
     * @param  int     $objectIndex
     * @return ?callable
     */
    private function stringEncryptor(?string $fileKey, ?string $algorithm, int $objectIndex): ?callable
    {
        if ($fileKey === null) {
            return null;
        }

        return function (string $data) use ($algorithm, $fileKey, $objectIndex): string {
            return ($algorithm === Document\Security::AES_128)
                ? PdfSecurity\ObjectCipher::encryptAes128($fileKey, $objectIndex, 0, $data)
                : PdfSecurity\ObjectCipher::encryptAes256($fileKey, $data);
        };
    }

    /**
     * Build the on/off appearance-stream XObjects for a checkbox or radio
     * widget and return the pieces Button::getStream() needs to reference
     * them - does not set 'checked', callers fill that in themselves since
     * a radio group's checked state depends on its sibling widgets too.
     *
     * @param  Button $field
     * @param  float  $width
     * @param  float  $height
     * @return array
     */
    private function createCheckableAppearance(Button $field, float $width, float $height): array
    {
        $exportName = $this->sanitizeExportName($field->getValue() ?? 'Yes');
        $onContent  = ($field->isRadio())
            ? $this->radioDotAppearanceStream($width, $height)
            : $this->checkMarkAppearanceStream($width, $height);

        $onIndex = $this->lastIndex() + 1;
        $this->addObject($onIndex, $this->buildAppearanceXObject($onIndex, $width, $height, $onContent));

        $offIndex = $this->lastIndex() + 1;
        $this->addObject($offIndex, $this->buildAppearanceXObject($offIndex, $width, $height, ''));

        return [
            'onName' => $exportName,
            'onRef'  => "{$onIndex} 0 R",
            'offRef' => "{$offIndex} 0 R",
        ];
    }

    /**
     * Sanitize an arbitrary HTML checkbox/radio value into a valid bare PDF
     * name token (letters, digits, underscore only)
     *
     * @param  string $value
     * @return string
     */
    private function sanitizeExportName(string $value): string
    {
        $sanitized = (string) preg_replace('/[^A-Za-z0-9_]/', '_', $value);
        return ($sanitized === '') ? 'Yes' : $sanitized;
    }

    /**
     * Content stream for a checked checkbox's "on" appearance: a simple
     * filled square inset within the widget's own box
     *
     * @param  float $width
     * @param  float $height
     * @return string
     */
    private function checkMarkAppearanceStream(float $width, float $height): string
    {
        $inset = min($width, $height) * 0.25;
        $w     = $width - (2 * $inset);
        $h     = $height - (2 * $inset);

        return sprintf("0 g\n%.2F %.2F %.2F %.2F re\nf\n", $inset, $inset, $w, $h);
    }

    /**
     * Content stream for a selected radio button's "on" appearance: a
     * filled circle (4-Bezier approximation, kappa = 0.5523) inset within
     * the widget's own box
     *
     * @param  float $width
     * @param  float $height
     * @return string
     */
    private function radioDotAppearanceStream(float $width, float $height): string
    {
        $cx = $width / 2;
        $cy = $height / 2;
        $r  = min($width, $height) * 0.3;
        $k  = $r * 0.5523;

        $stream  = sprintf("0 g\n%.2F %.2F m\n", $cx + $r, $cy);
        $stream .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", $cx + $r, $cy + $k, $cx + $k, $cy + $r, $cx, $cy + $r);
        $stream .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", $cx - $k, $cy + $r, $cx - $r, $cy + $k, $cx - $r, $cy);
        $stream .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", $cx - $r, $cy - $k, $cx - $k, $cy - $r, $cx, $cy - $r);
        $stream .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", $cx + $k, $cy - $r, $cx + $r, $cy - $k, $cx + $r, $cy);
        $stream .= "f\n";

        return $stream;
    }

    /**
     * Wrap a content stream fragment into a standalone Form XObject PDF object
     *
     * @param  int    $i
     * @param  float  $width
     * @param  float  $height
     * @param  string $content
     * @return PdfObject\StreamObject
     */
    private function buildAppearanceXObject(int $i, float $width, float $height, string $content): PdfObject\StreamObject
    {
        $length = strlen($content);
        $stream = "{$i} 0 obj\n<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 {$width} {$height}] /Length {$length} >>\nstream\n{$content}\nendstream\nendobj\n\n";
        return PdfObject\StreamObject::parse($stream);
    }

    /**
     * Encrypt an embedded CID font's /CIDSystemInfo strings (the CID font
     * dictionary's /Registry /Ordering pair) to match /StrF /StdCF.
     *
     * Build\Font\Parser builds these objects inside Document::embedFont(),
     * long before this method's caller (finalize()) knows encryption is even
     * configured - unlike annotations/fields, there is no live callback-hook
     * opportunity here. The value is always one of a small set of fixed
     * constants ("Adobe"/"Identity" for the CID font dictionary), so a
     * targeted find-and-replace over the already-built object definition
     * text - using that object's own index for per-object key derivation -
     * is sufficient and avoids retrofitting Font\Parser's already-eager
     * construction path.
     *
     * The ToUnicode CMap stream's own copy of /CIDSystemInfo
     * ("Adobe"/"UCS") is NOT handled here: StreamObject::parse() splits that
     * object into a bare "<</Length N>>" definition and a separate $stream
     * holding the actual CMap program text (including that /CIDSystemInfo),
     * so it never appears in getDefinition() here - it is already correctly
     * encrypted as opaque stream bytes by the per-object stream-encryption
     * pass above, and decrypts back to valid plaintext on read.
     *
     * @param  ?string $fileKey
     * @param  ?string $algorithm
     * @return void
     */
    private function encryptEmbeddedFontStrings(?string $fileKey, ?string $algorithm): void
    {
        if ($fileKey === null) {
            return;
        }

        $pattern = '/\/CIDSystemInfo\s*<<\s*\/Registry\s*\(([^)]*)\)\s*\/Ordering\s*\(([^)]*)\)\s*\/Supplement\s+(\d+)\s*>>/';

        foreach ($this->objects as $object) {
            if (!($object instanceof PdfObject\StreamObject)) {
                continue;
            }

            $definition = (string)$object->getDefinition();
            if (preg_match($pattern, $definition) !== 1) {
                continue;
            }

            $encryptor = $this->stringEncryptor($fileKey, $algorithm, $object->getIndex());

            // preg_replace_callback(), not preg_replace(), is required here:
            // the encrypted+escaped values below are essentially random
            // ciphertext bytes, which can coincidentally contain a literal
            // "$1"/"$2"-looking (or "\1"/"\2") sequence. preg_replace()
            // treats its $replacement argument as a backreference template
            // and would silently substitute (or blank out) such a sequence,
            // corrupting the ciphertext. A callback's return value is used
            // verbatim, with no backreference interpretation.
            $object->setDefinition(preg_replace_callback(
                $pattern,
                function (array $matches) use ($encryptor): string {
                    $registry = Text::escape($encryptor($matches[1]));
                    $ordering = Text::escape($encryptor($matches[2]));
                    return "/CIDSystemInfo <</Registry ({$registry}) /Ordering ({$ordering}) /Supplement {$matches[3]}>>";
                },
                $definition,
                1
            ) ?? $definition);
        }
    }

    /**
     * Prepare the font objects
     *
     * @throws Exception|Font\Exception
     * @return void
     */
    public function prepareFonts(): void
    {
        foreach ($this->fonts as $font) {
            if ($font instanceof \Pop\Pdf\Document\Font) {
                $f = count($this->fontReferences) + 1;
                $i = $this->lastIndex() + 1;

                if ($font->isStandard()) {
                    $this->fontReferences[$font->getName()] = '/MF' . $f . ' ' . $i . ' 0 R';
                    $this->addObject($i, PdfObject\StreamObject::parse(
                        "{$i} 0 obj\n<<\n    /Type /Font\n    /Subtype /Type1\n    /Name /MF{$f}\n    /BaseFont /" .
                        $font->getName() . "\n    /Encoding /WinAnsiEncoding\n>>\nendobj\n\n"
                    ));
                } else {
                    $parser = $font->parser()
                        ->setCompression($this->compression)
                        ->setFontIndex($f)
                        ->setFontObjectIndex($i);

                    if ($font->isCid()) {
                        $parser->setCidFontObjectIndex($i + 1)
                            ->setFontDescIndex($i + 2)
                            ->setFontFileIndex($i + 3)
                            ->setToUnicodeIndex($i + 4);
                    } else {
                        $parser->setFontDescIndex($i + 1)
                            ->setFontFileIndex($i + 2);
                    }

                    $parser->parse();

                    $this->fontReferences[$parser->getFontName()] = $parser->getFontReference();
                    foreach ($parser->getObjects() as $fontObject) {
                        $this->addObject($fontObject->getIndex(), $fontObject);
                    }
                }
            } else if (is_array($font)) {
                $this->fontReferences[$font['name']] = $font['ref'];
            }
        }
    }

    /**
     * Prepare the image objects
     *
     * @param  array $images
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    protected function prepareImages(array $images, PdfObject\PageObject $pageObject): void
    {
        $imgs = [];

        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->addObject($contentObject->getIndex(), $contentObject);
        $pageObject->addContentIndex($contentObject->getIndex());

        // Page::addImage() always appends to $images with a fresh
        // auto-incrementing key, so within a single call every $key here is
        // unique - $imgs (built up only inside this same loop) can never
        // already hold one.
        foreach ($images as $key => $image) {
            $coordinates = $this->getCoordinates($image['x'], $image['y'], $pageObject);
            $i = $this->lastIndex() + 1;
            if ($image['image']->isStream()) {
                $imageParser = Image\Parser::createImageFromStream(
                    $image['image']->getStream(), (int)round($coordinates['x']), (int)round($coordinates['y']),
                    $image['image']->getResizeDimensions(), $image['image']->isPreserveResolution()
                );
            } else {
                $imageParser = Image\Parser::createImageFromFile(
                    $image['image']->getImage(), (int)round($coordinates['x']), (int)round($coordinates['y']),
                    $image['image']->getResizeDimensions(), $image['image']->isPreserveResolution()
                );
            }

            $imageParser->setIndex($i);
            $contentObject->appendStream($imageParser->getStream());
            $pageObject->addXObjectReference($imageParser->getXObject());
            foreach ($imageParser->getObjects() as $oi => $imageObject) {
                $this->addObject($oi, $imageObject);
            }
            $imgs[$key] = $imageParser;
        }
    }

    /**
     * Prepare the path objects
     *
     * @param  array $paths
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    protected function preparePaths(array $paths, PdfObject\PageObject $pageObject): void
    {
        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->addObject($contentObject->getIndex(), $contentObject);
        $pageObject->addContentIndex($contentObject->getIndex());

        foreach ($paths as $path) {
            $stream  = null;
            $streams = $path->getStreams();
            foreach ($streams as $str) {
                $s = $str['stream'];
                if (isset($str['points'])) {
                    foreach ($str['points'] as $points) {
                        $keys = array_keys($points);
                        $coordinates = $this->getCoordinates($points[$keys[0]], $points[$keys[1]], $pageObject);
                        $s = str_replace(
                            ['[{' . $keys[0] . '}]', '[{' . $keys[1] . '}]'], [$coordinates['x'], $coordinates['y']], $s
                        );
                    }
                }
                $stream .= $s;
            }

            $contentObject->appendStream($stream);
        }
    }

    /**
     * Prepare the text objects
     *
     * @param  array $text
     * @param  PdfObject\PageObject $pageObject
     * @throws Exception
     * @return void
     */
    protected function prepareText(array $text, PdfObject\PageObject $pageObject): void
    {
        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->addObject($contentObject->getIndex(), $contentObject);
        $pageObject->addContentIndex($contentObject->getIndex());

        foreach ($text as $txt) {
            $styleColor = null;

            if ($this->document->hasStyle($txt['font'])) {
                $style = $this->document->getStyle($txt['font']);
                if ($style->hasSize()) {
                    $txt['text']->setSize($style->getSize());
                }
                if ($style->hasFont()) {
                    $txt['font'] = $style->getFont();
                }
                if ($style->hasColor()) {
                    $styleColor = $style->getColor();
                    $txt['text']->setFillColor($styleColor);
                }
            }
            if (!isset($this->fontReferences[$txt['font']])) {
                throw new Exception('Error: The font \'' . $txt['font'] . '\' has not been added to the document.');
            }

            $fontObject = $this->fonts[$txt['font']] ?? null;
            if ($fontObject instanceof \Pop\Pdf\Document\Font) {
                $txt['text']->setFont($fontObject);
            }

            $coordinates = $this->getCoordinates($txt['x'], $txt['y'], $pageObject);
            $itemStream  = '';

            // Auto-wrap text by character length
            if ($txt['text']->hasCharWrap()) {
                $font        = $this->fontReferences[$txt['font']];
                $itemStream .= $txt['text']->startStream($font, $coordinates['x'], $coordinates['y']);
                $itemStream .= $txt['text']->getPartialStream($font);
                $itemStream .= $txt['text']->endStream();
            // Left/right/center align text
            } else if ($txt['text']->hasAlignment()) {
                $strings = $txt['text']->getAlignment()->getStrings($txt['text'], $fontObject, $coordinates['y']);
                foreach ($strings as $string) {
                    $textString = new Text($string['string'], $txt['text']->getSize());
                    if ($fontObject instanceof \Pop\Pdf\Document\Font) {
                        $textString->setFont($fontObject);
                    }
                    if ($styleColor !== null) {
                        $textString->setFillColor($styleColor);
                    }
                    $itemStream .= $textString->getStream($this->fontReferences[$txt['font']], $string['x'], $string['y']);
                }
            // Left/right wrap text around box boundary
            } else if ($txt['text']->hasWrap()) {
                $strings     = $txt['text']->getWrap()->getStrings($txt['text'], $fontObject, $coordinates['y']);
                $colorStream = $txt['text']->getColorStream();
                if (!empty($colorStream)) {
                    $itemStream .= $colorStream;
                }
                foreach ($strings as $string) {
                    $textString = new Text($string['string'], $txt['text']->getSize());
                    if ($fontObject instanceof \Pop\Pdf\Document\Font) {
                        $textString->setFont($fontObject);
                    }
                    $itemStream .= $textString->getStream($this->fontReferences[$txt['font']], $string['x'], $string['y']);
                }
            // Else, just append the text stream
            } else {
                $itemStream .= $txt['text']->getStream($this->fontReferences[$txt['font']], $coordinates['x'], $coordinates['y']);
            }

            // A style color is only meant to apply to this one piece of text -
            // bracket it in its own graphics-state save/restore so it can't
            // leak forward and tint whatever renders after it, the same way
            // Path::openLayer()/closeLayer() isolate a path's own state.
            if ($styleColor !== null) {
                $itemStream = "\nq\n" . $itemStream . "\nQ\n";
            }

            $contentObject->appendStream($itemStream);
        }
    }

    /**
     * Prepare the text streams objects
     *
     * @param  array $textStreams
     * @param  PdfObject\PageObject $pageObject
     * @throws Exception
     * @return void
     */
    protected function prepareTextStreams(array $textStreams, PdfObject\PageObject $pageObject): void
    {
        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->addObject($contentObject->getIndex(), $contentObject);
        $pageObject->addContentIndex($contentObject->getIndex());

        foreach ($textStreams as $txt) {
            $stream = $txt->getStream($this->fonts, $this->fontReferences);
            $contentObject->appendStream($stream);
        }
    }

    /**
     * Prepare the annotation objects
     *
     * @param  array    $annotations
     * @param  PdfObject\PageObject $pageObject
     * @param  ?string  $fileKey
     * @param  ?string  $algorithm
     * @return void
     */
    protected function prepareAnnotations(
        array $annotations, PdfObject\PageObject $pageObject, ?string $fileKey = null, ?string $algorithm = null
    ): void
    {
        foreach ($annotations as $annotation) {
            $i = $this->lastIndex() + 1;
            $pageObject->addAnnotIndex($i);

            $coordinates = $this->getCoordinates($annotation['x'], $annotation['y'], $pageObject);
            if ($annotation['annotation'] instanceof \Pop\Pdf\Document\Page\Annotation\Url) {
                if ($fileKey !== null) {
                    $annotation['annotation']->encryptWith($this->stringEncryptor($fileKey, $algorithm, $i));
                }
                $stream = $annotation['annotation']->getStream($i, $coordinates['x'], $coordinates['y']);
            } else {
                $targetCoordinates = $this->getCoordinates(
                    $annotation['annotation']->getXTarget(), $annotation['annotation']->getYTarget(), $pageObject
                );

                $annotation['annotation']->setXTarget($targetCoordinates['x']);
                $annotation['annotation']->setYTarget($targetCoordinates['y']);
                $stream = $annotation['annotation']->getStream(
                    $i, $coordinates['x'], $coordinates['y'], $pageObject->getIndex(), $this->parent->getKids()
                );
            }
            $this->addObject($i, PdfObject\StreamObject::parse($stream));
        }
    }

    /**
     * Prepare the field objects
     *
     * @param  array    $fields
     * @param  PdfObject\PageObject $pageObject
     * @param  ?string  $fileKey
     * @param  ?string  $algorithm
     * @throws Exception
     * @return void
     */
    protected function prepareFields(
        array $fields, PdfObject\PageObject $pageObject, ?string $fileKey = null, ?string $algorithm = null
    ): void
    {
        foreach ($fields as $field) {
            if ($this->document->getForm($field['form']) !== null) {
                if (($field['field']->getFont() !== null) && (!isset($this->fontReferences[$field['field']->getFont()]))) {
                    throw new Exception('Error: The font \'' . $field['field']->getFont() . '\' has not been added to the document.');
                } else if (($field['field']->getFont() !== null) && (isset($this->fontReferences[$field['field']->getFont()]))) {
                    $fontRef = $this->fontReferences[$field['field']->getFont()];
                } else {
                    $fontRef = null;
                }
                // For a checkbox/radio, the on/off appearance XObjects must be
                // allocated (and registered via addObject()) BEFORE this
                // field's own object index ($i) is computed below - $i is
                // only reserved via lastIndex()+1 here and not actually
                // added to $this->objects until this loop body's very last
                // statement, so calling createCheckableAppearance() (which
                // also allocates via lastIndex()+1) any later would collide
                // with $i and its object silently clobber the field's own
                // widget dict once addObject($i, ...) ran.
                $appearance = null;
                if (($field['field'] instanceof Button) && (!$field['field']->isPushButton())) {
                    $appearance = $this->createCheckableAppearance(
                        $field['field'], (float) $field['field']->getWidth(), (float) $field['field']->getHeight()
                    );
                    $appearance['checked'] = ($field['field']->getValue() !== null);
                }

                $i = $this->lastIndex() + 1;
                $pageObject->addAnnotIndex($i);
                $coordinates = $this->getCoordinates($field['x'], $field['y'], $pageObject);
                $this->document->getForm($field['form'])->addFieldIndex($i);
                if ($fileKey !== null) {
                    $field['field']->encryptWith($this->stringEncryptor($fileKey, $algorithm, $i));
                }

                $stream = ($appearance !== null)
                    ? $field['field']->getStream($i, $pageObject->getIndex(), $fontRef, $coordinates['x'], $coordinates['y'], $appearance)
                    : $field['field']->getStream($i, $pageObject->getIndex(), $fontRef, $coordinates['x'], $coordinates['y']);

                $this->addObject($i, PdfObject\StreamObject::parse($stream));
            }
        }
    }

    /**
     * Prepare the form objects
     *
     * @return void
     */
    protected function prepareForms(): void
    {
        // Per spec, a document's Catalog may only have a single /AcroForm
        // entry, and its value must be a single form dictionary (not an
        // array of them) - so every named Document\Form's field indices are
        // combined into one dictionary object here, rather than compiling
        // each Form into its own object and referencing them all as a list.
        $fieldIndices = [];
        foreach ($this->document->getForms() as $form) {
            $fieldIndices = array_merge($fieldIndices, $form->getFieldIndices());
        }

        $fields = implode(' ', array_map(fn($index) => $index . ' 0 R', $fieldIndices));

        $i = $this->lastIndex() + 1;
        $this->addObject($i, PdfObject\StreamObject::parse("{$i} 0 obj\n<</Fields[{$fields}]>>\nendobj\n\n"));
        $this->root->setFormReferences($i . ' 0 R');
    }

}
