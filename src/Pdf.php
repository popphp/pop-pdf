<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf;

use Pop\Pdf\Build;

/**
 * Pop Pdf class
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
class Pdf extends AbstractPdf
{

    /**
     * Import from an existing PDF file
     *
     * @param  string $file
     * @param  mixed  $pages
     * @return AbstractDocument
     */
    public function importFromFile($file, $pages = null)
    {
        $parser = new Build\Parser();
        return $parser->parse($file, $pages);
    }

    /**
     * Write document to file
     *
     * @param  AbstractDocument $document
     * @param  string           $filename
     * @return void
     */
    public function writeToFile(AbstractDocument $document, $filename)
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
     * @return string
     */
    public function outputToHttp(AbstractDocument $document, $filename = 'pop.pdf', $forceDownload = false)
    {
        $headers = [
            'Content-type'        => 'application/pdf',
            'Content-disposition' => (($forceDownload) ? 'attachment; ' : null) . 'filename=' . $filename
        ];

        if (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 443)) {
            $headers['Expires']       = 0;
            $headers['Cache-Control'] = 'private, must-revalidate';
            $headers['Pragma']        = 'cache';
        }

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