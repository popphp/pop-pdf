<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
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
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
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
     * Constructor
     *
     * Instantiate a PDF stream object.
     *
     * @param  int $index
     */
    public function __construct(int $index = 5)
    {
        $this->setIndex($index);
        $this->setData("\n[{object_index}] 0 obj\n[{definition}]\n[{stream}]\nendobj\n\n");
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
        $object->setIndex(substr($stream, 0, strpos($stream, ' ')));
        $stream = str_replace($object->getIndex() . ' 0 obj', '[{object_index}] 0 obj', $stream);

        // Determine the objects definition and stream, if applicable.
        $s = substr($stream, (strpos($stream, ' obj') + 4));
        $s = substr($s, 0, strpos($s, 'endobj'));
        if (str_contains($s, 'stream')) {
            $def = substr($s, 0, strpos($s, 'stream'));
            $str = substr($s, (strpos($s, 'stream') + 6));
            $str = substr($str, 0, strpos($str, 'endstream'));
            $object->setDefinition($def);
            $object->appendStream($str);
        } else {
            $object->setDefinition($s);
        }

        $object->setData("\n[{object_index}] 0 obj\n[{definition}]\n[{stream}]\nendobj\n\n");
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
        // Set the stream.
        $stream = ($this->stream !== null) ? "stream" . $this->stream . "endstream\n" : '';

        // Set up the Length definition.
        if ((str_contains((string)$this->definition, '/Length ')) && (!str_contains((string)$this->definition, '/Length1')) &&
            (!str_contains((string)$this->definition, '/Image'))) {
            $matches = [];
            preg_match('/\/Length\s\d*/', $this->definition, $matches);
            if (isset($matches[0])) {
                $len = $matches[0];
                $len = str_replace('/Length', '', $len);
                $len = str_replace(' ', '', $len);
                $this->definition = str_replace($len, '[{byte_length}]', $this->definition);
            }
        } else if (!str_contains((string)$this->definition, '/Length')) {
            $this->definition .= "<</Length [{byte_length}]>>\n";
        }

        // Calculate the byte length of the stream and swap out the placeholders.
        $byteLength = (($this->encoding == 'FlateDecode') && (function_exists('gzcompress')) &&
            (!str_contains((string)$this->definition, ' /Image')) && (!str_contains((string)$this->definition, '/FlateDecode'))) ?
            $this->calculateByteLength($this->stream) . " /Filter /FlateDecode" : $this->calculateByteLength($this->stream);

        $data = str_replace(
            ['[{object_index}]', '[{stream}]', '[{definition}]', '[{byte_length}]'],
            [$this->index, $stream, $this->definition, $byteLength],
            $this->data
        );

        // Clear Length definition if it is zero.
        if (str_contains((string)$data, '<</Length 0>>')) {
            $data = str_replace('<</Length 0>>', '', $data);
        }

        return $data;
    }

}
