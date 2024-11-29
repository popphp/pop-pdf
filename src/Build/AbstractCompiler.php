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
namespace Pop\Pdf\Build;

use Pop\Pdf\Document;

/**
 * Abstract Pdf compiler class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.1
 */
abstract class AbstractCompiler implements CompilerInterface
{

    /**
     * Root object
     * @var ?PdfObject\RootObject
     */
    protected ?PdfObject\RootObject $root = null;

    /**
     * Parent object
     * @var ?PdfObject\ParentObject
     */
    protected ?PdfObject\ParentObject$parent = null;

    /**
     * Info object
     * @var ?PdfObject\InfoObject
     */
    protected ?PdfObject\InfoObject$info = null;

    /**
     * Document object
     * @var ?Document
     */
    protected ?Document $document = null;

    /**
     * Pages array
     * @var array
     */
    protected array $pages = [];

    /**
     * Objects array
     * @var array
     */
    protected array $objects = [];

    /**
     * Fonts array
     * @var array
     */
    protected array $fonts = [];

    /**
     * Font references
     * @var array
     */
    protected array $fontReferences = [];

    /**
     * Compression property
     * @var bool
     */
    protected bool $compression = true;

    /**
     * PDF byte length
     * @var ?int
     */
    protected ?int $byteLength = null;

    /**
     * PDF document trailer
     * @var ?string
     */
    protected ?string $trailer = null;

    /**
     * PDF document output buffer
     * @var ?string
     */
    protected ?string $output = null;

    /**
     * Get the document object
     *
     * @return ?Document
     */
    public function getDocument(): ?Document
    {
        return $this->document;
    }

    /**
     * Get the root object
     *
     * @return ?PdfObject\RootObject
     */
    public function getRoot(): ?PdfObject\RootObject
    {
        return $this->root;
    }

    /**
     * Get the parent object
     *
     * @return ?PdfObject\ParentObject
     */
    public function getParent(): ?PdfObject\ParentObject
    {
        return $this->parent;
    }

    /**
     * Get the info object
     *
     * @return ?PdfObject\InfoObject
     */
    public function getInfo(): ?PdfObject\InfoObject
    {
        return $this->info;
    }

    /**
     * Get the fonts
     *
     * @return array
     */
    public function getFonts(): array
    {
        return $this->fonts;
    }

    /**
     * Get the font references
     *
     * @return array
     */
    public function getFontReferences(): array
    {
        return $this->fontReferences;
    }

    /**
     * Return the last object index.
     *
     * @return int
     */
    public function lastIndex(): int
    {
        if (count($this->objects) == 0) {
            return 0;
        } else {
            $indices = array_keys($this->objects);
            sort($indices);
            return $indices[count($indices) - 1];
        }
    }

    /**
     * Get the compiled output
     *
     * @return string
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Set the root object
     *
     * @param  PdfObject\RootObject $root
     * @return AbstractCompiler
     */
    protected function setRoot(PdfObject\RootObject $root): AbstractCompiler
    {
        $this->root = $root;
        $this->objects[$this->root->getIndex()] = $this->root;
        return $this;
    }

    /**
     * Set the parent object
     *
     * @param  PdfObject\ParentObject $parent
     * @return AbstractCompiler
     */
    protected function setParent(PdfObject\ParentObject $parent): AbstractCompiler
    {
        $this->parent = $parent;
        $this->objects[$this->parent->getIndex()] = $this->parent;
        return $this;
    }

    /**
     * Set the info object
     *
     * @param  PdfObject\InfoObject $info
     * @return AbstractCompiler
     */
    protected function setInfo(PdfObject\InfoObject $info): AbstractCompiler
    {
        $this->info = $info;
        $this->objects[$this->info->getIndex()] = $this->info;
        return $this;
    }

    /**
     * Calculate byte length
     *
     * @param  ?string $string
     * @return int
     */
    protected function calculateByteLength(?string $string): int
    {
        return strlen((string)$string);
    }

    /**
     * Format byte length
     *
     * @param  int|string $num
     * @return string
     */
    protected function formatByteLength(int|string $num): string
    {
        return sprintf('%010d', $num);
    }

    /**
     * Get coordinates based on document origin
     *
     * @param  int $x
     * @param  int $y
     * @param  PdfObject\PageObject $pageObject
     * @return array
     */
    protected function getCoordinates(int $x, int $y, PdfObject\PageObject $pageObject): array
    {
        $coordinates = ['x' => $x, 'y' => $y];
        $width       = $pageObject->getWidth();
        $height      = $pageObject->getHeight();

        switch ($this->document->getOrigin()) {
            case \Pop\Pdf\Document::ORIGIN_TOP_LEFT:
                $coordinates['y'] = $height - $y;
                break;
            case \Pop\Pdf\Document::ORIGIN_TOP_RIGHT:
                $coordinates['x'] = $width - $x;
                $coordinates['y'] = $height - $y;
                break;
            case \Pop\Pdf\Document::ORIGIN_BOTTOM_RIGHT:
                $coordinates['x'] = $width - $x;
                break;
            case \Pop\Pdf\Document::ORIGIN_CENTER:
                $coordinates['x'] = round($width / 2) + $x;
                $coordinates['y'] = round($height / 2) + $y;
                break;
        }

        return $coordinates;
    }

    /**
     * Set the document object
     *
     * @param  Document\AbstractDocument $document
     * @return Compiler
     */
    abstract public function setDocument(Document\AbstractDocument $document): Compiler;

    /**
     * Compile and finalize the PDF document
     *
     * @param  ?Document\AbstractDocument $document
     * @return void
     */
    abstract public function finalize(Document\AbstractDocument $document = null): void;

    /**
     * Prepare the font objects
     *
     * @return void
     */
    abstract public function prepareFonts(): void;

    /**
     * Prepare the image objects
     *
     * @param  array $images
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    abstract protected function prepareImages(array $images, PdfObject\PageObject $pageObject): void;

    /**
     * Prepare the text objects
     *
     * @param  array $text
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    abstract protected function prepareText(array $text, PdfObject\PageObject $pageObject): void;

    /**
     * Prepare the annotation objects
     *
     * @param  array $annotations
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    abstract protected function prepareAnnotations(array $annotations, PdfObject\PageObject $pageObject): void;

    /**
     * Prepare the path objects
     *
     * @param  array $paths
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    abstract protected function preparePaths(array $paths, PdfObject\PageObject $pageObject): void;

}
