<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
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
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class StreamObject extends AbstractObject
{

    /**
     * PDF stream object index
     * @var int
     */
    protected $index = 5;

    /**
     * PDF stream object definition
     * @var string
     */
    protected $definition = null;

    /**
     * PDF stream object stream
     * @var string
     */
    protected $stream = null;

    /**
     * Encoding filter
     * @var boolean
     */
    protected $encoding = null;

    /**
     * Palette object flag
     * @var boolean
     */
    protected $isPalette = false;

    /**
     * XObject object flag
     * @var boolean
     */
    protected $isXObject = false;

    /**
     * Constructor
     *
     * Instantiate a PDF stream object.
     *
     * @param  int $index
     */
    public function __construct($index = 5)
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
    public static function parse($stream)
    {
        $object = new self();
        $object->setIndex(substr($stream, 0, strpos($stream, ' ')));
        $stream = str_replace($object->getIndex() . ' 0 obj', '[{object_index}] 0 obj', $stream);

        // Determine the objects definition and stream, if applicable.
        $s = substr($stream, (strpos($stream, ' obj') + 4));
        $s = substr($s, 0, strpos($s, 'endobj'));
        if (strpos($s, 'stream') !== false) {
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
    public function setDefinition($definition)
    {
        $this->definition = $definition;

        if (strpos($this->definition, '/ASCIIHexDecode') !== false) {
            $this->encoding = 'ASCIIHexDecode';
        } else if (strpos($this->definition, '/ASCII85Decode') !== false) {
            $this->encoding = 'ASCII85Decode';
        } else if (strpos($this->definition, '/LZWDecode') !== false) {
            $this->encoding = 'LZWDecode';
        } else if (strpos($this->definition, '/FlateDecode') !== false) {
            $this->encoding = 'FlateDecode';
        } else if (strpos($this->definition, '/RunLengthDecode') !== false) {
            $this->encoding = 'RunLengthDecode';
        } else if (strpos($this->definition, '/CCITTFaxDecode') !== false) {
            $this->encoding = 'CCITTFaxDecode';
        } else if (strpos($this->definition, '/JBIG2Decode') !== false) {
            $this->encoding = 'JBIG2Decode';
        } else if (strpos($this->definition, '/DCTDecode') !== false) {
            $this->encoding = 'DCTDecode';
        } else if (strpos($this->definition, '/JPXDecode') !== false) {
            $this->encoding = 'JPXDecode';
        } else if (strpos($this->definition, '/Crypt') !== false) {
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
    public function setStream($stream)
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
    public function appendStream($stream)
    {
        $this->stream .= $stream;
        return $this;
    }

    /**
     * Get the stream object definition
     *
     * @return string
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * Get the PDF stream object stream
     *
     * @return string
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Method to encode the PDF stream object with FlateDecode (gzcompress)
     *
     * @return void
     */
    public function encode()
    {
        if (($this->stream != '') && (function_exists('gzcompress')) &&
            (strpos($this->definition, ' /Image') === false) && (strpos($this->definition, '/FlateDecode') === false)) {
            $this->stream   = "\n" . gzcompress($this->stream, 9) . "\n";
            $this->encoding = 'FlateDecode';
        }
    }

    /**
     * Method to decode the PDF stream contents with FlateDecode (gzuncompress)
     *
     * @return boolean|string
     */
    public function decode()
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
     * @return boolean
     */
    public function isEncoded()
    {
        return (null !== $this->encoding);
    }

    /**
     * Get the encoding filter
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Set whether the PDF stream object is a palette object
     *
     * @param  boolean $isPalette
     * @return void
     */
    public function setPalette($isPalette)
    {
        $this->isPalette = (bool)$isPalette;
    }

    /**
     * Get whether the PDF stream object is a palette object
     *
     * @return boolean
     */
    public function isPalette()
    {
        return $this->isPalette;
    }

    /**
     * Get whether the PDF stream object is an XObject
     *
     * @return boolean
     */
    public function isXObject()
    {
        return $this->isXObject;
    }

    /**
     * Get the PDF stream object byte length
     *
     * @return int
     */
    public function getByteLength()
    {
        return $this->calculateByteLength($this);
    }

    /**
     * Calculate the byte length of a string
     *
     * @param  string $string
     * @return int
     */
    protected function calculateByteLength($string)
    {
        return strlen($string);
    }

    /**
     * Method to print the PDF stream object.
     *
     * @return string
     */
    public function __toString()
    {
        // Set the stream.
        $stream = (null !== $this->stream) ? "stream" . $this->stream . "endstream\n" : '';

        // Set up the Length definition.
        if ((strpos($this->definition, '/Length ') !== false) && (strpos($this->definition, '/Length1') === false) &&
            (strpos($this->definition, '/Image') === false)) {
            $matches = [];
            preg_match('/\/Length\s\d*/', $this->definition, $matches);
            if (isset($matches[0])) {
                $len = $matches[0];
                $len = str_replace('/Length', '', $len);
                $len = str_replace(' ', '', $len);
                $this->definition = str_replace($len, '[{byte_length}]', $this->definition);
            }
        } else if (strpos($this->definition, '/Length') === false) {
            $this->definition .= "<</Length [{byte_length}]>>\n";
        }

        // Calculate the byte length of the stream and swap out the placeholders.
        $byteLength = (($this->encoding == 'FlateDecode') && (function_exists('gzcompress')) &&
            (strpos($this->definition, ' /Image') === false) && (strpos($this->definition, '/FlateDecode') === false)) ?
            $this->calculateByteLength($this->stream) . " /Filter /FlateDecode" : $this->calculateByteLength($this->stream);

        $data = str_replace(
            ['[{object_index}]', '[{stream}]', '[{definition}]', '[{byte_length}]'],
            [$this->index, $stream, $this->definition, $byteLength],
            $this->data
        );

        // Clear Length definition if it is zero.
        if (strpos($data, '<</Length 0>>') !== false) {
            $data = str_replace('<</Length 0>>', '', $data);
        }

        return $data;
    }

}