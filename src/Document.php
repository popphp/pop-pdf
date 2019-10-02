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
 * Pdf document class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
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
    public function orderPages(array $pages)
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
     * Extract text from the imported objects
     *
     * @param  boolean $coords
     * @param  string  $flatten
     * @return array
     */
    public function extractText($coords = false, $flatten = null)
    {
        $streams     = [];
        $text        = [];
        $pageOriginX = 0;
        $pageOriginY = 0;
        $pageWidth   = 0;
        $pageHeight  = 0;

        if ($this->hasPages()) {
            foreach ($this->pages as $page) {
                if ($page->hasImportedPageObject()) {
                    $pageWidth  = $page->getWidth();
                    $pageHeight = $page->getHeight();
                    break;
                }
            }
        }

        // Try and get the page width and height
        foreach ($this->importedObjects as $i => $object) {
            if ($object instanceof Build\PdfObject\ParentObject) {
                $data     = $object->getData();
                $mediaBox = null;
                if ((strpos($data, '/MediaBox [') !== false) || (strpos($data, '/MediaBox[') !== false)) {
                    $mediaBox = substr($data, (strpos($data, '/MediaBox') + 9));
                    $mediaBox = substr($mediaBox, (strpos($mediaBox, '[') + 1));
                    $mediaBox = trim(substr($mediaBox, 0, strpos($mediaBox, ']')));
                } else if (strpos($data, '/MediaBox ') !== false) {
                    $mediaBoxIndex = substr($data, (strpos($data, '/MediaBox ') + 10));
                    $mediaBoxIndex = trim(substr($mediaBoxIndex, 0, strpos($mediaBoxIndex, ' 0 R')));
                    if (isset($this->importedObjects[$mediaBoxIndex])) {
                        $mediaBox = trim($this->importedObjects[$mediaBoxIndex]->getDefinition());
                        $mediaBox = substr($mediaBox, (strpos($mediaBox, '[') + 1));
                        $mediaBox = trim(substr($mediaBox, 0, strpos($mediaBox, ']')));
                    }
                }

                if (null !== $mediaBox) {
                    list($pageOriginX, $pageOriginY, $pageWidth, $pageHeight) = explode(' ', $mediaBox);
                }
            }

            if ($object instanceof Build\PdfObject\StreamObject) {
                $stream = trim($object->getStream());
                if ((strpos($object->getDefinition(), '/Image') === false) && ($object->getEncoding() == 'FlateDecode')) {
                    $stream = gzuncompress($stream);
                }
                // TJ operator not functioning yet
                if ((preg_match('/\((.*)\)\s*Tj/', $stream) > 0) || (preg_match('/\[(.*)\]\s*TJ/', $stream) > 0)) {
                    $streams[] = $stream;
                }
            }
        }

        if ($coords) {
            foreach ($streams as $i => $string) {
                $text[$i] = [];
                $matches  = [];
                preg_match_all('/BT(.*?)ET$/ms', $string, $matches);

                if (isset($matches[1])) {
                    foreach ($matches[1] as $match) {
                        $match      = trim($match);
                        $curXOffset = 0;
                        $curYOffset = 0;
                        $size       = null;

                        if (strpos($match, 'Tm') !== false) {
                            $matrix = substr($match, 0, strpos($match, 'Tm'));
                            $matrix = trim(substr($matrix, strrpos($matrix, "\n")));
                            $matrix = explode(' ', $matrix);
                            if (count($matrix) == 6) {
                                $curXOffset = $matrix[4];
                                $curYOffset = $matrix[5];
                            }
                        }

                        $lines   = explode("\n", $match);
                        $x       = ($curXOffset <= 0) ? $pageOriginX + $curXOffset : $curXOffset;
                        $y       = ($curYOffset <= 0) ? $pageHeight + $curYOffset : $curYOffset;
                        $offset  = false;
                        $lastTj  = false;

                        foreach ($lines as $l => $line) {
                            if ($l == 8) {
                                $var = 123;
                            }
                            $line = trim($line);
                            if (substr($line, -2) == 'Tf') {
                                list($ref, $size) = explode(' ', $line);
                                $lastTj = false;
                            }

                            if (substr($line, -2) == 'Td') {
                                $offset = true;
                                list($curXOffset, $curYOffset) = explode(' ', $line);
                                $lastTj = false;
                            }

                            $txt = null;
                            // Need to figure out TJ decoding issue
                            if (substr($line, -2) == 'TJ') {
                                $txt = substr($line, (strpos($line, '[') + 1));
                                $txt = substr($txt, 0, strrpos($txt, ']'));

                                $textMatches = [];
                                preg_match_all('/\(([^)]+)\)/', $txt, $textMatches);

                                if (isset($textMatches[1])) {
                                    $txt = implode('', $textMatches[1]);
                                }
                                if (!$lastTj) {
                                    if ($offset) {
                                        $x = $x + $curXOffset;
                                        $y = $y + $curYOffset;
                                    }
                                    $text[$i][] = [
                                        'text' => $txt,
                                        'size' => $size,
                                        'x'    => $x,
                                        'y'    => $y
                                    ];
                                } else {
                                    $index = count($text) - 1;
                                    if (isset($text[$index])) {
                                        $textIndex = count($text[$index]) - 1;
                                        if (isset($text[$index][$textIndex])) {
                                            $text[$index][$textIndex]['text'] .= $txt;
                                        }
                                    }
                                }
                                $lastTj = false;
                            } else if (substr($line, -2) == 'Tj') {
                                $txt = substr($line, (strpos($line, '(') + 1));
                                $txt = substr($txt, 0, strrpos($txt, ')'));
                                $lastTj = true;
                                if ($offset) {
                                    $x = $x + $curXOffset;
                                    $y = $y + $curYOffset;
                                }
                                $text[$i][] = [
                                    'text' => $txt,
                                    'size' => $size,
                                    'x'    => $x,
                                    'y'    => $y
                                ];
                            }
                        }
                    }
                }
            }
        } else {
            foreach ($streams as $i => $string) {
                $matches = [];
                preg_match_all('/\((.*)\)\s*Tj|\[(.*)\]\s*TJ/', $string, $matches);

                if (isset($matches[0])) {
                    $lastTj = false;
                    foreach ($matches[0] as $match) {
                        $txt = null;
                        if (substr($match, -2) == 'TJ') {
                            $txt = substr($match, (strpos($match, '[') + 1));
                            $txt = substr($txt, 0, strrpos($txt, ']'));

                            $textMatches = [];
                            preg_match_all('/\(([^)]+)\)/', $txt, $textMatches);

                            if (isset($textMatches[1])) {
                                $txt = implode('', $textMatches[1]);
                            }

                            if (!empty($txt)) {
                                if ($lastTj) {
                                    $index = count($text) - 1;
                                    if (isset($text[$index])) {
                                        $textIndex = count($text[$index]) - 1;
                                        if (isset($text[$index][$textIndex])) {
                                            $text[$index][$textIndex] .= $txt;
                                        }
                                    }
                                } else {
                                    $text[$i][] = $txt;
                                }
                            }
                            $lastTj = false;

                        } else if (substr($match, -2) == 'Tj') {
                            $txt = substr($match, (strpos($match, '(') + 1));
                            $txt = substr($txt, 0, strrpos($txt, ')'));
                            $lastTj = true;
                            if (!empty($txt)) {
                                $text[$i][] = $txt;
                            }
                        }
                    }
                }

                if (null !== $flatten) {
                    $text[$i] = implode($flatten, $text[$i]);
                }
            }
        }

        return $text;
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