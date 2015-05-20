<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document\Page\Field;

/**
 * Pdf page button field class
 *
 * @category   Pop
 * @package    Pop_Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
class Button extends AbstractField
{

    /**
     * Set no toggle to off
     *
     * @return Text
     */
    public function setNoToggleToOff()
    {
        if (!in_array(15, $this->flagBits)) {
            $this->flagBits[] = 15;
        }
        return $this;
    }

    /**
     * Set radio
     *
     * @return Text
     */
    public function setRadio()
    {
        if (!in_array(16, $this->flagBits)) {
            $this->flagBits[] = 16;
        }
        return $this;
    }

    /**
     * Set push button
     *
     * @return Text
     */
    public function setPushButton()
    {
        if (!in_array(17, $this->flagBits)) {
            $this->flagBits[] = 17;
        }
        return $this;
    }

    /**
     * Set radios in unison
     *
     * @return Text
     */
    public function setRadiosInUnison()
    {
        if (!in_array(26, $this->flagBits)) {
            $this->flagBits[] = 26;
        }
        return $this;
    }

    /**
     * Get the field stream
     *
     * @param  int    $i
     * @param  int    $pageIndex
     * @param  string $fontReference
     * @param  int    $x
     * @param  int    $y
     * @return string
     */
    public function getStream($i, $pageIndex, $fontReference, $x, $y)
    {
        $fontReference = substr($fontReference, 0, strpos($fontReference, ' '));

        $name  = (null !== $this->name) ? '    /T(' . $this->name . ')/TU(' . $this->name . ')/TM(' . $this->name . ')' : '';
        $text  = '/DA(' . $fontReference . ' 12 Tf 0 g)';
        $flags = (count($this->flagBits) > 0) ? "\n    /Ff " . $this->getFlags() . "\n" : null;

        // Return the stream
        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Widget\n    /FT /Btn\n    /P {$pageIndex} 0 R\n" . "    /TP 4\n    /CA(Yes)" .
            "    {$text}\n{$name}\n{$flags}>>\nendobj\n\n";
    }

}
