<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
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
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
interface PageInterface
{

    /**
     * Set the page width
     *
     * @param  mixed
     * @return Page
     */
    public function setWidth($width);

    /**
     * Set the page height
     *
     * @param  mixed
     * @return Page
     */
    public function setHeight($height);

    /**
     * Set the page index
     *
     * @param  int $i
     * @return Page
     */
    public function setIndex($i);

    /**
     * Get the page width
     *
     * @return int
     */
    public function getWidth();

    /**
     * Get the page height
     *
     * @return int
     */
    public function getHeight();

    /**
     * Get the page index
     *
     * @return int
     */
    public function getIndex();

    /**
     * Add an image to the PDF page
     *
     * @param  Page\Image $image
     * @param  int        $x
     * @param  int        $y
     * @return Page
     */
    public function addImage(Page\Image $image, $x = 0, $y = 0);

    /**
     * Add text to the PDF page
     *
     * @param  Page\Text $text
     * @param  string    $font
     * @param  int       $x
     * @param  int       $y
     * @return Page
     */
    public function addText(Page\Text $text, $font, $x = 0, $y = 0);

    /**
     * Add an annotation to the PDF page
     *
     * @param  Annotation\AbstractAnnotation $annotation
     * @param  int                           $x
     * @param  int                           $y
     * @return Page
     */
    public function addAnnotation(Annotation\AbstractAnnotation $annotation, $x = 0, $y = 0);

    /**
     * Add a URL annotation to the PDF page
     *
     * @param  Annotation\Url $url
     * @param  int            $x
     * @param  int            $y
     * @return Page
     */
    public function addUrl(Annotation\Url $url, $x = 0, $y = 0);

    /**
     * Add a link annotation to the PDF page
     *
     * @param  Annotation\Link $link
     * @param  int             $x
     * @param  int             $y
     * @return Page
     */
    public function addLink(Annotation\Link $link, $x = 0, $y = 0);

    /**
     * Add a path to the Pdf page
     *
     * @param  Page\Path $path
     * @return Page
     */
    public function addPath(Page\Path $path);

    /**
     * Get image objects
     *
     * @return array
     */
    public function getImages();

    /**
     * Get text objects
     *
     * @return array
     */
    public function getText();

    /**
     * Get annotation objects
     *
     * @return array
     */
    public function getAnnotations();

    /**
     * Get path objects
     *
     * @return array
     */
    public function getPaths();

    /**
     * Determine if the page has image objects
     *
     * @return boolean
     */
    public function hasImages();

    /**
     * Determine if the page has text objects
     *
     * @return boolean
     */
    public function hasText();

    /**
     * Determine if the page has annotation objects
     *
     * @return boolean
     */
    public function hasAnnotations();

    /**
     * Determine if the page has path objects
     *
     * @return boolean
     */
    public function hasPaths();

}