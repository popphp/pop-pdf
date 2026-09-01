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

use Pop\Pdf\Document\AbstractDocument;
use Pop\Pdf\Document\Metadata;
use Pop\Pdf\Extract\Document as ExtractDocument;
use Pop\Pdf\Extract\Value;

/**
 * Pdf parser class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Parser extends AbstractParser
{

    /**
     * Parsed object data streams - retained only for public API
     * compatibility (getObjectStreams()); no longer populated under the
     * Extract\Document-based implementation, since no consumer depends on
     * its contents (only its array type).
     * @var array
     */
    protected array $objectStreams = [];

    /**
     * Object map - retained only for public API compatibility
     * (getObjectMap()); see $objectStreams.
     * @var array
     */
    protected array $objectMap = [];

    /**
     * Document fonts - retained only for public API compatibility
     * (getFonts()); font resources are now carried per-page via each
     * translated PageObject's own structured font references instead of
     * this document-wide bag.
     * @var array
     */
    protected array $fonts = [];

    /**
     * Password used to decrypt an encrypted source PDF, if any
     * @var ?string
     */
    protected ?string $password = null;

    /**
     * Get the object streams
     *
     * @return array
     */
    public function getObjectStreams(): array
    {
        return $this->objectStreams;
    }

    /**
     * Get the object map
     *
     * @return array
     */
    public function getObjectMap(): array
    {
        return $this->objectMap;
    }

    /**
     * Get the document fonts
     *
     * @return array
     */
    public function getFonts(): array
    {
        return $this->fonts;
    }

    /**
     * Parse from file
     *
     * @param  string  $file
     * @param  mixed   $pages
     * @param  ?string $password
     * @throws Exception
     * @return AbstractDocument
     */
    public function parseFile(string $file, mixed $pages = null, ?string $password = null): AbstractDocument
    {
        $this->password = $password;
        $this->initFile($file);
        return $this->parse($pages);
    }

    /**
     * Parse from raw data stream
     *
     * @param  string  $data
     * @param  mixed   $pages
     * @param  ?string $password
     * @throws Exception
     * @return AbstractDocument
     */
    public function parseData(string $data, mixed $pages = null, ?string $password = null): AbstractDocument
    {
        $this->password = $password;
        $this->initData($data);
        return $this->parse($pages);
    }

    /**
     * Parse the data stream
     *
     * @param  mixed  $pages
     * @throws Exception
     * @return AbstractDocument
     */
    public function parse(mixed $pages = null): AbstractDocument
    {
        try {
            $extractDoc = new ExtractDocument($this->data, $this->password);
            $graph      = Import\ObjectGraphReader::read($extractDoc, 0);
        } catch (\Pop\Pdf\Extract\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        $rootObjNum = $graph['nextOffset'] + 1;
        $nextFree   = $rootObjNum + 1;

        $parent = new PdfObject\ParentObject($graph['topPagesObjNum']);
        $parent->setKids(self::kidNumbers($graph['topPagesDict']));
        $parent->setCount(count($graph['pageObjects']));
        $parent->setImported(true);

        $root = new PdfObject\RootObject($rootObjNum);
        $root->setParentIndex($graph['topPagesObjNum']);
        $root->setImported(true);

        $objects                           = $graph['objects'];
        $objects[$graph['topPagesObjNum']] = $parent;
        $objects[$rootObjNum]              = $root;

        $doc = new \Pop\Pdf\Document();

        // A source PDF that encrypts its STRINGS (any non-/Identity /StrF -
        // the norm for third-party encryptors, unlike this library's own
        // /StrF /Identity output) hands back /Info values that are still raw
        // AES ciphertext, because nothing in Extract\* decrypts strings. Those
        // bytes are not metadata in any useful sense, so they are dropped
        // rather than carried into Document\Metadata and re-emitted as this
        // document's title/author/dates.
        if (($graph['infoDict'] !== null) && !$extractDoc->hasEncryptedStrings()) {
            $doc->setMetadata(self::metadataFromDict($graph['infoDict']));
        }

        $info = new PdfObject\InfoObject($nextFree);
        $info->setImported(true);
        $objects[$nextFree] = $info;

        $doc->importObjects($objects);

        $pageObjects = $graph['pageObjects'];

        if ($pages !== null) {
            $pages    = (!is_array($pages)) ? [$pages] : $pages;
            $kept     = [];
            $keptKids = [];

            foreach ($pages as $pageNum) {
                if (isset($pageObjects[$pageNum - 1])) {
                    $kept[]     = $pageObjects[$pageNum - 1];
                    $keptKids[] = $pageObjects[$pageNum - 1]->getIndex();
                }
            }

            $pageObjects = $kept;
            $parent->setKids($keptKids);
            $parent->setCount(count($keptKids));
        }

        foreach ($pageObjects as $pageObject) {
            $pg = new \Pop\Pdf\Document\Page($pageObject->getWidth(), $pageObject->getHeight(), $pageObject->getIndex());
            $pg->importPageObject($pageObject);
            $doc->addPage($pg);
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
    protected function initFile(string $file): Parser
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
     * @return Parser
     */
    protected function initData(string $data): Parser
    {
        $this->data = $data;

        $this->objectStreams = [];
        $this->objectMap     = [];
        $this->fonts         = [];

        return $this;
    }

    /**
     * Extract a rewritten Pages node dict's Kids as a flat list of new object numbers
     *
     * @param  array $topPagesDict
     * @return array
     */
    protected static function kidNumbers(array $topPagesDict): array
    {
        $numbers = [];
        $kids    = $topPagesDict['Kids'] ?? [];

        if (is_array($kids)) {
            foreach ($kids as $kid) {
                if ($kid instanceof Value\Reference) {
                    $numbers[] = $kid->objNum;
                }
            }
        }

        return $numbers;
    }

    /**
     * Build a Document\Metadata from a rewritten Info dict
     *
     * @param  array $infoDict
     * @return Metadata
     */
    protected static function metadataFromDict(array $infoDict): Metadata
    {
        $metadata = new Metadata();

        if (isset($infoDict['Title']) && is_string($infoDict['Title'])) {
            $metadata->setTitle($infoDict['Title']);
        }
        if (isset($infoDict['Author']) && is_string($infoDict['Author'])) {
            $metadata->setAuthor($infoDict['Author']);
        }
        if (isset($infoDict['Subject']) && is_string($infoDict['Subject'])) {
            $metadata->setSubject($infoDict['Subject']);
        }
        if (isset($infoDict['Creator']) && is_string($infoDict['Creator'])) {
            $metadata->setCreator($infoDict['Creator']);
        }
        if (isset($infoDict['Producer']) && is_string($infoDict['Producer'])) {
            $metadata->setProducer($infoDict['Producer']);
        }
        if (isset($infoDict['CreationDate']) && is_string($infoDict['CreationDate'])) {
            $metadata->setCreationDate($infoDict['CreationDate']);
        }
        if (isset($infoDict['ModDate']) && is_string($infoDict['ModDate'])) {
            $metadata->setModDate($infoDict['ModDate']);
        }

        return $metadata;
    }

}
