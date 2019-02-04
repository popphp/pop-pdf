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
     * Alignment constant
     */
    const CENTER = 'CENTER';

    /**
     * Text alignment
     * @var string
     */
    protected $alignment = 'LEFT';

    /**
     * Constructor
     *
     * Instantiate a PDF text alignment object.
     *
     * @param string $alignment
     * @param int    $charWrap
     * @param int    $pageWrap
     * @param int    $leading
     */
    public function __construct($alignment = 'LEFT', $charWrap = 0, $pageWrap = 0, $leading = 0)
    {
        parent::__construct($charWrap, $pageWrap, $leading);
        $this->setAlignment($alignment);
    }

    /**
     * Set text alignment
     *
     * @param  string $alignment
     * @throws \InvalidArgumentException
     * @return Alignment
     */
    public function setAlignment($alignment)
    {
        $alignment = strtoupper($alignment);

        if (($alignment != self::LEFT) && ($alignment != self::RIGHT) && ($alignment != self::CENTER)) {
            throw new \InvalidArgumentException('Error: The alignment must be either LEFT, RIGHT or CENTER');
        }

        $this->alignment = $alignment;
        return $this;
    }

    /**
     * Get text alignment
     *
     * @return string
     */
    public function getAlignment()
    {
        return $this->alignment;
    }

    /**
     * Determine if text has alignment
     *
     * @return boolean
     */
    public function hasAlignment()
    {
        return !empty($this->alignment);
    }

}