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

/**
 * Abstract Page class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
abstract class AbstractPage implements PageInterface
{

    /**
     * Page size constants
     */
    const ENVELOPE_10 = 'ENVELOPE_10';
    const ENVELOPE_C5 = 'ENVELOPE_C5';
    const ENVELOPE_DL = 'ENVELOPE_DL';
    const FOLIO       = 'FOLIO';
    const EXECUTIVE   = 'EXECUTIVE';
    const LETTER      = 'LETTER';
    const LEGAL       = 'LEGAL';
    const LEDGER      = 'LEDGER';
    const TABLOID     = 'TABLOID';
    const A0          = 'A0';
    const A1          = 'A1';
    const A2          = 'A2';
    const A3          = 'A3';
    const A4          = 'A4';
    const A5          = 'A5';
    const A6          = 'A6';
    const A7          = 'A7';
    const A8          = 'A8';
    const A9          = 'A9';
    const B0          = 'B0';
    const B1          = 'B1';
    const B2          = 'B2';
    const B3          = 'B3';
    const B4          = 'B4';
    const B5          = 'B5';
    const B6          = 'B6';
    const B7          = 'B7';
    const B8          = 'B8';
    const B9          = 'B9';
    const B10         = 'B10';

    /**
     * Array of page sizes
     * @var array
     */
    protected array $sizes = [
        'ENVELOPE_10' => ['width' => 297,  'height' => 684],
        'ENVELOPE_C5' => ['width' => 461,  'height' => 648],
        'ENVELOPE_DL' => ['width' => 312,  'height' => 624],
        'FOLIO'       => ['width' => 595,  'height' => 935],
        'EXECUTIVE'   => ['width' => 522,  'height' => 756],
        'LETTER'      => ['width' => 612,  'height' => 792],
        'LEGAL'       => ['width' => 612,  'height' => 1008],
        'LEDGER'      => ['width' => 1224, 'height' => 792],
        'TABLOID'     => ['width' => 792,  'height' => 1224],
        'A0'          => ['width' => 2384, 'height' => 3370],
        'A1'          => ['width' => 1684, 'height' => 2384],
        'A2'          => ['width' => 1191, 'height' => 1684],
        'A3'          => ['width' => 842,  'height' => 1191],
        'A4'          => ['width' => 595,  'height' => 842],
        'A5'          => ['width' => 420,  'height' => 595],
        'A6'          => ['width' => 297,  'height' => 420],
        'A7'          => ['width' => 210,  'height' => 297],
        'A8'          => ['width' => 148,  'height' => 210],
        'A9'          => ['width' => 105,  'height' => 148],
        'B0'          => ['width' => 2920, 'height' => 4127],
        'B1'          => ['width' => 2064, 'height' => 2920],
        'B2'          => ['width' => 1460, 'height' => 2064],
        'B3'          => ['width' => 1032, 'height' => 1460],
        'B4'          => ['width' => 729,  'height' => 1032],
        'B5'          => ['width' => 516,  'height' => 729],
        'B6'          => ['width' => 363,  'height' => 516],
        'B7'          => ['width' => 258,  'height' => 363],
        'B8'          => ['width' => 181,  'height' => 258],
        'B9'          => ['width' => 127,  'height' => 181],
        'B10'         => ['width' => 91,   'height' => 127]
    ];

    /**
     * Page index if page object represents an imported page
     * @var ?int
     */
    protected ?int $index = null;

    /**
     * Page width
     * @var ?int
     */
    protected ?int $width = null;

    /**
     * Page height
     * @var int
     */
    protected ?int $height = null;

    /**
     * Images array
     * @var array
     */
    protected array $images = [];

    /**
     * Text array
     * @var array
     */
    protected array $text = [];

    /**
     * Text streams array
     * @var array
     */
    protected array $textStreams = [];

    /**
     * Annotations array
     * @var array
     */
    protected array $annotations = [];

    /**
     * Paths array
     * @var array
     */
    protected array $paths = [];

    /**
     * Fields array
     * @var array
     */
    protected array $fields = [];

    /**
     * Set the page width
     *
     * @param  mixed $width
     * @return AbstractPage
     */
    public function setWidth(mixed $width): AbstractPage
    {
        $this->width = (float)$width;
        return $this;
    }

    /**
     * Set the page height
     *
     * @param  mixed $height
     * @return AbstractPage
     */
    public function setHeight(mixed $height): AbstractPage
    {
        $this->height = (float)$height;
        return $this;
    }

    /**
     * Set the page index
     *
     * @param  int $i
     * @return AbstractPage
     */
    public function setIndex(int $i): AbstractPage
    {
        $this->index = $i;
        return $this;
    }

    /**
     * Get the page width
     *
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Get the page height
     *
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Get the page index
     *
     * @return int
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Get image objects
     *
     * @return array
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * Get text objects
     *
     * @return array
     */
    public function getText(): array
    {
        return $this->text;
    }

    /**
     * Get text stream objects
     *
     * @return array
     */
    public function getTextStreams(): array
    {
        return $this->textStreams;
    }

    /**
     * Get annotation objects
     *
     * @return array
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    /**
     * Get path objects
     *
     * @return array
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Get field objects
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Determine if the page has image objects
     *
     * @return bool
     */
    public function hasImages(): bool
    {
        return (count($this->images) > 0);
    }

    /**
     * Determine if the page has text objects
     *
     * @return bool
     */
    public function hasText(): bool
    {
        return (count($this->text) > 0);
    }

    /**
     * Determine if the page has text stream objects
     *
     * @return bool
     */
    public function hasTextStreams(): bool
    {
        return (count($this->textStreams) > 0);
    }

    /**
     * Determine if the page has annotation objects
     *
     * @return bool
     */
    public function hasAnnotations(): bool
    {
        return (count($this->annotations) > 0);
    }

    /**
     * Determine if the page has path objects
     *
     * @return bool
     */
    public function hasPaths(): bool
    {
        return (count($this->paths) > 0);
    }

    /**
     * Determine if the page has field objects
     *
     * @return bool
     */
    public function hasFields(): bool
    {
        return (count($this->fields) > 0);
    }

    /**
     * Clear page content
     *
     * @return AbstractPage
     */
    public function clearContent(): AbstractPage
    {
        $this->images      = [];
        $this->text        = [];
        $this->textStreams = [];
        $this->annotations = [];
        $this->paths       = [];
        $this->fields      = [];

        return $this;
    }

    /**
     * Constructor
     *
     * Instantiate a PDF page.
     *
     * @throws Exception
     * @return AbstractPage
     */
    abstract public function __construct();

    /**
     * Add an image to the PDF page
     *
     * @param  Page\Image $image
     * @param  int        $x
     * @param  int        $y
     * @return Page
     */
    abstract public function addImage(Page\Image $image, int $x = 0, int $y = 0): AbstractPage;

    /**
     * Add text to the PDF page
     *
     * @param  Page\Text $text
     * @param  string    $font
     * @param  int       $x
     * @param  int       $y
     * @return Page
     */
    abstract public function addText(Page\Text $text, string $font, int $x = 0, int $y = 0): AbstractPage;

    /**
     * Add text stream to the PDF page
     *
     * @param  Page\Text\Stream $textStream
     * @return Page
     */
    abstract public function addTextStream(Page\Text\Stream $textStream): Page;

    /**
     * Add an annotation to the PDF page
     *
     * @param  Annotation\AbstractAnnotation $annotation
     * @param  int                           $x
     * @param  int                           $y
     * @return Page
     */
    abstract public function addAnnotation(Annotation\AbstractAnnotation $annotation, int $x = 0, int $y = 0): AbstractPage;

    /**
     * Add a URL annotation to the PDF page
     *
     * @param  Annotation\Url $url
     * @param  int            $x
     * @param  int            $y
     * @return Page
     */
    abstract public function addUrl(Annotation\Url $url, int $x = 0, int $y = 0): AbstractPage;

    /**
     * Add a link annotation to the PDF page
     *
     * @param  Annotation\Link $link
     * @param  int             $x
     * @param  int             $y
     * @return Page
     */
    abstract public function addLink(Annotation\Link $link, int $x = 0, int $y = 0): AbstractPage;

    /**
     * Add a path to the Pdf page
     *
     * @param  Page\Path $path
     * @return Page
     */
    abstract public function addPath(Page\Path $path): AbstractPage;

    /**
     * Add a form to the Pdf page
     *
     * @param  Page\Field\AbstractField $field
     * @param  string                   $form
     * @param  int                      $x
     * @param  int                      $y
     * @return Page
     */
    abstract public function addField(Page\Field\AbstractField $field, string $form, int $x = 0, int $y = 0): Page;

}