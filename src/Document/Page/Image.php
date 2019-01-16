<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document\Page;

/**
 * Pdf page image class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Image
{

    /**
     * Image file name
     * @var string
     */
    protected $image = null;

    /**
     * Image stream
     * @var string
     */
    protected $stream = null;

    /**
     * Image width
     * @var int
     */
    protected $width = null;

    /**
     * Image height
     * @var int
     */
    protected $height = null;

    /**
     * Image resize value
     * @var array
     */
    protected $resize = null;

    /**
     * Flag to preserve image resolution
     * @var boolean
     */
    protected $preserveResolution = false;

    /**
     * Create PDF image object from file
     *
     * @param  string $file
     * @throws Exception
     * @return Image
     */
    public static function createImageFromFile($file)
    {
        $image = new self();
        $image->loadImageFromFile($file);
        return $image;
    }

    /**
     * Create PDF image object from data stream
     *
     * @param  string $stream
     * @throws Exception
     * @return Image
     */
    public static function createImageFromStream($stream)
    {
        $image = new self();
        $image->loadImageFromStream($stream);
        return $image;
    }

    /**
     * Load image from file
     *
     * @param  string $file
     * @throws Exception
     * @return Image
     */
    public function loadImageFromFile($file)
    {
        if (!file_exists($file)) {
            throw new Exception('Error: That image file does not exist.');
        }

        $imgSize = getimagesize($file);

        if (!isset($imgSize['mime']) ||
            (isset($imgSize['mime']) && ($imgSize['mime'] != 'image/jpeg') && ($imgSize['mime'] != 'image/gif') && ($imgSize['mime'] != 'image/png'))) {
            throw new Exception('Error: That image type is not supported. Only GIF, JPG and PNG image types are supported.');
        }

        // Set image properties.
        $this->image  = $file;
        $this->width  = $imgSize[0];
        $this->height = $imgSize[1];

        return $this;
    }

    /**
     * Load image from stream
     *
     * @param  string $stream
     * @throws Exception
     * @return Image
     */
    public function loadImageFromStream($stream)
    {
        $imgSize = getimagesizefromstring($stream);

        if (!isset($imgSize['mime']) ||
            (isset($imgSize['mime']) && ($imgSize['mime'] != 'image/jpeg') && ($imgSize['mime'] != 'image/gif') && ($imgSize['mime'] != 'image/png'))) {
            throw new Exception('Error: That image type is not supported. Only GIF, JPG and PNG image types are supported.');
        }

        $this->stream = $stream;
        $this->width  = $imgSize[0];
        $this->height = $imgSize[1];

        return $this;
    }

    /**
     * Resize image to width
     *
     * @param  int     $width
     * @param  boolean $preserveResolution
     * @return Image
     */
    public function resizeToWidth($width, $preserveResolution = false)
    {
        $this->resize = [
            'width'  => $width,
            'height' => round($this->height * ($width / $this->width))
        ];

        $this->preserveResolution = (bool)$preserveResolution;
        return $this;
    }

    /**
     * Resize image to height
     *
     * @param  int     $height
     * @param  boolean $preserveResolution
     * @return Image
     */
    public function resizeToHeight($height, $preserveResolution = false)
    {
        $this->resize = [
            'width'  => round($this->width * ($height / $this->height)),
            'height' => $height
        ];

        $this->preserveResolution = (bool)$preserveResolution;
        return $this;
    }

    /**
     * Resize image on whichever dimension is the greatest
     *
     * @param  int     $pixel
     * @param  boolean $preserveResolution
     * @return Image
     */
    public function resize($pixel, $preserveResolution = false)
    {
        $scale        = ($this->width > $this->height) ? ($pixel / $this->width) : ($pixel / $this->height);
        $this->resize = [
            'width'  => round($this->width * $scale),
            'height' => round($this->height * $scale)
        ];

        $this->preserveResolution = (bool)$preserveResolution;
        return $this;
    }

    /**
     * Scale image
     *
     * @param  float   $scale
     * @param  boolean $preserveResolution
     * @return Image
     */
    public function scale($scale, $preserveResolution = false)
    {
        $this->resize = [
            'width'  => round($this->width * $scale),
            'height' => round($this->height * $scale)
        ];
        $this->preserveResolution = (bool)$preserveResolution;
        return $this;
    }

    /**
     * Is image file
     *
     * @return boolean
     */
    public function isFile()
    {
        return ((null !== $this->image) && (null === $this->stream));
    }

    /**
     * Is image stream
     *
     * @return boolean
     */
    public function isStream()
    {
        return ((null !== $this->stream) && (null === $this->image));
    }

    /**
     * Get the image file
     *
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Get the image stream
     *
     * @return string
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Get the image width
     *
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Get the image height
     *
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Get the image resized width
     *
     * @return int
     */
    public function getResizedWidth()
    {
        return ((null !== $this->resize) && isset($this->resize['width'])) ? $this->resize['width'] : null;
    }

    /**
     * Get the image resized height
     *
     * @return int
     */
    public function getResizedHeight()
    {
        return ((null !== $this->resize) && isset($this->resize['height'])) ? $this->resize['height'] : null;
    }

    /**
     * Get the image resize dimensions
     *
     * @return array
     */
    public function getResizeDimensions()
    {
        return $this->resize;
    }

    /**
     * Get the image preserve resolution flag
     *
     * @return boolean
     */
    public function isPreserveResolution()
    {
        return $this->preserveResolution;
    }

}