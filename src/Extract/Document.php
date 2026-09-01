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
namespace Pop\Pdf\Extract;

use Pop\Pdf\Build\Security\Exception as SecurityException;
use Pop\Pdf\Build\Security\ObjectCipher;
use Pop\Pdf\Build\Security\StandardSecurityHandler;
use Pop\Pdf\Extract\Filter\Budget;

/**
 * Pdf extract document class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
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
     * Positional view of each expanded object stream (index within the stream
     * to object value), keyed by stream object number. Built once alongside
     * objectStreamCache so index lookups don't re-run array_values() on every call.
     * @var array
     */
    protected array $objectStreamIndexCache = [];

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
     * Password used to open this document, if it is encrypted
     * @var ?string
     */
    protected ?string $password = null;

    /**
     * File Encryption Key recovered from the /Encrypt dictionary, if this
     * document is encrypted and the password checked out
     * @var ?string
     */
    protected ?string $fileKey = null;

    /**
     * Stream content encryption algorithm ('AES128' or 'AES256'), or null when
     * this document either isn't encrypted or declares /StmF /Identity (i.e.
     * its streams aren't encrypted even though the document is)
     * @var ?string
     */
    protected ?string $encryptionAlgorithm = null;

    /**
     * Whether this document's STRINGS are encrypted (i.e. it declares a
     * non-/Identity /StrF). Nothing in Extract\* decrypts strings, so when
     * this is true every string value this document hands back - /Info
     * metadata above all - is raw ciphertext rather than readable text.
     * @var bool
     */
    protected bool $encryptedStrings = false;

    /**
     * Constructor
     *
     * Instantiate a document from raw PDF data.
     *
     * @param string  $data
     * @param ?string $password Required if the PDF is encrypted; either the
     *                          user or the owner password will open it
     */
    public function __construct(string $data, ?string $password = null)
    {
        $this->decodeBudget = new Budget(self::MAX_TOTAL_DECODED_BYTES);
        $this->data         = $data;
        $this->password     = $password;
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
     * @param  string  $file
     * @param  ?string $password Required if the PDF is encrypted
     * @throws Exception
     * @return Document
     */
    public static function fromFile(string $file, ?string $password = null): Document
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

        return new self($data, $password);
    }

    /**
     * Determine if this document was encrypted (and therefore opened with a password)
     *
     * @return bool
     */
    public function isEncrypted(): bool
    {
        return ($this->fileKey !== null);
    }

    /**
     * Determine if this document's STRING values are encrypted.
     *
     * Extract\* has no string-decryption layer at all (see the read-path
     * plan's disclosed non-goal), so when this is true every string this
     * document returns is raw ciphertext. A caller that would otherwise
     * present those bytes as text - Build\Parser copying /Info into
     * Document\Metadata, most notably - should skip them instead.
     *
     * @return bool
     */
    public function hasEncryptedStrings(): bool
    {
        return $this->encryptedStrings;
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

        if (isset($location['inStream'])) {
            // Objects packed inside an object stream are NEVER separately
            // encrypted (ISO 32000-1 7.5.7): the container /ObjStm's own
            // stream was already decrypted as a whole when it passed through
            // this same method, so its contents are plaintext by the time
            // they get here. Decrypting again would corrupt them.
            $value = $this->getFromObjectStream($location['inStream'], $location['index']);
        } else {
            $generation = 0;
            $value      = $this->parseAt($location['offset'], $generation);

            if ($value instanceof Value\Stream) {
                $value = $this->decryptStream($objNum, $generation, $value);
            }
        }

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
     * @param  int  $offset
     * @param  ?int $generation Set to the object's generation number, which
     *                          revision 4 (AES-128) decryption needs
     * @throws Exception
     * @return mixed
     */
    protected function parseAt(int $offset, ?int &$generation = null): mixed
    {
        $tokenizer = new Tokenizer($this->data, $offset);
        $tokenizer->next(); // object number
        $genToken = $tokenizer->next();
        $objToken = $tokenizer->next();

        $generation = (($genToken['type'] === 'number') && is_int($genToken['value'])) ? $genToken['value'] : 0;

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

                $this->objectStreamCache[$streamObjNum]      = ObjectStream::expand($streamObj, $this->decodeBudget);
                $this->objectStreamIndexCache[$streamObjNum] = array_values($this->objectStreamCache[$streamObjNum]);
            } finally {
                unset($this->expandingStreams[$streamObjNum]);
            }
        }

        return $this->objectStreamIndexCache[$streamObjNum][$index] ?? null;
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

        $repairOffsets = null;

        if (!$this->isUsable($offsets, $trailer)) {
            [$offsets, $trailer] = $this->loadViaRepair();
            $repairOffsets       = $offsets;

            // A repair scan can only recover a trailer from a literal
            // "trailer" keyword, which a cross-reference-STREAM file doesn't
            // have - its /Encrypt and /ID live in the xref stream's own
            // dictionary instead. Without them an encrypted document would
            // load "successfully" and hand back raw ciphertext, silently,
            // rather than either decrypting it or saying it couldn't.
            if (!isset($trailer['Encrypt'])) {
                $trailer = $this->recoverEncryptionTrailerKeys($offsets) + $trailer;
            }

            // Backstop for every repair shape the recovery above cannot cover -
            // a CLASSIC-xref document damaged badly enough to lose both its
            // literal "trailer" keyword and its "startxref" has no surviving
            // /Encrypt anywhere the repair scan looks, even though the raw
            // bytes plainly still carry one. Proceeding would report the
            // document as unencrypted and hand back undecrypted ciphertext -
            // in practice, silently empty page content even when the caller
            // supplied the CORRECT password, indistinguishable from a
            // legitimately text-free PDF. Saying so is strictly better than
            // that, so this refuses rather than guesses.
            if (!isset($trailer['Encrypt']) && str_contains($this->data, '/Encrypt')) {
                throw new Exception(
                    'Error: This PDF appears to be encrypted (its raw data contains an /Encrypt entry), but its ' .
                    'cross-reference data is damaged badly enough that the encryption dictionary could not be ' .
                    'located, so its contents cannot be decrypted.'
                );
            }
        }

        // Encryption has to be set up before anything reads an object's stream
        // body - including the repair path's object-stream pre-expansion below,
        // whose /ObjStm containers are themselves encrypted.
        $this->initEncryption($offsets, $trailer);

        $this->offsets = $offsets;
        $this->trailer = $trailer;

        if ($repairOffsets !== null) {
            $this->cache = $this->expandObjectStreamsFromRepair($repairOffsets) + $this->cache;
        }
    }

    /**
     * Recover /Encrypt (and the /ID revision 4 needs alongside it) from a
     * cross-reference stream's dictionary, for a repaired document whose
     * trailer the repair scan couldn't find
     *
     * Deliberately narrow: it returns nothing at all unless some xref stream
     * actually declares /Encrypt, so it cannot change how any unencrypted
     * document is repaired. The LAST such stream in the file wins, matching
     * how an incremental update's most recent section supersedes earlier ones.
     *
     * @param  array $offsets
     * @return array
     */
    protected function recoverEncryptionTrailerKeys(array $offsets): array
    {
        $recovered = [];
        $bestAt    = -1;

        foreach ($offsets as $location) {
            if (!isset($location['offset']) || ($location['offset'] <= $bestAt)) {
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

            if (!($type instanceof Value\Name) || ($type->name !== 'XRef') || !isset($value->dict['Encrypt'])) {
                continue;
            }

            $recovered = ['Encrypt' => $value->dict['Encrypt']];
            $bestAt    = $location['offset'];

            if (isset($value->dict['ID'])) {
                $recovered['ID'] = $value->dict['ID'];
            }
        }

        return $recovered;
    }

    /**
     * Verify the supplied password against the /Encrypt dictionary and recover
     * the File Encryption Key, if this document is encrypted
     *
     * @param  array $offsets
     * @param  array $trailer
     * @throws Exception
     * @return void
     */
    protected function initEncryption(array $offsets, array $trailer): void
    {
        if (!isset($trailer['Encrypt'])) {
            return;
        }

        if ($this->password === null) {
            throw new Exception('Error: This PDF is encrypted; a password is required to open it.');
        }

        $encryptDict = $this->resolveEncryptDictRaw($offsets, $trailer['Encrypt']);
        $revision    = (int) ($encryptDict['R'] ?? 0);
        $method      = (string) ($encryptDict['CFM'] ?? '');

        // /Identity means the document is encrypted but its STREAMS are not,
        // which is legal and still requires the password to be verified - it
        // just leaves nothing for decryptStream() to do.
        if (($revision === 6) && (($method === 'AESV3') || ($method === 'Identity'))) {
            $streamAlgorithm = ($method === 'AESV3') ? 'AES256' : null;
        } elseif (($revision === 4) && (($method === 'AESV2') || ($method === 'Identity'))) {
            $streamAlgorithm = ($method === 'AESV2') ? 'AES128' : null;
        } else {
            throw new Exception(
                'Error: This PDF uses an unsupported encryption configuration (revision ' . $revision .
                ", stream method '" . (($method === '') ? 'unknown' : $method) .
                "'); only AES-128 (/AESV2, revision 4) and AES-256 (/AESV3, revision 6) are supported."
            );
        }

        // Guarded with is_array() rather than just ?? - a malformed file whose
        // /ID is an indirect reference (an object, not an array) would
        // otherwise raise a fatal "cannot use object as array" Error here
        // instead of a catchable Exception.
        $id     = is_array($trailer['ID'] ?? null) ? ($trailer['ID'][0] ?? null) : null;
        $fileId = is_string($id) ? $id : '';

        // Anything other than an explicit /Identity leaves this document's
        // strings as ciphertext, since nothing in Extract\* decrypts strings.
        $this->encryptedStrings = ((string)($encryptDict['StrCFM'] ?? '') !== 'Identity');

        try {
            $this->fileKey = ($revision === 6)
                ? StandardSecurityHandler::openRevision6($encryptDict, $this->password)
                : StandardSecurityHandler::openRevision4($encryptDict, $fileId, $this->password);
        } catch (SecurityException $e) {
            // Rethrown in this namespace's own type so every caller of
            // Extract\Document only ever has to catch Extract\Exception, but
            // with the underlying message preserved - "the password is wrong"
            // and "the /Encrypt dictionary is malformed" are different
            // problems and flattening them together points at the wrong one.
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        $this->encryptionAlgorithm = $streamAlgorithm;
    }

    /**
     * Resolve the /Encrypt dictionary down to the raw scalar values
     * StandardSecurityHandler expects
     *
     * The Tokenizer already decodes both <hex> and (literal) PDF strings into
     * raw bytes (see Tokenizer::readAngleOpen()/readLiteralString()), so /O,
     * /U, /OE and /UE arrive here in exactly the shape the security handler
     * wants - no extra hex decoding is needed. The dictionary is parsed
     * directly at its byte offset rather than through getObject()/resolve()
     * so it never touches the object cache and can never itself be treated as
     * something to decrypt.
     *
     * @param  array $offsets
     * @param  mixed $encryptRef
     * @throws Exception
     * @return array
     */
    protected function resolveEncryptDictRaw(array $offsets, mixed $encryptRef): array
    {
        $dict = null;

        if (is_array($encryptRef)) {
            // The spec requires /Encrypt to be indirect, but a direct
            // dictionary is trivially readable, so accept it too.
            $dict = $encryptRef;
        } elseif ($encryptRef instanceof Value\Reference) {
            $location = $offsets[$encryptRef->objNum] ?? null;

            // An /Encrypt dictionary is never inside an object stream - it has
            // to be readable before anything can be decrypted at all.
            if (!isset($location['offset'])) {
                throw new Exception("Error: Could not locate this PDF's encryption dictionary.");
            }

            $dict = $this->parseAt($location['offset']);
        }

        if (!is_array($dict)) {
            throw new Exception("Error: This PDF's encryption dictionary is missing or malformed.");
        }

        $raw = [];

        foreach (['O', 'U', 'OE', 'UE'] as $key) {
            if (isset($dict[$key]) && is_string($dict[$key])) {
                $raw[$key] = $dict[$key];
            }
        }

        foreach (['R', 'V', 'P', 'Length'] as $key) {
            if (isset($dict[$key]) && is_int($dict[$key])) {
                $raw[$key] = $dict[$key];
            }
        }

        // ISO 32000-1 Table 21: /EncryptMetadata defaults to true when absent,
        // and only an explicit `false` turns it off. It is load-bearing for
        // revision 4 key derivation (Algorithm 2 step (f)), not just metadata
        // handling, so it has to be read here and threaded through.
        $raw['EncryptMetadata'] = !(($dict['EncryptMetadata'] ?? true) === false);

        $raw['CFM']    = $this->cryptFilterMethod($dict, 'StmF');
        $raw['StrCFM'] = $this->cryptFilterMethod($dict, 'StrF');

        return $raw;
    }

    /**
     * Determine the crypt filter method (/CFM) that applies to one category of
     * this document's content, per the named filter entry (/StmF for streams,
     * /StrF for strings) and the /CF crypt filter map
     *
     * @param  array  $dict
     * @param  string $filterKey 'StmF' or 'StrF'
     * @return string 'AESV2', 'AESV3', 'Identity', 'V2' (RC4), or '' if undeterminable
     */
    protected function cryptFilterMethod(array $dict, string $filterKey): string
    {
        $v = (isset($dict['V']) && is_int($dict['V'])) ? $dict['V'] : 0;

        // Crypt filters only exist from /V 4 onward - /V 1 and /V 2 are always
        // RC4 over everything, with no /CF map to consult.
        if ($v < 4) {
            return ($v === 0) ? '' : 'V2';
        }

        $filter = $dict[$filterKey] ?? null;
        $name   = ($filter instanceof Value\Name) ? $filter->name : 'Identity'; // ISO 32000-1 Table 20 default

        if ($name === 'Identity') {
            return 'Identity';
        }

        $cf = $dict['CF'] ?? null;

        if (!is_array($cf) || !isset($cf[$name]) || !is_array($cf[$name])) {
            return '';
        }

        $cfm = $cf[$name]['CFM'] ?? null;

        return ($cfm instanceof Value\Name) ? $cfm->name : '';
    }

    /**
     * Decrypt a top-level object's stream body, if this document is encrypted
     *
     * @param  int          $objNum
     * @param  int          $generation
     * @param  Value\Stream $value
     * @throws Exception
     * @return Value\Stream
     */
    protected function decryptStream(int $objNum, int $generation, Value\Stream $value): Value\Stream
    {
        if (($this->fileKey === null) || ($this->encryptionAlgorithm === null)) {
            return $value;
        }

        // A cross-reference stream is never encrypted (ISO 32000-1 7.5.8.2) -
        // it has to be readable before the /Encrypt dictionary it points at
        // can even be found. An /ObjStm, by contrast, IS encrypted, once, as a
        // whole; that's exactly what makes its contents plaintext afterward.
        $type = $value->dict['Type'] ?? null;

        if (($type instanceof Value\Name) && ($type->name === 'XRef')) {
            return $value;
        }

        try {
            $decrypted = ($this->encryptionAlgorithm === 'AES256')
                ? ObjectCipher::decryptAes256($this->fileKey, $value->raw)
                : ObjectCipher::decryptAes128($this->fileKey, $objNum, $generation, $value->raw);
        } catch (SecurityException $e) {
            throw new Exception(
                "Error: Could not decrypt the stream of object {$objNum}: " . $e->getMessage(), $e->getCode(), $e
            );
        }

        return new Value\Stream($value->dict, $decrypted);
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

        return [$result['offsets'], $trailer];
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

        foreach ($offsets as $objNum => $location) {
            if (!isset($location['offset'])) {
                continue;
            }

            try {
                $generation = 0;
                $value      = $this->parseAt($location['offset'], $generation);
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
                // An /ObjStm in an encrypted document is itself encrypted, and
                // this pre-expansion pass bypasses getObject(), so it has to
                // decrypt for itself before it can expand anything.
                $value = $this->decryptStream((int) $objNum, $generation, $value);
            } catch (\Throwable $e) {
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
