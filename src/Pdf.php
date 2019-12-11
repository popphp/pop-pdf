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
namespace Pop\Pdf;

use Pop\Pdf\Build;
use Pop\Pdf\Document\AbstractDocument;

/**
 * Pop Pdf class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Pdf
{

    /**
     * Import from an existing PDF file
     *
     * @param  string $file
     * @param  mixed  $pages
     * @return AbstractDocument
     */
    public static function importFromFile($file, $pages = null)
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
    public static function importRawData($data, $pages = null)
    {
        $parser = new Build\Parser();
        return $parser->parseData($data, $pages);
    }

    /**
     * Write document to file
     *
     * @param  AbstractDocument $document
     * @param  string           $filename
     * @return void
     */
    public static function writeToFile(AbstractDocument $document, $filename)
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
     * @param  boolean          $forceDownload
     * @param  array            $headers
     * @return void
     */
    public static function outputToHttp(AbstractDocument $document, $filename = 'pop.pdf', $forceDownload = false, array $headers = [])
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

}