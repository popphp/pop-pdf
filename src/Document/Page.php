<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document;

use Pop\Pdf\Document\Page\Annotation;

/**
 * Pdf page class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Page extends AbstractPage
{

    /**
     * Imported page object
     * @var \Pop\Pdf\Build\PdfObject\PageObject
     */
    protected $importedPageObject = null;

    /**
     * Constructor
     *
     * Instantiate a PDF page.
     *
     * @param  mixed $size
     * @param  mixed $height
     * @param  int   $i
     * @throws Exception
     */
    public function __construct($size, $height = null, $i = null)
    {
        // Numeric width and height is passed
        if ((null !== $height) && is_numeric($height) && is_numeric($size)) {
            $this->setWidth($size);
            $this->setHeight($height);
        // Else, a pre-defined page size is passed
        } else if (array_key_exists($size, $this->sizes)) {
            $this->setWidth($this->sizes[$size]['width']);
            $this->setHeight($this->sizes[$size]['height']);
        } else {
            throw new Exception('Error: The page size was not valid.');
        }

        if (null !== $i) {
            $this->setIndex($i);
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
     * @throws Exception
     * @return Page
     */
    public function addText(Page\Text $text, $font = null, $x = 0, $y = 0)
    {
        if (null === $font) {
            $font = $text->getFont();
        }
        if (null === $font) {
            throw new Exception('Error: You must either pass a font or set the font in the text object.');
        }
        $this->text[] = [
            'text' => $text,
            'font' => $font,
            'x'    => (int)$x,
            'y'    => (int)$y
        ];
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
     * @param \Pop\Pdf\Build\PdfObject\PageObject $page
     * @return Page
     */
    public function importPageObject(\Pop\Pdf\Build\PdfObject\PageObject $page)
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
     * @return \Pop\Pdf\Build\PdfObject\PageObject
     */
    public function getImportedPageObject()
    {
        return $this->importedPageObject;
    }

}