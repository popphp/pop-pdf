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
class Alignment extends AbstractAlignment
{

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

}