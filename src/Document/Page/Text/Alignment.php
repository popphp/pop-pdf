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
namespace Pop\Pdf\Document\Page\Text;

use Pop\Pdf\Document\Exception;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Font;

/**
 * Pdf page text alignment class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
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
        $curWidth   = 0;
        // Split the raw (unescaped) string, not getString() - the fragments
        // are handed back to fresh Text objects that escape them again on
        // their own, so starting from an already-escaped string would
        // double-escape backslashes/parens.
        $words      = explode(' ', $text->getRawString());
        $wrapLength = abs($this->rightX - $this->leftX);
        $startX     = $this->leftX;
        $size       = $text->getSize();
        $spaceWidth = $font->getStringWidth(' ', $size);

        if ((int)$this->leading == 0) {
            $this->leading = $size;
        }

        foreach ($words as $word) {
            $newString = ($curString != '') ? $curString . ' ' . $word : $word;
            $wordWidth = $font->getStringWidth($word, $size);
            $newWidth  = ($curString != '') ? ($curWidth + $spaceWidth + $wordWidth) : $wordWidth;

            if ($newWidth <= $wrapLength) {
                $curString = $newString;
                $curWidth  = $newWidth;
            } else {
                if ($this->isRight()) {
                    $x = $this->leftX + ($wrapLength - $curWidth);
                } else if ($this->isCenter()) {
                    $x = $this->leftX + (($wrapLength - $curWidth) / 2);
                } else {
                    $x = $startX;
                }

                $strings[] = [
                    'string' => $curString,
                    'x'      => $x,
                    'y'      => $startY
                ];
                $curString = $word;
                $curWidth  = $wordWidth;
                $startY   -= $this->leading;
            }
        }

        if (!empty($curString)) {
            if ($this->isRight()) {
                $x = $this->leftX + ($wrapLength - $curWidth);
            } else if ($this->isCenter()) {
                $x = $this->leftX + (($wrapLength - $curWidth) / 2);
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
