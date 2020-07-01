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

use Pop\Pdf\Document\AbstractDocument;

/**
 * Pdf parser class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Parser extends AbstractParser
{

    /**
     * Parsed object data streams
     * @var array
     */
    protected $objectStreams = [];

    /**
     * Object map
     * @var array
     */
    protected $objectMap = [];

    /**
     * Document fonts
     * @var array
     */
    protected $fonts = [];

    /**
     * Get the object streams
     *
     * @return array
     */
    public function getObjectStreams()
    {
        return $this->objectStreams;
    }

    /**
     * Get the object map
     *
     * @return array
     */
    public function getObjectMap()
    {
        return $this->objectMap;
    }

    /**
     * Get the document fonts
     *
     * @return array
     */
    public function getFonts()
    {
        return $this->fonts;
    }

    /**
     * Parse from file
     *
     * @param  string $file
     * @param  mixed  $pages
     * @return AbstractDocument
     */
    public function parseFile($file, $pages = null)
    {
        $this->initFile($file);
        return $this->parse($pages);
    }

    /**
     * Parse from raw data stream
     *
     * @param  string $data
     * @param  mixed  $pages
     * @return AbstractDocument
     */
    public function parseData($data, $pages = null)
    {
        $this->initData($data);
        return $this->parse($pages);
    }

    /**
     * Parse the data stream
     *
     * @param  mixed  $pages
     * @return AbstractDocument
     */
    public function parse($pages = null)
    {
        $matches = [];
        preg_match_all('/\d*\s\d*\sobj(.*?)endobj/sm', $this->data, $matches, PREG_OFFSET_CAPTURE);

        if (isset($matches[0]) && isset($matches[0][0])) {
            foreach ($matches[0] as $match) {
                if ((strpos($match[0], '/Linearized') === false) && (strpos($match[0], '/Type/Metadata') === false)) {
                    $this->objectStreams[] = $match[0];
                }
            }
        }

        // Map the objects by parsing the object streams
        $this->mapObjects();

        if (isset($this->objectMap['pages'])) {
            // Map fonts, if any
            if (isset($this->objectMap['streams'])) {
                $this->mapFonts();
            }
            // If certain pages are to be imported, filter out the unwanted pages
            if (null !== $pages) {
                $this->filterPages($pages);
            }
        }

        $doc = new \Pop\Pdf\Document();

        if (isset($this->objectMap['root']) && isset($this->objectMap['root']['object'])) {
            $doc->setVersion($this->objectMap['root']['object']->getVersion());
        }
        if (isset($this->objectMap['info']) && isset($this->objectMap['info']['object'])) {
            $doc->setMetadata($this->objectMap['info']['object']->getMetadata());
        }

        $doc->importObjects($this->getObjects());
        $doc->importFonts($this->getFonts());

        if (isset($this->objectMap['pages'])) {
            foreach ($this->objectMap['pages'] as $i => $page) {
                $pg = new \Pop\Pdf\Document\Page($page['width'], $page['height'], $i);
                $pg->importPageObject($page['object']);
                $doc->addPage($pg);
            }
        }

        return $doc;
    }

    /**
     * Initialize the file and get the data
     *
     * @param  string $file
     * @throws Exception
     * @return Parser
     */
    protected function initFile($file)
    {
        if (!file_exists($file)) {
            throw new Exception('Error: That PDF file does not exist.');
        }

        $this->file = $file;
        $this->data = file_get_contents($this->file);

        $this->objectStreams = [];
        $this->objectMap     = [];
        $this->fonts         = [];

        return $this;
    }

    /**
     * Initialize data
     *
     * @param  string $data
     * @throws Exception
     * @return Parser
     */
    protected function initData($data)
    {
        $this->data = $data;

        $this->objectStreams = [];
        $this->objectMap     = [];
        $this->fonts         = [];

        return $this;
    }

    /**
     * Map the objects
     *
     * @return void
     */
    protected function mapObjects()
    {
        foreach ($this->objectStreams as $stream) {
            switch ($this->getStreamType($stream)) {
                case 'root':
                    $root = PdfObject\RootObject::parse($stream);
                    $root->setImported(true);
                    $root->setVersion(substr($this->data, 5, 3));
                    $this->objectMap['root'] = [
                        'stream' => $stream,
                        'object' => $root,
                        'index'  => $root->getIndex(),
                        'parent' => $root->getParentIndex()
                    ];
                    break;
                case 'parent':
                    $parent = PdfObject\ParentObject::parse($stream);
                    $parent->setImported(true);
                    $this->objectMap['parent'] = [
                        'stream' => $stream,
                        'object' => $parent,
                        'index'  => $parent->getIndex(),
                        'count'  => $parent->getCount(),
                        'kids'   => $parent->getKids()
                    ];
                    break;
                case 'info':
                    $info = PdfObject\InfoObject::parse($stream);
                    $info->setImported(true);
                    $this->objectMap['info'] = [
                        'stream' => $stream,
                        'object' => $info,
                        'index'  => $info->getIndex(),
                    ];
                    break;
                case 'page':
                    if (!isset($this->objectMap['pages'])) {
                        $this->objectMap['pages'] = [];
                    }

                    $page = PdfObject\PageObject::parse($stream);
                    $page->setImported(true);

                    $this->objectMap['pages'][$page->getIndex()] = [
                        'stream'   => $stream,
                        'object'   => $page,
                        'index'    => $page->getIndex(),
                        'parent'   => $page->getParentIndex(),
                        'width'    => $page->getWidth(),
                        'height'   => $page->getHeight(),
                        'content'  => $page->getContent(),
                        'annots'   => $page->getAnnots(),
                        'fonts'    => $page->getFonts(),
                        'xObjects' => $page->getXObjects()
                    ];
                    break;
                case 'stream':
                    if (!isset($this->objectMap['streams'])) {
                        $this->objectMap['streams'] = [];
                    }
                    $stream = PdfObject\StreamObject::parse($stream);
                    $stream->setImported(true);
                    $this->objectMap['streams'][$stream->getIndex()] = [
                        'stream' => $stream,
                        'object' => $stream,
                        'index'  => $stream->getIndex()
                    ];
                    break;
            }
        }
    }

    /**
     * Map the fonts, if any
     *
     * @return void
     */
    protected function mapFonts()
    {
        foreach ($this->objectMap['pages'] as $page) {
            if (isset($page['fonts']) && (count($page['fonts']) > 0)) {
                foreach ($page['fonts'] as $i => $font) {
                    if (strpos($this->objectMap['streams'][$i]['stream'], '/BaseFont') !== false) {
                        $fontName = trim(
                            substr(
                                $this->objectMap['streams'][$i]['stream'],
                                (strpos($this->objectMap['streams'][$i]['stream'], '/BaseFont') + 9)
                            )
                        );

                        if (substr($fontName, 0, 1) == '/') {
                            $fontName = substr($fontName, 1);
                        }
                        $fontName = ((strpos($fontName, '/') !== false)) ?
                            substr($fontName, 0, strpos($fontName, '/')) :
                            substr($fontName, 0, strpos($fontName, '>'));

                        $f = [
                            'name'  => trim($fontName),
                            'index' => $i,
                            'ref'   => $font
                        ];

                        if (!in_array($f, $this->fonts, true)) {
                            $this->fonts[] = $f;
                        }
                    }
                }
            }
        }

        $fontFileObjects = [];
        foreach ($this->objectStreams as $stream) {
            if (strpos($stream, '/FontFile') !== false) {
                $fontFileObject = substr($stream, strpos($stream, '/FontFile'));
                $fontFileObject = substr($fontFileObject, (strpos($fontFileObject, ' ') + 1));
                $fontFileObject = trim(substr($fontFileObject, 0, strpos($fontFileObject, '0 R')));
                $fontFileObjects[] = $fontFileObject;
            }
        }

        if (!empty($fontFileObjects)) {
            foreach ($fontFileObjects as $fontFileObject) {
                if (($fontFileObject == 13) && isset($this->objectMap['streams'][$fontFileObject])) {
                    $fontFile = $this->objectMap['streams'][$fontFileObject];
                    $contents = ($fontFile['object']->getEncoding() == 'FlateDecode') ?
                        gzuncompress(trim($fontFile['object']->getStream())) : $fontFile['object']->getStream();

                    $fontParser = new \Pop\Pdf\Build\Font\TrueType(null, $contents);
                }
            }
        }
    }

    /**
     * Filter pages
     *
     * @param  mixed $pages
     * @return void
     */
    protected function filterPages($pages)
    {
        $pages = (!is_array($pages)) ? [$pages] : $pages;
        $kids = $this->objectMap['parent']['object']->getKids();
        $keep = [];
        foreach ($pages as $page) {
            if (isset($kids[$page - 1])) {
                $keep[] = $kids[$page - 1];
            }
        }

        $this->objectMap['parent']['object']->setKids($keep);
        $this->objectMap['parent']['count']  = count($keep);
        $this->objectMap['parent']['kids']   = $keep;

        foreach ($kids as $kid) {
            if (!in_array($kid, $keep) && isset($this->objectMap['pages'][$kid])) {
                unset($this->objectMap['pages'][$kid]);
            }
        }
    }

    /**
     * Get the objects for import
     *
     * @return array
     */
    protected function getObjects()
    {
        $objects = [];
        foreach ($this->objectMap as $type => $object) {
            if (($type == 'root') || ($type == 'parent') || ($type == 'info')) {
                $objects[$object['index']] = $object['object'];
            } else if ($type == 'streams') {
                foreach ($object as $obj) {
                    $objects[$obj['index']] = $obj['stream'];
                }
            }
        }

        return $objects;
    }

}