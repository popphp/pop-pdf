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
namespace Pop\Pdf;

use Pop\Pdf\Document\AbstractDocument;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Metadata;
use Pop\Pdf\Document\Exception;
use Pop\Pdf\Document\Style;

/**
 * Pdf document class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.2.0
 */
class Document extends AbstractDocument
{

    /**
     * Imported objects
     * @var array
     */
    protected array $importedObjects = [];

    /**
     * Imported fonts
     * @var array
     */
    protected array $importedFonts = [];

    /**
     * Constructor
     *
     * Instantiate a PDF document
     *
     * @param ?Page            $page
     * @param ?Metadata        $metadata
     * @param Style|array|null $style
     */
    public function __construct(?Page $page = null, ?Metadata $metadata = null, Style|array|null $style = null)
    {
        if ($page !== null) {
            $this->addPage($page);
        }

        $this->setMetadata((($metadata !== null) ? $metadata : new Metadata()));

        if ($style !== null) {
            if (is_array($style)) {
                $this->addStyles($style);
            } else {
                $this->addStyle($style);
            }
        }
    }

    /**
     * Add a page to the PDF document
     *
     * @param  Page $page
     * @return Document
     */
    public function addPage(Page $page): Document
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
    public function addPages(array $pages): Document
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
     * @param  ?int  $height
     * @throws Exception
     * @return Page
     */
    public function createPage(mixed $size, ?int $height = null): Page
    {
        $page = new Page($size, $height);
        $this->addPage($page);
        return $page;
    }

    /**
     * Copy and return a page of the PDF, adding it to the PDF document
     *
     * @param  int  $p
     * @param  bool $preserveContent
     * @throws Exception
     * @return Page
     */
    public function copyPage(int $p, bool $preserveContent = true): Page
    {
        if (!isset($this->pages[$p - 1])) {
            throw new Exception("Error: That page (" . $p . ") does not exist.");
        }

        $page = clone $this->pages[$p - 1];

        if (!$preserveContent) {
            $page->clearContent();
        }

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
    public function orderPages(array $pages): Document
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
    public function deletePage(int $p): Document
    {
        if (!isset($this->pages[$p - 1])) {
            throw new Exception("Error: That page (" . $p . ") does not exist.");
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
     * @param  Font|string $font
     * @param  bool        $embedOverride
     * @throws Exception
     * @return Document
     */
    public function addFont(Font|string $font, bool $embedOverride = false): Document
    {
        if (is_string($font)) {
            $font = new Font($font);
        }
        if (!$font->isStandard()) {
            $this->embedFont($font, $embedOverride);
        } else {
            if (!array_key_exists($font->getName(), $this->fonts)) {
                $this->fonts[$font->getName()] = $font;
                $this->currentFont = $font->getName();
            }
        }

        return $this;
    }

    /**
     * Add fonts
     *
     * @param  array $fonts
     * @param  bool  $embedOverride
     * @throws Exception
     * @return Document
     */
    public function addFonts(array $fonts, bool $embedOverride = false): Document
    {
        foreach ($fonts as $font) {
            $this->addFont($font, $embedOverride);
        }

        return $this;
    }

    /**
     * Add a font
     *
     * @param  Font $font
     * @param  bool $embedOverride
     * @throws Exception
     * @return Document
     */
    public function embedFont(Font $font, bool $embedOverride = false): Document
    {
        if (!$font->isEmbedded()) {
            $this->addFont($font);
        } else {
            if (!$font->parser()->isEmbeddable() && !$embedOverride) {
                throw new Exception('Error: The font license does not allow for it to be embedded.');
            }

            if (!array_key_exists($font->parser()->getFontName(), $this->fonts)) {
                $font->parser()->setCompression($this->compression);
                $this->fonts[$font->parser()->getFontName()] = $font;
                $this->currentFont = $font->parser()->getFontName();
            }
        }

        return $this;
    }

    /**
     * Embed fonts
     *
     * @param  array $fonts
     * @param  bool  $embedOverride
     * @throws Exception
     * @return Document
     */
    public function embedFonts(array $fonts, bool $embedOverride = false): Document
    {
        foreach ($fonts as $font) {
            $this->embedFont($font, $embedOverride);
        }

        return $this;
    }

    /**
     * Create style
     *
     * @param  Style|string $style
     * @return Document
     */
    public function createStyle(Style|string $style, ?string $font = null, int|float|null $size = null): Document
    {
        return ($style instanceof Style) ?
            $this->addStyle($style) : $this->addStyle(new Style($style, $font, $size));
    }

    /**
     * Add a style
     *
     * @param  Style|string $style
     * @return Document
     */
    public function addStyle(Style|string $style): Document
    {
        if (is_string($style)) {
            $style = new Style($style);
        }

        if (!array_key_exists($style->getName(), $this->styles)) {
            $this->styles[$style->getName()] = $style;
        }

        return $this;
    }

    /**
     * Add styles
     *
     * @param  array $styles
     * @return AbstractDocument
     */
    public function addStyles(array $styles): Document
    {
        foreach ($styles as $style) {
            $this->addStyle($style);
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
    public function setCurrentPage(int $p): Document
    {
        // Check if the page exists.
        if (!isset($this->pages[$p - 1])) {
            throw new Exception("Error: That page (" . $p . ") does not exist.");
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
    public function setCurrentFont(string $name): Document
    {
        // Check if the font exists.
        if (!isset($this->fonts[$name])) {
            throw new Exception("Error: The font '" . $name . "' has not been added to the PDF document.");
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
    public function importObjects(array $objects): Document
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
    public function importFonts(array $fonts): Document
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
     * @return bool
     */
    public function hasImportedObjects(): bool
    {
        return (count($this->importedObjects) > 0);
    }

    /**
     * Determine if the document has imported fonts
     *
     * @return bool
     */
    public function hasImportedFonts(): bool
    {
        return (count($this->importedFonts) > 0);
    }

    /**
     * Get the imported objects
     *
     * @return array
     */
    public function getImportObjects(): array
    {
        return $this->importedObjects;
    }

    /**
     * Get the import fonts
     *
     * @return array
     */
    public function getImportedFonts(): array
    {
        return $this->importedFonts;
    }

    /**
     * Output the PDF document to string
     *
     * @return string
     */
    public function __toString(): string
    {
        $compiler = new Build\Compiler();
        $compiler->finalize($this);
        return $compiler->getOutput();
    }

}
