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
namespace Pop\Pdf\Document\Page\Field;

use Pop\Color\Color;
use Pop\Pdf\Document\Page\Text as TextHelper;

/**
 * Pdf abstract form field class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
abstract class AbstractField implements FieldInterface
{

    /**
     * Field name
     * @var ?string
     */
    protected ?string $name = null;

    /**
     * Text field width
     * @var ?int
     */
    protected ?int $width = null;

    /**
     * Text field height
     * @var ?int
     */
    protected ?int $height = null;

    /**
     * Field value
     * @var ?string
     */
    protected ?string $value = null;

    /**
     * Field default value
     * @var ?string
     */
    protected ?string $defaultValue = null;

    /**
     * Text field font
     * @var ?string
     */
    protected ?string $font = null;

    /**
     * Text field font size
     * @var int
     */
    protected int $size = 12;

    /**
     * Field font color
     * @var ?Color\ColorInterface
     */
    protected ?Color\ColorInterface $fontColor = null;

    /**
     * Field flag bits
     * @var array
     */
    protected array $flagBits = [];

    /**
     * String encryptor callable, set by encryptWith() and applied lazily
     * by encryptLiteral()
     * @var ?callable
     */
    protected $stringEncryptor = null;

    /**
     * Constructor
     *
     * Instantiate a PDF text field object.
     *
     * @param  string  $name
     * @param  ?string $font
     * @param  int     $size
     */
    public function __construct(string $name, ?string $font = null, int $size = 12)
    {
        $this->setName($name);
        $this->setSize($size);
        if ($font !== null) {
            $this->setFont($font);
        }
    }

    /**
     * Set the field name
     *
     * @param  string $name
     * @return AbstractField
     */
    public function setName(string $name): AbstractField
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the field value
     *
     * @param  string $value
     * @return AbstractField
     */
    public function setValue(string $value): AbstractField
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Set the field default value
     *
     * @param  string $value
     * @return AbstractField
     */
    public function setDefaultValue(string $value): AbstractField
    {
        $this->defaultValue = $value;
        return $this;
    }

    /**
     * Set the font
     *
     * @param  string $font
     * @return AbstractField
     */
    public function setFont(string $font): AbstractField
    {
        $this->font = $font;
        return $this;
    }

    /**
     * Get the font
     *
     * @return ?string
     */
    public function getFont(): ?string
    {
        return $this->font;
    }

    /**
     * Set the font size
     *
     * @param  int $size
     * @return AbstractField
     */
    public function setSize(int $size): AbstractField
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Get the font size
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Set the font color
     *
     * @param  Color\ColorInterface $color
     * @return AbstractField
     */
    public function setFontColor(Color\ColorInterface $color): AbstractField
    {
        $this->fontColor = $color;
        return $this;
    }

    /**
     * Get the field font color
     *
     * @return ?Color\ColorInterface
     */
    public function getFontColor(): ?Color\ColorInterface
    {
        return $this->fontColor;
    }

    /**
     * Set read-only
     *
     * @return AbstractField
     */
    public function setReadOnly(): AbstractField
    {
        if (!in_array(1, $this->flagBits)) {
            $this->flagBits[] = 1;
        }
        return $this;
    }

    /**
     * Set required
     *
     * @return AbstractField
     */
    public function setRequired(): AbstractField
    {
        if (!in_array(2, $this->flagBits)) {
            $this->flagBits[] = 2;
        }
        return $this;
    }

    /**
     * Set no export
     *
     * @return AbstractField
     */
    public function setNoExport(): AbstractField
    {
        if (!in_array(3, $this->flagBits)) {
            $this->flagBits[] = 3;
        }
        return $this;
    }

    /**
     * Get the field name
     *
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the field value
     *
     * @return ?string
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * Get the field default value
     *
     * @return ?string
     */
    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    /**
     * Set the field width
     *
     * @param  int $width
     * @return AbstractField
     */
    public function setWidth(int $width): AbstractField
    {
        $this->width = $width;
        return $this;
    }

    /**
     * Get the field width
     *
     * @return ?int
     */
    public function getWidth(): ?int
    {
        return $this->width;
    }

    /**
     * Set the field height
     *
     * @param  int $height
     * @return AbstractField
     */
    public function setHeight(int $height): AbstractField
    {
        $this->height = $height;
        return $this;
    }

    /**
     * Get the field height
     *
     * @return ?int
     */
    public function getHeight(): ?int
    {
        return $this->height;
    }

    /**
     * Get the flags value
     *
     * @return int
     */
    protected function getFlags(): int
    {
        $flags = '';

        for ($i = 1; $i <= 32; $i++) {
            $flags = ((in_array($i, $this->flagBits)) ? '1' : '0') . $flags;
        }

        return bindec($flags);
    }

    /**
     * Encrypt this field's literal strings for a compiled, encrypted
     * document. Called by Build\Compiler::prepareFields() before
     * getStream().
     *
     * @param  callable $encryptor
     * @return static
     */
    public function encryptWith(callable $encryptor): static
    {
        $this->stringEncryptor = $encryptor;
        return $this;
    }

    /**
     * Encrypt (if a document encryptor was set) a raw plaintext value and
     * always PDF-literal-string-escape the result before it is embedded as
     * "(...)" in a getStream() template.
     *
     * Always takes RAW, unescaped plaintext - never a value some caller has
     * already escaped - because escaping and encrypting are genuinely
     * ordered operations: encrypting an already-escaped string would
     * encrypt the wrong bytes (the backslash-escapes themselves), so a
     * reader would decrypt back to the escaped form, not the true value.
     *
     * Always escapes its own return value, even when no encryptor is set.
     * This is a deliberate, small side effect beyond pure encryption
     * support: /T, /TU, /TM, and /DA never escaped their value at all
     * before this behavior existed (unlike /V and /DV, which already called
     * Text::escape() directly). Centralizing escaping into this one helper
     * fixes that pre-existing gap for every caller uniformly, since an
     * unencrypted value containing "(" ")" or "\" was already just as
     * capable of corrupting the surrounding dictionary syntax as an
     * encrypted one is.
     *
     * @param  string $plaintext
     * @return string
     */
    protected function encryptLiteral(string $plaintext): string
    {
        $value = ($this->stringEncryptor !== null) ? ($this->stringEncryptor)($plaintext) : $plaintext;
        return TextHelper::escape($value);
    }

}
