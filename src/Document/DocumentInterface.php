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

/**
 * Pdf document interface
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.1
 */
interface DocumentInterface
{

    /**
     * Set the document version
     *
     * @param  float $version
     * @return DocumentInterface
     */
    public function setVersion(float $version): DocumentInterface;

    /**
     * Set the document metadata
     *
     * @param  Metadata $metadata
     * @return DocumentInterface
     */
    public function setMetadata(Metadata $metadata): DocumentInterface;

    /**
     * Set the document origin
     *
     * @param  string $origin
     * @return DocumentInterface
     */
    public function setOrigin(string $origin): DocumentInterface;

    /**
     * Get the document version
     *
     * @return float
     */
    public function getVersion(): float;

    /**
     * Get the document origin
     *
     * @return string
     */
    public function getOrigin(): string;

    /**
     * Get the document metadata
     *
     * @return ?Metadata
     */
    public function getMetadata(): ?Metadata;

    /**
     * Get the PDF page objects array
     *
     * @return array
     */
    public function getPages(): array;

    /**
     * Get a PDF page object
     *
     * @param  int $p
     * @throws \Pop\Pdf\Exception
     * @return Page
     */
    public function getPage(int $p): Page;

    /**
     * Determine if the document has page objects
     *
     * @return bool
     */
    public function hasPages(): bool;

    /**
     * Get the PDF font objects array
     *
     * @return array
     */
    public function getFonts(): array;

    /**
     * Get a PDF font object
     *
     * @param  string $name
     * @throws \Pop\Pdf\Exception
     * @return Font
     */
    public function getFont(string $name): Font;

    /**
     * Determine if the document has font objects
     *
     * @return bool
     */
    public function hasFonts(): bool;

    /**
     * Get available fonts that have been added to the PDF document
     *
     * @return array
     */
    public function getAvailableFonts(): array;

    /**
     * Determine if a font has been added to the PDF document
     *
     * @param  string $font
     * @return bool
     */
    public function isFontAvailable(string $font): bool;

    /**
     * Determine if a font has been added to the PDF document (alias)
     *
     * @param  string $font
     * @return bool
     */
    public function hasFont(string $font): bool;

    /**
     * Get the current page number
     *
     * @return ?int
     */
    public function getCurrentPage(): ?int;

    /**
     * Get the current number of pages
     *
     * @return int
     */
    public function getNumberOfPages(): int;

    /**
     * Get the current font
     *
     * @return ?string
     */
    public function getCurrentFont(): ?string;

    /**
     * Get the current number of fonts
     *
     * @return int
     */
    public function getNumberOfFonts(): int;

    /**
     * Get form objects
     *
     * @return array
     */
    public function getForms(): array;

    /**
     * Get form objects
     *
     * @param  string $name
     * @return ?Form
     */
    public function getForm(string $name): ?Form;

    /**
     * Determine if the document has form objects
     *
     * @return bool
     */
    public function hasForms(): bool;

    /**
     * Add form
     *
     * @param  Form $form
     * @return DocumentInterface
     */
    public function addForm(Form $form): DocumentInterface;

    /**
     * Set the compression
     *
     * @param  bool $compression
     * @return DocumentInterface
     */
    public function setCompression(bool $compression): DocumentInterface;

    /**
     * Determine whether the PDF is compressed or not
     *
     * @return bool
     */
    public function isCompressed(): bool;

    /**
     * Constructor
     *
     * Instantiate a PDF document
     *
     * @param  ?Page     $page
     * @param  ?Metadata $metadata
     * @return DocumentInterface
     */
    public function __construct(?Page $page = null, ?Metadata $metadata = null);

    /**
     * Add a page to the PDF document
     *
     * @param  Page $page
     * @return DocumentInterface
     */
    public function addPage(Page $page): DocumentInterface;

    /**
     * Add pages to the PDF document
     *
     * @param  array $pages
     * @return DocumentInterface
     */
    public function addPages(array $pages): DocumentInterface;

    /**
     * Create and return a new page object, adding it to the PDF document
     *
     * @param  mixed $size
     * @param  ?int   $height
     * @return Page
     */
    public function createPage(mixed $size, ?int $height = null): Page;

    /**
     * Copy and return a page of the PDF, adding it to the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return Page
     */
    public function copyPage(int $p): Page;

    /**
     * Order the pages
     *
     * @param  array $pages
     * @throws Exception
     * @return DocumentInterface
     */
    public function orderPages(array $pages): DocumentInterface;

    /**
     * Delete a page from the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return DocumentInterface
     */
    public function deletePage(int $p): DocumentInterface;

    /**
     * Add a font
     *
     * @param  Font $font
     * @throws Exception
     * @return DocumentInterface
     */
    public function addFont(Font $font): DocumentInterface;

    /**
     * Add fonts
     *
     * @param  array $fonts
     * @throws Exception
     * @return AbstractDocument
     */
    public function addFonts(array $fonts): DocumentInterface;

    /**
     * Add a font
     *
     * @param  Font $font
     * @param  bool $embedOverride
     * @throws Exception
     * @return DocumentInterface
     */
    public function embedFont(Font $font, bool $embedOverride = false): DocumentInterface;

    /**
     * Embed fonts
     *
     * @param  array $fonts
     * @param  bool $embedOverride
     * @throws Exception
     * @return DocumentInterface
     */
    public function embedFonts(array $fonts, bool $embedOverride = false): DocumentInterface;

    /**
     * Create style
     *
     * @param  Style|string $style
     * @return DocumentInterface
     */
    public function createStyle(Style|string $style, ?string $font = null, int|float|null $size = null): DocumentInterface;

    /**
     * Add a style
     *
     * @param  Style|string $style
     * @return DocumentInterface
     */
    public function addStyle(Style|string $style): DocumentInterface;

    /**
     * Add styles
     *
     * @param  array $styles
     * @return DocumentInterface
     */
    public function addStyles(array $styles): DocumentInterface;

    /**
     * Set the current page of the PDF document
     *
     * @param  int $p
     * @throws Exception
     * @return DocumentInterface
     */
    public function setCurrentPage(int $p): DocumentInterface;

    /**
     * Set the current font of the PDF document
     *
     * @param  string $name
     * @throws Exception
     * @return DocumentInterface
     */
    public function setCurrentFont(string $name): DocumentInterface;

    /**
     * Output the PDF document to string
     *
     * @return string
     */
    public function __toString(): string;

}
