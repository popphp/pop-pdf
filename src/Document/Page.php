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
namespace Pop\Pdf\Document;

use Pop\Pdf\Document\Page\Annotation;
use \Pop\Pdf\Build\PdfObject;

/**
 * Pdf page class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Page extends AbstractPage
{

    /**
     * Imported page object
     * @var PdfObject\PageObject
     */
    protected $importedPageObject = null;

    /**
     * Constructor
     *
     * Instantiate a PDF page.
     *
     * @throws Exception
     */
    public function __construct()
    {
        $args   = func_get_args();
        $width  = null;
        $height = null;
        $index  = null;

        if (is_string($args[0]) && array_key_exists($args[0], $this->sizes)) {
            $width  = $this->sizes[$args[0]]['width'];
            $height = $this->sizes[$args[0]]['height'];

            if (isset($args[1]) && is_numeric($args[1])) {
                $index = $args[1];
            }
        }

        if ((null === $width) && (null === $height) && (count($args) >= 2)) {
            $width  = $args[0];
            $height = $args[1];

            if (isset($args[2]) && is_numeric($args[2])) {
                $index = $args[2];
            }
        }

        if ((null === $width) && (null === $height)) {
            throw new Exception('Error: The page size was not correctly passed or was not valid.');
        } else {
            $this->setWidth($width);
            $this->setHeight($height);
            if (null !== $index) {
                $this->setIndex($index);
            }
        }
    }

    /**
     * Add an image to the PDF page
     *
     * @param  Page\Image $image
     * @param  int        $x
     * @param  int        $y
     * @return Page
     */
    public function addImage(Page\Image $image, $x = 0, $y = 0)
    {
        $this->images[] = [
            'image' => $image,
            'x'     => (int)$x,
            'y'     => (int)$y
        ];
        return $this;
    }

    /**
     * Add text to the PDF page
     *
     * @param  Page\Text $text
     * @param  string    $font
     * @param  int       $x
     * @param  int       $y
     * @return Page
     */
    public function addText(Page\Text $text, $font, $x = 0, $y = 0)
    {
        $this->text[] = [
            'text' => $text,
            'font' => $font,
            'x'    => (int)$x,
            'y'    => (int)$y
        ];
        return $this;
    }

    /**
     * Add text to the PDF page
     *
     * @param  Page\Text\Stream $textStream
     * @return Page
     */
    public function addTextStream(Page\Text\Stream $textStream)
    {
        $this->textStreams[] = $textStream;
        return $this;
    }

    /**
     * Add an annotation to the PDF page
     *
     * @param  Annotation\AbstractAnnotation $annotation
     * @param  int                           $x
     * @param  int                           $y
     * @return Page
     */
    public function addAnnotation(Annotation\AbstractAnnotation $annotation, $x = 0, $y = 0)
    {
        $this->annotations[] = [
            'annotation' => $annotation,
            'x'          => (int)$x,
            'y'          => (int)$y
        ];
        return $this;
    }

    /**
     * Add a URL annotation to the PDF page
     *
     * @param  Annotation\Url $url
     * @param  int            $x
     * @param  int            $y
     * @return Page
     */
    public function addUrl(Annotation\Url $url, $x = 0, $y = 0)
    {
        return $this->addAnnotation($url, $x, $y);
    }

    /**
     * Add a link annotation to the PDF page
     *
     * @param  Annotation\Link $link
     * @param  int             $x
     * @param  int             $y
     * @return Page
     */
    public function addLink(Annotation\Link $link, $x = 0, $y = 0)
    {
        return $this->addAnnotation($link, $x, $y);
    }

    /**
     * Add a path to the Pdf page
     *
     * @param  Page\Path $path
     * @return Page
     */
    public function addPath(Page\Path $path)
    {
        $this->paths[] = $path;
        return $this;
    }

    /**
     * Add a form to the Pdf page
     *
     * @param  Page\Field\AbstractField $field
     * @param  string                   $form
     * @param  int                      $x
     * @param  int                      $y
     * @return Page
     */
    public function addField(Page\Field\AbstractField $field, $form, $x = 0, $y = 0)
    {
        $this->fields[] = [
            'field' => $field,
            'form'  => $form,
            'x'     => (int)$x,
            'y'     => (int)$y
        ];
        return $this;
    }

    /**
     * Import page object into the page
     *
     * @param PdfObject\PageObject $page
     * @return Page
     */
    public function importPageObject(PdfObject\PageObject $page)
    {
        $this->importedPageObject = $page;
        return $this;
    }

    /**
     * Determine if the document has an imported page object
     *
     * @return boolean
     */
    public function hasImportedPageObject()
    {
        return (null !== $this->importedPageObject);
    }

    /**
     * Get the import page object
     *
     * @return PdfObject\PageObject
     */
    public function getImportedPageObject()
    {
        return $this->importedPageObject;
    }

}