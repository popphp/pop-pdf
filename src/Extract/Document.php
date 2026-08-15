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
namespace Pop\Pdf\Extract;

use Pop\Pdf\Extract\Filter\Budget;

/**
 * Pdf extract document class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Document
{

    /**
     * Maximum bytes retained by the font info cache
     */
    protected const MAX_FONT_INFO_CACHE_BYTES = 67108864; // 64MB

    /**
     * Maximum total bytes this document may decode across every stream over its lifetime.
     *
     * The Budget only throws once a charge pushes the running total past this ceiling - by
     * then, the decode that tipped it over (up to Flate's own 64MB per-call cap) has already
     * completed and its output is retained in memory alongside every prior charged chunk, so
     * real peak usage runs measurably higher than this number, on top of the PHP process's own
     * baseline footprint. This must stay comfortably below common PHP memory_limit floors (128M
     * on conservative/shared hosting) or the process hits a hard, uncatchable OOM fatal before
     * the Budget ever gets to throw its catchable Exception - which is exactly what this
     * constant exists to prevent.
     */
    protected const MAX_TOTAL_DECODED_BYTES = 67108864; // 64MB

    /**
     * Raw PDF data
     * @var string
     */
    protected string $data;

    /**
     * Object number to xref location map
     * @var array
     */
    protected array $offsets = [];

    /**
     * Trailer dictionary
     * @var array
     */
    protected array $trailer = [];

    /**
     * Resolved object cache
     * @var array
     */
    protected array $cache = [];

    /**
     * Expanded object stream cache, keyed by stream object number
     * @var array
     */
    protected array $objectStreamCache = [];

    /**
     * Object numbers currently being resolved, used to detect circular references
     * @var array
     */
    protected array $resolving = [];

    /**
     * Object stream numbers currently being expanded, used to detect circular references
     * @var array
     */
    protected array $expandingStreams = [];

    /**
     * Resolved FontInfo cache, keyed by a caller-supplied cache key
     * @var array
     */
    protected array $fontInfoCache = [];

    /**
     * Running total of bytes retained in the font info cache
     * @var int
     */
    protected int $fontInfoCacheBytes = 0;

    /**
     * Most-recently-used font info cache key, used once the byte budget is exhausted
     * @var ?string
     */
    protected ?string $fontInfoMruKey = null;

    /**
     * Most-recently-used font info cache value, used once the byte budget is exhausted
     * @var mixed
     */
    protected mixed $fontInfoMruValue = null;

    /**
     * Total decoded-byte budget shared by every stream this document decodes
     * @var Budget
     */
    protected Budget $decodeBudget;

    /**
     * Constructor
     *
     * Instantiate a document from raw PDF data.
     *
     * @param string $data
     */
    public function __construct(string $data)
    {
        $this->decodeBudget = new Budget(self::MAX_TOTAL_DECODED_BYTES);
        $this->data = $data;
        $this->load();
    }

    /**
     * Get this document's shared decode budget
     *
     * @return Budget
     */
    public function getDecodeBudget(): Budget
    {
        return $this->decodeBudget;
    }

    /**
     * Create a document from a PDF file
     *
     * @param  string $file
     * @throws Exception
     * @return Document
     */
    public static function fromFile(string $file): Document
    {
        if (!file_exists($file)) {
            throw new Exception('Error: That PDF file does not exist.');
        }

        // Suppressed: a read failure here (e.g. permission denied) is
        // already converted into a typed Exception below, so the native
        // PHP warning would just be noise on an already-handled failure.
        $data = @file_get_contents($file);

        if ($data === false) {
            throw new Exception('Error: Could not read that PDF file.');
        }

        return new self($data);
    }

    /**
     * Get the trailer dictionary
     *
     * @return array
     */
    public function getTrailer(): array
    {
        return $this->trailer;
    }

    /**
     * Get every object number this document's xref exposes
     *
     * @return array
     */
    public function getObjectNumbers(): array
    {
        return array_keys($this->offsets);
    }

    /**
     * Get the resolved document catalog (Root)
     *
     * @throws Exception
     * @return array
     */
    public function getRoot(): array
    {
        $root = $this->resolve($this->trailer['Root'] ?? null);

        if (!is_array($root)) {
            throw new Exception('Error: Could not resolve the PDF document catalog (Root).');
        }

        return $root;
    }

    /**
     * Get an object by object number, from the cache or by parsing/expanding it
     *
     * @param  int $objNum
     * @return mixed
     */
    public function getObject(int $objNum): mixed
    {
        if (array_key_exists($objNum, $this->cache)) {
            return $this->cache[$objNum];
        }

        if (!isset($this->offsets[$objNum])) {
            return null;
        }

        $location = $this->offsets[$objNum];
        $value    = isset($location['inStream'])
            ? $this->getFromObjectStream($location['inStream'], $location['index'])
            : $this->parseAt($location['offset']);

        $this->cache[$objNum] = $value;

        return $value;
    }

    /**
     * Resolve a value, following indirect references until a direct value is reached
     *
     * @param  mixed $value
     * @throws Exception
     * @return mixed
     */
    public function resolve(mixed $value): mixed
    {
        if ($value instanceof Value\Reference) {
            $objNum = $value->objNum;

            if (isset($this->resolving[$objNum])) {
                throw new Exception("Error: Circular reference detected while resolving object {$objNum}.");
            }

            $this->resolving[$objNum] = true;

            try {
                return $this->resolve($this->getObject($objNum));
            } finally {
                unset($this->resolving[$objNum]);
            }
        }

        return $value;
    }

    /**
     * Get a cached FontInfo result for a key, or compute and (budget permitting) cache it
     *
     * @param  string   $key
     * @param  callable $factory
     * @return mixed
     */
    public function getOrResolveFontInfo(string $key, callable $factory): mixed
    {
        if (array_key_exists($key, $this->fontInfoCache)) {
            return $this->fontInfoCache[$key];
        }

        if ($this->fontInfoMruKey === $key) {
            return $this->fontInfoMruValue;
        }

        $result = $factory();

        // Bound how much decoded font data (e.g. decompressed embedded
        // TrueType programs) this cache may retain for the document's whole
        // lifetime - a PDF with more/larger distinct fonts than the budget
        // still works correctly, it just stops benefiting from caching once
        // exhausted, rather than retaining every font's data forever (a
        // 206KB PDF with 20 fonts each decompressing to 10MB was confirmed
        // to otherwise inflate peak memory ~8x during Phase D's final
        // review).
        $size = strlen(serialize($result));

        if (($this->fontInfoCacheBytes + $size) <= self::MAX_FONT_INFO_CACHE_BYTES) {
            $this->fontInfoCache[$key] = $result;
            $this->fontInfoCacheBytes += $size;
        } else {
            // Even once the budget is exhausted, always keep the single
            // MOST RECENTLY resolved result cached - consecutive runs
            // overwhelmingly share the SAME font (Interpreter only
            // re-resolves on Tf), so this collapses what would otherwise be
            // a per-run recompute back down to a per-font-activation one,
            // without giving up the overall memory ceiling. Without this, a
            // single font whose resolved size alone exceeds the budget
            // (e.g. one 70MB embedded TrueType program) would be
            // re-decompressed on every single run referencing it - a worse
            // CPU DoS than the memory regression this cache was added to
            // fix (confirmed during Phase D's final re-review).
            $this->fontInfoMruKey   = $key;
            $this->fontInfoMruValue = $result;
        }

        return $result;
    }

    /**
     * Parse an object directly at a byte offset
     *
     * @param  int $offset
     * @throws Exception
     * @return mixed
     */
    protected function parseAt(int $offset): mixed
    {
        $tokenizer = new Tokenizer($this->data, $offset);
        $tokenizer->next(); // object number
        $tokenizer->next(); // generation number
        $objToken = $tokenizer->next();

        if (($objToken['type'] !== 'keyword') || ($objToken['value'] !== 'obj')) {
            throw new Exception('Error: Expected obj keyword while resolving a PDF object.');
        }

        $parser = new ObjectParser($tokenizer);

        return $parser->parseValue();
    }

    /**
     * Get an object at an index within an object stream, expanding and caching the stream if needed
     *
     * @param  int $streamObjNum
     * @param  int $index
     * @throws Exception
     * @return mixed
     */
    protected function getFromObjectStream(int $streamObjNum, int $index): mixed
    {
        if (!isset($this->objectStreamCache[$streamObjNum])) {
            if (isset($this->expandingStreams[$streamObjNum])) {
                throw new Exception(
                    "Error: Circular object stream reference detected while expanding object {$streamObjNum}."
                );
            }

            $this->expandingStreams[$streamObjNum] = true;

            try {
                $streamObj = $this->getObject($streamObjNum);

                if (!($streamObj instanceof Value\Stream)) {
                    throw new Exception("Error: Object {$streamObjNum} is not a valid object stream.");
                }

                $this->objectStreamCache[$streamObjNum] = ObjectStream::expand($streamObj, $this->decodeBudget);
            } finally {
                unset($this->expandingStreams[$streamObjNum]);
            }
        }

        $objects = array_values($this->objectStreamCache[$streamObjNum]);

        return $objects[$index] ?? null;
    }

    /**
     * Load offsets/trailer via xref, falling back to brute-force repair if unusable
     *
     * @throws Exception
     * @return void
     */
    protected function load(): void
    {
        try {
            [$offsets, $trailer] = $this->loadViaXref();
        } catch (\Throwable $e) {
            // Any lower-layer failure - not just this namespace's own
            // Extract\Exception, but raw PHP errors from malformed data
            // (e.g. a TypeError from a corrupt xref stream's /W array) -
            // must trigger the repair fallback rather than leak out.
            $offsets = [];
            $trailer = [];
        }

        if (!$this->isUsable($offsets, $trailer)) {
            [$offsets, $trailer, $preResolved] = $this->loadViaRepair();
            $this->cache = $preResolved + $this->cache;
        }

        if (isset($trailer['Encrypt'])) {
            throw new Exception('Error: Encrypted PDFs are not currently supported for text extraction.');
        }

        $this->offsets = $offsets;
        $this->trailer = $trailer;
    }

    /**
     * Load offsets/trailer by following the startxref chain (classic tables and/or xref streams)
     *
     * @throws Exception
     * @return array
     */
    protected function loadViaXref(): array
    {
        $startXrefPos = strrpos($this->data, 'startxref');
        if ($startXrefPos === false) {
            throw new Exception('Error: No startxref marker found.');
        }

        $tokenizer = new Tokenizer($this->data, $startXrefPos + strlen('startxref'));
        $posToken  = $tokenizer->next();

        if ($posToken['type'] !== 'number') {
            throw new Exception('Error: Malformed startxref value.');
        }

        $offsets = [];
        $trailer = [];
        $visited = [];
        $xrefPos = (int) $posToken['value'];

        while (($xrefPos !== null) && (!isset($visited[$xrefPos]))) {
            $visited[$xrefPos] = true;

            $section = $this->isClassicXref($xrefPos)
                ? Xref\Table::parse($this->data, $xrefPos)
                : Xref\Stream::parse($this->data, $xrefPos, $this->decodeBudget);

            $this->mergeXrefSection($section, $offsets, $trailer);

            // A hybrid-reference file's classic xref table may point to a
            // supplemental cross-reference stream (for compressed objects
            // the classic table can't express) via /XRefStm, alongside a
            // /Prev continuing the classic chain - both must be merged,
            // per PDF spec 7.5.8.4, not treated as mutually exclusive.
            if (isset($section['trailer']['XRefStm'])) {
                $xrefStmPos = (int) $section['trailer']['XRefStm'];
                if (!isset($visited[$xrefStmPos])) {
                    $visited[$xrefStmPos] = true;
                    $xrefStmSection = Xref\Stream::parse($this->data, $xrefStmPos, $this->decodeBudget);
                    $this->mergeXrefSection($xrefStmSection, $offsets, $trailer);
                }
            }

            $xrefPos = isset($section['trailer']['Prev']) ? (int) $section['trailer']['Prev'] : null;
        }

        return [$offsets, $trailer];
    }

    /**
     * Merge one xref section's offsets/trailer into the accumulated result
     *
     * @param  array $section
     * @param  array $offsets
     * @param  array $trailer
     * @return void
     */
    protected function mergeXrefSection(array $section, array &$offsets, array &$trailer): void
    {
        foreach ($section['offsets'] as $objNum => $location) {
            if (!isset($offsets[$objNum])) {
                $offsets[$objNum] = $location;
            }
        }

        $trailer = $trailer + $section['trailer'];
    }

    /**
     * Determine if the xref section at a position is a classic table (vs. an xref stream)
     *
     * @param  int $pos
     * @return bool
     */
    protected function isClassicXref(int $pos): bool
    {
        $tokenizer = new Tokenizer($this->data, $pos);
        $token     = $tokenizer->next();

        return ($token['type'] === 'keyword') && ($token['value'] === 'xref');
    }

    /**
     * Load offsets/trailer via brute-force repair scan
     *
     * @return array
     */
    protected function loadViaRepair(): array
    {
        $result  = Repair::scan($this->data);
        $trailer = $result['trailer'];

        if (!isset($trailer['Root'])) {
            $trailer['Root'] = $this->findCatalogReference($result['offsets']);
        }

        $preResolved = $this->expandObjectStreamsFromRepair($result['offsets']);

        return [$result['offsets'], $trailer, $preResolved];
    }

    /**
     * A brute-force repair scan only finds objects that appear as literal
     * "N G obj ... endobj" text - objects packed inside a /Type /ObjStm
     * container's stream body don't match that pattern at all, since
     * they're serialized inline within the ObjStm's own stream data rather
     * than as their own "obj" markers. Without this second pass, any object
     * that only exists inside an object stream would be silently
     * unrecoverable after repair fires.
     *
     * These recovered objects don't have a byte offset the way normal
     * repair-scanned objects do - they're already fully parsed values, not
     * "here's where to find it" locations - so instead of trying to fit
     * them into the offsets/inStream shape, this returns a ready-to-use
     * [objNum => value] map that the caller seeds directly into the object
     * cache (getObject() already checks the cache before consulting
     * offsets).
     *
     * @param  array $offsets
     * @return array
     */
    protected function expandObjectStreamsFromRepair(array $offsets): array
    {
        $preResolved = [];

        foreach ($offsets as $location) {
            if (!isset($location['offset'])) {
                continue;
            }

            try {
                $value = $this->parseAt($location['offset']);
            } catch (\Throwable $e) {
                continue;
            }

            if (!($value instanceof Value\Stream)) {
                continue;
            }

            $type = $value->dict['Type'] ?? null;
            if (!($type instanceof Value\Name) || ($type->name !== 'ObjStm')) {
                continue;
            }

            try {
                $expanded = ObjectStream::expand($value, $this->decodeBudget);
            } catch (\Throwable $e) {
                continue;
            }

            foreach ($expanded as $objNum => $objValue) {
                if (!isset($preResolved[$objNum])) {
                    $preResolved[$objNum] = $objValue;
                }
            }
        }

        return $preResolved;
    }

    /**
     * Scan repair-recovered offsets for an object that looks like the document catalog
     *
     * @param  array $offsets
     * @return ?Value\Reference
     */
    protected function findCatalogReference(array $offsets): ?Value\Reference
    {
        foreach ($offsets as $objNum => $location) {
            if (!isset($location['offset'])) {
                continue;
            }

            try {
                $tokenizer = new Tokenizer($this->data, $location['offset']);
                $tokenizer->next();
                $genToken = $tokenizer->next();
                $objToken = $tokenizer->next();

                if (($objToken['type'] === 'keyword') && ($objToken['value'] === 'obj')) {
                    $parser = new ObjectParser($tokenizer);
                    $value  = $parser->parseValue();

                    if (is_array($value) && isset($value['Type']) &&
                        ($value['Type'] instanceof Value\Name) && ($value['Type']->name === 'Catalog')) {
                        return new Value\Reference($objNum, (int) $genToken['value']);
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Determine if xref-derived offsets/trailer look usable (vs. needing repair)
     *
     * @param  array $offsets
     * @param  array $trailer
     * @return bool
     */
    protected function isUsable(array $offsets, array $trailer): bool
    {
        if (empty($offsets) || !isset($trailer['Root'])) {
            return false;
        }

        $root = $trailer['Root'];
        if (!($root instanceof Value\Reference) || !isset($offsets[$root->objNum])) {
            return false;
        }

        $sample = array_slice($offsets, 0, 5, true);
        foreach ($sample as $location) {
            if (isset($location['offset']) && !$this->looksLikeObjectAt($location['offset'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if a byte offset looks like the start of an "N G obj" object
     *
     * @param  int $offset
     * @return bool
     */
    protected function looksLikeObjectAt(int $offset): bool
    {
        if (($offset < 0) || ($offset >= strlen($this->data))) {
            return false;
        }

        $chunk = substr($this->data, $offset, 32);

        return (bool) preg_match('/^\s*\d+\s+\d+\s+obj\b/', $chunk);
    }

}
