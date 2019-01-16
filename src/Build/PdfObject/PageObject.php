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
namespace Pop\Pdf\Build\PdfObject;

/**
 * Pdf page object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class PageObject extends AbstractObject
{

    /**
     * PDF page object index
     * @var int
     */
    protected $index = 4;

    /**
     * PDF page object parent index
     * @var int
     */
    protected $parent = 2;

    /**
     * PDF page object width
     * @var int
     */
    protected $width = 612;

    /**
     * PDF page object height
     * @var int
     */
    protected $height = 792;

    /**
     * PDF page object current content object index
     * @var int
     */
    protected $currentContentIndex = null;

    /**
     * PDF page object annotation object indices
     * @var array
     */
    protected $annots = [];

    /**
     * PDF page object content object indices
     * @var array
     */
    protected $content = [];

    /**
     * PDF page object XObject references
     * @var array
     */
    protected $xObjects = [];

    /**
     * PDF page object font object references
     * @var array
     */
    protected $fonts = [];

    /**
     * Constructor
     *
     * Instantiate a PDF page object, defaults to letter size.
     *
     * @param  mixed $width
     * @param  mixed $height
     * @param  int   $index
     */
    public function __construct($width = 612, $height = 792, $index = 4)
    {
        $this->setWidth($width);
        $this->setHeight($height);
        $this->setIndex($index);
        $this->setData("\n[{page_index}] 0 obj\n<</Type/Page/Parent [{parent}] 0 R[{annotations}]/MediaBox[0 0 " .
            "[{width}] [{height}]][{content_objects}]/Resources" .
            "<</ProcSet[/PDF/Text/ImageB/ImageC/ImageI][{xobjects}][{fonts}]>>>>\nendobj\n");
    }

    /**
     * Parse a page object from a string
     *
     * @param  string $stream
     * @return PageObject
     */
    public static function parse($stream)
    {
        $page = new self();
        $page->setIndex(substr($stream, 0, strpos($stream, ' ')));

        // Determine the page parent object index.
        $parent = substr($stream, (strpos($stream, '/Parent') + 7));
        $parent = trim(substr($parent, 0, strpos($parent, '0 R')));
        $page->setParentIndex($parent);

        // Determine the page width and height.
        $dims = substr($stream, (strpos($stream, '/MediaBox') + 9));
        $dims = substr($dims, 0, strpos($dims, ']'));
        $dims = trim(str_replace('[', '', $dims));
        $dims = explode(' ', $dims);
        $page->setWidth($dims[2]);
        $page->setHeight($dims[3]);

        // Determine the page content objects.
        if (strpos($stream, '/Contents') !== false) {
            $contents = substr($stream, (strpos($stream, '/Contents') + 9));
            $contents = $page->getDictionaryReferences($contents);
            foreach ($contents as $content) {
                $page->addContentIndex($content);
            }

            // Set placeholder
            $contents = substr($stream, (strpos($stream, '/Contents') + 9));
            if (strpos($contents, '[') !== false) {
                $contents = substr($contents, 0, (strpos($contents, ']') + 1));
                $stream   = str_replace($contents, '[{content_objects}]', $stream);
            } else {
                $contents = (strpos($contents , '/') !== false) ?
                    substr($contents, 0, strpos($contents, '/')) :
                    substr($contents, 0, strpos($contents, '>'));
                $stream   = str_replace($contents, '[{content_objects}]', $stream);
            }
        }

        // If they exist, determine the page annotation objects.
        if (strpos($stream, '/Annots') !== false) {
            $annots = substr($stream, (strpos($stream, '/Annots') + 7));
            $annots = $page->getDictionaryReferences($annots);
            foreach ($annots as $annot) {
                $page->addAnnotIndex($annot);
            }

            // Set placeholder
            $annots = substr($stream, (strpos($stream, '/Annots') + 7));
            if (strpos($annots, '[') !== false) {
                $annots = substr($annots, 0, (strpos($annots, ']') + 1));
                $stream = str_replace($annots, '[{annotations}]', $stream);
            } else {
                $annots = (strpos($annots , '/') !== false) ?
                    substr($annots, 0, strpos($annots, '/')) :
                    substr($annots, 0, strpos($annots, '>'));
                $stream = str_replace($annots, '[{annotations}]', $stream);
            }
        }

        // If they exist, determine the page font references.
        if (strpos($stream, '/Font') !== false) {
            $fonts  = substr($stream, strpos($stream, 'Font'));
            $fonts  = substr($fonts, 0, (strpos($fonts, '>>') + 2));
            $stream = str_replace('/' . $fonts, '[{fonts}]', $stream);
            $fonts  = str_replace('Font<<', '', $fonts);
            $fonts  = str_replace('>>', '', $fonts);
            $fonts  = explode('/', $fonts);
            foreach ($fonts as $value) {
                if ($value != '') {
                    $page->addFontReference('/' . $value);
                }
            }
        }

        // If they exist, determine the page XObjects references.
        if (strpos($stream, '/XObject') !== false) {
            $xo     = substr($stream, strpos($stream, 'XObject'));
            $xo     = substr($xo, 0, (strpos($xo, '>>') + 2));
            $stream = str_replace('/' . $xo, '[{xobjects}]', $stream);
            $xo     = str_replace('XObject<<', '', $xo);
            $xo     = str_replace('>>', '', $xo);
            $xo     = explode('/', $xo);
            foreach ($xo as $value) {
                if ($value != '') {
                    $page->addXObjectReference('/' . $value);
                }
            }
        }

        // If they exist, determine the page graphic states.
        if (strpos($stream, '/ExtGState') !== false) {
            $gState = substr($stream, strpos($stream, 'ExtGState'));
            $gState = '/' . substr($gState, 0, (strpos($gState, '>>') + 2));
        } else {
            $gState = '';
        }

        // If any groups exist
        if (strpos($stream, '/Group') !== false) {
            $group = substr($stream, strpos($stream, 'Group'));
            $group = '/' . substr($group, 0, (strpos($group, '>>') + 2));
        } else {
            $group = '';
        }

        // If resources exists
        if (strpos($stream, '/Resources') !== false) {
            $resources = substr($stream, strpos($stream, 'Resources'));
            if (strpos($resources, ' R') !== false) {
                $resources = '/' . substr($resources, 0, (strpos($resources, ' R') + 2));
            } else if (strpos($resources, '>>') !== false) {
                $resources = '/' . substr($resources, 0, (strpos($resources, '>>') + 2));
            } else {
                $resources = "/Resources<</ProcSet[/PDF/Text/ImageB/ImageC/ImageI][{xobjects}][{fonts}]{$gState}>>";
            }
        } else {
            $resources = "/Resources<</ProcSet[/PDF/Text/ImageB/ImageC/ImageI][{xobjects}][{fonts}]{$gState}>>";
        }

        if (substr_count($resources, '<<') > substr_count($resources, '>>')) {
            $resources .= str_repeat('>>', (substr_count($resources, '<<') - substr_count($resources, '>>')));
        }

        $page->setData("\n[{page_index}] 0 obj\n<</Type/Page/Parent [{parent}] 0 R[{annotations}]/MediaBox" .
            "[0 0 [{width}] [{height}]]{$group}[{content_objects}]{$resources}>>\nendobj\n");

        return $page;
    }

    /**
     * Set the page object parent index
     *
     * @param  int $parent
     * @return PageObject
     */
    public function setParentIndex($parent)
    {
        $this->parent = (int)$parent;
        return $this;
    }

    /**
     * Set the page object width
     *
     * @param  float $width
     * @return PageObject
     */
    public function setWidth($width)
    {
        $this->width = (float)$width;
        return $this;
    }

    /**
     * Set the page object height
     *
     * @param  float $height
     * @return PageObject
     */
    public function setHeight($height)
    {
        $this->height = (float)$height;
        return $this;
    }

    /**
     * Set the page object annotation indices
     *
     * @param  array $annots
     * @return PageObject
     */
    public function setAnnots(array $annots)
    {
        $this->annots = $annots;
        return $this;
    }

    /**
     * Set the page object content object indices
     *
     * @param  array $content
     * @return PageObject
     */
    public function setContent(array $content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Set the page object XObject references
     *
     * @param  array $xObjects
     * @return PageObject
     */
    public function setXObjects(array $xObjects)
    {
        $this->xObjects = [];
        foreach ($xObjects as $xObject) {
            $this->addXObjectReference($xObject);
        }
        return $this;
    }

    /**
     * Set the page object font references
     *
     * @param  array $fonts
     * @return PageObject
     */
    public function setFonts(array $fonts)
    {
        $this->fonts = [];
        foreach ($fonts as $font) {
            $this->addFontReference($font);
        }
        return $this;
    }

    /**
     * Set the page object current content object index
     *
     * @param  mixed $i
     * @return PageObject
     */
    public function setCurrentContentIndex($i = null)
    {
        $this->currentContentIndex = (null !== $i) ? (int)$i : null;
        return $this;
    }

    /**
     * Add annotation index
     *
     * @param  int $i
     * @return PageObject
     */
    public function addAnnotIndex($i)
    {
        $this->annots[] = (int)$i;
        return $this;
    }

    /**
     * Add content object index
     *
     * @param  int $i
     * @return PageObject
     */
    public function addContentIndex($i)
    {
        $this->content[] = (int)$i;
        $this->setCurrentContentIndex($i);
        return $this;
    }

    /**
     * Add XObject reference
     *
     * @param  string $xObject
     * @return PageObject
     */
    public function addXObjectReference($xObject)
    {
        $i = substr($xObject, (strpos($xObject, ' ') + 1));
        $i = substr($i, 0, strpos($i, ' '));
        $this->xObjects[(int)$i] = $xObject;
        return $this;
    }

    /**
     * Add font reference
     *
     * @param  string $font
     * @return PageObject
     */
    public function addFontReference($font)
    {
        $i = substr($font, (strpos($font, ' ') + 1));
        $i = substr($i, 0, strpos($i, ' '));
        $this->fonts[(int)$i] = $font;
        return $this;
    }

    /**
     * Get the page object parent index
     *
     * @return int
     */
    public function getParentIndex()
    {
        return $this->parent;
    }

    /**
     * Get the page object width
     *
     * @return float
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Get the page object height
     *
     * @return float
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Get the page object current content object index
     *
     * @return int
     */
    public function getCurrentContentIndex()
    {
        return $this->currentContentIndex;
    }

    /**
     * Get the page object annotation indices
     *
     * @return array
     */
    public function getAnnots()
    {
        return $this->annots;
    }

    /**
     * Get the page object content object indices
     *
     * @return array
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get the page object XObject references
     *
     * @return array
     */
    public function getXObjects()
    {
        return $this->xObjects;
    }

    /**
     * Get the page object font references
     *
     * @return array
     */
    public function getFonts()
    {
        return $this->fonts;
    }

    /**
     * Determine if the page object has an annotation index
     *
     * @param  int $i
     * @return boolean
     */
    public function hasAnnot($i)
    {
        return (isset($this->annots[$i]));
    }

    /**
     * Determine if the page object has a content index
     *
     * @param  int $i
     * @return boolean
     */
    public function hasContent($i)
    {
        return (isset($this->content[$i]));
    }

    /**
     * Method to print the page object.
     *
     * @return string
     */
    public function __toString()
    {
        $annots   = '';
        $xObjects = '';
        $fonts    = '';

        // Format the annotations.
        if (count($this->annots) > 0) {
            $annots = '/Annots[';
            $annots .= implode(" 0 R ", $this->annots);
            $annots .= " 0 R]";
        }

        // Format the xobjects.
        if (count($this->xObjects) > 0) {
            $xObjects = '/XObject<<';
            $xObjects .= implode('', $this->xObjects);
            $xObjects .= '>>';
        }

        // Format the fonts.
        if (count($this->fonts) > 0) {
            $fonts = '/Font<<';
            $fonts .= implode('', $this->fonts);
            $fonts .= '>>';
        }

        // Swap out the placeholders.
        $obj = str_replace(
            ['[{page_index}]', '[{parent}]', '[{width}]', '[{height}]'],
            [$this->index, $this->parent, $this->width, $this->height],
            $this->data
        );

        $obj = (($annots != '') && (strpos($obj, '[{annotations}]') === false)) ?
            str_replace('/MediaBox', $annots . '/MediaBox', $obj) :
            str_replace('[{annotations}]', $annots, $obj);

        $obj = (($xObjects != '') && (strpos($obj, '[{xobjects}]') === false)) ?
            str_replace('/ProcSet', $xObjects . '/ProcSet', $obj) :
            str_replace('[{xobjects}]', $xObjects, $obj);

        $obj = (($fonts != '') && (strpos($obj, '[{fonts}]') === false)) ?
            str_replace('/ProcSet', $fonts . '/ProcSet', $obj) :
            str_replace('[{fonts}]', $fonts, $obj);

        if (count($this->content) > 0) {
            $obj = str_replace('[{content_objects}]', '/Contents[' . implode(" 0 R ", $this->content) . " 0 R]", $obj);
        } else {
            $obj = str_replace('[{content_objects}]', '', $obj);
        }

        return $obj;
    }

}