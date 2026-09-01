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
namespace Pop\Pdf;

use Pop\Pdf\Document\AbstractDocument;
use Pop\Pdf\Document\Exception;

/**
 * Pop Pdf class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class Pdf
{

    /**
     * Write document to file
     *
     * @param  Document $document
     * @param  string   $filename
     * @return void
     */
    public static function writeToFile(Document $document, string $filename = 'pop.pdf'): void
    {
        $compiler = new Build\Compiler();
        $compiler->finalize($document);
        file_put_contents($filename, $compiler->getOutput());
    }

    /**
     * Output to HTTP response
     *
     * @param  Document $document
     * @param  string   $filename
     * @param  bool     $forceDownload
     * @param  array    $headers
     * @return void
     */
    public static function outputToHttp(
        Document $document, string $filename = 'pop.pdf', bool $forceDownload = false, array $headers = []
    ): void
    {
        $headers['Content-type']        = 'application/pdf';
        $headers['Content-disposition'] = (($forceDownload) ? 'attachment; ' : null) . 'filename=' . $filename;

        $compiler = new Build\Compiler();
        $compiler->finalize($document);

        // Send the headers and output the PDF
        if (!headers_sent()) {
            header('HTTP/1.1 200 OK');
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $compiler->getOutput();
    }

    /**
     * Import from an HTML string
     *
     * @param  string            $html
     * @param  Document          $document
     * @param  string|array|null $pageSize
     * @return AbstractDocument
     */
    public static function importFromHtml(
        string $html, Document $document = new Document(), string|array|null $pageSize = null
    ): AbstractDocument
    {
        $parser = new Build\Html\Parser($document, $pageSize);
        $parser->parseHtml($html)->process();

        return $parser->document();
    }

    /**
     * Import from an HTML file
     *
     * @param  string            $htmlFile
     * @param  Document          $document
     * @param  string|array|null $pageSize
     * @return AbstractDocument
     */
    public static function importFromHtmlFile(
        string $htmlFile, Document $document = new Document(), string|array|null $pageSize = null
    ): AbstractDocument
    {
        $parser = new Build\Html\Parser($document, $pageSize);
        $parser->parseHtmlFile($htmlFile)->process();

        return $parser->document();
    }

    /**
     * Import from an HTML URI
     *
     * @param  string            $htmlUri
     * @param  Document          $document
     * @param  string|array|null $pageSize
     * @return AbstractDocument
     */
    public static function importFromHtmlUri(
        string $htmlUri, Document $document = new Document(), string|array|null $pageSize = null
    ): AbstractDocument
    {
        $parser = new Build\Html\Parser($document, $pageSize);
        $parser->parseHtmlUri($htmlUri)->process();

        return $parser->document();
    }

    /**
     * Import from an existing PDF file
     *
     * @param  string  $file
     * @param  mixed   $pages
     * @param  ?string $password
     * @return AbstractDocument
     */
    public static function importFromFile(string $file, mixed $pages = null, ?string $password = null): AbstractDocument
    {
        $parser = new Build\Parser();
        return $parser->parseFile($file, $pages, $password);
    }

    /**
     * Import from raw data stream
     *
     * @param  string  $data
     * @param  mixed   $pages
     * @param  ?string $password
     * @return AbstractDocument
     */
    public static function importRawData(string $data, mixed $pages = null, ?string $password = null): AbstractDocument
    {
        $parser = new Build\Parser();
        return $parser->parseData($data, $pages, $password);
    }

    /**
     * Merge PDF files into one document
     *
     * @param  array    $files
     * @param  Document $document
     * @param  array    $passwords per-source decryption passwords, keyed the same way as $files
     *                             (e.g. [1 => 'secret'] to supply a password only for $files[1]);
     *                             a source with no entry (or a null entry) is opened with no password.
     * @return AbstractDocument
     */
    public static function merge(array $files, Document $document = new Document(), array $passwords = []): AbstractDocument
    {
        $merger = new Build\Merger();
        return $merger->mergeFiles($files, $document, $passwords);
    }

    /**
     * Merge raw PDF data streams into one document
     *
     * @param  array    $data
     * @param  Document $document
     * @param  array    $passwords per-source decryption passwords, keyed the same way as $data
     *                             (e.g. [1 => 'secret'] to supply a password only for $data[1]);
     *                             a source with no entry (or a null entry) is opened with no password.
     * @return AbstractDocument
     */
    public static function mergeRawData(array $data, Document $document = new Document(), array $passwords = []): AbstractDocument
    {
        $merger = new Build\Merger();
        return $merger->mergeData($data, $document, $passwords);
    }

    /**
     * Import from an existing PDF file
     *
     * @param  string|array $images
     * @param  int          $quality
     * @throws Exception
     * @return AbstractDocument
     */
    public static function importFromImages(string|array $images, int $quality = 70): AbstractDocument
    {
        if (!is_array($images)) {
            $images = [$images];
        }

        $document = new Document();

        foreach ($images as $image) {
            $document->addPage(Document\Page::createFromImage($image, $quality));
        }

        return $document;
    }

    /**
     * Extract as images
     *
     * @param  string $file
     * @param  string $location
     * @param  string $format
     * @param  int    $resolution
     * @param  string $filenameFormat sprintf() format string, given the file's basename and the 1-indexed
     *                                page number (e.g. '%1$s-%2$02d' => 'document-01', 'page-%2$02d' => 'page-01')
     * @param  mixed  $pages
     * @param  ?int   $pageLimit
     * @return array
     *@throws Build\Exception
     */
    public static function extractAsImages(
        string $file, string $location, string $format = 'jpg', int $resolution = 300,
        string $filenameFormat = '%1$s-%2$02d', mixed $pages = null, ?int $pageLimit = null
    ): array
    {
        $extractor  = new Build\Image\Extractor();
        $totalPages = $extractor->countPages($file);
        $allPages   = ($totalPages > 0) ? range(1, $totalPages) : [];

        $pages       = self::normalizePages($pages);
        $pageNumbers = self::selectByPageFilter($allPages, $pages, $pageLimit);

        return $extractor->extract($file, $location, $format, $resolution, $filenameFormat, $pageNumbers);
    }

    /**
     * Extract text from file
     *
     * @param  string  $file
     * @param  mixed   $pages
     * @param  ?int    $pageLimit
     * @param  ?string $password
     * @return string
     */
    public static function extractTextFromFile(
        string $file, mixed $pages = null, ?int $pageLimit = null, ?string $password = null
    ): string
    {
        $doc = Extract\Document::fromFile($file, $password);
        return self::extractTextFromDocument($doc, $pages, $pageLimit);
    }

    /**
     * Extract text from raw data stream
     *
     * @param  string  $data
     * @param  mixed   $pages
     * @param  ?int    $pageLimit
     * @param  ?string $password
     * @return string
     */
    public static function extractTextFromData(
        string $data, mixed $pages = null, ?int $pageLimit = null, ?string $password = null
    ): string
    {
        $doc = new Extract\Document($data, $password);
        return self::extractTextFromDocument($doc, $pages, $pageLimit);
    }

    /**
     * Extract text from a parsed Extract\Document, joining pages/runs
     *
     * @param  Extract\Document $doc
     * @param  mixed            $pages
     * @param  ?int             $pageLimit
     * @return string
     */
    protected static function extractTextFromDocument(Extract\Document $doc, mixed $pages, ?int $pageLimit): string
    {
        $pages    = self::normalizePages($pages);
        $docPages = Extract\Content\PageWalker::walk($doc, $pages, $pageLimit);
        $docPages = self::selectByPageFilter($docPages, $pages, $pageLimit);

        $texts = [];

        foreach ($docPages as $docPage) {
            $pageText = self::extractTextFromPage($doc, $docPage);
            $pageText = trim($pageText);

            if ($pageText !== '') {
                $texts[] = $pageText;
            }
        }

        return implode("\n\n", $texts);
    }

    /**
     * Extract text from a single page, joining runs by their separator
     *
     * @param  Extract\Document         $doc
     * @param  Extract\Content\PageInfo $page
     * @return string
     */
    protected static function extractTextFromPage(Extract\Document $doc, Extract\Content\PageInfo $page): string
    {
        $interpreter = new Extract\Content\Interpreter();
        $runs        = $interpreter->run($doc, $page->content, $page->resources);

        $text = '';

        foreach ($runs as $run) {
            $text .= match ($run->separator) {
                Extract\Content\TextRun::SEPARATOR_SPACE   => ' ',
                Extract\Content\TextRun::SEPARATOR_TAB     => "\t",
                Extract\Content\TextRun::SEPARATOR_NEWLINE => "\n",
                default                                    => '',
            };

            $decoded = Extract\Font\Resolver::decodeRun($run, $doc);

            if ($run->reversed) {
                // /ReversedChars marked content (Interpreter::isReversedActive())
                // stores its character stream in reversed logical order - the
                // decoder faithfully decodes byte-for-byte, so the CONSUMER
                // (here) is responsible for restoring logical reading order.
                // mb_str_split(), not strrev(), since the decoded text is
                // UTF-8 and a byte-level reverse would corrupt multi-byte
                // sequences.
                $decoded = implode('', array_reverse(mb_str_split($decoded)));
            }

            $text .= $decoded;
        }

        return $text;
    }

    /**
     * Determine if every page of a PDF file is nothing but a single scanned/drawn image
     *
     * @param  string  $file
     * @param  mixed   $pages
     * @param  ?int    $pageLimit
     * @param  ?string $password
     * @return bool
     */
    public static function isImageOnlyDocument(
        string $file, mixed $pages = null, ?int $pageLimit = null, ?string $password = null
    ): bool
    {
        return self::allPagesImageOnly(self::getImageOnlyPages($file, $pages, $pageLimit, $password));
    }

    /**
     * Determine if every page of raw PDF data is nothing but a single scanned/drawn image
     *
     * @param  string  $data
     * @param  mixed   $pages
     * @param  ?int    $pageLimit
     * @param  ?string $password
     * @return bool
     */
    public static function isImageOnlyData(
        string $data, mixed $pages = null, ?int $pageLimit = null, ?string $password = null
    ): bool
    {
        return self::allPagesImageOnly(self::getImageOnlyPagesFromData($data, $pages, $pageLimit, $password));
    }

    /**
     * Get a per-page image-only classification for a PDF file
     *
     * @param  string  $file
     * @param  mixed   $pages
     * @param  ?int    $pageLimit
     * @param  ?string $password
     * @return array
     */
    public static function getImageOnlyPages(
        string $file, mixed $pages = null, ?int $pageLimit = null, ?string $password = null
    ): array
    {
        $doc = Extract\Document::fromFile($file, $password);
        return self::classifyPages($doc, $pages, $pageLimit);
    }

    /**
     * Get a per-page image-only classification for raw PDF data
     *
     * @param  string  $data
     * @param  mixed   $pages
     * @param  ?int    $pageLimit
     * @param  ?string $password
     * @return array
     */
    public static function getImageOnlyPagesFromData(
        string $data, mixed $pages = null, ?int $pageLimit = null, ?string $password = null
    ): array
    {
        $doc = new Extract\Document($data, $password);
        return self::classifyPages($doc, $pages, $pageLimit);
    }

    /**
     * Classify every page of a parsed Extract\Document as image-only or not
     *
     * @param  Extract\Document $doc
     * @param  mixed            $pages
     * @param  ?int             $pageLimit
     * @return array
     */
    protected static function classifyPages(Extract\Document $doc, mixed $pages = null, ?int $pageLimit = null): array
    {
        $pages    = self::normalizePages($pages);
        $docPages = Extract\Content\PageWalker::walk($doc, $pages, $pageLimit);
        $docPages = self::selectByPageFilter($docPages, $pages, $pageLimit);

        $result = [];

        foreach ($docPages as $i => $docPage) {
            $result[$i] = Extract\Content\PageClassifier::isImageOnly($doc, $docPage);
        }

        return $result;
    }

    /**
     * Normalize a $pages argument (int, array, or null) into an array or null
     *
     * @param  mixed $pages
     * @return ?array
     */
    protected static function normalizePages(mixed $pages): ?array
    {
        return ($pages !== null) ? ((!is_array($pages)) ? [$pages] : $pages) : null;
    }

    /**
     * Select items by 1-indexed position, either an explicit page list or a page limit
     *
     * An explicit $pages list wins outright; $pageLimit only applies when
     * $pages is null. Shared by every extract-style method that filters a
     * per-page result set the same way (text extraction, image-only
     * classification, image extraction).
     *
     * @param  array  $items
     * @param  ?array $pages
     * @param  ?int   $pageLimit
     * @return array
     */
    protected static function selectByPageFilter(array $items, ?array $pages, ?int $pageLimit): array
    {
        if ($pages !== null) {
            $selected = [];
            foreach ($items as $i => $item) {
                if (in_array(($i + 1), $pages)) {
                    $selected[] = $item;
                }
            }
            return $selected;
        } elseif (is_int($pageLimit) && ($pageLimit > 0)) {
            return array_slice($items, 0, $pageLimit);
        }

        return $items;
    }

    /**
     * Determine if a set of per-page image-only results means the whole document is image-only
     *
     * @param  array $pages
     * @return bool
     */
    protected static function allPagesImageOnly(array $pages): bool
    {
        return (!empty($pages)) && !in_array(false, $pages, true);
    }

}
