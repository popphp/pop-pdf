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
namespace Pop\Pdf\Document\Page\Annotation;

/**
 * Pdf page url annotation class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
 */
class Url extends AbstractAnnotation
{

    /**
     * External URL to link to
     * @var ?string
     */
    protected ?string $url = null;

    /**
     * Constructor
     *
     * Instantiate a PDF URL annotation object.
     *
     * @param  int    $width
     * @param  int    $height
     * @param  string $url
     */
    public function __construct(int $width, int $height, string $url)
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
     * Get the annotation stream
     *
     * @param  int $i
     * @param  int $x
     * @param  int $y
     * @return string
     */
    public function getStream(int $i, int $x, int $y): string
    {
        // Assemble the border parameters
        $border = $this->hRadius . ' ' . $this->vRadius . ' ' . $this->borderWidth;
        if (($this->dashGap != 0) && ($this->dashLength != 0)) {
            $border .= ' [' . $this->dashGap . ' ' . $this->dashLength . ']';
        }

        // Return the stream
        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Link\n    /Rect [{$x} {$y} " . ($this->width + $x) .
            " " . ($this->height + $y) . "]\n    /Border [" . $border .  "]\n    /A <</S /URI /URI (" .
            $this->url . ")>>\n>>\nendobj\n\n";
    }

}
