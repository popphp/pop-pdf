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
namespace Pop\Pdf\Build;

use Pop\Pdf\Build\Image;
use Pop\Pdf\Build\Object;

/**
 * Pdf compiler class
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
class Compiler extends AbstractCompiler
{

    /**
     * Set the document object
     *
     * @param  \Pop\Pdf\Document $document
     * @return Compiler
     */
    public function setDocument(\Pop\Pdf\Document $document)
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
                if ($object instanceof Object\RootObject) {
                    $this->setRoot($object);
                } else if ($object instanceof Object\ParentObject) {
                    $this->setParent($object);
                } else if ($object instanceof Object\InfoObject) {
                    $this->setInfo($object);
                } else {
                    $this->objects[$i] = $object;
                }
            }
        }

        if (null === $this->root) {
            $this->setRoot(new Object\RootObject());
        }
        if (null === $this->parent) {
            $this->setParent(new Object\ParentObject());
        }
        if (null === $this->info) {
            $this->setInfo(new Object\InfoObject());
        }

        $this->root->setVersion($this->document->getVersion());
        $this->info->setMetadata($this->document->getMetadata());

        return $this;
    }

    /**
     * Compile and finalize the PDF document
     *
     * @param  \Pop\Pdf\Document $document
     * @return void
     */
    public function finalize(\Pop\Pdf\Document $document)
    {
        $this->setDocument($document);
        $this->prepareFonts();

        $pageObjects = [];

        foreach ($this->pages as $page) {
            if ($page->hasImportedPageObject()) {
                $pageObject = $page->getImportedPageObject();
                $pageObject->setCurrentContentIndex(null);
                $this->objects[$pageObject->getIndex()] = $pageObject;
            } else {
                $page->setIndex($this->lastIndex() + 1);
                $pageObject = new Object\PageObject($page->getWidth(), $page->getHeight(), $page->getIndex());
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
            // Prepare text objects
            if ($page->hasText()) {
                $this->prepareText($page->getText(), $pageObject);
            }
            // Prepare path objects
            if ($page->hasPaths()) {
                $this->preparePaths($page->getPaths(), $pageObject);
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

        $numObjs       = count($this->objects) + 1;
        $this->trailer = "xref\n0 {$numObjs}\n0000000000 65535 f \n";

        $this->byteLength += $this->calculateByteLength($this->root);
        $this->trailer    .= $this->formatByteLength($this->byteLength) . " 00000 n \n";

        $this->output .= $this->root;

        // Loop through the rest of the objects, calculate their size and length
        // for the xref table and add their data to the output.
        foreach ($this->objects as $object) {
            if ($object->getIndex() != $this->root->getIndex()) {
                if (($object instanceof Object\StreamObject) && ($this->compression) && (!$object->isPalette()) &&
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
    protected function prepareFonts()
    {
        foreach ($this->fonts as $font) {
            if ($font instanceof \Pop\Pdf\Document\Font) {
                $f = count($this->fontReferences) + 1;
                $i = $this->lastIndex() + 1;

                if ($font->isStandard()) {
                    $this->fontReferences[$font->getName()] = '/MF' . $f . ' ' . $i . ' 0 R';
                    $this->objects[$i] = Object\StreamObject::parse(
                        "{$i} 0 obj\n<<\n    /Type /Font\n    /Subtype /Type1\n    /Name /MF{$f}\n    /BaseFont /" .
                        $font->getName() . "\n    /Encoding /WinAnsiEncoding\n>>\nendobj\n\n"
                    );
                } else {
                    $font->parser()->setCompression($this->compression)
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
     * @param  Object\PageObject $pageObject
     * @return void
     */
    protected function prepareImages(array $images, Object\PageObject $pageObject)
    {
        $imgs = [];
        if (null === $pageObject->getCurrentContentIndex()) {
            $contentObject = new Object\StreamObject($this->lastIndex() + 1);
            $this->objects[$contentObject->getIndex()] = $contentObject;
            $pageObject->addContentIndex($contentObject->getIndex());
        } else {
            $contentObject = $this->objects[$pageObject->getCurrentContentIndex()];
        }
        foreach ($images as $image) {
            $coordinates = $this->getCoordinates($image['x'], $image['y'], $pageObject);
            if (!array_key_exists($image['image']->getImage(), $imgs)) {
                $i = $this->lastIndex() + 1;
                $imageParser = new Image\Parser(
                    $image['image']->getImage(), $coordinates['x'], $coordinates['y'],
                    $image['image']->getResizeDimensions(), $image['image']->isPreserveResolution()
                );
                $imageParser->setIndex($i);
                $contentObject->appendStream($imageParser->getStream());
                $pageObject->addXObjectReference($imageParser->getXObject());
                foreach ($imageParser->getObjects() as $oi => $imageObject) {
                    $this->objects[$oi] = $imageObject;
                }
                $imgs[$image['image']->getImage()] = $imageParser;
            } else {
                $imgs[$image['image']->getImage()]->setX($coordinates['x']);
                $imgs[$image['image']->getImage()]->setY($coordinates['y']);
                $contentObject->appendStream($imgs[$image['image']->getImage()]->getStream());
            }
        }
    }

    /**
     * Prepare the text objects
     *
     * @param  array $text
     * @param  Object\PageObject $pageObject
     * @return void
     */
    protected function prepareText(array $text, Object\PageObject $pageObject)
    {
        if (null === $pageObject->getCurrentContentIndex()) {
            $contentObject = new Object\StreamObject($this->lastIndex() + 1);
            $this->objects[$contentObject->getIndex()] = $contentObject;
            $pageObject->addContentIndex($contentObject->getIndex());
        } else {
            $contentObject = $this->objects[$pageObject->getCurrentContentIndex()];
        }
        foreach ($text as $txt) {
            $coordinates = $this->getCoordinates($txt['x'], $txt['y'], $pageObject);
            $contentObject->appendStream(
                $txt['text']->getStream($this->fontReferences[$txt['font']], $coordinates['x'], $coordinates['y'])
            );
        }
    }

    /**
     * Prepare the path objects
     *
     * @param  array $paths
     * @param  Object\PageObject $pageObject
     * @return void
     */
    protected function preparePaths(array $paths, Object\PageObject $pageObject)
    {
        if (null === $pageObject->getCurrentContentIndex()) {
            $contentObject = new Object\StreamObject($this->lastIndex() + 1);
            $this->objects[$contentObject->getIndex()] = $contentObject;
            $pageObject->addContentIndex($contentObject->getIndex());
        } else {
            $contentObject = $this->objects[$pageObject->getCurrentContentIndex()];
        }
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
     * Prepare the annotation objects
     *
     * @param  array $annotations
     * @param  Object\PageObject $pageObject
     * @return void
     */
    protected function prepareAnnotations(array $annotations, Object\PageObject $pageObject)
    {
        foreach ($annotations as $annotation) {
            $i = $this->lastIndex() + 1;
            $pageObject->addAnnotIndex($i);

            $targetCoordinates = $this->getCoordinates(
                $annotation['annotation']->getXTarget(), $annotation['annotation']->getYTarget(), $pageObject
            );

            $annotation['annotation']->setXTarget($targetCoordinates['x']);
            $annotation['annotation']->setYTarget($targetCoordinates['y']);

            $coordinates = $this->getCoordinates($annotation['x'], $annotation['y'], $pageObject);
            if ($annotation['annotation'] instanceof \Pop\Pdf\Document\Page\Annotation\Url) {
                $stream = $annotation['annotation']->getStream($i, $coordinates['x'], $coordinates['y']);
            } else {
                $stream = $annotation['annotation']->getStream(
                    $i, $coordinates['x'], $coordinates['y'], $pageObject->getIndex(), $this->parent->getKids()
                );
            }
            $this->objects[$i] = Object\StreamObject::parse($stream);
        }
    }

    /**
     * Prepare the field objects
     *
     * @param  array $fields
     * @param  Object\PageObject $pageObject
     * @return void
     */
    protected function prepareFields(array $fields, Object\PageObject $pageObject)
    {
        $i = $this->lastIndex() + 1;
        $formIndex = $i;

        $this->root->setFormIndex($formIndex);
        $form = $formIndex . " 0 obj\n<<\n    /DA(/MF1 0 Tf 0 g)\n    /DR<</Font<</MF1 4 0 R>>>>\n    /Fields[[{field_refs}]]\n>>\nendobj\n";

        $fieldIndices = [];
        foreach ($fields as $key => $field) {
            $fieldIndices[$key] = ++$i;
        }

        // Root AcroForm object
        $this->objects[$formIndex] = Object\StreamObject::parse(
            str_replace('[{field_refs}]', implode(' 0 R', $fieldIndices) . ' 0 R', $form)
        );

        foreach ($fields as $key => $field) {
            $coordinates = $this->getCoordinates($field['x'], $field['y'], $pageObject);
            $this->objects[$i] = Object\StreamObject::parse($field['field']->getStream(
                $fieldIndices[$key], $pageObject->getIndex(), $coordinates['x'], $coordinates['y'])
            );
        }
    }

}