<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Html;

use Pop\Css;
use Pop\Color\Color;
use Pop\Dom\Child;
use Pop\Pdf\Document;

/**
 * Pdf HTML parser class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.7
 */
class Parser
{

    /**
     * PDF document
     * @var ?Document
     */
    protected ?Document $document = null;

    /**
     * HTML object or array of HTML objects
     * @var Child|array|null
     */
    protected Child|array|null $html = null;

    /**
     * CSS object
     * @var ?Css\Css
     */
    protected ?Css\Css $css = null;

    /**
     * Page size
     * @var string
     */
    protected string $pageSize = 'LETTER';

    /**
     * Page margins
     * @var array
     */
    protected array $pageMargins = [
        'top'    => 80,
        'right'  => 60,
        'bottom' => 60,
        'left'   => 60
    ];

    /**
     * Default styles
     * @var array
     */
    protected array $defaultStyles = [
        'font-family' => 'Arial',
        'font-size'   => 10,
        'font-weight' => 'normal',
        'color'       => [0, 0, 0],
        'line-height' => 14
    ];

    /**
     * Current x-position
     * @var int
     */
    protected int $x = 0;

    /**
     * Current y-position
     * @var int
     */
    protected int $y = 0;

    /**
     * Current page object
     * @var ?Document\Page
     */
    protected ?Document\Page $page = null;

    /**
     * HTML file directory
     * @var ?string
     */
    protected ?string $fileDir = null;

    /**
     * Text wrap object
     * @var ?Document\Page\Text\Wrap
     */
    protected ?Document\Page\Text\Wrap $textWrap = null;

    /**
     * Y-override
     * @var ?int
     */
    protected ?int $yOverride = null;

    /**
     * Constructor
     *
     * Instantiate the HTML parser object
     *
     * @param ?Document $document
     */
    public function __construct(?Document $document = null)
    {
        if ($document !== null) {
            $this->setDocument($document);
        }
        $this->createDefaultStyles();
    }

    /**
     * Parse HTML string
     *
     * @param  string    $htmlString
     * @param  ?Document $document
     * @return Parser
     */
    public static function parseString(string $htmlString, ?Document $document = null): Parser
    {
        $html = new self($document);
        $html->parseHtml($htmlString);

        return $html;
    }

    /**
     * Parse $html from file
     *
     * @param  string    $htmlFile
     * @param  ?Document $document
     * @throws Exception
     * @return Parser
     */
    public static function parseFile(string $htmlFile, ?Document $document = null): Parser
    {
        $html = new self($document);
        $html->parseHtmlFile($htmlFile);

        return $html;
    }

    /**
     * Parse $html from URI
     *
     * @param string $htmlUri
     * @param  ?Document $document
     * @return Parser
     */
    public static function parseUri(string $htmlUri, ?Document $document = null): Parser
    {
        $html = new self($document);
        $html->parseHtmlUri($htmlUri);

        return $html;
    }

    /**
     * Set document
     *
     * @param  Document $document
     * @return Parser
     */
    public function setDocument(Document $document): Parser
    {
        $this->document = $document;
        return $this;
    }

    /**
     * Parse HTML string
     *
     * @param  string  $htmlString
     * @param  ?string $basePath
     * @return Parser
     */
    public function parseHtml(string $htmlString, ?string $basePath = null): Parser
    {
        if ($basePath !== null) {
            $this->fileDir = $basePath;
        }
        $this->html = Child::parseString($htmlString);
        return $this;
    }

    /**
     * Parse HTML string from file
     *
     * @param  string $htmlFile
     * @throws Exception
     * @return Parser
     */
    public function parseHtmlFile(string $htmlFile): Parser
    {
        if (!file_exists($htmlFile)) {
            throw new Exception('Error: That file does not exist.');
        }
        return $this->parseHtml(file_get_contents($htmlFile), dirname(realpath($htmlFile)));
    }

    /**
     * Parse HTML string from URI
     *
     * @param  string $htmlUri
     * @return Parser
     */
    public function parseHtmlUri(string $htmlUri): Parser
    {
        return $this->parseHtml(file_get_contents($htmlUri));
    }

    /**
     * Parse CSS string
     *
     * @param  string $cssString
     * @return Parser
     */
    public function parseCss(string $cssString): Parser
    {
        if ($this->css === null) {
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
     * @throws \Pop\Css\Exception
     * @return Parser
     */
    public function parseCssFile(string $cssFile): Parser
    {
        if ($this->css === null) {
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
     * @return Parser
     */
    public function parseCssUri(string $cssUri): Parser
    {
        if ($this->css === null) {
            $this->css = Css\Css::parseUri($cssUri);
        } else {
            $this->css->parseCssUri($cssUri);
        }
        return $this;
    }

    /**
     * Get document
     *
     * @return ?Document
     */
    public function getDocument(): ?Document
    {
        return $this->document;
    }

    /**
     * Get document (alias)
     *
     * @return ?Document
     */
    public function document(): ?Document
    {
        return $this->document;
    }

    /**
     * Set page size
     *
     * @param  mixed $size
     * @param  mixed $height
     * @return Parser
     */
    public function setPageSize(mixed $size, mixed $height = null): Parser
    {
        $this->pageSize = ($height !== null) ? ['width' => $size, 'height' => $height] : $size;

        return $this;
    }

    /**
     * Get page size
     *
     * @return string|array
     */
    public function getPageSize(): string|array
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
     * @return Parser
     */
    public function setPageMargins(int $top, int $right, int $bottom, int $left): Parser
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
     * @return Parser
     */
    public function setPageTopMargin(int $margin): Parser
    {
        $this->pageMargins['top'] = $margin;
        return $this;
    }

    /**
     * Set page right margin
     *
     * @param  int $margin
     * @return Parser
     */
    public function setPageRightMargin(int $margin): Parser
    {
        $this->pageMargins['right'] = $margin;
        return $this;
    }

    /**
     * Set page bottom margin
     *
     * @param  int $margin
     * @return Parser
     */
    public function setPageBottomMargin(int $margin): Parser
    {
        $this->pageMargins['bottom'] = $margin;
        return $this;
    }

    /**
     * Set page left margin
     *
     * @param  int $margin
     * @return Parser
     */
    public function setPageLeftMargin(int $margin): Parser
    {
        $this->pageMargins['left'] = $margin;
        return $this;
    }

    /**
     * Set a default style
     *
     * @param  string $property
     * @param  string $value
     * @return Parser
     */
    public function setDefaultStyle(string $property, string $value): Parser
    {
        $this->defaultStyles[$property] = $value;
        return $this;
    }

    /**
     * Set x-position
     *
     * @param  int $x
     * @return Parser
     */
    public function setX(int $x): Parser
    {
        $this->x = $x;
        return $this;
    }

    /**
     * Set y-position
     *
     * @param  int $y
     * @return Parser
     */
    public function setY(int $y): Parser
    {
        $this->y = $y;
        return $this;
    }

    /**
     * Get page margins
     *
     * @return array
     */
    public function getPageMargins(): array
    {
        return $this->pageMargins;
    }

    /**
     * Get page top margin
     *
     * @return int
     */
    public function getPageTopMargin(): int
    {
        return $this->pageMargins['top'];
    }

    /**
     * Get page right margin
     *
     * @return int
     */
    public function getPageRightMargin(): int
    {
        return $this->pageMargins['right'];
    }

    /**
     * Get page bottom margin
     *
     * @return int
     */
    public function getPageBottomMargin(): int
    {
        return $this->pageMargins['bottom'];
    }

    /**
     * Get page left margin
     *
     * @return int
     */
    public function getPageLeftMargin(): int
    {
        return $this->pageMargins['left'];
    }

    /**
     * Get x-position
     *
     * @return int
     */
    public function getX(): int
    {
        return $this->x;
    }

    /**
     * Get y-position
     *
     * @return int
     */
    public function getY(): int
    {
        return $this->y;
    }

    /**
     * Get a default style
     *
     * @param  string $property
     * @return ?string
     */
    public function getDefaultStyle(string $property): ?string
    {
        return $this->defaultStyles[$property] ?? null;
    }

    /**
     * Get default styles
     *
     * @return array
     */
    public function getDefaultStyles(): array
    {
        return $this->defaultStyles;
    }

    /**
     * Get styles
     *
     * @return ?Css\Css
     */
    public function getCss(): ?Css\Css
    {
        return $this->css;
    }

    /**
     * Get HTML nodes
     *
     * @return array|Child|null
     */
    public function getHtml(): array|Child|null
    {
        return $this->html;
    }

    /**
     * Prepare for conversion of HTML into PDF objects
     *
     * @return array|Child|null
     */
    public function prepare(): array|Child|null
    {
        $htmlNodes = null;
        if ($this->html instanceof Child) {
            foreach ($this->html->getChildNodes() as $child) {
                if ($child->getNodeName() == 'head') {
                    foreach ($child->getChildNodes() as $c) {
                        if (($c->getNodeName() == 'link') && ($c->hasAttribute('href')) &&
                            ($c->hasAttribute('type')) && ($c->getAttribute('type') == 'text/css')) {
                            $href = $c->getAttribute('href');
                            if ($this->css === null) {
                                $this->css = Css\Css::parseFile($this->fileDir . '/' . $href);
                            } else {
                                $this->css->parseCssFile($this->fileDir . '/' . $href);
                            }
                        }
                    }
                } else if ($child->getNodeName() == 'body') {
                    $htmlNodes = $child;
                }
            }
        }

        if ($htmlNodes === null) {
            $htmlNodes = $this->html;
        }

        return $htmlNodes;
    }

    /**
     * Process conversion of HTML into PDF objects
     *
     * @throws Exception
     * @return ?Document
     */
    public function process(): ?Document
    {
        $htmlNodes = $this->prepare();

        if ($htmlNodes instanceof Child) {
            foreach ($htmlNodes->getChildNodes() as $i => $child) {
                $this->addNodeToDocument($child, $i);
            }
        } else {
            foreach ($htmlNodes as $i => $child) {
                $this->addNodeToDocument($child, $i);
            }
        }

        return $this->document;
    }

    /**
     * Add node to document
     *
     * @param  Child $child
     * @param  int   $i
     * @throws Exception|\Pop\Pdf\Exception
     * @return void
     */
    protected function addNodeToDocument(Child $child, int $i = 0): void
    {
        $styles   = $this->prepareNodeStyles($child->getNodeName(), $child->getAttributes());
        $currentX = $this->getCurrentX();

        if ($this->yOverride !== null) {
            $currentY        = $this->yOverride;
            $this->yOverride = null;
        } else {
            $currentY = $this->getCurrentY(($i != 0) ? $styles['marginBottom'] ?? 0 : 0);
        }

        $wrapLength = ($this->x > $this->pageMargins['left']) ?
            $this->page->getWidth() - $this->pageMargins['right'] - $this->x :
            $this->page->getWidth() - $this->pageMargins['right'] - $this->pageMargins['left'];

        // Image node
        if ($child->getNodeName() == 'img') {
            $image = Document\Page\Image::createImageFromFile($this->fileDir . '/' . $child->getAttribute('src'));
            $width = null;
            $height = null;
            $align = null;

            if ($child->hasAttribute('width')) {
                $width = (strpos($child->getAttribute('width'), '%')) ?
                    $this->page->getWidth() * ((int)$child->getAttribute('width') / 100) : (int)$child->getAttribute('width');
            } else if ($child->hasAttribute('height')) {
                $height = (strpos($child->getAttribute('height'), '%')) ?
                    $this->page->getHeight() * ((int)$child->getAttribute('height') / 100) : (int)$child->getAttribute('height');
            } else if ($styles['width'] !== null) {
                $width = (strpos($styles['width'], '%')) ?
                    $this->page->getWidth() * ((int)$styles['width'] / 100) : (int)$styles['width'];
            } else if ($styles['height'] !== null) {
                $height = (strpos($styles['height'], '%')) ?
                    $this->page->getHeight() * ((int)$styles['height'] / 100) : (int)$styles['height'];
            }

            if ($width !== null) {
                $image->resizeToWidth($width);
            } else if ($height !== null) {
                $image->resizeToHeight($height);
            }

            if ($height === null) {
                $height = ($image->getResizedHeight() !== null) ? $image->getResizedHeight() : $image->getHeight();
            }

            if ($child->hasAttribute('align')) {
                $align = strtoupper($child->getAttribute('align'));
            } else if (isset($styles['float'])) {
                $align = strtoupper($styles['float']);
            }

            if ($align == 'LEFT') {
                $box = [
                    'left' => $currentX,
                    'right' => $currentX + $width + ($styles['marginRight'] ?? 0),
                    'top' => $currentY,
                    'bottom' => $currentY - $height - ($styles['marginBottom'] ?? 0)
                ];
                $this->textWrap = new Document\Page\Text\Wrap('RIGHT', $this->pageMargins['left'], $this->page->getWidth() - $this->pageMargins['right'], $box);
            } else if ($align == 'RIGHT') {
                $box = [
                    'left' => $this->page->getWidth() - $this->pageMargins['right'] - $width - ($styles['marginLeft'] ?? 0),
                    'right' => $this->page->getWidth() - $this->pageMargins['right'],
                    'top' => $currentY,
                    'bottom' => $currentY - $height - ($styles['marginBottom'] ?? 0)
                ];
                $this->textWrap = new Document\Page\Text\Wrap('LEFT', $this->pageMargins['left'], $this->page->getWidth() - $this->pageMargins['right'], $box);
            }

            if ($this->textWrap !== null) {
                $newY = $currentY - (($image->getResizedHeight() !== null) ? $image->getResizedHeight() : $image->getHeight());
                if ($align == 'RIGHT') {
                    $this->page->addImage($image, ($this->page->getWidth() - $this->pageMargins['right'] - $width), $newY);
                } else {
                    $this->page->addImage($image, $currentX, $newY);
                }
                $currentY -= $styles['lineHeight'];
                $this->y += $styles['lineHeight'];
            } else {
                $currentY -= ($image->getResizedHeight() !== null) ? $image->getResizedHeight() : $image->getHeight();
                $this->y += ($image->getResizedHeight() !== null) ? $image->getResizedHeight() : $image->getHeight();
                $this->page->addImage($image, $currentX, $currentY);
                $currentY -= $styles['lineHeight'];
                $this->y += $styles['lineHeight'];
            }
        // Table node
        } else if ($child->getNodeName() == 'table') {
            $tableWidth  = $this->page->getWidth() - $this->pageMargins['left'] - $this->pageMargins['right'];
            $columnCount = count($child->getChild(0)->getChildNodes());;
            $rowCount    = 0;
            $columnWidth = floor($tableWidth / $columnCount);
            $fontObject  = $this->document->getFont($styles['currentFont']);
            $currentRow  = 0;
            $currentX    = $this->pageMargins['left'] + 10;
            $thHeight    = 0;
            $offset      = 0;
            $startY      = $currentY;

            foreach ($child->getChildNodes() as $childNode) {
                if (($childNode->getNodeName() == 'tr') && ($childNode->hasChildNodes())) {
                    foreach ($childNode->getChildNodes() as $grandChild) {
                        if ($grandChild->getNodeName() == 'th') {
                            $thString = $grandChild->getNodeValue();

                            $thStringWidth = $fontObject->getStringWidth($thString, $styles['fontSize']);
                            if ($thStringWidth > ($columnWidth - 20)) {
                                $strings = $this->getStringLines($thString, $styles['fontSize'], $columnWidth - 20, $fontObject);
                                foreach ($strings as $i => $string) {
                                    $text = new Document\Page\Text($string, $styles['fontSize']);
                                    $this->page->addText($text, $styles['currentFont'], $currentX, ($currentY - ($styles['fontSize'] * $i)));
                                }
                                $newThHeight = (count($strings) * $styles['fontSize']) + 20;
                                if ($newThHeight > $thHeight) {
                                    $thHeight = $newThHeight;
                                    $offset   = $thHeight - 30;
                                }
                            } else {
                                $text = new Document\Page\Text($thString, $styles['fontSize']);
                                $this->page->addText($text, $styles['currentFont'], $currentX, $currentY);
                            }
                            $currentX += $columnWidth;
                        }
                    }
                }
            }

            foreach ($child->getChildNodes() as $childNode) {
                if (($childNode->getNodeName() == 'tr') && ($childNode->hasChildNodes())) {
                    $rowCount++;
                    $currentX  = $this->pageMargins['left'] + 10;
                    foreach ($childNode->getChildNodes() as $grandChild) {
                        if ($grandChild->getNodeName() == 'td') {
                            $text = new Document\Page\Text($grandChild->getNodeValue(), $styles['fontSize']);
                            $this->page->addText($text, $styles['currentFont'], $currentX, $currentY - $offset);
                            $currentX += $columnWidth;
                        }
                    }
                    $currentY -= 25;
                }
            }

            $currentY += 15;
            $x1 = $this->pageMargins['left'];
/**
            $finalHeight = (25 * $rowCount);
            $path    = new Document\Page\Path();
            $path->drawRectangle($x1, $currentY, $tableWidth, $finalHeight);
            $this->page->addPath($path);

            for ($i = 1; $i < $columnCount; $i++) {
                $path = new Document\Page\Path();
                $path->drawLine($x1 + ($i * $columnWidth), $currentY, $x1 + ($i * $columnWidth), $this->page->getHeight() - $finalHeight + 35);
                $this->page->addPath($path);
            }
*/

            for ($i = 0; $i < $rowCount; $i++) {
                if (($i == 0) && ($thHeight > 0)) {
                    $lineY = $startY - $thHeight + 20;
                } else {
                    $lineY = ($startY - ($i * 25) - 10) - $offset;
                }
                $path  = new Document\Page\Path();
                $path->drawLine($x1, $lineY, $tableWidth + $this->pageMargins['left'], $lineY);
                $this->page->addPath($path);
/**
                if (($i == 1) && ($thHeight > 0)) {
                    $lineY = ($currentY + ($i * 25));
                } else {
                    $lineY = ($currentY + ($i * 25));
                }
                $path  = new Document\Page\Path();
                $path->drawLine($x1, $lineY, $tableWidth + $this->pageMargins['left'], $lineY);
                $this->page->addPath($path);
*/
            }


        // Text node
        } else {
            if ($this->textWrap !== null) {
                $box = $this->textWrap->getBox();
                if ($this->textWrap->isRight()) {
                    $startX = $box['right'];
                    $startY = $box['top'] - $styles['fontSize'];
                    $edgeX  = $wrapLength;
                    $edgeY  = $box['bottom'];
                } else {
                    $startX = $currentX;
                    $startY = $box['top'] - $styles['fontSize'];
                    $edgeX  = $box['left'] - 40;
                    $edgeY  = $box['bottom'];
                }
            } else {
                $startX = $currentX;
                $startY = $currentY;
                $edgeX  = $wrapLength;
                $edgeY  = $this->pageMargins['bottom'];
            }

            $textStream = new Document\Page\Text\Stream($startX, $startY, $edgeX, $edgeY);
            $textStream->setCurrentStyle(
                $styles['currentFont'],
                $styles['fontSize'],
                new Color\Rgb($styles['color'][0], $styles['color'][1], $styles['color'][2]),
                $styles['textAlign']
            );
            $streamY = $styles['lineHeight'] ?? null;
            if (!empty($child->getNodeValue())) {
                $textStream->addText($child->getNodeValue(), $streamY);
            }

            $childTextStreams = [];
            if ($child->hasChildNodes()) {
                foreach ($child->getChildNodes() as $grandChild) {
                    $gcStyles = $this->prepareNodeStyles($grandChild->getNodeName(), $grandChild->getAttributes(), $styles);
                    $textStream->setCurrentStyle(
                        $gcStyles['currentFont'],
                        $gcStyles['fontSize'],
                        new Color\Rgb($gcStyles['color'][0], $gcStyles['color'][1], $gcStyles['color'][2])
                    );
                    $streamY = $gcStyles['lineHeight'] ?? null;
                    if (!empty($grandChild->getNodeValue())) {
                        $textStream->addText($grandChild->getNodeValue(), $streamY, ($grandChild->getNodeName() == 'br'));
                    }
                }
            }

            $this->page->addTextStream($textStream);
            if (!empty($childTextStreams)) {
                foreach ($childTextStreams as $childTextStream) {
                    $this->page->addTextStream($childTextStream);
                }
            }

            $orphanStream = clone $textStream;
            $hasOrphans   = false;

            while ($orphanStream->hasOrphans($this->document->getFonts())) {
                $orphanStream = $orphanStream->getOrphanStream();
                if ($orphanStream->getCurrentY() <= $this->pageMargins['bottom']) {
                    $currentY = $this->newPage();
                    $orphanStream->setCurrentY($currentY);
                } else {
                    $orphanStream->setStartX($this->pageMargins['left']);
                    $orphanStream->setEdgeX($wrapLength);
                    $orphanStream->setEdgeY($this->pageMargins['bottom']);
                }

                $orphanStream->setCurrentX($currentX);
                $this->page->addTextStream($orphanStream);

                $orphanStream = clone $orphanStream;
                $hasOrphans = true;
            }

            if ($hasOrphans) {
                $this->yOverride = $orphanStream->getCurrentY();
            } else {
                $this->yOverride = null;
                $this->y  += (!empty($styles['marginBottom'])) ? $styles['marginBottom'] : 25;
            }
        }
    }

    /**
     * Add node stream to document
     *
     * @param  Child $child
     * @throws Exception
     * @return void
     */
    protected function addNodeStreamToDocument(Child $child): void
    {
        $styles     = $this->prepareNodeStyles($child->getNodeName(), $child->getAttributes());
        $currentX   = $this->getCurrentX();
        $currentY   = $this->getCurrentY();
        $fontObject = $this->document->getFont($styles['currentFont']);
        $wrapLength = ($this->x > $this->pageMargins['left']) ?
            $this->page->getWidth() - $this->pageMargins['right'] - $this->x :
            $this->page->getWidth() - $this->pageMargins['right'] - $this->pageMargins['left'];

        $string      = $child->getNodeValue();
        $stringWidth = $fontObject->getStringWidth($string, $styles['fontSize']);

        if ($stringWidth > $wrapLength) {
            $strings = $this->getStringLines($string, $styles['fontSize'], $wrapLength, $fontObject);
            if ($this->x > $this->pageMargins['left']) {
                $text = new Document\Page\Text($strings[0], $styles['fontSize']);
                $text->setFillColor(new Color\Rgb($styles['color'][0], $styles['color'][1], $styles['color'][2]));
                $this->page->addText($text, $styles['currentFont'], $currentX, $currentY);
                if ($currentY <= $this->pageMargins['bottom']) {
                    $currentY = $this->newPage();
                } else {
                    $currentY -= $styles['lineHeight'];
                    $this->y  += $styles['lineHeight'];
                }
                $currentX = $this->resetX();
                $wrapLength = $this->page->getWidth() - $this->pageMargins['right'] - $this->pageMargins['left'];
                unset($strings[0]);
                $strings = $this->getStringLines(implode(' ', $strings), $styles['fontSize'], $wrapLength, $fontObject);
            }

            foreach ($strings as $i => $string) {
                $text = new Document\Page\Text($string, $styles['fontSize']);
                $text->setFillColor(new Color\Rgb($styles['color'][0], $styles['color'][1], $styles['color'][2]));
                $this->page->addText($text, $styles['currentFont'], $currentX, $currentY);
                if ($i < (count($strings) - 1)) {
                    if ($currentY <= $this->pageMargins['bottom']) {
                        $currentY = $this->newPage();
                    } else {
                        $currentY -= $styles['lineHeight'];
                        $this->y  += $styles['lineHeight'];
                    }
                }
            }
            $this->x += $fontObject->getStringWidth($string, $styles['fontSize']);
        } else {
            $text = new Document\Page\Text($string, $styles['fontSize']);
            $text->setFillColor(new Color\Rgb($styles['color'][0], $styles['color'][1], $styles['color'][2]));
            $this->page->addText($text, $styles['currentFont'], $currentX, $currentY);
            $this->x += $fontObject->getStringWidth($string, $styles['fontSize']);
        }

        foreach ($child->getChildNodes() as $grandChild) {
            $this->addNodeStreamToDocument($grandChild);
        }
    }

    /**
     * Create default styles
     *
     * @return void
     */
    protected function createDefaultStyles(): void
    {
        $h1 = new Css\Selector('h1');
        $h1['margin-bottom'] = '18px';
        $h1['font-size']     = '32px';
        $h1['font-weight']   = 'bold';

        $h2 = new Css\Selector('h2');
        $h2['margin-bottom'] = '18px';
        $h2['font-size']     = '28px';
        $h2['font-weight']   = 'bold';

        $h3 = new Css\Selector('h3');
        $h3['margin-bottom'] = '16px';
        $h3['font-size']     = '24px';
        $h3['font-weight']   = 'bold';

        $h4 = new Css\Selector('h4');
        $h4['margin-bottom'] = '14px';
        $h4['font-size']     = '20px';
        $h4['font-weight']   = 'bold';

        $h5 = new Css\Selector('h5');
        $h5['margin-bottom'] = '12px';
        $h5['font-size']     = '16px';
        $h5['font-weight']   = 'bold';

        $h6 = new Css\Selector('h6');
        $h6['margin-bottom'] = '10px';
        $h6['font-size']     = '14px';
        $h6['font-weight']   = 'bold';

        $p = new Css\Selector('p');
        $p['margin-bottom'] = '24px';
        $p['font-size']     = '12px';

        $a = new Css\Selector('a');
        $a['color'] = new Color\Rgb(0, 0, 255);

        $strong = new Css\Selector('strong');
        $strong['font-size']   = '10px';
        $strong['font-weight'] = 'bold';

        $em = new Css\Selector('em');
        $em['font-size']  = '10px';
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
     * @param  array  $currentStyles
     * @throws Exception
     * @return array
     */
    protected function prepareNodeStyles(string $name, array $attribs = [], array $currentStyles = []): array
    {
        $styles = [
            'currentFont'   => null,
            'fontFamily'    => $currentStyles['fontFamily'] ?? $this->defaultStyles['font-family'],
            'fontSize'      => $currentStyles['fontSize'] ?? $this->defaultStyles['font-size'],
            'fontWeight'    => $currentStyles['fontWeight'] ?? $this->defaultStyles['font-weight'],
            'float'         => null,
            'width'         => null,
            'height'        => null,
            'color'         => $currentStyles['color'] ?? $this->defaultStyles['color'],
            'lineHeight'    => $currentStyles['lineHeight'] ?? $this->defaultStyles['line-height'],
            'marginTop'     => 0,
            'paddingTop'    => 0,
            'marginRight'   => 0,
            'paddingRight'  => 0,
            'marginBottom'  => 0,
            'paddingBottom' => 0,
            'marginLeft'    => 0,
            'paddingLeft'   => 0,
            'textAlign'     => null
        ];

        if (in_array($name, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
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
                //$styles['fontSize'] = (int)$this->css[$name]['font-size'];
            }
            if ($this->css[$name]->hasProperty('font-weight')) {
                $styles['fontWeight'] = $this->css[$name]['font-weight'];
            }
            if ($this->css[$name]->hasProperty('color')) {
                $styles['color'] = $this->css[$name]['color'];
                if (is_string($styles['color'])) {
                    $cssColor = Color::parse($styles['color']);
                    $styles['color'] = $cssColor->toRgb()->toArray(false);
                }
            }
            if ($this->css[$name]->hasProperty('float')) {
                $styles['float'] = $this->css[$name]['float'];
            }
            if ($this->css[$name]->hasProperty('width')) {
                $styles['width'] = $this->css[$name]['width'];
            }
            if ($this->css[$name]->hasProperty('height')) {
                $styles['height'] = $this->css[$name]['height'];
            }
            if ($this->css[$name]->hasProperty('line-height')) {
                $styles['lineHeight'] = (int)$this->css[$name]['line-height'];
            }
            if ((int)$this->css[$name]['margin-top'] > 0) {
                $styles['marginTop'] = (int)$this->css[$name]['margin-top'];
            }
            if ((int)$this->css[$name]['padding-top'] > 0) {
                $styles['paddingTop'] = (int)$this->css[$name]['padding-top'];
            }
            if ((int)$this->css[$name]['margin-right'] > 0) {
                $styles['marginRight'] = (int)$this->css[$name]['margin-right'];
            }
            if ((int)$this->css[$name]['padding-right'] > 0) {
                $styles['paddingRight'] = (int)$this->css[$name]['padding-right'];
            }
            if ((int)$this->css[$name]['margin-bottom'] > 0) {
                $styles['marginBottom'] = (int)$this->css[$name]['margin-bottom'];
            }
            if ((int)$this->css[$name]['padding-bottom'] > 0) {
                $styles['paddingBottom'] = (int)$this->css[$name]['padding-bottom'];
            }
            if ((int)$this->css[$name]['margin-left'] > 0) {
                $styles['marginLeft'] = (int)$this->css[$name]['margin-left'];
            }
            if ((int)$this->css[$name]['padding-left'] > 0) {
                $styles['paddingLeft'] = (int)$this->css[$name]['padding-left'];
            }
            if ($this->css[$name]->hasProperty('text-align')) {
                $styles['textAlign'] = $this->css[$name]['text-align'];
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
                    $cssColor = Color::parse($styles['color']);
                    $styles['color'] = $cssColor->toRgb()->toArray(false);
                }
            }
            if ($this->css['#' . $attribs['id']]->hasProperty('float')) {
                $styles['float'] = $this->css['#' . $attribs['id']]['float'];
            }
            if ($this->css['#' . $attribs['id']]->hasProperty('width')) {
                $styles['width'] = $this->css['#' . $attribs['id']]['width'];
            }
            if ($this->css['#' . $attribs['id']]->hasProperty('height')) {
                $styles['height'] = $this->css['#' . $attribs['id']]['height'];
            }
            if ($this->css['#' . $attribs['id']]->hasProperty('line-height')) {
                $styles['lineHeight'] = (int)$this->css['#' . $attribs['id']]['line-height'];
            }
            if ((int)$this->css['#' . $attribs['id']]['margin-top'] > 0) {
                $styles['marginTop'] = (int)$this->css['#' . $attribs['id']]['margin-top'];
            }
            if ((int)$this->css['#' . $attribs['id']]['padding-top'] > 0) {
                $styles['paddingTop'] = (int)$this->css['#' . $attribs['id']]['padding-top'];
            }
            if ((int)$this->css['#' . $attribs['id']]['margin-right'] > 0) {
                $styles['marginRight'] = (int)$this->css['#' . $attribs['id']]['margin-right'];
            }
            if ((int)$this->css['#' . $attribs['id']]['padding-right'] > 0) {
                $styles['paddingRight'] = (int)$this->css['#' . $attribs['id']]['padding-right'];
            }
            if ((int)$this->css['#' . $attribs['id']]['margin-bottom'] > 0) {
                $styles['marginBottom'] = (int)$this->css['#' . $attribs['id']]['margin-bottom'];
            }
            if ((int)$this->css['#' . $attribs['id']]['padding-bottom'] > 0) {
                $styles['paddingBottom'] = (int)$this->css['#' . $attribs['id']]['padding-bottom'];
            }
            if ((int)$this->css['#' . $attribs['id']]['margin-left'] > 0) {
                $styles['marginLeft'] = (int)$this->css['#' . $attribs['id']]['margin-left'];
            }
            if ((int)$this->css['#' . $attribs['id']]['padding-left'] > 0) {
                $styles['paddingLeft'] = (int)$this->css['#' . $attribs['id']]['padding-left'];
            }
            if ($this->css['#' . $attribs['id']]->hasProperty('text-align')) {
                $styles['textAlign'] = $this->css['#' . $attribs['id']]['text-align'];
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
                    $cssColor = Color::parse($styles['color']);
                    $styles['color'] = $cssColor->toRgb()->toArray(false);
                }
            }
            if ($this->css['.' . $attribs['class']]->hasProperty('float')) {
                $styles['float'] = $this->css['.' . $attribs['class']]['float'];
            }
            if ($this->css['.' . $attribs['class']]->hasProperty('width')) {
                $styles['width'] = $this->css['.' . $attribs['class']]['width'];
            }
            if ($this->css['.' . $attribs['class']]->hasProperty('height')) {
                $styles['height'] = $this->css['.' . $attribs['class']]['height'];
            }
            if ($this->css['.' . $attribs['class']]->hasProperty('line-height')) {
                $styles['lineHeight'] = (int)$this->css['.' . $attribs['class']]['line-height'];
            }
            if ((int)$this->css['.' . $attribs['class']]['margin-top'] > 0) {
                $styles['marginTop'] = (int)$this->css['.' . $attribs['class']]['margin-top'];
            }
            if ((int)$this->css['.' . $attribs['class']]['padding-top'] > 0) {
                $styles['paddingTop'] = (int)$this->css['.' . $attribs['class']]['padding-top'];
            }
            if ((int)$this->css['.' . $attribs['class']]['margin-right'] > 0) {
                $styles['marginRight'] = (int)$this->css['.' . $attribs['class']]['margin-right'];
            }
            if ((int)$this->css['.' . $attribs['class']]['padding-right'] > 0) {
                $styles['paddingRight'] = (int)$this->css['.' . $attribs['class']]['padding-right'];
            }
            if ((int)$this->css['.' . $attribs['class']]['margin-bottom'] > 0) {
                $styles['marginBottom'] = (int)$this->css['.' . $attribs['class']]['margin-bottom'];
            }
            if ((int)$this->css['.' . $attribs['class']]['padding-bottom'] > 0) {
                $styles['paddingBottom'] = (int)$this->css['.' . $attribs['class']]['padding-bottom'];
            }
            if ((int)$this->css['.' . $attribs['class']]['margin-left'] > 0) {
                $styles['marginLeft'] = (int)$this->css['.' . $attribs['class']]['margin-left'];
            }
            if ((int)$this->css['.' . $attribs['class']]['padding-left'] > 0) {
                $styles['paddingLeft'] = (int)$this->css['.' . $attribs['class']]['padding-left'];
            }
            if ($this->css['.' . $attribs['class']]->hasProperty('text-align')) {
                $styles['textAlign'] = $this->css['.' . $attribs['class']]['text-align'];
            }
        }

        if (str_contains($styles['fontFamily'], ',')) {
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

        if ($styles['currentFont'] === null) {
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
    protected function getCurrentX(): int
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
    protected function resetX(): int
    {
        $this->x = $this->pageMargins['left'];
        return $this->x;
    }

    /**
     * Get current Y-position
     *
     * @param  int $marginBottom
     * @return int
     */
    protected function getCurrentY($marginBottom = 0): int
    {
        if (!($this->document->hasPages())) {
            $this->page = (is_array($this->pageSize)) ?
                new Document\Page($this->pageSize['width'], $this->pageSize['height']) : new Document\Page($this->pageSize);
            $this->document->addPage($this->page);
        } else {
            $this->page = $this->document->getPage($this->document->getCurrentPage());
        }

        $currentY = $this->page->getHeight() - $this->pageMargins['top'] - $this->y - $marginBottom;

        if ($currentY <= $this->pageMargins['bottom']) {
            $this->page = (is_array($this->pageSize)) ?
                new Document\Page($this->pageSize['width'], $this->pageSize['height']) : new Document\Page($this->pageSize);
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
    protected function resetY(): int
    {
        if (!($this->document->hasPages())) {
            $this->page = (is_array($this->pageSize)) ?
                new Document\Page($this->pageSize['width'], $this->pageSize['height']) : new Document\Page($this->pageSize);
            $this->document->addPage($this->page);
        } else {
            $this->page = $this->document->getPage($this->document->getCurrentPage());
        }

        $this->y  = 0;

        return $this->page->getHeight() - $this->pageMargins['top'];
    }

    /**
     * Create new page
     *
     * @return int
     */
    protected function newPage(): int
    {
        $this->page = (is_array($this->pageSize)) ?
            new Document\Page($this->pageSize['width'], $this->pageSize['height']) : new Document\Page($this->pageSize);
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
    protected function goToNextLine(array $styles): void
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
    protected function getStringLines(string $string, int $fontSize, int $wrapLength, Document\Font $fontObject): array
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
