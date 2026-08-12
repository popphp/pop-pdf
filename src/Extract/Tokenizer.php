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
 * Pdf extract tokenizer class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Tokenizer
{

    /**
     * Raw data being tokenized
     * @var string
     */
    protected string $data;

    /**
     * Length of the raw data
     * @var int
     */
    protected int $length;

    /**
     * Current byte offset into the data
     * @var int
     */
    protected int $pos;

    /**
     * Constructor
     *
     * Instantiate a tokenizer.
     *
     * @param string $data
     * @param int    $pos
     */
    public function __construct(string $data, int $pos = 0)
    {
        $this->data   = $data;
        $this->length = strlen($data);
        $this->pos    = $pos;
    }

    /**
     * Get the current byte offset
     *
     * @return int
     */
    public function getPosition(): int
    {
        return $this->pos;
    }

    /**
     * Set the current byte offset
     *
     * @param  int $pos
     * @return void
     */
    public function setPosition(int $pos): void
    {
        $this->pos = $pos;
    }

    /**
     * Get the raw data being tokenized
     *
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Determine if a byte is PDF whitespace
     *
     * @param  string $c
     * @return bool
     */
    public static function isWhitespace(string $c): bool
    {
        return ($c === ' ') || ($c === "\t") || ($c === "\r") || ($c === "\n") || ($c === "\f") || ($c === "\0");
    }

    /**
     * Determine if a byte is a PDF delimiter
     *
     * @param  string $c
     * @return bool
     */
    public static function isDelimiter(string $c): bool
    {
        return ($c === '(') || ($c === ')') || ($c === '<') || ($c === '>') ||
            ($c === '[') || ($c === ']') || ($c === '{') || ($c === '}') ||
            ($c === '/') || ($c === '%');
    }

    /**
     * Skip whitespace and comments
     *
     * @return void
     */
    protected function skipWhitespaceAndComments(): void
    {
        while ($this->pos < $this->length) {
            $c = $this->data[$this->pos];
            if (self::isWhitespace($c)) {
                $this->pos++;
            } elseif ($c === '%') {
                while (($this->pos < $this->length) && ($this->data[$this->pos] !== "\n") && ($this->data[$this->pos] !== "\r")) {
                    $this->pos++;
                }
            } else {
                break;
            }
        }
    }

    /**
     * Read the next token
     *
     * @return array
     */
    public function next(): array
    {
        // Loop rather than recurse for tokens that may need to skip over
        // themselves and continue (e.g. a stray '>' byte) - a malicious
        // input with many such bytes in a row must not grow the call stack.
        while (true) {
            $this->skipWhitespaceAndComments();

            if ($this->pos >= $this->length) {
                return ['type' => 'eof', 'value' => null];
            }

            $c = $this->data[$this->pos];

            if ($c === '/') {
                return $this->readName();
            } elseif ($c === '(') {
                return $this->readLiteralString();
            } elseif ($c === '<') {
                return $this->readAngleOpen();
            } elseif ($c === '>') {
                $token = $this->readAngleClose();
                if ($token === null) {
                    // Stray '>' - already skipped by readAngleClose(); loop
                    // back around to tokenize whatever follows.
                    continue;
                }
                return $token;
            } elseif ($c === '[') {
                $this->pos++;
                return ['type' => 'array_start', 'value' => '['];
            } elseif ($c === ']') {
                $this->pos++;
                return ['type' => 'array_end', 'value' => ']'];
            } elseif (($c === '+') || ($c === '-') || ($c === '.') || (($c >= '0') && ($c <= '9'))) {
                return $this->readNumber();
            } else {
                return $this->readKeyword();
            }
        }
    }

    /**
     * Read a name token
     *
     * @return array
     */
    protected function readName(): array
    {
        $this->pos++; // skip '/'
        $name = '';

        while (($this->pos < $this->length) &&
            (!self::isWhitespace($this->data[$this->pos])) &&
            (!self::isDelimiter($this->data[$this->pos]))) {
            if (($this->data[$this->pos] === '#') && (($this->pos + 2) < $this->length) &&
                ctype_xdigit($this->data[$this->pos + 1]) && ctype_xdigit($this->data[$this->pos + 2])) {
                $name .= chr((int) hexdec(substr($this->data, $this->pos + 1, 2)));
                $this->pos += 3;
            } else {
                $name .= $this->data[$this->pos];
                $this->pos++;
            }
        }

        return ['type' => 'name', 'value' => $name];
    }

    /**
     * Read a literal string token
     *
     * @return array
     */
    protected function readLiteralString(): array
    {
        $this->pos++; // skip '('
        $depth = 1;
        $value = '';

        while (($this->pos < $this->length) && ($depth > 0)) {
            $c = $this->data[$this->pos];

            if ($c === '\\') {
                $this->pos++;
                if ($this->pos >= $this->length) {
                    break;
                }
                $esc = $this->data[$this->pos];

                if ($esc === 'n') {
                    $value .= "\n"; $this->pos++;
                } elseif ($esc === 'r') {
                    $value .= "\r"; $this->pos++;
                } elseif ($esc === 't') {
                    $value .= "\t"; $this->pos++;
                } elseif ($esc === 'b') {
                    $value .= "\x08"; $this->pos++;
                } elseif ($esc === 'f') {
                    $value .= "\x0C"; $this->pos++;
                } elseif (($esc === '(') || ($esc === ')') || ($esc === '\\')) {
                    $value .= $esc; $this->pos++;
                } elseif ($esc === "\n") {
                    $this->pos++;
                } elseif ($esc === "\r") {
                    $this->pos++;
                    if (($this->pos < $this->length) && ($this->data[$this->pos] === "\n")) {
                        $this->pos++;
                    }
                } elseif (($esc >= '0') && ($esc <= '7')) {
                    $octal = '';
                    $count = 0;
                    while (($count < 3) && ($this->pos < $this->length) &&
                        ($this->data[$this->pos] >= '0') && ($this->data[$this->pos] <= '7')) {
                        $octal .= $this->data[$this->pos];
                        $this->pos++;
                        $count++;
                    }
                    $value .= chr(((int) octdec($octal)) & 0xFF);
                } else {
                    $value .= $esc;
                    $this->pos++;
                }
            } elseif ($c === '(') {
                $depth++;
                $value .= $c;
                $this->pos++;
            } elseif ($c === ')') {
                $depth--;
                $this->pos++;
                if ($depth > 0) {
                    $value .= $c;
                }
            } else {
                $value .= $c;
                $this->pos++;
            }
        }

        return ['type' => 'string', 'value' => $value];
    }

    /**
     * Read a token starting with '<' (hex string or dict-open)
     *
     * @return array
     */
    protected function readAngleOpen(): array
    {
        if ((($this->pos + 1) < $this->length) && ($this->data[$this->pos + 1] === '<')) {
            $this->pos += 2;
            return ['type' => 'dict_start', 'value' => '<<'];
        }

        $this->pos++; // skip '<'
        $hex = '';

        while (($this->pos < $this->length) && ($this->data[$this->pos] !== '>')) {
            $c = $this->data[$this->pos];
            if (ctype_xdigit($c)) {
                $hex .= $c;
            }
            $this->pos++;
        }

        if (($this->pos < $this->length) && ($this->data[$this->pos] === '>')) {
            $this->pos++;
        }

        if ((strlen($hex) % 2) !== 0) {
            $hex .= '0';
        }

        return ['type' => 'string', 'value' => ($hex === '') ? '' : (string) hex2bin($hex)];
    }

    /**
     * Read a token starting with '>' (dict-close, or a stray byte to skip)
     *
     * @return ?array
     */
    protected function readAngleClose(): ?array
    {
        if ((($this->pos + 1) < $this->length) && ($this->data[$this->pos + 1] === '>')) {
            $this->pos += 2;
            return ['type' => 'dict_end', 'value' => '>>'];
        }

        // Stray '>' outside of a hex string / dict-close - skip it. Return
        // null so next() loops back around instead of recursing here.
        $this->pos++;

        return null;
    }

    /**
     * Read a number token
     *
     * @return array
     */
    protected function readNumber(): array
    {
        $start = $this->pos;

        if (($this->data[$this->pos] === '+') || ($this->data[$this->pos] === '-')) {
            $this->pos++;
        }

        while (($this->pos < $this->length) &&
            ((($this->data[$this->pos] >= '0') && ($this->data[$this->pos] <= '9')) || ($this->data[$this->pos] === '.'))) {
            $this->pos++;
        }

        $raw = substr($this->data, $start, $this->pos - $start);

        if (($raw === '') || ($raw === '+') || ($raw === '-') || ($raw === '.')) {
            return ['type' => 'keyword', 'value' => $raw];
        }

        $value = (str_contains($raw, '.')) ? (float) $raw : (int) $raw;

        return ['type' => 'number', 'value' => $value];
    }

    /**
     * Read a keyword token
     *
     * @return array
     */
    protected function readKeyword(): array
    {
        $start = $this->pos;

        while (($this->pos < $this->length) &&
            (!self::isWhitespace($this->data[$this->pos])) &&
            (!self::isDelimiter($this->data[$this->pos]))) {
            $this->pos++;
        }

        if ($this->pos === $start) {
            $this->pos++;
            return ['type' => 'keyword', 'value' => $this->data[$start]];
        }

        return ['type' => 'keyword', 'value' => substr($this->data, $start, $this->pos - $start)];
    }

}
