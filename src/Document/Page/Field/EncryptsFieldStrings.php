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

namespace Pop\Pdf\Document\Page\Field;

use Pop\Pdf\Document\Page\Text as TextHelper;

/**
 * Shared literal-string encryption for form field classes.
 *
 * Unlike Build\PdfObject\InfoObject::encryptWith() and
 * Annotation\Url::encryptWith(), which resolve their encrypted value
 * immediately (their plaintext is a simple stored property with no other
 * dependencies), this trait stores the encryptor callable itself and
 * applies it lazily via encryptLiteral() - a field's /DA appearance string
 * is only fully known once getStream() computes it from a font reference
 * that isn't available until then.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
trait EncryptsFieldStrings
{

    /**
     * @var ?callable
     */
    protected $stringEncryptor = null;

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
     * before this trait existed (unlike /V and /DV, which already called
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
