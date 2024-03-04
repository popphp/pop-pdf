<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document;

use Pop\Pdf\Document\Page\Annotation;
use Pop\Pdf\Build\PdfObject;
use Pop\Pdf\Build\Image;

/**
 * Pdf page class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Page extends AbstractPage
{

    /**
     * Imported page object
     * @var ?PdfObject\PageObject
     */
    protected ?PdfObject\PageObject $importedPageObject = null;

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

        if (($width === null) && ($height === null) && (count($args) >= 2)) {
            $width  = $args[0];
            $height = $args[1];

            if (isset($args[2]) && is_numeric($args[2])) {
                $index = $args[2];
            }
        }

        if (($width === null) && ($height === null)) {
            throw new Exception('Error: The page size was not correctly passed or was not valid.');
        } else {
            $this->setWidth($width);
            $this->setHeight($height);
            if ($index !== null) {
                $this->setIndex($index);
            }
        }
    }

    /**
     * Create a page from an image
     *
     * @param  string $image
     * @param  int    $quality
     * @throws Exception|Page\Exception
     * @return Page
     */
    public static function createFromImage(string $image, int $quality = 70): Page
    {
        if (!file_exists($image)) {
            throw new Exception('Error: That image file does not exist.');
        }

        $imageParser = Image\Parser::createImageFromFile($image, 0, 0);
        $imageParser->convertToJpeg($quality);

        $image  = $imageParser->getConvertedImage();
        $width  = $imageParser->getWidth();
        $height = $imageParser->getHeight();
        $page   = new self($width, $height);
        $page->addImage(Page\Image::createImageFromFile($image), 0, 0);

        return $page;
    }

    /**
     * Add an image to the PDF page
     *
     * @param  Page\Image $image
     * @param  int        $x
     * @param  int        $y
     * @return Page
     */
    public function addImage(Page\Image $image, int $x = 0, int $y = 0): Page
    {
        $this->images[] = [
            'image' => $image,
            'x'     => $x,
            'y'     => $y
        ];
        return $this;
    }

    /**
     * Add text to the PDF page
     *
     * @param  Page\Text|string $text
     * @param  string           $fontStyle (can be either a reference to a font or a style)
     * @param  int              $x
     * @param  int              $y
     * @return Page
     */
    public function addText(Page\Text|string $text, string $fontStyle, int $x = 0, int $y = 0): Page
    {
        $this->text[] = [
            'text' => (is_string($text)) ? new Page\Text($text) : $text,
            'font' => $fontStyle,
            'x'    => $x,
            'y'    => $y
        ];
        return $this;
    }

    /**
     * Add text to the PDF page
     *
     * @param  Page\Text\Stream $textStream
     * @return Page
     */
    public function addTextStream(Page\Text\Stream $textStream): Page
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
    public function addAnnotation(Annotation\AbstractAnnotation $annotation, int $x = 0, int $y = 0): Page
    {
        $this->annotations[] = [
            'annotation' => $annotation,
            'x'          => $x,
            'y'          => $y
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
    public function addUrl(Annotation\Url $url, int $x = 0, int $y = 0): Page
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
    public function addLink(Annotation\Link $link, int $x = 0, int $y = 0): Page
    {
        return $this->addAnnotation($link, $x, $y);
    }

    /**
     * Add a path to the Pdf page
     *
     * @param  Page\Path $path
     * @return Page
     */
    public function addPath(Page\Path $path): Page
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
    public function addField(Page\Field\AbstractField $field, string $form, int $x = 0, int $y = 0): Page
    {
        $this->fields[] = [
            'field' => $field,
            'form'  => $form,
            'x'     => $x,
            'y'     => $y
        ];
        return $this;
    }

    /**
     * Import page object into the page
     *
     * @param PdfObject\PageObject $page
     * @return Page
     */
    public function importPageObject(PdfObject\PageObject $page): Page
    {
        $this->importedPageObject = $page;
        return $this;
    }

    /**
     * Determine if the document has an imported page object
     *
     * @return bool
     */
    public function hasImportedPageObject(): bool
    {
        return ($this->importedPageObject !== null);
    }

    /**
     * Get the import page object
     *
     * @return ?PdfObject\PageObject
     */
    public function getImportedPageObject(): ?PdfObject\PageObject
    {
        return $this->importedPageObject;
    }

}
