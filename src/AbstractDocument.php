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
namespace Pop\Pdf;

use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Metadata;

/**
 * Abstract Pdf document class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
abstract class AbstractDocument
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
    protected $version = 1.7;

    /**
     * PDF metadata for the info object
     * @var Document\Metadata
     */
    protected $metadata = null;

    /**
     * Pages array
     * @var array
     */
    protected $pages = [];

    /**
     * Fonts array
     * @var array
     */
    protected $fonts = [];

    /**
     * Forms array
     * @var array
     */
    protected $forms = [];

    /**
     * Current page
     * @var int
     */
    protected $currentPage = null;

    /**
     * Current font
     * @var string
     */
    protected $currentFont = null;

    /**
     * Compression property
     * @var boolean
     */
    protected $compression = false;

    /**
     * Document origin
     * @var string
     */
    protected $origin = 'ORIGIN_BOTTOM_LEFT';

    /**
     * Set the document version
     *
     * @param  float $version
     * @return AbstractDocument
     */
    public function setVersion($version)
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
    public function setMetadata(Metadata $metadata)
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
    public function setOrigin($origin)
    {
        if (defined('Pop\Pdf\AbstractDocument::' . $origin)) {
            $this->origin = $origin;
        }
        return $this;
    }

    /**
     * Get the document version
     *
     * @return float
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get the document origin
     *
     * @return string
     */
    public function getOrigin()
    {
        return $this->origin;
    }

    /**
     * Get the document metadata
     *
     * @return Metadata
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Get the PDF page objects array
     *
     * @return array
     */
    public function getPages()
    {
        return $this->pages;
    }

    /**
     * Get a PDF page object
     *
     * @param  int $p
     * @throws Exception
     * @return Page
     */
    public function getPage($p)
    {
        if (!isset($this->pages[$p - 1])) {
            throw new Exception('Error: That page does not exist.');
        }
        return $this->pages[$p - 1];
    }

    /**
     * Determine if the document has page objects
     *
     * @return boolean
     */
    public function hasPages()
    {
        return (count($this->pages) > 0);
    }

    /**
     * Get the PDF font objects array
     *
     * @return array
     */
    public function getFonts()
    {
        return $this->fonts;
    }

    /**
     * Get a PDF font object
     *
     * @param  string $name
     * @throws Exception
     * @return Font
     */
    public function getFont($name)
    {
        if (!isset($this->fonts[$name])) {
            throw new Exception('Error: That font has not been added to the PDF document.');
        }
        return $this->fonts[$name];
    }

    /**
     * Determine if the document has font objects
     *
     * @return boolean
     */
    public function hasFonts()
    {
        return (count($this->fonts) > 0);
    }

    /**
     * Get available fonts that have been added to the PDF document
     *
     * @return array
     */
    public function getAvailableFonts()
    {
        return array_keys($this->fonts);
    }

    /**
     * Determine if a font has been added to the PDF document
     *
     * @param  string $font
     * @return boolean
     */
    public function isFontAvailable($font)
    {
        return array_key_exists($font, $this->fonts);
    }

    /**
     * Determine if a font has been added to the PDF document (alias)
     *
     * @param  string $font
     * @return boolean
     */
    public function hasFont($font)
    {
        return array_key_exists($font, $this->fonts);
    }

    /**
     * Get the current page number
     *
     * @return int
     */
    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * Get the current number of pages
     *
     * @return int
     */
    public function getNumberOfPages()
    {
        return count($this->pages);
    }

    /**
     * Get the current font
     *
     * @return string
     */
    public function getCurrentFont()
    {
        return $this->currentFont;
    }

    /**
     * Get the current number of fonts
     *
     * @return int
     */
    public function getNumberOfFonts()
    {
        return count($this->fonts);
    }

    /**
     * Get form objects
     *
     * @return array
     */
    public function getForms()
    {
        return $this->forms;
    }

    /**
     * Get form objects
     *
     * @param string $name
     * @return Document\Form
     */
    public function getForm($name)
    {
        return (isset($this->forms[$name])) ? $this->forms[$name] : null;
    }

    /**
     * Determine if the document has form objects
     *
     * @return boolean
     */
    public function hasForms()
    {
        return (count($this->forms) > 0);
    }

    /**
     * Add form
     *
     * @param  Document\Form $form
     * @return AbstractDocument
     */
    public function addForm(Document\Form $form)
    {
        $this->forms[$form->getName()] = $form;
        return $this;
    }

    /**
     * Set the compression
     *
     * @param  boolean $compression
     * @return AbstractDocument
     */
    public function setCompression($compression)
    {
        $this->compression = (bool)$compression;
        return $this;
    }

    /**
     * Determine whether the PDF is compressed or not
     *
     * @return boolean
     */
    public function isCompressed()
    {
        return $this->compression;
    }

    /**
     * Constructor
     *
     * Instantiate a PDF document
     *
     * @param  Page     $page
     * @param  Metadata $metadata
     * @return AbstractDocument
     */
    abstract public function __construct(Page $page = null, Metadata $metadata = null);

    /**
     * Add a page to the PDF document
     *
     * @param  Page $page
     * @return AbstractDocument
     */
    abstract public function addPage(Page $page);

    /**
     * Add pages to the PDF document
     *
     * @param  array $pages
     * @return AbstractDocument
     */
    abstract public function addPages(array $pages);

    /**
     * Create and return a new page object, adding it to the PDF document
     *
     * @param  mixed $size
     * @param  int   $height
     * @return Page
     */
    abstract public function createPage($size, $height = null);

    /**
     * Copy and return a page of the PDF, adding it to the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return Page
     */
    abstract public function copyPage($p);

    /**
     * Order the pages
     *
     * @param  array $pages
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function orderPages(array $pages);

    /**
     * Delete a page from the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function deletePage($p);

    /**
     * Add a font
     *
     * @param  Font $font
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function addFont(Font $font);

    /**
     * Add a font
     *
     * @param  Font    $font
     * @param  boolean $embedOverride
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function embedFont(Font $font, $embedOverride = false);

    /**
     * Set the current page of the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function setCurrentPage($p);

    /**
     * Set the current font of the PDF document
     *
     * @param  string $name
     * @throws Exception
     * @return AbstractDocument
     */
    abstract public function setCurrentFont($name);

    /**
     * Output the PDF document to string
     *
     * @return string
     */
    abstract public function __toString();

}