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
namespace Pop\Pdf\Document\Page\Annotation;

/**
 * Pdf page url annotation class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
 */
class Url extends AbstractAnnotation
{

    /**
     * External URL to link to
     * @var ?string
     */
    protected ?string $url = null;

    /**
     * Encrypted, already-escaped URI, set by encryptWith() - used by
     * getStream() in place of the plain URL when present.
     * @var ?string
     */
    protected ?string $encryptedUrl = null;

    /**
     * Constructor
     *
     * Instantiate a PDF URL annotation object.
     *
     * @param  int|float $width
     * @param  int|float $height
     * @param  string    $url
     */
    public function __construct(int|float $width, int|float $height, string $url)
    {
        parent::__construct($width, $height);
        $this->setUrl($url);
    }

    /**
     * Set the URL
     *
     * @param  string $url
     * @return Url
     */
    public function setUrl(string $url): Url
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get the URL link
     *
     * @return ?string
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Encrypt this annotation's URI target for a compiled, encrypted
     * document. Called by Build\Compiler::prepareAnnotations() before
     * getStream() - mirrors Build\PdfObject\InfoObject::encryptWith()'s
     * shape and CR/LF-escaping rationale exactly (encrypted bytes are
     * arbitrary binary, not human-authored text, and routinely contain raw
     * bytes a literal-string reader would otherwise silently normalize).
     *
     * @param  callable $encryptor
     * @return Url
     */
    public function encryptWith(callable $encryptor): Url
    {
        $this->encryptedUrl = \Pop\Pdf\Document\Page\Text::escape($encryptor($this->url));
        return $this;
    }

    /**
     * Get the annotation stream
     *
     * @param  int       $i
     * @param  int|float $x
     * @param  int|float $y
     * @return string
     */
    public function getStream(int $i, int|float $x, int|float $y): string
    {
        // Assemble the border parameters
        $border = $this->hRadius . ' ' . $this->vRadius . ' ' . $this->borderWidth;
        if (($this->dashGap != 0) && ($this->dashLength != 0)) {
            $border .= ' [' . $this->dashGap . ' ' . $this->dashLength . ']';
        }

        // Return the stream
        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Link\n    /Rect [{$x} {$y} " . ($this->width + $x) .
            " " . ($this->height + $y) . "]\n    /Border [" . $border .  "]\n    /A <</S /URI /URI (" .
            ($this->encryptedUrl ?? $this->url) . ")>>\n>>\nendobj\n\n";
    }

}
