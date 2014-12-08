<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * Pdf document class
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
class Document extends AbstractDocument
{

    /**
     * Imported objects
     * @var array
     */
    protected $importedObjects = [];

    /**
     * Imported fonts
     * @var array
     */
    protected $importedFonts = [];

    /**
     * Constructor
     *
     * Instantiate a PDF document
     *
     * @param  Page     $page
     * @param  Metadata $metadata
     * @return Document
     */
    public function __construct(Page $page = null, Metadata $metadata = null)
    {
        if (null !== $page) {
            $this->addPage($page);
        }
        $metadata = (null !== $metadata) ? $metadata : new Metadata();
        $this->setMetadata($metadata);
    }

    /**
     * Add a page to the PDF document
     *
     * @param  Page $page
     * @return Document
     */
    public function addPage(Page $page)
    {
        $this->pages[]     = $page;
        $this->currentPage = count($this->pages);
        return $this;
    }

    /**
     * Add pages to the PDF document
     *
     * @param  array $pages
     * @return Document
     */
    public function addPages(array $pages)
    {
        foreach ($pages as $page) {
            $this->addPage($page);
        }
        return $this;
    }

    /**
     * Create and return a new page object, adding it to the PDF document
     *
     * @param  mixed $size
     * @param  int   $height
     * @return Page
     */
    public function createPage($size, $height = null)
    {
        $page = new Page($size, $height);
        $this->addPage($page);
        return $page;
    }

    /**
     * Copy and return a page of the PDF, adding it to the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return Page
     */
    public function copyPage($p)
    {
        if (!isset($this->pages[$p - 1])) {
            throw new Exception('Error: That page does not exist.');
        }
        $page = $this->pages[$p - 1];
        $this->addPage($page);
        return $page;
    }

    /**
     * Order the pages
     *
     * @param  array $pages
     * @throws Exception
     * @return Document
     */
    public function orderPages($pages)
    {
        $newOrder = [];

        // Check if the numbers of pages passed equals the number of pages in the PDF.
        if (count($pages) != count($this->pages)) {
            throw new Exception('Error: The pages array passed does not contain the same number of pages as the PDF.');
        }

        // Make sure each page passed is within the PDF and not out of range.
        foreach ($pages as $value) {
            if (!array_key_exists(($value - 1), $this->pages)) {
                throw new Exception('Error: The pages array passed contains a page that does not exist.');
            }
        }

        // Set the new order of the page objects.
        foreach ($pages as $value) {
            $newOrder[] = $this->pages[$value - 1];
        }

        // Set the pages arrays to the new order.
        $this->pages = $newOrder;
        return $this;
    }

    /**
     * Delete a page from the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return Document
     */
    public function deletePage($p)
    {
        if (!isset($this->pages[$p - 1])) {
            throw new Exception('Error: That page does not exist.');
        }

        unset($this->pages[$p - 1]);

        // Reset current page if current page was the one deleted
        if ($this->currentPage == $p) {
            $this->currentPage = (count($this->pages) > 0) ? (count($this->pages) - 1) : null;
        }

        return $this;
    }

    /**
     * Add a font
     *
     * @param  Font $font
     * @throws Exception
     * @return Document
     */
    public function addFont(Font $font)
    {
        if (!$font->isStandard()) {
            throw new Exception('Error: The \'addFont\' method is for adding standard PDF fonts only.');
        }

        if (!array_key_exists($font->getName(), $this->fonts)) {
            $this->fonts[$font->getName()] = $font;
            $this->currentFont = $font->getName();
        }
        return $this;
    }

    /**
     * Add a font
     *
     * @param  Font    $font
     * @param  boolean $embedOverride
     * @throws Exception
     * @return Document
     */
    public function embedFont(Font $font, $embedOverride = false)
    {
        if (!$font->isEmbedded()) {
            throw new Exception('Error: The \'embedFont\' method is for embedding external fonts only.');
        }

        if (!$font->parser()->isEmbeddable() && !$embedOverride) {
            throw new Exception('Error: The font license does not allow for it to be embedded.');
        }

        if (!array_key_exists($font->parser()->getFontName(), $this->fonts)) {
            $font->parser()->setCompression($this->compression);
            $this->fonts[$font->parser()->getFontName()] = $font;
            $this->currentFont = $font->parser()->getFontName();
        }
        return $this;
    }

    /**
     * Set the current page of the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return Document
     */
    public function setCurrentPage($p)
    {
        // Check if the page exists.
        if (!isset($this->pages[$p - 1])) {
            throw new Exception('Error: That page does not exist.');
        }
        $this->currentPage = $p;

        return $this;
    }

    /**
     * Set the current font of the PDF document
     *
     * @param  string $name
     * @throws Exception
     * @return Document
     */
    public function setCurrentFont($name)
    {
        // Check if the font exists.
        if (!isset($this->fonts[$name])) {
            throw new Exception('Error: That font has not been added to the PDF document.');
        }
        $this->currentFont = $name;

        return $this;
    }

    /**
     * Import objects into document
     *
     * @param  array $objects
     * @return Document
     */
    public function importObjects(array $objects)
    {
        $this->importedObjects = $objects;
        return $this;
    }

    /**
     * Import fonts into document
     *
     * @param  array $fonts
     * @return Document
     */
    public function importFonts(array $fonts)
    {
        foreach ($fonts as $font) {
            $this->fonts[$font['name']] = $font;
        }
        $this->importedFonts = $fonts;
        return $this;
    }

    /**
     * Determine if the document has imported objects
     *
     * @return boolean
     */
    public function hasImportedObjects()
    {
        return (count($this->importedObjects) > 0);
    }

    /**
     * Determine if the document has imported fonts
     *
     * @return boolean
     */
    public function hasImportedFonts()
    {
        return (count($this->importedFonts) > 0);
    }

    /**
     * Get the imported objects
     *
     * @return array
     */
    public function getImportObjects()
    {
        return $this->importedObjects;
    }

    /**
     * Get the import fonts
     *
     * @return array
     */
    public function getImportedFonts()
    {
        return $this->importedFonts;
    }

    /**
     * Output the PDF document to string
     *
     * @return string
     */
    public function __toString()
    {
        $compiler = new Build\Compiler();
        $compiler->finalize($this);
        return $compiler->getOutput();
    }

}