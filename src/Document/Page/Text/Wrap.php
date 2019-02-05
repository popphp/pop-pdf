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
namespace Pop\Pdf\Document\Page\Text;

use Pop\Pdf\Document\Page\Text as Txt;
use Pop\Pdf\Document\Font;

/**
 * Pdf page text alignment class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Wrap extends AbstractAlignment
{

   /**
     * Wrap box boundary
     * @var array
     */
    protected $box = [
        'left'   => 0,
        'right'  => 0,
        'top'    => 0,
        'bottom' => 0
    ];

    /**
     * Constructor
     *
     * Instantiate a PDF text wrap object.
     *
     * @param string $alignment
     * @param int    $leftX
     * @param int    $rightX
     * @param array  $box
     * @param int    $leading
     * @param int $leading
     */
    public function __construct($alignment = self::LEFT, $leftX = 0, $rightX = 0, $box = [], $leading = 0)
    {
        parent::__construct($alignment, $leftX, $rightX, $leading);
        if (!empty($box)) {
            $this->setBox($box);
        }
    }

    /**
     * Create LEFT alignment object
     *
     * @param int    $leftX
     * @param int    $rightX
     * @param array  $box
     * @param int    $leading
     * @return Wrap
     */
    public static function createLeft($leftX = 0, $rightX = 0, $box = [], $leading = 0)
    {
        return new self(self::LEFT, $leftX, $rightX, $box, $leading);
    }

    /**
     * Create RIGHT alignment object
     *
     * @param int    $leftX
     * @param int    $rightX
     * @param array  $box
     * @param int    $leading
     * @return Wrap
     */
    public static function createRight($leftX = 0, $rightX = 0, $box = [], $leading = 0)
    {
        return new self(self::RIGHT, $leftX, $rightX, $box, $leading);
    }

    /**
     * Set the wrap box boundary
     *
     * @param  array $box
     * @throws \InvalidArgumentException
     * @return Wrap
     */
    public function setBox(array $box)
    {
        if ((count($box) != 4) || !isset($box['left']) || !isset($box['right']) || !isset($box['top']) || !isset($box['bottom'])) {
            throw new \InvalidArgumentException(
                "Error: The box array must contain the four coordinates 'left', 'right', 'top' and 'bottom'."
            );
        }

        $this->box['left']   = $box['left'];
        $this->box['right']  = $box['right'];
        $this->box['top']    = $box['top'];
        $this->box['bottom'] = $box['bottom'];

        return $this;
    }

    /**
     * Set the wrap box boundary by coordinates
     *
     * @param  int $left
     * @param  int $right
     * @param  int $top
     * @param  int $bottom
     * @return Wrap
     */
    public function setBoxCoordinates($left, $right, $top, $bottom)
    {
        $this->box['left']   = $left;
        $this->box['right']  = $right;
        $this->box['top']    = $top;
        $this->box['bottom'] = $bottom;

        return $this;
    }

    /**
     * Get the wrap box boundary
     *
     * @return array
     */
    public function getBox()
    {
        return $this->box;
    }

    /**
     * Get strings
     *
     * @param  Txt  $text
     * @param  Font $font
     * @param  int  $startY
     * @return array
     */
    public function getStrings(Txt $text, Font $font, $startY)
    {
        $strings    = [];
        $curString  = '';
        $words      = explode(' ', $text->getString());
        $startX     = $this->leftX;

        if ((int)$this->leading == 0) {
            $this->leading = $text->getSize();
        }

        foreach ($words as $word) {
            if ($this->isRight()) {
                if (($startY <= $this->box['top']) && ($startY >= $this->box['bottom'])) {
                    $wrapLength = abs($this->rightX - $this->box['right']);
                    $x          = $this->box['right'];
                } else {
                    $wrapLength = abs($this->rightX - $this->leftX);
                    $x          = $startX;
                }
            } else {
                $x          = $startX;
                $wrapLength = (($startY <= $this->box['top']) && ($startY >= $this->box['bottom'])) ?
                    abs($this->box['left'] - $this->leftX) : abs($this->rightX - $this->leftX);
            }

            $newString = ($curString != '') ? $curString . ' ' . $word : $word;
            if ($font->getStringWidth($newString, $text->getSize()) <= $wrapLength) {
                $curString = $newString;
            } else {
                $strings[] = [
                    'string' => $curString,
                    'x'      => $x,
                    'y'      => $startY
                ];
                $curString = $word;
                $startY   -= $this->leading;
            }
        }

        if (!empty($curString)) {
            if ($this->isRight()) {
                $x = (($startY <= $this->box['top']) && ($startY >= $this->box['bottom'])) ?
                    $this->box['right'] : $startX;
            } else {
                $x = $startX;
            }

            $strings[] = [
                'string' => $curString,
                'x'      => $x,
                'y'      => $startY
            ];
        }

        return $strings;
    }

}