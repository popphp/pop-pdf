<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Image
{

    /**
     * Image file name
     * @var string
     */
    protected $image = null;

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
     * Constructor
     *
     * Instantiate a PDF image object.
     *
     * @param  string $image
     * @return Image
     */
    public function __construct($image)
    {
        $this->setImage($image);
    }

    /**
     * Set the image file name
     *
     * @param  string $image
     * @throws Exception
     * @return Image
     */
    public function setImage($image)
    {
        if (!file_exists($image)) {
            throw new Exception('Error: That image file does not exist.');
        }

        $parts     = pathinfo($image);
        $extension = (isset($parts['extension']) && ($parts['extension'] != '')) ? strtolower($parts['extension']) : null;

        if ((substr($extension, 0, 2) != 'jp') && ($extension != 'gif') && ($extension != 'png')) {
            throw new Exception('Error: That image type is not supported. Only GIF, JPG and PNG image types are supported.');
        }

        // Set image properties.
        $this->image  = $image;
        $imgSize      = getimagesize($this->image);
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
     * Get the image file name
     *
     * @return string
     */
    public function getImage()
    {
        return $this->image;
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