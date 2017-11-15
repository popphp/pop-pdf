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

use Pop\Dom\Child;
use Pop\Css\Css;
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
     * HTML object
     * @var Child
     */
    protected $html = null;

    /**
     * CSS object
     * @var Css
     */
    protected $css = null;

    /**
     * Style defaults
     * @var array
     */
    protected $styleDefaults = [
        'page'   => 'LETTER',
        'font'   => 'Arial',
        'size'   => 12,
        'color'  => [0, 0, 0],
        'h1'     => ['size' => 32, 'bold' => true],
        'h2'     => ['size' => 28, 'bold' => true],
        'h3'     => ['size' => 24, 'bold' => true],
        'h4'     => ['size' => 20, 'bold' => true],
        'h5'     => ['size' => 16, 'bold' => true],
        'h6'     => ['size' => 12, 'bold' => true],
        'p'      => ['size' => 12],
        'a'      => ['color' => [0, 0, 255]],
        'strong' => ['size' => 12, 'bold' => true],
        'em'     => ['size' => 12, 'italic' => true]
    ];

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
        $css = new self($document);
        $css->parseHtmlFile($htmlFile);

        return $css;
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
        $css = new self($document);
        $css->parseHtmlUri($htmlUri);

        return $css;
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

}