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
namespace Pop\Pdf\Document\Page\Text;

use Pop\Pdf\Document\Exception;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Font;

/**
 * Pdf page text alignment class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.1
 */
class Alignment extends AbstractAlignment
{

    /**
     * Alignment constants
     */
    const CENTER = 'CENTER';

    /**
     * Create LEFT alignment object
     *
     * @param int    $leftX
     * @param int    $rightX
     * @param int    $leading
     * @return Alignment
     */
    public static function createLeft(int $leftX = 0, int $rightX = 0, int $leading = 0): Alignment
    {
        return new self(self::LEFT, $leftX, $rightX, $leading);
    }

    /**
     * Create RIGHT alignment object
     *
     * @param int    $leftX
     * @param int    $rightX
     * @param int    $leading
     * @return Alignment
     */
    public static function createRight(int $leftX = 0, int $rightX = 0, int $leading = 0): Alignment
    {
        return new self(self::RIGHT, $leftX, $rightX, $leading);
    }

    /**
     * Create CENTER alignment object
     *
     * @param int    $leftX
     * @param int    $rightX
     * @param int    $leading
     * @return Alignment
     */
    public static function createCenter(int $leftX = 0, int $rightX = 0, int $leading = 0): Alignment
    {
        return new self(self::CENTER, $leftX, $rightX, $leading);
    }

    /**
     * Get strings
     *
     * @param  Page\Text $text
     * @param  Font $font
     * @param  int $startY
     * @throws Exception
     * @return array
     */
    public function getStrings(Page\Text $text, Font $font, int $startY): array
    {
        $strings    = [];
        $curString  = '';
        $words      = explode(' ', $text->getString());
        $wrapLength = abs($this->rightX - $this->leftX);
        $startX     = $this->leftX;

        if ((int)$this->leading == 0) {
            $this->leading = $text->getSize();
        }

        foreach ($words as $word) {
            $newString = ($curString != '') ? $curString . ' ' . $word : $word;
            if ($font->getStringWidth($newString, $text->getSize()) <= $wrapLength) {
                $curString = $newString;
            } else {
                if ($this->isRight()) {
                    $x = $this->leftX + ($wrapLength - $font->getStringWidth($curString, $text->getSize()));
                } else if ($this->isCenter()) {
                    $x = $this->leftX + (($wrapLength - $font->getStringWidth($curString, $text->getSize())) / 2);
                } else {
                    $x = $startX;
                }

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
                $x = $this->leftX + ($wrapLength - $font->getStringWidth($curString, $text->getSize()));
            } else if ($this->isCenter()) {
                $x = $this->leftX + (($wrapLength - $font->getStringWidth($curString, $text->getSize())) / 2);
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

    /**
     * Is CENTER alignment
     *
     * @return bool
     */
    public function isCenter(): bool
    {
        return ($this->alignment == self::CENTER);
    }

}
