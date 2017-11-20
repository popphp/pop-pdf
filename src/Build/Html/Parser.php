<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2017 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Html;

use Pop\Css;
use Pop\Dom\Child;
use Pop\Pdf\Document;

/**
 * Pdf HTML parser class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2017 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.1.0
 */
class Parser
{

    /**
     * PDF document
     * @var Document
     */
    protected $document = null;

    /**
     * HTML object or array of HTML objects
     * @var Child|array
     */
    protected $html = null;

    /**
     * CSS object
     * @var Css\Css
     */
    protected $css = null;

    /**
     * Page size
     * @var string
     */
    protected $pageSize = 'LETTER';

    /**
     * Page margins
     * @var array
     */
    protected $pageMargins = [
        'top'    => 80,
        'right'  => 60,
        'bottom' => 60,
        'left'   => 60
    ];

    /**
     * Default styles
     * @var array
     */
    protected $defaultStyles = [
        'font-family' => 'Arial',
        'font-size'   => 12,
        'font-weight' => 'normal',
        'color'       => [0, 0, 0],
        'line-height' => 16
    ];

    /**
     * Current x-position
     * @var int
     */
    protected $x = 0;

    /**
     * Current y-position
     * @var int
     */
    protected $y = 0;

    /**
     * Current page object
     * @var Document\Page
     */
    protected $page = null;

    /**
     * Constructor
     *
     * Instantiate the HTML parser object
     *
     * @param  Document $document
     */
    public function __construct(Document $document = null)
    {
        if (null !== $document) {
            $this->setDocument($document);
        }
        $this->createDefaultStyles();
    }

    /**
     * Parse HTML string
     *
     * @param  string   $htmlString
     * @param  Document $document
     * @return self
     */
    public static function parseString($htmlString, Document $document = null)
    {
        $html = new self($document);
        $html->parseHtml($htmlString);

        return $html;
    }

    /**
     * Parse $html from file
     *
     * @param  string   $htmlFile
     * @param  Document $document
     * @return self
     */
    public static function parseFile($htmlFile, Document $document = null)
    {
        $html = new self($document);
        $html->parseHtmlFile($htmlFile);

        return $html;
    }

    /**
     * Parse $html from URI
     *
     * @param  string   $htmlUri
     * @param  Document $document
     * @return self
     */
    public static function parseUri($htmlUri, Document $document = null)
    {
        $html = new self($document);
        $html->parseHtmlUri($htmlUri);

        return $html;
    }

    /**
     * Set document
     *
     * @param  Document $document
     * @return self
     */
    public function setDocument(Document $document)
    {
        $this->document = $document;
        return $this;
    }

    /**
     * Parse HTML string
     *
     * @param  string $htmlString
     * @return self
     */
    public function parseHtml($htmlString)
    {
        $this->html = Child::parseString($htmlString);
        return $this;
    }

    /**
     * Parse HTML string from file
     *
     * @param  string $htmlFile
     * @throws Exception
     * @return self
     */
    public function parseHtmlFile($htmlFile)
    {
        if (!file_exists($htmlFile)) {
            throw new Exception('Error: That file does not exist.');
        }
        return $this->parseHtml(file_get_contents($htmlFile));
    }

    /**
     * Parse HTML string from URI
     *
     * @param  string $htmlUri
     * @throws Exception
     * @return self
     */
    public function parseHtmlUri($htmlUri)
    {
        return $this->parseHtml(file_get_contents($htmlUri));
    }

    /**
     * Parse CSS string
     *
     * @param  string $cssString
     * @return self
     */
    public function parseCss($cssString)
    {
        if (null === $this->css) {
            $this->css = Css\Css::parseString($cssString);
        } else {
            $this->css->parseCss($cssString);
        }
        return $this;
    }

    /**
     * Parse CSS file
     *
     * @param  string $cssFile
     * @return self
     */
    public function parseCssFile($cssFile)
    {
        if (null === $this->css) {
            $this->css = Css\Css::parseFile($cssFile);
        } else {
            $this->css->parseCssFile($cssFile);
        }
        return $this;
    }

    /**
     * Parse CSS URI
     *
     * @param  string $cssUri
     * @return self
     */
    public function parseCssUri($cssUri)
    {
        if (null === $this->css) {
            $this->css = Css\Css::parseUri($cssUri);
        } else {
            $this->css->parseCssUri($cssUri);
        }
        return $this;
    }

    /**
     * Get document
     *
     * @return Document
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * Get document (alias)
     *
     * @return Document
     */
    public function document()
    {
        return $this->document;
    }

    /**
     * Set page size
     *
     * @param  string $size
     * @return self
     */
    public function setPageSize($size)
    {
        $this->pageSize = $size;
        return $this;
    }

    /**
     * Get page size
     *
     * @return string
     */
    public function getPageSize()
    {
        return $this->pageSize;
    }

    /**
     * Set page margins
     *
     * @param  int $top
     * @param  int $right
     * @param  int $bottom
     * @param  int $left
     * @return self
     */
    public function setPageMargins($top, $right, $bottom, $left)
    {
        $this->pageMargins['top']    = $top;
        $this->pageMargins['right']  = $right;
        $this->pageMargins['bottom'] = $bottom;
        $this->pageMargins['left']   = $left;
        return $this;
    }

    /**
     * Set page top margin
     *
     * @param  int $margin
     * @return self
     */
    public function setPageTopMargin($margin)
    {
        $this->pageMargins['top'] = $margin;
        return $this;
    }

    /**
     * Set page right margin
     *
     * @param  int $margin
     * @return self
     */
    public function setPageRightMargin($margin)
    {
        $this->pageMargins['right'] = $margin;
        return $this;
    }

    /**
     * Set page bottom margin
     *
     * @param  int $margin
     * @return self
     */
    public function setPageBottomMargin($margin)
    {
        $this->pageMargins['bottom'] = $margin;
        return $this;
    }

    /**
     * Set page left margin
     *
     * @param  int $margin
     * @return self
     */
    public function setPageLeftMargin($margin)
    {
        $this->pageMargins['left'] = $margin;
        return $this;
    }

    /**
     * Get page margins
     *
     * @return array
     */
    public function getPageMargins()
    {
        return $this->pageMargins;
    }

    /**
     * Get page top margin
     *
     * @return int
     */
    public function getPageTopMargin()
    {
        return $this->pageMargins['top'];
    }

    /**
     * Get page right margin
     *
     * @return int
     */
    public function getPageRightMargin()
    {
        return $this->pageMargins['right'];
    }

    /**
     * Get page bottom margin
     *
     * @return int
     */
    public function getPageBottomMargin()
    {
        return $this->pageMargins['bottom'];
    }

    /**
     * Get page left margin
     *
     * @return int
     */
    public function getPageLeftMargin()
    {
        return $this->pageMargins['left'];
    }

    /**
     * Get default styles
     *
     * @return array
     */
    public function getDefaultStyles()
    {
        return $this->defaultStyles;
    }

    /**
     * Get styles
     *
     * @return Css\Css
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * Get HTML nodes
     *
     * @return Child|array
     */
    public function getHtml()
    {
        return $this->html;
    }

    /**
     * Process conversion of HTML into PDF objects
     *
     * @return Document
     */
    public function process()
    {
        $htmlNodes = null;
        if ($this->html instanceof Child) {
            foreach ($this->html->getChildNodes() as $child) {
                if ($child->getNodeName() == 'body') {
                    $htmlNodes = $child;
                }
            }
        }

        if (null === $htmlNodes) {
            $htmlNodes = $this->html;
        }

        if ($htmlNodes instanceof Child) {
            foreach ($htmlNodes->getChildNodes() as $child) {
                $this->addNodeToDocument($child);
            }
        } else {
            foreach ($htmlNodes as $child) {
                $this->addNodeToDocument($child);
            }
        }

        return $this->document;
    }

    /**
     * Add node to document
     *
     * @param  Child   $child
     * @param  boolean $nextLine
     * @throws Exception
     * @return void
     */
    protected function addNodeToDocument(Child $child, $nextLine = true)
    {
        $styles     = $this->prepareNodeStyles($child->getNodeName(), $child->getAttributes());
        $currentX   = $this->getCurrentX();
        $currentY   = $this->getCurrentY();
        $fontObject = $this->document->getFont($styles['currentFont']);
        $wrapLength = $this->page->getWidth() - $this->pageMargins['right'] - $this->pageMargins['left'];

        if ($child->hasChildNodes()) {
            $string = $child->getNodeValue();
            if (!empty($string)) {
                if ($fontObject->getStringWidth($string, $styles['fontSize']) > $wrapLength) {
                    $strings = $this->getStringLines($string, $styles['fontSize'], $wrapLength, $fontObject);
                    foreach ($strings as $i => $string) {
                        $text = new Document\Page\Text($string, $styles['fontSize']);
                        $text->setFillColor(new Document\Page\Color\Rgb($styles['color'][0], $styles['color'][1], $styles['color'][2]));
                        $this->page->addText($text, $styles['currentFont'], $currentX, $currentY);
                    }
                    $this->x += $fontObject->getStringWidth($string . ' ', $styles['fontSize']);
                } else {
                    $text = new Document\Page\Text($string . ' ', $styles['fontSize']);
                    $text->setFillColor(new Document\Page\Color\Rgb($styles['color'][0], $styles['color'][1], $styles['color'][2]));
                    $this->page->addText($text, $styles['currentFont'], $currentX, $currentY);
                    $this->x += $fontObject->getStringWidth($string . ' ', $styles['fontSize']);
                }
            }
            foreach ($child->getChildNodes() as $grandChild) {
                $this->addNodeToDocument($grandChild, false);
            }
        } else {
            $string      = $child->getNodeValue();
            $stringWidth = $fontObject->getStringWidth($string, $styles['fontSize']);
            if ($stringWidth > $wrapLength) {
                $strings = $this->getStringLines($string, $styles['fontSize'], $wrapLength, $fontObject);
                foreach ($strings as $i => $string) {
                    $text = new Document\Page\Text($string, $styles['fontSize']);
                    $text->setFillColor(new Document\Page\Color\Rgb($styles['color'][0], $styles['color'][1], $styles['color'][2]));
                    $this->page->addText($text, $styles['currentFont'], $currentX, $currentY);
                    if ($currentY <= $this->pageMargins['bottom']) {
                        $currentY = $this->newPage();
                    } else {
                        $currentY -= $styles['lineHeight'];
                        $this->y  += $styles['lineHeight'];
                    }
                }
                if ($nextLine) {
                    $this->goToNextLine($styles);
                } else {
                    $this->x += $fontObject->getStringWidth($string, $styles['fontSize']);
                }
            } else {
                $text = new Document\Page\Text($string, $styles['fontSize']);
                $text->setFillColor(new Document\Page\Color\Rgb($styles['color'][0], $styles['color'][1], $styles['color'][2]));
                $this->page->addText($text, $styles['currentFont'], $currentX, $currentY);
                if ($nextLine) {
                    $this->goToNextLine($styles);
                } else {
                    $this->x += $fontObject->getStringWidth($string, $styles['fontSize']);
                }
            }
        }
    }

    /**
     * Create default styles
     *
     * @return void
     */
    protected function createDefaultStyles()
    {
        $h1 = new Css\Selector('h1');
        $h1['margin-bottom'] = '18px';
        $h1['font-size']   = '32px';
        $h1['font-weight'] = 'bold';

        $h2 = new Css\Selector('h2');
        $h2['margin-bottom'] = '18px';
        $h2['font-size']   = '28px';
        $h2['font-weight'] = 'bold';

        $h3 = new Css\Selector('h3');
        $h3['margin-bottom'] = '16px';
        $h3['font-size']   = '24px';
        $h3['font-weight'] = 'bold';

        $h4 = new Css\Selector('h4');
        $h4['margin-bottom'] = '14px';
        $h4['font-size']   = '20px';
        $h4['font-weight'] = 'bold';

        $h5 = new Css\Selector('h5');
        $h5['margin-bottom'] = '12px';
        $h5['font-size']   = '16px';
        $h5['font-weight'] = 'bold';

        $h6 = new Css\Selector('h6');
        $h6['margin-bottom'] = '12px';
        $h6['font-size']   = '12px';
        $h6['font-weight'] = 'bold';

        $p = new Css\Selector('p');
        $p['margin-bottom'] = '24px';
        $p['font-size'] = '12px';

        $a = new Css\Selector('a');
        $a['color'] = new Css\Color\Rgb(0, 0, 255);

        $strong = new Css\Selector('strong');
        $strong['font-size']   = '12px';
        $strong['font-weight'] = 'bold';

        $em = new Css\Selector('em');
        $em['font-size']  = '12px';
        $em['font-style'] = 'italic';

        $this->css = new Css\Css();
        $this->css->addSelectors([$h1, $h2, $h3, $h4, $h5, $h6, $p, $a, $strong, $em]);

        if (!($this->document->hasFont('Arial'))) {
            $this->document->addFont(new Document\Font('Arial'));
        }
        if (!($this->document->hasFont('Arial,Bold'))) {
            $this->document->addFont(new Document\Font('Arial,Bold'));
        }
        if (!($this->document->hasFont('Arial,Italic'))) {
            $this->document->addFont(new Document\Font('Arial,Italic'));
        }
        if (!($this->document->hasFont('Arial,BoldItalic'))) {
            $this->document->addFont(new Document\Font('Arial,BoldItalic'));
        }
        if (!($this->document->hasFont('TimesNewRoman'))) {
            $this->document->addFont(new Document\Font('TimesNewRoman'));
        }
        if (!($this->document->hasFont('TimesNewRoman,Bold'))) {
            $this->document->addFont(new Document\Font('TimesNewRoman,Bold'));
        }
        if (!($this->document->hasFont('TimesNewRoman,Italic'))) {
            $this->document->addFont(new Document\Font('TimesNewRoman,Italic'));
        }
        if (!($this->document->hasFont('TimesNewRoman,BoldItalic'))) {
            $this->document->addFont(new Document\Font('TimesNewRoman,BoldItalic'));
        }
    }

    /**
     * Prepare node styles
     *
     * @param  string $name
     * @param  array  $attribs
     * @throws Exception
     * @return array
     */
    protected function prepareNodeStyles($name, array $attribs = [])
    {
        $styles = [
            'currentFont'   => null,
            'fontFamily'    => $this->defaultStyles['font-family'],
            'fontSize'      => $this->defaultStyles['font-size'],
            'fontWeight'    => $this->defaultStyles['font-weight'],
            'color'         => $this->defaultStyles['color'],
            'lineHeight'    => $this->defaultStyles['line-height'],
            'marginBottom'  => 0,
            'paddingBottom' => 0
        ];

        if (($name == 'h1') || ($name == 'h2') || ($name == 'h3') || ($name == 'h4') || ($name == 'h5') || ($name == 'h6')) {
            switch ($name) {
                case 'h1':
                    $styles['fontSize']   = round($styles['fontSize'] * 2.67); // 32
                    $styles['fontWeight'] = 'bold';
                    break;
                case 'h2':
                    $styles['fontSize']   = round($styles['fontSize'] * 2.33);  // 28
                    $styles['fontWeight'] = 'bold';
                    break;
                case 'h3':
                    $styles['fontSize']   = $styles['fontSize'] * 2;  // 24
                    $styles['fontWeight'] = 'bold';
                    break;
                case 'h4':
                    $styles['fontSize']   = round($styles['fontSize'] * 1.67); // 20
                    $styles['fontWeight'] = 'bold';
                    break;
                case 'h5':
                    $styles['fontSize']   = round($styles['fontSize'] * 1.33);  // 16
                    $styles['fontWeight'] = 'bold';
                    break;
                case 'h6':
                    $styles['fontWeight'] = 'bold';
                    break;
            }
        }

        if ($this->css->hasSelector($name)) {
            if ($this->css[$name]->hasProperty('font-family')) {
                $styles['fontFamily'] = str_replace('"', '', $this->css[$name]['font-family']);
            }
            if ($this->css[$name]->hasProperty('font-size')) {
                $styles['fontSize'] = (int)$this->css[$name]['font-size'];
            }
            if ($this->css[$name]->hasProperty('font-weight')) {
                $styles['fontWeight'] = $this->css[$name]['font-weight'];
            }
            if ($this->css[$name]->hasProperty('color')) {
                $styles['color'] = $this->css[$name]['color'];
                if (is_string($styles['color'])) {
                    $cssColor = Css\Color::parse($styles['color']);
                    $styles['color'] = $cssColor->toRgb()->toArray(false);
                }
            }
            if ($this->css[$name]->hasProperty('line-height')) {
                $styles['lineHeight'] = (int)$this->css[$name]['line-height'];
            }
            if ((int)$this->css[$name]['margin-bottom'] > 0) {
                $styles['marginBottom'] = (int)$this->css[$name]['margin-bottom'];
            }
            if ((int)$this->css[$name]['padding-bottom'] > 0) {
                $styles['paddingBottom'] = (int)$this->css[$name]['padding-bottom'];
            }
        }

        if (isset($attribs['id']) && $this->css->hasSelector('#' . $attribs['id'])) {
            if ($this->css['#' . $attribs['id']]->hasProperty('font-family')) {
                $styles['fontFamily'] = str_replace('"', '', $this->css['#' . $attribs['id']]['font-family']);
            }
            if ($this->css['#' . $attribs['id']]->hasProperty('font-size')) {
                $styles['fontSize'] = (int)$this->css['#' . $attribs['id']]['font-size'];
            }
            if ($this->css['#' . $attribs['id']]->hasProperty('font-weight')) {
                $styles['fontWeight'] = $this->css['#' . $attribs['id']]['font-weight'];
            }
            if ($this->css['#' . $attribs['id']]->hasProperty('color')) {
                $styles['color'] = $this->css['#' . $attribs['id']]['color'];
                if (is_string($styles['color'])) {
                    $cssColor = Css\Color::parse($styles['color']);
                    $styles['color'] = $cssColor->toRgb()->toArray(false);
                }
            }
            if ($this->css['#' . $attribs['id']]->hasProperty('line-height')) {
                $styles['lineHeight'] = (int)$this->css['#' . $attribs['id']]['line-height'];
            }
            if ((int)$this->css['#' . $attribs['id']]['margin-bottom'] > 0) {
                $styles['marginBottom'] = (int)$this->css['#' . $attribs['id']]['margin-bottom'];
            }
            if ((int)$this->css['#' . $attribs['id']]['padding-bottom'] > 0) {
                $styles['paddingBottom'] = (int)$this->css['#' . $attribs['id']]['padding-bottom'];
            }
        }

        if (isset($attribs['class']) && $this->css->hasSelector('.' . $attribs['class'])) {
            if ($this->css['.' . $attribs['class']]->hasProperty('font-family')) {
                $styles['fontFamily'] = str_replace('"', '', $this->css['.' . $attribs['class']]['font-family']);
            }
            if ($this->css['.' . $attribs['class']]->hasProperty('font-size')) {
                $styles['fontSize'] = (int)$this->css['.' . $attribs['class']]['font-size'];
            }
            if ($this->css['.' . $attribs['class']]->hasProperty('font-weight')) {
                $styles['fontWeight'] = $this->css['.' . $attribs['class']]['font-weight'];
            }
            if ($this->css['.' . $attribs['class']]->hasProperty('color')) {
                $styles['color'] = $this->css['.' . $attribs['class']]['color'];
                if (is_string($styles['color'])) {
                    $cssColor = Css\Color::parse($styles['color']);
                    $styles['color'] = $cssColor->toRgb()->toArray(false);
                }
            }
            if ($this->css['.' . $attribs['class']]->hasProperty('line-height')) {
                $styles['lineHeight'] = (int)$this->css['.' . $attribs['class']]['line-height'];
            }
            if ((int)$this->css['.' . $attribs['class']]['margin-bottom'] > 0) {
                $styles['marginBottom'] = (int)$this->css['.' . $attribs['class']]['margin-bottom'];
            }
            if ((int)$this->css['.' . $attribs['class']]['padding-bottom'] > 0) {
                $styles['paddingBottom'] = (int)$this->css['.' . $attribs['class']]['padding-bottom'];
            }
        }

        if (strpos($styles['fontFamily'], ',') !== false) {
            $fonts = explode(',', $styles['fontFamily']);
            foreach ($fonts as $font) {
                $font = trim($font);
                if ($this->document->hasFont($font)) {
                    $styles['currentFont'] = $font;
                    break;
                } else if ($this->document->hasFont(str_replace(' ', '-', $font))) {
                    $styles['currentFont'] = str_replace(' ', '-', $font);
                } else if ($this->document->hasFont(str_replace(' ', ',', $font))) {
                    $styles['currentFont'] = str_replace(' ', ',', $font);
                } else if ($this->document->hasFont(str_replace(' ', '', $font))) {
                    $styles['currentFont'] = str_replace(' ', '', $font);
                }
            }
        } else {
            $styles['currentFont'] = $styles['fontFamily'];
        }

        if (null === $styles['currentFont']) {
            throw new Exception('Error: No available font has been detected.');
        } else if ($styles['currentFont'] == 'sans-serif') {
            $styles['currentFont'] = 'Arial';
        } else if ($styles['currentFont'] == 'serif') {
            $styles['currentFont'] = 'TimesNewRoman';
        }

        if ($styles['fontWeight'] == 'bold') {
            if ($this->document->hasFont($styles['currentFont'] . 'Bold')) {
                $styles['currentFont'] .= 'Bold';
            } else if ($this->document->hasFont($styles['currentFont'] . '-Bold')) {
                $styles['currentFont'] .= '-Bold';
            } else if ($this->document->hasFont($styles['currentFont'] . ',Bold')) {
                $styles['currentFont'] .= ',Bold';
            }
        }

        if (!($this->document->hasFont($styles['currentFont']))) {
            $standardFonts = Document\Font::standardFonts();
            if (in_array($styles['currentFont'], $standardFonts)) {
                $this->document->addFont(new Document\Font($styles['currentFont']));
            } else if (in_array(str_replace(' ', '-', $styles['currentFont']), $standardFonts)) {
                $styles['currentFont'] = str_replace(' ', '-', $styles['currentFont']);
                $this->document->addFont(new Document\Font($styles['currentFont']));
            } else if (in_array(str_replace(' ', ',', $styles['currentFont']), $standardFonts)) {
                $styles['currentFont'] = str_replace(' ', ',', $styles['currentFont']);
                $this->document->addFont(new Document\Font($styles['currentFont']));
            } else if (in_array(str_replace(' ', '', $styles['currentFont']), $standardFonts)) {
                $styles['currentFont'] = str_replace(' ', '', $styles['currentFont']);
                $this->document->addFont(new Document\Font($styles['currentFont']));
            } else {
                throw new Exception('Error: The current font has not been added to the document.');
            }

            if ($styles['fontWeight'] == 'bold') {
                if ($this->document->hasFont($styles['currentFont'] . 'Bold')) {
                    $styles['currentFont'] .= 'Bold';
                } else if ($this->document->hasFont($styles['currentFont'] . '-Bold')) {
                    $styles['currentFont'] .= '-Bold';
                } else if ($this->document->hasFont($styles['currentFont'] . ',Bold')) {
                    $styles['currentFont'] .= ',Bold';
                } else if (in_array($styles['currentFont'] . 'Bold', $standardFonts)) {
                    $styles['currentFont'] .= 'Bold';
                    $this->document->addFont(new Document\Font($styles['currentFont']));
                } else if (in_array($styles['currentFont'] . '-Bold', $standardFonts)) {
                    $styles['currentFont'] .= '-Bold';
                    $this->document->addFont(new Document\Font($styles['currentFont']));
                } else if (in_array($styles['currentFont'] . ',Bold', $standardFonts)) {
                    $styles['currentFont'] .= ',Bold';
                    $this->document->addFont(new Document\Font($styles['currentFont']));
                }
            }
        }

        return $styles;
    }

    /**
     * Get current X-position
     *
     * @return int
     */
    protected function getCurrentX()
    {
        if ($this->x < $this->pageMargins['left']) {
            $this->x = $this->pageMargins['left'];
        }
        return $this->x;
    }

    /**
     * Reset X-position
     *
     * @return int
     */
    protected function resetX()
    {
        $this->x = $this->pageMargins['left'];
        return $this->x;
    }

    /**
     * Get current Y-position
     *
     * @return int
     */
    protected function getCurrentY()
    {
        if (!($this->document->hasPages())) {
            $this->page = new Document\Page($this->pageSize);
            $this->document->addPage($this->page);
        } else {
            $this->page = $this->document->getPage($this->document->getCurrentPage());
        }

        $currentY = $this->page->getHeight() - $this->pageMargins['top'] - $this->y;

        if ($currentY <= $this->pageMargins['bottom']) {
            $this->page = new Document\Page($this->pageSize);
            $this->document->addPage($this->page);
            $currentY = $this->resetY();
        }

        return $currentY;
    }

    /**
     * Reset Y-position
     *
     * @return int
     */
    protected function resetY()
    {
        if (!($this->document->hasPages())) {
            $this->page = new Document\Page($this->pageSize);
            $this->document->addPage($this->page);
        } else {
            $this->page = $this->document->getPage($this->document->getCurrentPage());
        }

        $this->y  = 0;
        $currentY = $this->page->getHeight() - $this->pageMargins['top'];

        return $currentY;
    }

    /**
     * Create new page
     *
     * @return int
     */
    protected function newPage()
    {
        $this->page = new Document\Page($this->pageSize);
        $this->document->addPage($this->page);
        $this->y = 0;
        return $this->page->getHeight() - $this->pageMargins['top'] - $this->y;
    }

    /**
     * Go to next line
     *
     * @param  array $styles
     * @return void
     */
    protected function goToNextLine(array $styles)
    {
        $this->y += $styles['marginBottom'] + $styles['paddingBottom'] + $styles['lineHeight'];
    }

    /**
     * Get string lines
     *
     * @param  string        $string
     * @param  int           $fontSize
     * @param  int           $wrapLength
     * @param  Document\Font $fontObject
     * @return array
     */
    protected function getStringLines($string, $fontSize, $wrapLength, Document\Font $fontObject)
    {
        $strings   = [];
        $curString = '';
        $words     = explode(' ', $string);

        foreach ($words as $word) {
            $newString = ($curString != '') ? $curString . ' ' . $word : $word;
            if ($fontObject->getStringWidth($newString, $fontSize) <= $wrapLength) {
                $curString = $newString;
            } else {
                $strings[] = $curString;
                $curString = $word;
            }
        }
        if (!empty($curString)) {
            $strings[] = $curString;
        }

        return $strings;
    }

}