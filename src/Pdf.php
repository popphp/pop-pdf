<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
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
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
 */
class Pdf
{

    /**
     * Write document to file
     *
     * @param  AbstractDocument $document
     * @param  string           $filename
     * @return void
     */
    public static function writeToFile(AbstractDocument $document, string $filename = 'pop.pdf'): void
    {
        $compiler = new Build\Compiler();
        $compiler->finalize($document);
        file_put_contents($filename, $compiler->getOutput());
    }

    /**
     * Output to HTTP response
     *
     * @param  AbstractDocument $document
     * @param  string           $filename
     * @param  bool             $forceDownload
     * @param  array            $headers
     * @return void
     */
    public static function outputToHttp(
        AbstractDocument $document, string $filename = 'pop.pdf', bool $forceDownload = false, array $headers = []
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
     * Import from an existing PDF file
     *
     * @param  string $file
     * @param  mixed  $pages
     * @return AbstractDocument
     */
    public static function importFromFile(string $file, mixed $pages = null): AbstractDocument
    {
        $parser = new Build\Parser();
        return $parser->parseFile($file, $pages);
    }

    /**
     * Import from raw data stream
     *
     * @param  string $data
     * @param  mixed  $pages
     * @return AbstractDocument
     */
    public static function importRawData(string $data, mixed $pages = null): AbstractDocument
    {
        $parser = new Build\Parser();
        return $parser->parseData($data, $pages);
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
     * Extract text from file
     *
     * @param  string $file
     * @param  mixed  $pages
     * @param  ?int   $pageLimit
     * @return string
     */
    public static function extractTextFromFile(string $file, mixed $pages = null, ?int $pageLimit = null): string
    {
        $parser   = new \Smalot\PdfParser\Parser();
        $document = $parser->parseFile($file);

        if ($pages !== null) {
            $text     = '';
            $pages    = (!is_array($pages)) ? [$pages] : $pages;
            $docPages = $document->getPages();

            foreach ($docPages as $i => $docPage) {
                if (in_array(($i + 1), $pages)) {
                    $text .= $docPage->getText();
                }
            }
        } else {
            $text = $document->getText($pageLimit);
        }

        return $text;
    }

    /**
     * Extract text from raw data stream
     *
     * @param  string $data
     * @param  mixed  $pages
     * @param  ?int   $pageLimit
     * @return string
     */
    public static function extractTextFromData(string $data, mixed $pages = null, ?int $pageLimit = null): string
    {
        $parser   = new \Smalot\PdfParser\Parser();
        $document = $parser->parseContent($data);

        if ($pages !== null) {
            $text     = '';
            $pages    = (!is_array($pages)) ? [$pages] : $pages;
            $docPages = $document->getPages();

            foreach ($docPages as $i => $docPage) {
                if (in_array(($i + 1), $pages)) {
                    $text .= $docPage->getText();
                }
            }
        } else {
            $text = $document->getText($pageLimit);
        }

        return $text;
    }

}
