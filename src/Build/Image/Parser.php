<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Image;

use Pop\Pdf\Build\Object\StreamObject;

/**
 * Pdf image parser class
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Parser
{

    /**
     * Image width
     * @var int
     */
    protected $width           = 0;

    /**
     * Image height
     * @var int
     */
    protected $height          = 0;

    /**
     * Image mime
     * @var string
     */
    protected $mime            = null;

    /**
     * Image color mode
     * @var mixed
     */
    protected $colorMode       = null;

    /**
     * Number of channels in the image
     * @var int
     */
    protected $channels        = 0;

    /**
     * Image bit-depth
     * @var int
     */
    protected $depth           = 0;

    /**
     * Image basename
     * @var string
     */
    protected $basename        = null;

    /**
     * Image filename
     * @var string
     */
    protected $filename        = null;

    /**
     * Image extension
     * @var string
     */
    protected $extension       = null;

    /**
     * Image fullpath
     * @var string
     */
    protected $fullpath        = null;

    /**
     * Image total number of colors
     * @var int
     */
    protected $colorTotal      = 0;

    /**
     * Flag for if the image has an alpha channel
     * @var boolean
     */
    protected $alpha           = false;

    /**
     * Image data
     * @var mixed
     */
    protected $imageData       = null;

    /**
     * Image data length
     * @var int
     */
    protected $imageDataLength = null;

    /**
     * GD image resource
     * @var resource
     */
    protected $resource = null;

    /**
     * Image X Coordinate
     * @var array
     */
    protected $x = 0;

    /**
     * Image Y Coordinate
     * @var array
     */
    protected $y = 0;

    /**
     * Image object index
     * @var int
     */
    protected $index = null;

    /**
     * Image objects
     * @var array
     */
    protected $objects = [];

    /**
     * Converted GIF to PNG image
     * @var string
     */
    protected $convertedImage = null;

    /**
     * Resized image
     * @var string
     */
    protected $resizedImage = null;

    /**
     * Constructor
     *
     * Instantiate a image parser object
     *
     * @param  string  $imageFile
     * @param  int     $x
     * @param  int     $y
     * @param  array   $resize
     * @param  boolean $preserveResolution
     * @throws Exception
     * @return Parser
     */
    public function __construct($imageFile, $x, $y, array $resize = null, $preserveResolution = false)
    {
        $this->setImage($imageFile, $resize, $preserveResolution);
        $this->setX($x);
        $this->setY($y);
    }

    /**
     * Set the image file
     *
     * @param  string $imageFile
     * @param  array   $resize
     * @param  boolean $preserveResolution
     * @return Parser
     */
    public function setImage($imageFile, array $resize = null, $preserveResolution = false)
    {
        $parts           = pathinfo($imageFile);
        $this->fullpath  = realpath($imageFile);
        $this->basename  = $parts['basename'];
        $this->filename  = $parts['filename'];
        $this->extension = (isset($parts['extension']) && ($parts['extension'] != '')) ? $parts['extension'] : null;

        // Convert GIF image to PNG
        if (strtolower($this->extension) == 'gif') {
            $this->convertImage();
        } else {
            $this->mime = (strtolower($this->extension) == 'png') ? 'image/png' : 'image/jpeg';
        }

        // If resize dimensions are passed
        if ((null !== $resize) && !($preserveResolution)) {
            $this->resizeImage($resize);
        }

        $imgSize = getimagesize($this->fullpath);

        // Set image properties.
        if ((null !== $resize) && ($preserveResolution)) {
            $this->width  = $resize['width'];
            $this->height = $resize['height'];
        } else {
            $this->width  = $imgSize[0];
            $this->height = $imgSize[1];
        }

        $this->channels = (isset($imgSize['channels'])) ? $imgSize['channels'] : null;
        $this->depth    = (isset($imgSize['bits'])) ? $imgSize['bits'] : null;

        $this->imageData       = file_get_contents($this->fullpath);
        $this->imageDataLength = strlen($this->imageData);

        if (substr(strtolower($this->extension), 0, 2) == 'jp') {
            switch ($this->channels) {
                case 1:
                    $this->colorMode = 'Gray';
                    break;
                case 3:
                    $this->colorMode = 'RGB';
                    break;
                case 4:
                    $this->colorMode = 'CMYK';
                    break;
            }
        } else if (strtolower($this->extension) == 'png') {
            $colorType  = ord($this->imageData[25]);
            switch ($colorType) {
                case 0:
                    $this->channels  = 1;
                    $this->colorMode = 'Gray';
                    break;
                case 2:
                    $this->channels  = 3;
                    $this->colorMode = 'RGB';
                    break;
                case 3:
                    $this->channels  = 3;
                    $this->colorMode = 'Indexed';
                    break;
                case 4:
                    $this->channels  = 1;
                    $this->colorMode = 'Gray';
                    $this->alpha     = true;
                    break;
                case 6:
                    $this->channels  = 3;
                    $this->colorMode = 'RGB';
                    $this->alpha     = true;
                    break;
            }
        }

        $this->createResource();

        // Image clean up if the image was converted or resized.
        if ((null !== $this->convertedImage) && file_exists($this->convertedImage)) {
            unlink($this->convertedImage);
        }
        if ((null !== $this->resizedImage) && file_exists($this->resizedImage)) {
            unlink($this->resizedImage);
        }

        return $this;
    }

    /**
     * Set the X coordinate
     *
     * @param  int $x
     * @return Parser
     */
    public function setX($x)
    {
        $this->x = (int)$x;
        return $this;
    }

    /**
     * Set the Y coordinate
     *
     * @param  int $y
     * @return Parser
     */
    public function setY($y)
    {
        $this->y = (int)$y;
        return $this;
    }

    /**
     * Set the image object index
     *
     * @param  int $i
     * @return Parser
     */
    public function setIndex($i)
    {
        $this->index = (int)$i;
        return $this;
    }

    /**
     * Get the X coordinate
     *
     * @return int
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     * Get the Y coordinate
     *
     * @return int
     */
    public function getY()
    {
        return $this->y;
    }

    /**
     * Get the image object index
     *
     * @return int
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * Get the image stream
     *
     * @return string
     */
    public function getStream()
    {
        return "\n\nq\n\n{$this->width} 0 0 {$this->height} {$this->x} {$this->y} cm\n/I{$this->index} Do\n\nQ\n\n";
    }

    /**
     * Get the XObject
     *
     * @return string
     */
    public function getXObject()
    {
        return "/I{$this->index} {$this->index} 0 R";
    }

    /**
     * Get the image objects
     *
     * @return array
     */
    public function getObjects()
    {
        if (count($this->objects) == 0) {
            $this->parse();
        }
        return $this->objects;
    }

    /**
     * Parse the image data and create the image objects
     *
     * @throws Exception
     * @return void
     */
    public function parse()
    {
        if (null === $this->index) {
            throw new Exception('Error: The image index has not been set.');
        }
        if ($this->mime == 'image/png') {
            $this->parsePng();
        } else {
            $this->parseJpeg();
        }
    }

    /**
     * Parse the PNG image data
     *
     * @throws Exception
     * @return void
     */
    protected function parsePng()
    {
        // Define some PNG image-specific variables.
        $PLTE      = null;
        $TRNS      = null;
        $maskIndex = null;
        $mask      = null;

        // Determine the PNG colorspace.
        if ($this->colorMode == 'Gray') {
            $numOfColors = 1;
            $colorSpace  = '/DeviceGray';
        } else if (stripos($this->colorMode, 'RGB') !== false) {
            $numOfColors = 3;
            $colorSpace  = '/DeviceRGB';
        } else {
            $numOfColors = 1;
            $palIndex    = $this->index + 1;

            // If the PNG is indexed, parse and read the palette and any transparencies that might exist.
            if (strpos($this->imageData, 'PLTE') !== false) {
                $lenByte   = substr($this->imageData, (strpos($this->imageData, "PLTE") - 4), 4);
                $palLength = $this->readInt($lenByte);
                $PLTE      = substr($this->imageData, (strpos($this->imageData, "PLTE") + 4), $palLength);
                $mask      = null;

                // If a transparency exists, parse it and set the mask accordingly, along with the palette.
                if (strpos($this->imageData, 'tRNS') !== false) {
                    $lenByte   = substr($this->imageData, (strpos($this->imageData, "tRNS") - 4), 4);
                    $TRNS      = substr($this->imageData, (strpos($this->imageData, "tRNS") + 4), $this->readInt($lenByte));
                    $maskIndex = strpos($TRNS, chr(0));
                    $mask      = "    /Mask [" . $maskIndex . " " . $maskIndex . "]\n";
                }
            }

            $this->colorTotal = imagecolorstotal($this->resource);
            $colorSpace       = "[/Indexed /DeviceRGB " . ($this->colorTotal - 1) . " " . $palIndex . " 0 R]";
        }

        // Parse header data, bits and color type
        $lenByte   = substr($this->imageData, (strpos($this->imageData, "IHDR") - 4), 4);
        $header    = substr($this->imageData, (strpos($this->imageData, "IHDR") + 4), $this->readInt($lenByte));
        $bits      = ord(substr($header, 8, 1));
        $colorType = ord(substr($header, 9, 1));

        // Make sure the PNG does not contain a true alpha channel.
        if (($colorType >= 4) && (($bits == 8) || ($bits == 16))) {
            throw new Exception('Error: PNG alpha channels are not supported. Only 8-bit transparent PNG images are supported.');
        }

        // Parse and set the PNG image data and data length.
        $lenByte = substr($this->imageData, (strpos($this->imageData, "IDAT") - 4), 4);
        $this->imageDataLength = $this->readInt($lenByte);
        $IDAT = substr($this->imageData, (strpos($this->imageData, "IDAT") + 4), $this->imageDataLength);

        // Add the image to the objects array.
        $this->objects[$this->index] = StreamObject::parse(
            "{$this->index} 0 obj\n<<\n    /Type /XObject\n    /Subtype /Image\n    /Width " . $this->width .
            "\n    /Height " . $this->height . "\n    /ColorSpace {$colorSpace}\n    /BitsPerComponent " . $bits .
            "\n    /Filter /FlateDecode\n    /DecodeParms <</Predictor 15 /Colors {$numOfColors} /BitsPerComponent " .
            $bits . " /Columns " . $this->width . ">>\n{$mask}    /Length {$this->imageDataLength}\n>>\nstream\n{$IDAT}\nendstream\nendobj\n"
        );

        // If it exists, add the image palette to the objects array.
        if ($PLTE != '') {
            $this->objects[$palIndex] = StreamObject::parse(
                "{$palIndex} 0 obj\n<<\n    /Length " . $palLength . "\n>>\nstream\n{$PLTE}\nendstream\nendobj\n"
            );
            $this->objects[$palIndex]->setPalette(true);
        }
    }

    /**
     * Parse the JPG image data
     *
     * @throws Exception
     * @return void
     */
    protected function parseJpeg()
    {
        // Add the image to the objects array.
        $colorMode  = (strtolower($this->colorMode) == 'srgb') ? 'RGB' : $this->colorMode;
        $colorSpace = ($this->colorMode == 'CMYK') ? "/DeviceCMYK\n    /Decode [1 0 1 0 1 0 1 0]" : "/Device" . $colorMode;
        $this->objects[$this->index] = StreamObject::parse(
            "{$this->index} 0 obj\n<<\n    /Type /XObject\n    /Subtype /Image\n    /Width " . $this->width .
            "\n    /Height " . $this->height . "\n    /ColorSpace {$colorSpace}\n    /BitsPerComponent 8\n    " .
            "/Filter /DCTDecode\n    /Length {$this->imageDataLength}\n>>\nstream\n{$this->imageData}\nendstream\nendobj\n"
        );
    }

    /**
     * Create a new image resource based on the current image type
     * of the image object.
     *
     * @return void
     */
    protected function createResource()
    {
        if (file_exists($this->fullpath)) {
            switch ($this->mime) {
                case 'image/gif':
                    $this->resource = imagecreatefromgif($this->fullpath);
                    break;
                case 'image/png':
                    $this->resource = imagecreatefrompng($this->fullpath);
                    break;
                case 'image/jpeg':
                    $this->resource = imagecreatefromjpeg($this->fullpath);
                    break;
            }
        }
    }

    /**
     * Method to convert the image from GIF to PNG.
     *
     * @return void
     */
    protected function convertImage()
    {
        // Define the temp converted image.
        $this->convertedImage = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . $this->filename . '_' . time() . '.png';

        // Convert the GIF to PNG, save and clear the output buffer.
        $resource = imagecreatefromgif($this->fullpath);
        imageinterlace($resource, 0);
        imagepng($resource, $this->convertedImage);

        // Change the type of the image object to the new,
        // requested image type.
        $this->extension = 'png';
        $this->mime      = 'image/png';

        // Redefine the image object properties with the new values.
        $this->fullpath = $this->convertedImage;
        $this->basename = basename($this->convertedImage);
        $this->filename = basename($this->convertedImage, '.png');
    }

    /**
     * Method to resize the image.
     *
     * @param  array $resize
     * @throws Exception
     * @return void
     */
    protected function resizeImage($resize)
    {
        // Define the temp resized image.
        $this->resizedImage = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . $this->filename . '_' . time() . '.' . $this->extension;

        // Get image properties.
        $imgSize  = getimagesize($this->fullpath);
        $width    = $imgSize[0];
        $height   = $imgSize[1];
        $output   = imagecreatetruecolor($resize['width'], $resize['height']);
        $resource = ($this->mime == 'image/png') ?
            imagecreatefrompng($this->fullpath) : imagecreatefromjpeg($this->fullpath);

        imagecopyresampled($output, $resource, 0, 0, 0, 0, $resize['width'], $resize['height'], $width, $height);

        if ($this->mime == 'image/png') {
            imagepng($output, $this->resizedImage, 1);
        } else {
            imagejpeg($output, $this->resizedImage, 90);
        }

        // Redefine the image object properties with the new values.
        $this->fullpath = $this->resizedImage;
        $this->basename = basename($this->resizedImage);
        $this->filename = basename($this->resizedImage, '.png');
    }

    /**
     * Method to read an unsigned integer.
     *
     * @param  string $data
     * @return int
     */
    protected function readInt($data)
    {
        $ary = unpack('Nlength', $data);
        return $ary['length'];
    }

}