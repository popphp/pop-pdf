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
namespace Pop\Pdf\Document\Page;

/**
 * Pdf page image class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.0
 */
class Image
{

    /**
     * Image file name
     * @var ?string
     */
    protected ?string $image = null;

    /**
     * Image stream
     * @var string
     */
    protected ?string $stream = null;

    /**
     * Image width
     * @var ?int
     */
    protected ?int $width = null;

    /**
     * Image height
     * @var ?int
     */
    protected ?int $height = null;

    /**
     * Image resize value
     * @var ?array
     */
    protected ?array $resize = null;

    /**
     * Flag to preserve image resolution
     * @var bool
     */
    protected bool $preserveResolution = false;

    /**
     * Create PDF image object from file
     *
     * @param  string $file
     * @throws Exception
     * @return Image
     */
    public static function createImageFromFile(string $file): Image
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
    public static function createImageFromStream(string $stream): Image
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
    public function loadImageFromFile(string $file): Image
    {
        if (!file_exists($file)) {
            throw new Exception('Error: That image file does not exist.');
        }

        $imgSize = getimagesize($file);

        if (!isset($imgSize['mime']) ||
            (isset($imgSize['mime']) && ($imgSize['mime'] != 'image/jpeg') &&
                ($imgSize['mime'] != 'image/gif') && ($imgSize['mime'] != 'image/png'))) {
            throw new Exception(
                'Error: That image type is not supported. Only GIF, JPG and PNG image types are supported.'
            );
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
    public function loadImageFromStream(string $stream): Image
    {
        $imgSize = getimagesizefromstring($stream);

        if (!isset($imgSize['mime']) ||
            (isset($imgSize['mime']) && ($imgSize['mime'] != 'image/jpeg') &&
                ($imgSize['mime'] != 'image/gif') && ($imgSize['mime'] != 'image/png'))) {
            throw new Exception(
                'Error: That image type is not supported. Only GIF, JPG and PNG image types are supported.'
            );
        }

        $this->stream = $stream;
        $this->width  = $imgSize[0];
        $this->height = $imgSize[1];

        return $this;
    }

    /**
     * Resize image to width
     *
     * @param  int  $width
     * @param  bool $preserveResolution
     * @return Image
     */
    public function resizeToWidth(int $width, bool $preserveResolution = false): Image
    {
        $this->resize = [
            'width'  => $width,
            'height' => round($this->height * ($width / $this->width))
        ];

        $this->preserveResolution = $preserveResolution;
        return $this;
    }

    /**
     * Resize image to height
     *
     * @param  int  $height
     * @param  bool $preserveResolution
     * @return Image
     */
    public function resizeToHeight(int $height, bool $preserveResolution = false): Image
    {
        $this->resize = [
            'width'  => round($this->width * ($height / $this->height)),
            'height' => $height
        ];

        $this->preserveResolution = $preserveResolution;
        return $this;
    }

    /**
     * Resize image on whichever dimension is the greatest
     *
     * @param  int  $pixel
     * @param  bool $preserveResolution
     * @return Image
     */
    public function resize(int $pixel, bool $preserveResolution = false): Image
    {
        $scale        = ($this->width > $this->height) ? ($pixel / $this->width) : ($pixel / $this->height);
        $this->resize = [
            'width'  => round($this->width * $scale),
            'height' => round($this->height * $scale)
        ];

        $this->preserveResolution = $preserveResolution;
        return $this;
    }

    /**
     * Scale image
     *
     * @param  float $scale
     * @param  bool  $preserveResolution
     * @return Image
     */
    public function scale(float $scale, bool $preserveResolution = false): Image
    {
        $this->resize = [
            'width'  => round($this->width * $scale),
            'height' => round($this->height * $scale)
        ];
        $this->preserveResolution = $preserveResolution;
        return $this;
    }

    /**
     * Is image file
     *
     * @return bool
     */
    public function isFile(): bool
    {
        return (($this->image !== null) && ($this->stream === null));
    }

    /**
     * Is image stream
     *
     * @return bool
     */
    public function isStream(): bool
    {
        return (($this->stream !== null) && ($this->image === null));
    }

    /**
     * Get the image file
     *
     * @return ?string
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * Get the image stream
     *
     * @return ?string
     */
    public function getStream(): ?string
    {
        return $this->stream;
    }

    /**
     * Get the image width
     *
     * @return ?int
     */
    public function getWidth(): ?int
    {
        return $this->width;
    }

    /**
     * Get the image height
     *
     * @return ?int
     */
    public function getHeight(): ?int
    {
        return $this->height;
    }

    /**
     * Get the image resized width
     *
     * @return ?int
     */
    public function getResizedWidth(): ?int
    {
        return (($this->resize !== null) && isset($this->resize['width'])) ? $this->resize['width'] : null;
    }

    /**
     * Get the image resized height
     *
     * @return ?int
     */
    public function getResizedHeight(): ?int
    {
        return (($this->resize !== null) && isset($this->resize['height'])) ? $this->resize['height'] : null;
    }

    /**
     * Get the image resize dimensions
     *
     * @return ?array
     */
    public function getResizeDimensions(): ?array
    {
        return $this->resize;
    }

    /**
     * Get the image preserve resolution flag
     *
     * @return bool
     */
    public function isPreserveResolution(): bool
    {
        return $this->preserveResolution;
    }

}
