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
class Text extends AbstractField
{

    /**
     * Get the field stream
     *
     * @param  int $i
     * @param  int $pageIndex
     * @param  int $x
     * @param  int $y
     * @return string
     */
    public function getStream($i, $pageIndex, $x, $y)
    {
        // Return the stream
        //return "{$i} 0 obj\n<<form field here>>\nendobj\n\n";

        $name = (null !== $this->name) ? '    /T(' . $this->name . ')/TU(' . $this->name . ')' : '';
        $text = '/DA(/MF1 12 Tf 0 g)';

        // Return the stream
        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Widget\n    /FT /Tx\n    /Rect [{$x} {$y} " .
            (200 + $x) . " " . (20 + $y) . "]\n    /P {$pageIndex} 0 R\n    {$text}\n{$name}\n>>\nendobj\n\n";
    }

}
