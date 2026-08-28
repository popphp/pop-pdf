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
namespace Pop\Pdf\Build\Image;

use Pop\Pdf\Build\Exception;

/**
 * Pdf page-to-image extractor class
 *
 * Rasterizes pages of an existing PDF into standalone image files via
 * Imagick. The opposite direction of Parser (which turns a raster image
 * into a PDF page).
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Extractor
{

    /**
     * Supported output image formats
     * @var array
     */
    protected const array SUPPORTED_FORMATS = ['jpg', 'jpeg', 'png', 'webp', 'tiff', 'tif'];

    /**
     * Constructor
     *
     * Instantiate the extractor object
     *
     * @throws Exception
     */
    public function __construct()
    {
        if (!class_exists('Imagick', false)) {
            throw new Exception('Error: The Imagick extension is required to extract PDF pages as images.');
        }
    }

    /**
     * Get the total number of pages in the PDF file
     *
     * @param  string $file
     * @throws Exception
     * @return int
     */
    public function countPages(string $file): int
    {
        if (!file_exists($file)) {
            throw new Exception("Error: The PDF file '{$file}' does not exist.");
        }

        $imagick = new \Imagick();
        $imagick->pingImage($file);
        $totalPages = $imagick->getNumberImages();
        $imagick->clear();

        return $totalPages;
    }

    /**
     * Extract the given page numbers of the PDF file as individual image files
     *
     * @param  string $file
     * @param  string $location
     * @param  string $format
     * @param  int    $resolution
     * @param  array  $pageNumbers 1-indexed page numbers to extract
     * @throws Exception
     * @return array
     */
    public function extract(string $file, string $location, string $format, int $resolution, array $pageNumbers): array
    {
        if (!file_exists($file)) {
            throw new Exception("Error: The PDF file '{$file}' does not exist.");
        }
        if (!is_dir($location) || !is_writable($location)) {
            throw new Exception("Error: The location '{$location}' is not a writable directory.");
        }

        $format = strtolower($format);
        if (!in_array($format, self::SUPPORTED_FORMATS)) {
            throw new Exception("Error: The format '{$format}' is not supported.");
        }

        $basename = pathinfo($file, PATHINFO_FILENAME);
        $location = rtrim($location, '/\\');
        $images   = [];

        foreach ($pageNumbers as $pageNum) {
            // setResolution() must be called before readImage() - Imagick
            // only honors DPI at rasterization time, not after the fact.
            $page = new \Imagick();
            $page->setResolution($resolution, $resolution);
            $page->readImage($file . '[' . ($pageNum - 1) . ']');
            $page->setImageFormat($format);

            $path = $location . DIRECTORY_SEPARATOR . $basename . '-' . sprintf('%02d', $pageNum) . '.' . $format;
            $page->writeImage($path);
            $page->clear();

            $images[$pageNum] = $path;
        }

        return $images;
    }

}
