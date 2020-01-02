<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build;

use Pop\Pdf\Build\Image;
use Pop\Pdf\Build\PdfObject;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page\Text;

/**
 * Pdf compiler class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Compiler extends AbstractCompiler
{

    /**
     * Set the document object
     *
     * @param  Document\AbstractDocument $document
     * @return Compiler
     */
    public function setDocument(Document\AbstractDocument $document)
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
                    $this->objects[$i] = $object;
                }
            }
        }

        if (null === $this->root) {
            $this->setRoot(new PdfObject\RootObject());
        }
        if (null === $this->parent) {
            $this->setParent(new PdfObject\ParentObject());
        }
        if (null === $this->info) {
            $this->setInfo(new PdfObject\InfoObject());
        }

        $this->root->setVersion($this->document->getVersion());
        $this->info->setMetadata($this->document->getMetadata());

        return $this;
    }

    /**
     * Compile and finalize the PDF document
     *
     * @param  Document\AbstractDocument $document
     * @return void
     */
    public function finalize(Document\AbstractDocument $document = null)
    {
        if (null !== $document) {
            $this->setDocument($document);
        }
        $this->prepareFonts();

        $pageObjects = [];

        foreach ($this->pages as $page) {
            if ($page->hasImportedPageObject()) {
                $pageObject = $page->getImportedPageObject();
                $pageObject->setCurrentContentIndex(null);
                $this->objects[$pageObject->getIndex()] = $pageObject;
            } else {
                $page->setIndex($this->lastIndex() + 1);
                $pageObject = new PdfObject\PageObject($page->getWidth(), $page->getHeight(), $page->getIndex());
                $pageObject->setParentIndex($this->parent->getIndex());
                $this->objects[$pageObject->getIndex()] = $pageObject;
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

        $numObjs       = count($this->objects) + 1;
        $this->trailer = "xref\n0 {$numObjs}\n0000000000 65535 f \n";

        $this->byteLength += $this->calculateByteLength($this->root);
        $this->trailer    .= $this->formatByteLength($this->byteLength) . " 00000 n \n";

        $this->output .= $this->root;

        // Loop through the rest of the objects, calculate their size and length
        // for the xref table and add their data to the output.
        foreach ($this->objects as $object) {
            if ($object->getIndex() != $this->root->getIndex()) {
                if (($object instanceof PdfObject\StreamObject) && ($this->compression) && (!$object->isPalette()) &&
                    (!$object->isEncoded() && !$object->isImported() && (stripos($object->getDefinition(), '/length') === false))) {
                    $object->encode();
                }
                $this->byteLength += $this->calculateByteLength($object);
                $this->trailer    .= $this->formatByteLength($this->byteLength) . " 00000 n \n";
                $this->output     .= $object;
            }
        }

        // Finalize the trailer.
        $this->trailer .= "trailer\n<</Size {$numObjs}/Root " . $this->root->getIndex() . " 0 R/Info " .
            $this->info->getIndex() . " 0 R>>\nstartxref\n" . ($this->byteLength + 68) . "\n%%EOF";

        // Append the trailer to the final output.
        $this->output .= $this->trailer;
    }

    /**
     * Prepare the font objects
     *
     * @return void
     */
    public function prepareFonts()
    {
        foreach ($this->fonts as $font) {
            if ($font instanceof \Pop\Pdf\Document\Font) {
                $f = count($this->fontReferences) + 1;
                $i = $this->lastIndex() + 1;

                if ($font->isStandard()) {
                    $this->fontReferences[$font->getName()] = '/MF' . $f . ' ' . $i . ' 0 R';
                    $this->objects[$i] = PdfObject\StreamObject::parse(
                        "{$i} 0 obj\n<<\n    /Type /Font\n    /Subtype /Type1\n    /Name /MF{$f}\n    /BaseFont /" .
                        $font->getName() . "\n    /Encoding /WinAnsiEncoding\n>>\nendobj\n\n"
                    );
                } else {
                    $font->parser()
                        ->setCompression($this->compression)
                        ->setFontIndex($f)
                        ->setFontObjectIndex($i)
                        ->setFontDescIndex($i + 1)
                        ->setFontFileIndex($i + 2);

                    $font->parser()->parse();

                    $this->fontReferences[$font->parser()->getFontName()] = $font->parser()->getFontReference();
                    foreach ($font->parser()->getObjects() as $fontObject) {
                        $this->objects[$fontObject->getIndex()] = $fontObject;
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
    protected function prepareImages(array $images, PdfObject\PageObject $pageObject)
    {
        $imgs = [];

        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->objects[$contentObject->getIndex()] = $contentObject;
        $pageObject->addContentIndex($contentObject->getIndex());

        foreach ($images as $key => $image) {
            $coordinates = $this->getCoordinates($image['x'], $image['y'], $pageObject);
            if (!array_key_exists($key, $imgs)) {
                $i = $this->lastIndex() + 1;
                if ($image['image']->isStream()) {
                    $imageParser = Image\Parser::createImageFromStream(
                        $image['image']->getStream(), $coordinates['x'], $coordinates['y'],
                        $image['image']->getResizeDimensions(), $image['image']->isPreserveResolution()
                    );
                } else {
                    $imageParser = Image\Parser::createImageFromFile(
                        $image['image']->getImage(), $coordinates['x'], $coordinates['y'],
                        $image['image']->getResizeDimensions(), $image['image']->isPreserveResolution()
                    );
                }

                $imageParser->setIndex($i);
                $contentObject->appendStream($imageParser->getStream());
                $pageObject->addXObjectReference($imageParser->getXObject());
                foreach ($imageParser->getObjects() as $oi => $imageObject) {
                    $this->objects[$oi] = $imageObject;
                }
                $imgs[$key] = $imageParser;
            } else {
                $imgs[$key]->setX($coordinates['x']);
                $imgs[$key]->setY($coordinates['y']);
                $contentObject->appendStream($imgs[$key]->getStream());
            }
        }
    }

    /**
     * Prepare the path objects
     *
     * @param  array $paths
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    protected function preparePaths(array $paths, PdfObject\PageObject $pageObject)
    {
        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->objects[$contentObject->getIndex()] = $contentObject;
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
    protected function prepareText(array $text, PdfObject\PageObject $pageObject)
    {
        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->objects[$contentObject->getIndex()] = $contentObject;
        $pageObject->addContentIndex($contentObject->getIndex());

        foreach ($text as $txt) {
            if (!isset($this->fontReferences[$txt['font']])) {
                throw new Exception('Error: The font \'' . $txt['font'] . '\' has not been added to the document.');
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
                $fontObject = $this->fonts[$txt['font']];
                $strings    = $txt['text']->getAlignment()->getStrings($txt['text'], $fontObject, $coordinates['y']);
                foreach ($strings as $string) {
                    $textString = new Text($string['string'], $txt['text']->getSize());
                    $contentObject->appendStream(
                        $textString->getStream($this->fontReferences[$txt['font']], $string['x'], $string['y'])
                    );
                }
            // Left/right wrap text around box boundary
            } else if ($txt['text']->hasWrap()) {
                $fontObject = $this->fonts[$txt['font']];
                $strings    = $txt['text']->getWrap()->getStrings($txt['text'], $fontObject, $coordinates['y']);
                $stream     = $txt['text']->getColorStream();
                if (!empty($stream)) {
                    $contentObject->appendStream($stream);
                }
                foreach ($strings as $string) {
                    $textString = new Text($string['string'], $txt['text']->getSize());
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
    protected function prepareTextStreams(array $textStreams, PdfObject\PageObject $pageObject)
    {
        $contentObject = new PdfObject\StreamObject($this->lastIndex() + 1);
        $this->objects[$contentObject->getIndex()] = $contentObject;
        $pageObject->addContentIndex($contentObject->getIndex());

        foreach ($textStreams as $txt) {
            $contentObject->appendStream($txt->getStream($this->fonts, $this->fontReferences));
        }
    }

    /**
     * Prepare the annotation objects
     *
     * @param  array $annotations
     * @param  PdfObject\PageObject $pageObject
     * @return void
     */
    protected function prepareAnnotations(array $annotations, PdfObject\PageObject $pageObject)
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
            $this->objects[$i] = PdfObject\StreamObject::parse($stream);
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
    protected function prepareFields(array $fields, PdfObject\PageObject $pageObject)
    {
        foreach ($fields as $field) {
            if (null !== $this->document->getForm($field['form'])) {
                if ((null !== $field['field']->getFont()) && (!isset($this->fontReferences[$field['field']->getFont()]))) {
                    throw new Exception('Error: The font \'' . $field['field']->getFont() . '\' has not been added to the document.');
                } else if ((null !== $field['field']->getFont()) && (isset($this->fontReferences[$field['field']->getFont()]))) {
                    $fontRef = $this->fontReferences[$field['field']->getFont()];
                } else {
                    $fontRef = null;
                }
                $i = $this->lastIndex() + 1;
                $pageObject->addAnnotIndex($i);
                $coordinates = $this->getCoordinates($field['x'], $field['y'], $pageObject);
                $this->document->getForm($field['form'])->addFieldIndex($i);
                $this->objects[$i] = PdfObject\StreamObject::parse(
                    $field['field']->getStream($i, $pageObject->getIndex(), $fontRef, $coordinates['x'], $coordinates['y'])
                );
            }
        }
    }

    /**
     * Prepare the form objects
     *
     * @return void
     */
    protected function prepareForms()
    {
        $formRefs = '';
        foreach ($this->document->getForms() as $form) {
            $i = $this->lastIndex() + 1;
            $this->objects[$i] = PdfObject\StreamObject::parse($form->getStream($i));
            $formRefs .= $i . ' 0 R ';
        }
        $formRefs = substr($formRefs, 0, -1);
        $this->root->setFormReferences($formRefs);
    }

}