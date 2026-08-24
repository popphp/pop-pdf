<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build;

use Pop\Pdf\Document;
use Pop\Pdf\Document\Page\Text;

/**
 * Pdf compiler class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Compiler extends AbstractCompiler
{

    /**
     * Set the document object
     *
     * @param  Document $document
     * @return Compiler
     */
    public function setDocument(Document $document): Compiler
    {
        $this->document = $document;

        foreach ($this->document->getPages() as $key => $page) {
            if (!in_array($page, $this->pages, true)) {
                $this->pages[$key] = $page;
            }
        }

        foreach ($this->document->getFonts() as $key => $font) {
            if (!in_array($font, $this->fonts, true)) {
                $this->fonts[$key] = $font;
            }
        }

        $this->compression = $this->document->isCompressed();

        if ($this->document->hasImportedObjects()) {
            foreach ($this->document->getImportObjects() as $i => $object) {
                if ($object instanceof PdfObject\RootObject) {
                    $this->setRoot($object);
                } else if ($object instanceof PdfObject\ParentObject) {
                    $this->setParent($object);
                } else if ($object instanceof PdfObject\InfoObject) {
                    $this->setInfo($object);
                } else {
                    $this->addObject($i, $object);
                }
            }
        }

        if ($this->root === null) {
            $this->setRoot(new PdfObject\RootObject());
        }
        if ($this->parent === null) {
            $this->setParent(new PdfObject\ParentObject());
        }
        if ($this->info === null) {
            $this->setInfo(new PdfObject\InfoObject());
        }

        $this->root->setVersion($this->document->getVersion());
        $this->info->setMetadata($this->document->getMetadata());

        return $this;
    }

    /**
     * Compile and finalize the PDF document
     *
     * @param  ?Document $document
     * @throws Exception
     * @return void
     */
    public function finalize(?Document $document = null): void
    {
        if ($document !== null) {
            $this->setDocument($document);
        }
        $this->prepareFonts();

        $pageObjects = [];

        foreach ($this->pages as $page) {
            if ($page->hasImportedPageObject()) {
                $pageObject = $page->getImportedPageObject();
                $pageObject->setCurrentContentIndex(null);
                $this->addObject($pageObject->getIndex(), $pageObject);
            } else {
                $page->setIndex($this->lastIndex() + 1);
                $pageObject = new PdfObject\PageObject($page->getWidth(), $page->getHeight(), $page->getIndex());
                $pageObject->setParentIndex($this->parent->getIndex());
                $this->addObject($pageObject->getIndex(), $pageObject);
                $this->parent->addKid($pageObject->getIndex());
            }

            foreach ($this->fontReferences as $fontReference) {
                $pageObject->addFontReference($fontReference);
            }

            // Prepare image objects
            if ($page->hasImages()) {
                $this->prepareImages($page->getImages(), $pageObject);
            }
            // Prepare path objects
            if ($page->hasPaths()) {
                $this->preparePaths($page->getPaths(), $pageObject);
            }
            // Prepare text objects
            if ($page->hasText()) {
                $this->prepareText($page->getText(), $pageObject);
            }
            // Prepare text objects
            if ($page->hasTextStreams()) {
                $this->prepareTextStreams($page->getTextStreams(), $pageObject);
            }
            // Prepare field objects
            if ($page->hasFields()) {
                $this->prepareFields($page->getFields(), $pageObject);
            }

            $pageObjects[$pageObject->getIndex()] = $pageObject;
        }

        // Prepare annotation objects, after the pages have been set
        foreach ($this->pages as $page) {
            if ($page->hasAnnotations()) {
                $this->prepareAnnotations($page->getAnnotations(), $pageObjects[$page->getIndex()]);
            }
        }

        // If the document has forms
        if ($document->hasForms()) {
            $this->prepareForms();
        }

        // Compute each object's byte offset keyed by its real object number
        // rather than assuming objects are inserted in dense, ascending,
        // gapless order - imported/merged documents commonly have gaps
        // (excluded source Root/Info numbers) and PHP array insertion order
        // need not match ascending numeric order. The classical xref table
        // requires row N to correspond exactly to object number N, so the
        // table is built from this offset map afterward, not inline during
        // emission.
        $offsets = [];

        // Intial Length is the length of the version string
        $this->byteLength = 9;
        $offsets[$this->root->getIndex()] = $this->byteLength;

        // New Length is the distance to the second object
        $rootString        = (string)$this->root;
        $this->byteLength  = $this->calculateByteLength($rootString);

        $this->output .= $rootString;

        // Loop through the rest of the objects, calculate their size and length
        // for the xref table and add their data to the output.
        foreach ($this->objects as $object) {
            if ($object->getIndex() != $this->root->getIndex()) {
                if (($object instanceof PdfObject\StreamObject) && ($this->compression) && (!$object->isPalette()) &&
                    (!$object->isEncoded() && !$object->isImported() && (stripos((string)$object->getDefinition(), '/length') === false))) {
                    $object->encode();
                }
                $objectString  = (string)$object;
                $offsets[$object->getIndex()] = $this->byteLength;
                $this->output     .= $objectString;
                $this->byteLength += $this->calculateByteLength($objectString);
            }
        }

        $maxObjNum = max(array_keys($offsets));
        $numObjs   = $maxObjNum + 1;

        $this->trailer = "xref\n0 {$numObjs}\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObjNum; $i++) {
            $this->trailer .= isset($offsets[$i])
                ? $this->formatByteLength($offsets[$i]) . " 00000 n \n"
                : "0000000000 65535 f \n";
        }

        // Finalize the trailer.
        $this->trailer .= "trailer\n<</Size {$numObjs}/Root " . $this->root->getIndex() . " 0 R/Info " .
            $this->info->getIndex() . " 0 R>>\nstartxref\n" . ($this->byteLength) . "\n%%EOF";

        // Append the trailer to the final output.
        $this->output .= $this->trailer;
    }

    /**
     * Prepare the font objects
     *
     * @throws Exception|Font\Exception
     * @return void
     */
    public function prepareFonts(): void
    {
        foreach ($this->fonts as $font) {
            if ($font instanceof \Pop\Pdf\Document\Font) {
                $f = count($this->fontReferences) + 1;
                $i = $this->lastIndex() + 1;

                if ($font->isStandard()) {
                    $this->fontReferences[$font->getName()] = '/MF' . $f . ' ' . $i . ' 0 R';
                    $this->addObject($i, PdfObject\StreamObject::parse(
                        "{$i} 0 obj\n<<\n    /Type /Font\n    /Subtype /Type1\n    /Name /MF{$f}\n    /BaseFont /" .
                        $font->getName() . "\n    /Encoding /WinAnsiEncoding\n>>\nendobj\n\n"
                    ));
                } else {
                    $parser = $font->parser()
                        ->setCompression($this->compression)
                        ->setFontIndex($f)
                        ->setFontObjectIndex($i);

                    if ($font->isCid()) {
                        $parser->setCidFontObjectIndex($i + 1)
                            ->setFontDescIndex($i + 2)
                            ->setFontFileIndex($i + 3)
                            ->setToUnicodeIndex($i + 4);
                    } else {
                        $parser->setFontDescIndex($i + 1)
                            ->setFontFileIndex($i + 2);
                    }

                    $parser->parse();

                    $this->fontReferences[$parser->getFontName()] = $parser->getFontReference();
                    foreach ($parser->getObjects() as $fontObject) {
                        $this->addObject($fontObject->getIndex(), $fontObject);
                    }
                }
            } else if (is_array($font)) {
                $this->fontReferences[$font['name']] = $font['ref'];
            }
        }
    }

    /**
     * Prepare the image objects
     *
     * @param  array $images
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    protected function prepareImages(array $images, PdfObject\PageObject $pageObject): void
    {
        $imgs = [];

        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->addObject($contentObject->getIndex(), $contentObject);
        $pageObject->addContentIndex($contentObject->getIndex());

        // Page::addImage() always appends to $images with a fresh
        // auto-incrementing key, so within a single call every $key here is
        // unique - $imgs (built up only inside this same loop) can never
        // already hold one.
        foreach ($images as $key => $image) {
            $coordinates = $this->getCoordinates($image['x'], $image['y'], $pageObject);
            $i = $this->lastIndex() + 1;
            if ($image['image']->isStream()) {
                $imageParser = Image\Parser::createImageFromStream(
                    $image['image']->getStream(), (int)round($coordinates['x']), (int)round($coordinates['y']),
                    $image['image']->getResizeDimensions(), $image['image']->isPreserveResolution()
                );
            } else {
                $imageParser = Image\Parser::createImageFromFile(
                    $image['image']->getImage(), (int)round($coordinates['x']), (int)round($coordinates['y']),
                    $image['image']->getResizeDimensions(), $image['image']->isPreserveResolution()
                );
            }

            $imageParser->setIndex($i);
            $contentObject->appendStream($imageParser->getStream());
            $pageObject->addXObjectReference($imageParser->getXObject());
            foreach ($imageParser->getObjects() as $oi => $imageObject) {
                $this->addObject($oi, $imageObject);
            }
            $imgs[$key] = $imageParser;
        }
    }

    /**
     * Prepare the path objects
     *
     * @param  array $paths
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    protected function preparePaths(array $paths, PdfObject\PageObject $pageObject): void
    {
        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->addObject($contentObject->getIndex(), $contentObject);
        $pageObject->addContentIndex($contentObject->getIndex());

        foreach ($paths as $path) {
            $stream  = null;
            $streams = $path->getStreams();
            foreach ($streams as $str) {
                $s = $str['stream'];
                if (isset($str['points'])) {
                    foreach ($str['points'] as $points) {
                        $keys = array_keys($points);
                        $coordinates = $this->getCoordinates($points[$keys[0]], $points[$keys[1]], $pageObject);
                        $s = str_replace(
                            ['[{' . $keys[0] . '}]', '[{' . $keys[1] . '}]'], [$coordinates['x'], $coordinates['y']], $s
                        );
                    }
                }
                $stream .= $s;
            }

            $contentObject->appendStream($stream);
        }
    }

    /**
     * Prepare the text objects
     *
     * @param  array $text
     * @param  PdfObject\PageObject $pageObject
     * @throws Exception
     * @return void
     */
    protected function prepareText(array $text, PdfObject\PageObject $pageObject): void
    {
        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->addObject($contentObject->getIndex(), $contentObject);
        $pageObject->addContentIndex($contentObject->getIndex());

        foreach ($text as $txt) {
            if ($this->document->hasStyle($txt['font'])) {
                $style = $this->document->getStyle($txt['font']);
                if ($style->hasSize()) {
                    $txt['text']->setSize($style->getSize());
                }
                if ($style->hasFont()) {
                    $txt['font'] = $style->getFont();
                }
            }
            if (!isset($this->fontReferences[$txt['font']])) {
                throw new Exception('Error: The font \'' . $txt['font'] . '\' has not been added to the document.');
            }

            $fontObject = $this->fonts[$txt['font']] ?? null;
            if ($fontObject instanceof \Pop\Pdf\Document\Font) {
                $txt['text']->setFont($fontObject);
            }

            $coordinates = $this->getCoordinates($txt['x'], $txt['y'], $pageObject);

            // Auto-wrap text by character length
            if ($txt['text']->hasCharWrap()) {
                $font    = $this->fontReferences[$txt['font']];
                $stream  = $txt['text']->startStream($font, $coordinates['x'], $coordinates['y']);
                $stream .= $txt['text']->getPartialStream($font);
                $stream .= $txt['text']->endStream();
                $contentObject->appendStream($stream);
            // Left/right/center align text
            } else if ($txt['text']->hasAlignment()) {
                $strings = $txt['text']->getAlignment()->getStrings($txt['text'], $fontObject, $coordinates['y']);
                foreach ($strings as $string) {
                    $textString = new Text($string['string'], $txt['text']->getSize());
                    if ($fontObject instanceof \Pop\Pdf\Document\Font) {
                        $textString->setFont($fontObject);
                    }
                    $contentObject->appendStream(
                        $textString->getStream($this->fontReferences[$txt['font']], $string['x'], $string['y'])
                    );
                }
            // Left/right wrap text around box boundary
            } else if ($txt['text']->hasWrap()) {
                $strings = $txt['text']->getWrap()->getStrings($txt['text'], $fontObject, $coordinates['y']);
                $stream  = $txt['text']->getColorStream();
                if (!empty($stream)) {
                    $contentObject->appendStream($stream);
                }
                foreach ($strings as $string) {
                    $textString = new Text($string['string'], $txt['text']->getSize());
                    if ($fontObject instanceof \Pop\Pdf\Document\Font) {
                        $textString->setFont($fontObject);
                    }
                    $contentObject->appendStream(
                        $textString->getStream($this->fontReferences[$txt['font']], $string['x'], $string['y'])
                    );
                }
            // Else, just append the text stream
            } else {
                $contentObject->appendStream(
                    $txt['text']->getStream($this->fontReferences[$txt['font']], $coordinates['x'], $coordinates['y'])
                );
            }
        }
    }

    /**
     * Prepare the text streams objects
     *
     * @param  array $textStreams
     * @param  PdfObject\PageObject $pageObject
     * @throws Exception
     * @return void
     */
    protected function prepareTextStreams(array $textStreams, PdfObject\PageObject $pageObject): void
    {
        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->addObject($contentObject->getIndex(), $contentObject);
        $pageObject->addContentIndex($contentObject->getIndex());

        foreach ($textStreams as $txt) {
            $stream = $txt->getStream($this->fonts, $this->fontReferences);
            $contentObject->appendStream($stream);
        }
    }

    /**
     * Prepare the annotation objects
     *
     * @param  array $annotations
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    protected function prepareAnnotations(array $annotations, PdfObject\PageObject $pageObject): void
    {
        foreach ($annotations as $annotation) {
            $i = $this->lastIndex() + 1;
            $pageObject->addAnnotIndex($i);

            $coordinates = $this->getCoordinates($annotation['x'], $annotation['y'], $pageObject);
            if ($annotation['annotation'] instanceof \Pop\Pdf\Document\Page\Annotation\Url) {
                $stream = $annotation['annotation']->getStream($i, $coordinates['x'], $coordinates['y']);
            } else {
                $targetCoordinates = $this->getCoordinates(
                    $annotation['annotation']->getXTarget(), $annotation['annotation']->getYTarget(), $pageObject
                );

                $annotation['annotation']->setXTarget($targetCoordinates['x']);
                $annotation['annotation']->setYTarget($targetCoordinates['y']);
                $stream = $annotation['annotation']->getStream(
                    $i, $coordinates['x'], $coordinates['y'], $pageObject->getIndex(), $this->parent->getKids()
                );
            }
            $this->addObject($i, PdfObject\StreamObject::parse($stream));
        }
    }

    /**
     * Prepare the field objects
     *
     * @param  array $fields
     * @param  PdfObject\PageObject $pageObject
     * @throws Exception
     * @return void
     */
    protected function prepareFields(array $fields, PdfObject\PageObject $pageObject): void
    {
        foreach ($fields as $field) {
            if ($this->document->getForm($field['form']) !== null) {
                if (($field['field']->getFont() !== null) && (!isset($this->fontReferences[$field['field']->getFont()]))) {
                    throw new Exception('Error: The font \'' . $field['field']->getFont() . '\' has not been added to the document.');
                } else if (($field['field']->getFont() !== null) && (isset($this->fontReferences[$field['field']->getFont()]))) {
                    $fontRef = $this->fontReferences[$field['field']->getFont()];
                } else {
                    $fontRef = null;
                }
                $i = $this->lastIndex() + 1;
                $pageObject->addAnnotIndex($i);
                $coordinates = $this->getCoordinates($field['x'], $field['y'], $pageObject);
                $this->document->getForm($field['form'])->addFieldIndex($i);
                $this->addObject($i, PdfObject\StreamObject::parse(
                    $field['field']->getStream($i, $pageObject->getIndex(), $fontRef, $coordinates['x'], $coordinates['y'])
                ));
            }
        }
    }

    /**
     * Prepare the form objects
     *
     * @return void
     */
    protected function prepareForms(): void
    {
        // Per spec, a document's Catalog may only have a single /AcroForm
        // entry, and its value must be a single form dictionary (not an
        // array of them) - so every named Document\Form's field indices are
        // combined into one dictionary object here, rather than compiling
        // each Form into its own object and referencing them all as a list.
        $fieldIndices = [];
        foreach ($this->document->getForms() as $form) {
            $fieldIndices = array_merge($fieldIndices, $form->getFieldIndices());
        }

        $fields = implode(' ', array_map(fn($index) => $index . ' 0 R', $fieldIndices));

        $i = $this->lastIndex() + 1;
        $this->addObject($i, PdfObject\StreamObject::parse("{$i} 0 obj\n<</Fields[{$fields}]>>\nendobj\n\n"));
        $this->root->setFormReferences($i . ' 0 R');
    }

}
