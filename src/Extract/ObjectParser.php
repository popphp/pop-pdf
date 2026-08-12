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
namespace Pop\Pdf\Extract;

/**
 * Pdf extract object parser class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class ObjectParser
{

    /**
     * Maximum array/dict nesting depth
     */
    protected const MAX_DEPTH = 64;

    /**
     * Tokenizer feeding this parser
     * @var Tokenizer
     */
    protected Tokenizer $tokenizer;

    /**
     * Constructor
     *
     * Instantiate an object parser.
     *
     * @param Tokenizer $tokenizer
     */
    public function __construct(Tokenizer $tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    /**
     * Parse the next value from the tokenizer
     *
     * @throws Exception
     * @return mixed
     */
    public function parseValue(): mixed
    {
        return $this->parseValueFromToken($this->tokenizer->next(), 0);
    }

    /**
     * Parse a value from an already-read token
     *
     * @param  array $token
     * @param  int   $depth
     * @throws Exception
     * @return mixed
     */
    protected function parseValueFromToken(array $token, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new Exception('Error: Maximum nesting depth exceeded while parsing a PDF object.');
        }

        if ($token['type'] === 'number') {
            return $this->parseNumberOrReference($token['value']);
        } elseif ($token['type'] === 'name') {
            return new Value\Name($token['value']);
        } elseif ($token['type'] === 'string') {
            return $token['value'];
        } elseif ($token['type'] === 'array_start') {
            return $this->parseArray($depth + 1);
        } elseif ($token['type'] === 'dict_start') {
            return $this->parseDictOrStream($depth + 1);
        } elseif ($token['type'] === 'keyword') {
            if ($token['value'] === 'true') {
                return true;
            } elseif ($token['value'] === 'false') {
                return false;
            } elseif ($token['value'] === 'null') {
                return null;
            }
            return new Value\Keyword($token['value']);
        } elseif ($token['type'] === 'eof') {
            throw new Exception('Error: Unexpected end of data while parsing a PDF object.');
        }

        throw new Exception("Error: Unexpected token '{$token['type']}' while parsing a PDF object.");
    }

    /**
     * Parse a number, disambiguating a plain number from an "N G R" indirect reference
     *
     * @param  int|float $number
     * @return int|float|Value\Reference
     */
    protected function parseNumberOrReference(int|float $number): int|float|Value\Reference
    {
        if (is_float($number)) {
            return $number;
        }

        $savedPos = $this->tokenizer->getPosition();
        $genToken = $this->tokenizer->next();

        if (($genToken['type'] === 'number') && is_int($genToken['value'])) {
            $savedPos2 = $this->tokenizer->getPosition();
            $rToken    = $this->tokenizer->next();

            if (($rToken['type'] === 'keyword') && ($rToken['value'] === 'R')) {
                return new Value\Reference($number, $genToken['value']);
            }

            $this->tokenizer->setPosition($savedPos2);
        }

        $this->tokenizer->setPosition($savedPos);

        return $number;
    }

    /**
     * Parse an array
     *
     * @param  int $depth
     * @throws Exception
     * @return array
     */
    protected function parseArray(int $depth): array
    {
        $items = [];

        while (true) {
            $token = $this->tokenizer->next();

            if ($token['type'] === 'array_end') {
                break;
            }
            if ($token['type'] === 'eof') {
                throw new Exception('Error: Unexpected end of data while parsing a PDF array.');
            }

            $items[] = $this->parseValueFromToken($token, $depth);
        }

        return $items;
    }

    /**
     * Parse a dictionary, or a stream if the dictionary is followed by 'stream'
     *
     * @param  int $depth
     * @throws Exception
     * @return mixed
     */
    protected function parseDictOrStream(int $depth): mixed
    {
        $dict = [];

        while (true) {
            $token = $this->tokenizer->next();

            if ($token['type'] === 'dict_end') {
                break;
            }
            if ($token['type'] !== 'name') {
                throw new Exception('Error: Expected a name key while parsing a PDF dictionary.');
            }

            // Threads $depth through directly rather than calling the
            // public parseValue() (which always starts at depth 0) - a
            // deeply-nested array/dict as a dict VALUE must still count
            // toward the same nesting cap, not reset it.
            $dict[$token['value']] = $this->parseValueFromToken($this->tokenizer->next(), $depth);
        }

        $savedPos    = $this->tokenizer->getPosition();
        $streamToken = $this->tokenizer->next();

        if (($streamToken['type'] === 'keyword') && ($streamToken['value'] === 'stream')) {
            return $this->parseStreamData($dict);
        }

        $this->tokenizer->setPosition($savedPos);

        return $dict;
    }

    /**
     * Parse the raw bytes of a stream, honoring /Length when trustworthy
     *
     * @param  array $dict
     * @throws Exception
     * @return Value\Stream
     */
    protected function parseStreamData(array $dict): Value\Stream
    {
        $data = $this->tokenizer->getData();
        $pos  = $this->tokenizer->getPosition();

        if ((($pos + 1) < strlen($data)) && ($data[$pos] === "\r") && ($data[$pos + 1] === "\n")) {
            $pos += 2;
        } elseif (($pos < strlen($data)) && ($data[$pos] === "\n")) {
            $pos += 1;
        }

        $length = $dict['Length'] ?? null;
        $streamStart = $pos;
        $streamEnd   = null;

        // Trust a direct-integer /Length only if it actually lands on the
        // endstream keyword - a wrong /Length (a known real-world PDF
        // corruption pattern) must not silently truncate/mangle the stream.
        if (is_int($length) && ($length >= 0) && (($pos + $length) <= strlen($data)) &&
            $this->isEndstreamAt($data, $pos + $length)) {
            $streamEnd = $pos + $length;
        }

        if ($streamEnd === null) {
            $endPos = strpos($data, 'endstream', $pos);

            if ($endPos === false) {
                throw new Exception('Error: Could not locate endstream marker for a PDF stream object.');
            }

            $streamEnd = $endPos;

            if (($streamEnd > $streamStart) && ($data[$streamEnd - 1] === "\n")) {
                $streamEnd--;
                if (($streamEnd > $streamStart) && ($data[$streamEnd - 1] === "\r")) {
                    $streamEnd--;
                }
            }
        }

        $raw = substr($data, $streamStart, $streamEnd - $streamStart);

        $this->tokenizer->setPosition($streamEnd);

        $endToken = $this->tokenizer->next();
        if (($endToken['type'] !== 'keyword') || ($endToken['value'] !== 'endstream')) {
            $endPos = strpos($data, 'endstream', $this->tokenizer->getPosition());
            if ($endPos !== false) {
                $this->tokenizer->setPosition($endPos + strlen('endstream'));
            }
        }

        return new Value\Stream($dict, $raw);
    }

    /**
     * Determine if the 'endstream' keyword appears at (or after whitespace from) a position
     *
     * @param  string $data
     * @param  int    $pos
     * @return bool
     */
    protected function isEndstreamAt(string $data, int $pos): bool
    {
        $length = strlen($data);

        while (($pos < $length) && Tokenizer::isWhitespace($data[$pos])) {
            $pos++;
        }

        return substr($data, $pos, 9) === 'endstream';
    }

}
