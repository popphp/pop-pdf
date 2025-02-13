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
namespace Pop\Pdf\Document;

use Pop\Pdf\Document\Page\Annotation;

/**
 * Pdf page interface
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
 */
interface PageInterface
{

    /**
     * Set the page width
     *
     * @param  mixed $width
     * @return PageInterface
     */
    public function setWidth(mixed $width): PageInterface;

    /**
     * Set the page height
     *
     * @param  mixed $height
     * @return PageInterface
     */
    public function setHeight(mixed $height): PageInterface;

    /**
     * Set the page index
     *
     * @param  int $i
     * @return PageInterface
     */
    public function setIndex(int $i): PageInterface;

    /**
     * Get the page width
     *
     * @return int
     */
    public function getWidth(): int;

    /**
     * Get the page height
     *
     * @return int
     */
    public function getHeight(): int;

    /**
     * Get the page index
     *
     * @return ?int
     */
    public function getIndex(): ?int;

    /**
     * Add an image to the PDF page
     *
     * @param  Page\Image $image
     * @param  int        $x
     * @param  int        $y
     * @return PageInterface
     */
    public function addImage(Page\Image $image, int $x = 0, int $y = 0): PageInterface;

    /**
     * Add text to the PDF page
     *
     * @param  Page\Text $text
     * @param  string    $fontStyle (can be either a reference to a font or a style)
     * @param  int       $x
     * @param  int       $y
     * @return PageInterface
     */
    public function addText(Page\Text $text, string $fontStyle, int $x = 0, int $y = 0): PageInterface;

    /**
     * Add an annotation to the PDF page
     *
     * @param  Annotation\AbstractAnnotation $annotation
     * @param  int                           $x
     * @param  int                           $y
     * @return PageInterface
     */
    public function addAnnotation(Annotation\AbstractAnnotation $annotation, int $x = 0, int $y = 0): PageInterface;

    /**
     * Add a URL annotation to the PDF page
     *
     * @param  Annotation\Url $url
     * @param  int            $x
     * @param  int            $y
     * @return PageInterface
     */
    public function addUrl(Annotation\Url $url, int $x = 0, int $y = 0): PageInterface;

    /**
     * Add a link annotation to the PDF page
     *
     * @param  Annotation\Link $link
     * @param  int             $x
     * @param  int             $y
     * @return PageInterface
     */
    public function addLink(Annotation\Link $link, int $x = 0, int $y = 0): PageInterface;

    /**
     * Add a path to the Pdf page
     *
     * @param  Page\Path $path
     * @return PageInterface
     */
    public function addPath(Page\Path $path): PageInterface;

    /**
     * Get image objects
     *
     * @return array
     */
    public function getImages(): array;

    /**
     * Get text objects
     *
     * @return array
     */
    public function getText(): array;

    /**
     * Get annotation objects
     *
     * @return array
     */
    public function getAnnotations(): array;

    /**
     * Get path objects
     *
     * @return array
     */
    public function getPaths(): array;

    /**
     * Determine if the page has image objects
     *
     * @return bool
     */
    public function hasImages(): bool;

    /**
     * Determine if the page has text objects
     *
     * @return bool
     */
    public function hasText(): bool;

    /**
     * Determine if the page has annotation objects
     *
     * @return bool
     */
    public function hasAnnotations(): bool;

    /**
     * Determine if the page has path objects
     *
     * @return bool
     */
    public function hasPaths(): bool;

}
