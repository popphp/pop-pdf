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

/**
 * Abstract Pdf document class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
abstract class AbstractDocument implements DocumentInterface
{

    /**
     * Document origin constants
     */
    const ORIGIN_TOP_LEFT     = 'ORIGIN_TOP_LEFT';
    const ORIGIN_TOP_RIGHT    = 'ORIGIN_TOP_RIGHT';
    const ORIGIN_BOTTOM_LEFT  = 'ORIGIN_BOTTOM_LEFT';
    const ORIGIN_BOTTOM_RIGHT = 'ORIGIN_BOTTOM_RIGHT';
    const ORIGIN_CENTER       = 'ORIGIN_CENTER';

    /**
     * PDF version
     * @var float
     */
    protected float $version = 1.7;

    /**
     * PDF metadata for the info object
     * @var ?Metadata
     */
    protected ?Metadata $metadata = null;

    /**
     * Pages array
     * @var array
     */
    protected array $pages = [];

    /**
     * Fonts array
     * @var array
     */
    protected array $fonts = [];

    /**
     * Styles array
     * @var array
     */
    protected array $styles = [];

    /**
     * Forms array
     * @var array
     */
    protected array $forms = [];

    /**
     * Current page
     * @var ?int
     */
    protected ?int $currentPage = null;

    /**
     * Current font
     * @var ?string
     */
    protected ?string $currentFont = null;

    /**
     * Compression property
     * @var bool
     */
    protected bool$compression = false;

    /**
     * Document origin
     * @var string
     */
    protected string $origin = 'ORIGIN_BOTTOM_LEFT';

    /**
     * Set the document version
     *
     * @param  float $version
     * @return AbstractDocument
     */
    public function setVersion(float $version): AbstractDocument
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Set the document metadata
     *
     * @param  Metadata $metadata
     * @return AbstractDocument
     */
    public function setMetadata(Metadata $metadata): AbstractDocument
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Set the document origin
     *
     * @param  string $origin
     * @return AbstractDocument
     */
    public function setOrigin(string $origin): AbstractDocument
    {
        if (defined('Pop\Pdf\Document\AbstractDocument::' . $origin)) {
            $this->origin = $origin;
        }
        return $this;
    }

    /**
     * Get the document version
     *
     * @return float
     */
    public function getVersion(): float
    {
        return $this->version;
    }

    /**
     * Get the document origin
     *
     * @return string
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * Get the document metadata
     *
     * @return ?Metadata
     */
    public function getMetadata(): ?Metadata
    {
        return $this->metadata;
    }

    /**
     * Get the PDF page objects array
     *
     * @return array
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * Get a PDF page object
     *
     * @param  int $p
     * @throws \Pop\Pdf\Exception
     * @return Page
     */
    public function getPage(int $p): Page
    {
        if (!isset($this->pages[$p - 1])) {
            throw new \Pop\Pdf\Exception('Error: That page (' . $p . ') does not exist.');
        }
        return $this->pages[$p - 1];
    }

    /**
     * Determine if the document has page objects
     *
     * @return bool
     */
    public function hasPages(): bool
    {
        return (count($this->pages) > 0);
    }

    /**
     * Get the PDF font objects array
     *
     * @return array
     */
    public function getFonts(): array
    {
        return $this->fonts;
    }

    /**
     * Get a PDF font object
     *
     * @param  string $name
     * @throws \Pop\Pdf\Exception
     * @return Font
     */
    public function getFont(string $name): Font
    {
        if (!isset($this->fonts[$name])) {
            throw new \Pop\Pdf\Exception("Error: The font '" . $name . "' has not been added to the PDF document.");
        }
        return $this->fonts[$name];
    }

    /**
     * Determine if the document has font objects
     *
     * @return bool
     */
    public function hasFonts(): bool
    {
        return (count($this->fonts) > 0);
    }

    /**
     * Get available fonts that have been added to the PDF document
     *
     * @return array
     */
    public function getAvailableFonts(): array
    {
        return array_keys($this->fonts);
    }

    /**
     * Determine if a font has been added to the PDF document
     *
     * @param  string $font
     * @return bool
     */
    public function isFontAvailable(string $font): bool
    {
        return array_key_exists($font, $this->fonts);
    }

    /**
     * Determine if a font has been added to the PDF document (alias)
     *
     * @param  string $font
     * @return bool
     */
    public function hasFont(string $font): bool
    {
        return array_key_exists($font, $this->fonts);
    }

    /**
     * Get the PDF style objects array
     *
     * @return array
     */
    public function getStyles(): array
    {
        return $this->styles;
    }

    /**
     * Get a PDF style object
     *
     * @param  string $name
     * @throws \Pop\Pdf\Exception
     * @return Style
     */
    public function getStyle(string $name): Style
    {
        if (!isset($this->styles[$name])) {
            throw new \Pop\Pdf\Exception("Error: The style '" . $name . "' has not been added to the PDF document.");
        }
        return $this->styles[$name];
    }

    /**
     * Determine if the document has style objects
     *
     * @return bool
     */
    public function hasStyles(): bool
    {
        return (count($this->styles) > 0);
    }

    /**
     * Get available styles that have been added to the PDF document
     *
     * @return array
     */
    public function getAvailableStyles(): array
    {
        return array_keys($this->styles);
    }

    /**
     * Determine if a style has been added to the PDF document
     *
     * @param  string $style
     * @return bool
     */
    public function isStyleAvailable(string $style): bool
    {
        return array_key_exists($style, $this->styles);
    }

    /**
     * Determine if a style has been added to the PDF document (alias)
     *
     * @param  string $style
     * @return bool
     */
    public function hasStyle(string $style): bool
    {
        return array_key_exists($style, $this->styles);
    }

    /**
     * Get the current page number
     *
     * @return ?int
     */
    public function getCurrentPage(): ?int
    {
        return $this->currentPage;
    }

    /**
     * Get the current number of pages
     *
     * @return int
     */
    public function getNumberOfPages(): int
    {
        return count($this->pages);
    }

    /**
     * Get the current font
     *
     * @return ?string
     */
    public function getCurrentFont(): ?string
    {
        return $this->currentFont;
    }

    /**
     * Get the current number of fonts
     *
     * @return int
     */
    public function getNumberOfFonts(): int
    {
        return count($this->fonts);
    }

    /**
     * Get form objects
     *
     * @return array
     */
    public function getForms(): array
    {
        return $this->forms;
    }

    /**
     * Get form objects
     *
     * @param  string $name
     * @return ?Form
     */
    public function getForm(string $name): ?Form
    {
        return (isset($this->forms[$name])) ? $this->forms[$name] : null;
    }

    /**
     * Determine if the document has form objects
     *
     * @return bool
     */
    public function hasForms(): bool
    {
        return (count($this->forms) > 0);
    }

    /**
     * Add form
     *
     * @param  Form $form
     * @return AbstractDocument
     */
    public function addForm(Form $form): AbstractDocument
    {
        $this->forms[$form->getName()] = $form;
        return $this;
    }

    /**
     * Set the compression
     *
     * @param  bool $compression
     * @return AbstractDocument
     */
    public function setCompression(bool $compression): AbstractDocument
    {
        $this->compression = $compression;
        return $this;
    }

    /**
     * Determine whether the PDF is compressed or not
     *
     * @return bool
     */
    public function isCompressed(): bool
    {
        return $this->compression;
    }

    /**
     * Constructor
     *
     * Instantiate a PDF document
     *
     * @param  ?Page     $page
     * @param  ?Metadata $metadata
     * @return AbstractDocument
     */
    abstract public function __construct(?Page $page = null, ?Metadata $metadata = null);

    /**
     * Add a page to the PDF document
     *
     * @param  Page $page
     * @return AbstractDocument
     */
    abstract public function addPage(Page $page): AbstractDocument;

    /**
     * Add pages to the PDF document
     *
     * @param  array $pages
     * @return AbstractDocument
     */
    abstract public function addPages(array $pages): AbstractDocument;

    /**
     * Create and return a new page object, adding it to the PDF document
     *
     * @param  mixed $size
     * @param  ?int   $height
     * @return Page
     */
    abstract public function createPage(mixed $size, ?int $height = null): Page;

    /**
     * Copy and return a page of the PDF, adding it to the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return Page
     */
    abstract public function copyPage(int $p): Page;

    /**
     * Order the pages
     *
     * @param  array $pages
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function orderPages(array $pages): AbstractDocument;

    /**
     * Delete a page from the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function deletePage(int $p): AbstractDocument;

    /**
     * Add a font
     *
     * @param  Font $font
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function addFont(Font $font): AbstractDocument;

    /**
     * Add a font
     *
     * @param  Font $font
     * @param  bool $embedOverride
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function embedFont(Font $font, bool $embedOverride = false): AbstractDocument;

    /**
     * Set the current page of the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function setCurrentPage(int $p): AbstractDocument;

    /**
     * Set the current font of the PDF document
     *
     * @param  string $name
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function setCurrentFont(string $name): AbstractDocument;

    /**
     * Output the PDF document to string
     *
     * @return string
     */
    abstract public function __toString(): string;

}
