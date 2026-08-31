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
namespace Pop\Pdf\Build\PdfObject;

/**
 * Pdf stream object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class StreamObject extends AbstractObject
{

    /**
     * PDF stream object index
     * @var ?int
     */
    protected ?int $index = 5;

    /**
     * PDF stream object definition
     * @var ?string
     */
    protected ?string $definition = null;

    /**
     * PDF stream object stream
     * @var ?string
     */
    protected ?string $stream = null;

    /**
     * Encoding filter
     * @var ?string
     */
    protected ?string $encoding = null;

    /**
     * Palette object flag
     * @var bool
     */
    protected bool $isPalette = false;

    /**
     * XObject object flag
     * @var bool
     */
    protected bool $isXObject = false;

    /**
     * Number of leading bytes in $stream that are the mandatory end-of-line
     * marker (ISO 32000-1 7.3.8.1) after the "stream" keyword, rather than
     * real payload - non-zero only for objects built via parse() (or an
     * equivalent importer, e.g. ObjectGraphReader::translateGeneric()) that
     * retain a declared /Length verbatim (literal or indirect) and so keep
     * that EOL as part of $stream instead of having it added dynamically at
     * render time. Consulted by Compiler's encryption pass to strip exactly
     * this many leading bytes before encrypting, independent of whatever
     * /Length happens to say (a literal integer, an indirect reference, or
     * nothing at all).
     * @var int
     */
    protected int $leadingEolLength = 0;

    /**
     * Constructor
     *
     * Instantiate a PDF stream object.
     *
     * @param  int $index
     */
    public function __construct(int $index = 5)
    {
        $this->setIndex($index);
        $this->setData("[{object_index}] 0 obj\n[{definition}]\n[{stream}]\nendobj\n\n");
    }

    /**
     * Parse a stream object from a string
     *
     * @param  string $stream
     * @return StreamObject
     */
    public static function parse(string $stream): StreamObject
    {
        $object = new self();
        $object->setIndex((int)substr($stream, 0, strpos($stream, ' ')));
        $stream = str_replace($object->getIndex() . ' 0 obj', '[{object_index}] 0 obj', $stream);

        // Determine the objects definition and stream, if applicable.
        $s = substr($stream, (strpos($stream, ' obj') + 4));
        $s = substr($s, 0, strpos($s, 'endobj'));
        if (str_contains($s, 'stream')) {
            $def = substr($s, 0, strpos($s, 'stream'));
            $str = substr($s, (strpos($s, 'stream') + 6));
            $str = substr($str, 0, strpos($str, 'endstream'));

            // The stream keyword is always followed by a mandatory
            // end-of-line marker (ISO 32000-1 7.3.8.1) that is not part of
            // the real payload. Captured once up front - independent of
            // whether /Length below turns out to be a literal integer, an
            // indirect reference, or absent entirely - so callers (e.g.
            // Compiler's encryption pass, via getLeadingEolLength()) can
            // strip exactly this many leading bytes to recover the real
            // payload regardless of which branch below executes.
            $leadingEolLength = str_starts_with($str, "\r\n")
                ? 2 : ((str_starts_with($str, "\n") || str_starts_with($str, "\r")) ? 1 : 0);

            // __toString() always re-adds the EOL before 'endstream' itself, so
            // an EOL captured here from the original string would otherwise be
            // duplicated, making the declared and actual stream lengths disagree.
            //
            // A declared, literal /Length is authoritative on exactly how many
            // data bytes follow the leading EOL - a trailing-byte heuristic
            // can't be, since it can't distinguish the template's own
            // separator from a stream whose real last byte(s) happen to be
            // \r/\n themselves (e.g. "\r" + the template's "\n" looks
            // identical to one atomic "\r\n" separator, and stripping both
            // would silently drop a real trailing \r of data).
            if (preg_match('/\/Length\s+(\d+)\b(?!\s+\d+\s+R\b)/', $def, $lengthMatch)) {
                $str = substr($str, 0, $leadingEolLength + (int)$lengthMatch[1]);
            } else if (str_ends_with($str, "\r\n")) {
                $str = substr($str, 0, -2);
            } else if (str_ends_with($str, "\n") || str_ends_with($str, "\r")) {
                $str = substr($str, 0, -1);
            }

            $object->setDefinition($def);
            $object->appendStream($str);
            $object->setLeadingEolLength($leadingEolLength);
        } else {
            $object->setDefinition($s);
        }

        $object->setData("[{object_index}] 0 obj\n[{definition}]\n[{stream}]\nendobj\n\n");
        return $object;
    }

    /**
     * Set the stream object definition
     *
     * @param  string $definition
     * @return StreamObject
     */
    public function setDefinition(string $definition): StreamObject
    {
        $this->definition = (string)$definition;

        if (str_contains($this->definition, '/ASCIIHexDecode')) {
            $this->encoding = 'ASCIIHexDecode';
        } else if (str_contains($this->definition, '/ASCII85Decode')) {
            $this->encoding = 'ASCII85Decode';
        } else if (str_contains($this->definition, '/LZWDecode')) {
            $this->encoding = 'LZWDecode';
        } else if (str_contains($this->definition, '/FlateDecode')) {
            $this->encoding = 'FlateDecode';
        } else if (str_contains($this->definition, '/RunLengthDecode')) {
            $this->encoding = 'RunLengthDecode';
        } else if (str_contains($this->definition, '/CCITTFaxDecode')) {
            $this->encoding = 'CCITTFaxDecode';
        } else if (str_contains($this->definition, '/JBIG2Decode')) {
            $this->encoding = 'JBIG2Decode';
        } else if (str_contains($this->definition, '/DCTDecode')) {
            $this->encoding = 'DCTDecode';
        } else if (str_contains($this->definition, '/JPXDecode')) {
            $this->encoding = 'JPXDecode';
        } else if (str_contains($this->definition, '/Crypt')) {
            $this->encoding = 'Crypt';
        }

        if (stripos($this->definition, '/xobject') !== false) {
            $this->isXObject = true;
        }

        return $this;
    }

    /**
     * Set the stream object stream
     *
     * @param  string $stream
     * @return StreamObject
     */
    public function setStream(string $stream): StreamObject
    {
        $this->stream = $stream;
        return $this;
    }

    /**
     * Append to the stream the PDF stream object
     *
     * @param  string $stream
     * @return StreamObject
     */
    public function appendStream(string $stream): StreamObject
    {
        $this->stream .= $stream;
        return $this;
    }

    /**
     * Get the stream object definition
     *
     * @return ?string
     */
    public function getDefinition(): ?string
    {
        return $this->definition;
    }

    /**
     * Get the PDF stream object stream
     *
     * @return ?string
     */
    public function getStream(): ?string
    {
        return $this->stream;
    }

    /**
     * Method to encode the PDF stream object with FlateDecode (gzcompress)
     *
     * @return void
     */
    public function encode(): void
    {
        if (($this->stream != '') && (function_exists('gzcompress')) &&
            (!str_contains((string)$this->definition, ' /Image')) && (!str_contains((string)$this->definition, '/FlateDecode'))) {
            $this->stream   = "\n" . gzcompress($this->stream, 9) . "\n";
            $this->encoding = 'FlateDecode';
        }
    }

    /**
     * Method to decode the PDF stream contents with FlateDecode (gzuncompress)
     *
     * @return bool|string
     */
    public function decode(): bool|string
    {
        $decoded = false;
        if (($this->stream != '') && function_exists('gzuncompress')) {
            $decoded = @gzuncompress(trim($this->stream));
        }
        return $decoded;
    }

    /**
     * Determine whether or not the PDF stream object is encoded
     *
     * @return bool
     */
    public function isEncoded(): bool
    {
        return ($this->encoding !== null);
    }

    /**
     * Get the encoding filter
     *
     * @return ?string
     */
    public function getEncoding(): ?string
    {
        return $this->encoding;
    }

    /**
     * Set whether the PDF stream object is a palette object
     *
     * @param  bool $isPalette
     * @return StreamObject
     */
    public function setPalette(bool $isPalette): StreamObject
    {
        $this->isPalette = $isPalette;
        return $this;
    }

    /**
     * Get whether the PDF stream object is a palette object
     *
     * @return bool
     */
    public function isPalette(): bool
    {
        return $this->isPalette;
    }

    /**
     * Set the number of leading bytes in the stream that are the mandatory
     * post-"stream"-keyword end-of-line marker rather than real payload
     *
     * @param  int $length
     * @return StreamObject
     */
    public function setLeadingEolLength(int $length): StreamObject
    {
        $this->leadingEolLength = $length;
        return $this;
    }

    /**
     * Get the number of leading bytes in the stream that are the mandatory
     * post-"stream"-keyword end-of-line marker rather than real payload
     *
     * @return int
     */
    public function getLeadingEolLength(): int
    {
        return $this->leadingEolLength;
    }

    /**
     * Get whether the PDF stream object is an XObject
     *
     * @return bool
     */
    public function isXObject(): bool
    {
        return $this->isXObject;
    }

    /**
     * Get the PDF stream object byte length
     *
     * @return int
     */
    public function getByteLength(): int
    {
        return $this->calculateByteLength((string)$this);
    }

    /**
     * Calculate the byte length of a string
     *
     * @param  ?string $string
     * @return int
     */
    protected function calculateByteLength(?string $string): int
    {
        return strlen((string)$string);
    }

    /**
     * Method to print the PDF stream object.
     *
     * @return string
     */
    public function __toString(): string
    {
        // Set the stream, adding linefeed
        $stream = ($this->stream !== null) ? "stream" . $this->stream . "\nendstream\n" : '';

        // Set up the Length definition. The match/replace is scoped to the
        // exact "/Length N" (or indirect "/Length N G R") span via
        // preg_replace's own single-match substitution, rather than a
        // blanket string search-and-replace on the extracted digits - a
        // blanket replace would corrupt any other dict value that happens
        // to contain the same digit substring (e.g. "/Length 38" colliding
        // with "/Width 384"), and would leave a dangling, meaningless
        // indirect reference behind for a source using indirect /Length.
        if ((preg_match('/\/Length\s+\d+(?:\s+\d+\s+R)?/', (string) $this->definition)) &&
            (!str_contains((string)$this->definition, '/Length1')) &&
            (!str_contains((string)$this->definition, '/Image'))) {
            $this->definition = preg_replace(
                '/\/Length\s+\d+(?:\s+\d+\s+R)?/', '/Length [{byte_length}]', $this->definition, 1
            );
        } else if (!str_contains((string)$this->definition, '/Length')) {
            $this->definition .= "<</Length [{byte_length}]>>\n";
        }

        // Calculate the byte length of the stream and swap out the placeholders.
        $byteLength = (($this->encoding == 'FlateDecode') && (function_exists('gzcompress')) &&
            (!str_contains((string)$this->definition, ' /Image')) && (!str_contains((string)$this->definition, '/FlateDecode'))) ?
            $this->calculateByteLength($this->stream) . " /Filter /FlateDecode" : $this->calculateByteLength($this->stream);

        $data = str_replace(
            ['[{object_index}]', '[{stream}]', '[{definition}]', '[{byte_length}]'],
            [(string)$this->index, $stream, (string)$this->definition, (string)$byteLength],
            $this->data
        );

        // Clear Length definition if it is zero.
        if (str_contains((string)$data, '<</Length 0>>')) {
            $data = str_replace('<</Length 0>>', '', $data);
        }

        return $data;
    }

}
