<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
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
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.0.0
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
     * @param  ?int   $pageLimit
     * @return string
     */
    public static function extractTextFromFile(string $file, ?int $pageLimit = null): string
    {
        return (new \Smalot\PdfParser\Parser())->parseFile($file)->getText($pageLimit);
    }

    /**
     * Extract text from raw data stream
     *
     * @param  string $data
     * @param  ?int   $pageLimit
     * @return string
     */
    public static function extractTextFromData(string $data, ?int $pageLimit = null): string
    {
        return (new \Smalot\PdfParser\Parser())->parseContent($data)->getText($pageLimit);
    }

}
