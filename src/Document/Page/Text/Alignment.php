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
class Alignment
{

    /**
     * Justification constants
     */
    const LEFT   = 'LEFT';
    const RIGHT  = 'RIGHT';
    const CENTER = 'CENTER';

    /**
     * Text justification
     * @var string
     */
    protected $justification = 'LEFT';

    /**
     * Character wrap boundary (wrap by length of characters)
     * @var int
     */
    protected $charWrap = 0;

    /**
     * Page wrap boundary (wrap by the edge of the page)
     * @var int
     */
    protected $pageWrap = 0;

    /**
     * Text leading
     * @var int
     */
    protected $leading = 0;

    /**
     * Constructor
     *
     * Instantiate a PDF text alignment object.
     *
     */
    public function __construct()
    {

    }

    /**
     * Set text justification
     *
     * @param  string $justification
     * @throws \InvalidArgumentException
     * @return Alignment
     */
    public function setJustification($justification)
    {
        $justification = strtoupper($justification);

        if (($justification != self::LEFT) && ($justification != self::RIGHT) && ($justification != self::CENTER)) {
            throw new \InvalidArgumentException('Error: The justification must be either LEFT, RIGHT or CENTER');
        }

        $this->justification = $justification;
        return $this;
    }

    /**
     * Set character wrap boundary
     *
     * @param  int $charWrap
     * @return Alignment
     */
    public function setCharWrap($charWrap)
    {
        $this->charWrap = $charWrap;
        return $this;
    }

    /**
     * Set page wrap boundary
     *
     * @param  int $pageWrap
     * @return Alignment
     */
    public function setPageWrap($pageWrap)
    {
        $this->pageWrap = $pageWrap;
        return $this;
    }

    /**
     * Set the leading
     *
     * @param  int $leading
     * @return Alignment
     */
    public function setLeading($leading)
    {
        $this->leading = $leading;
        return $this;
    }

    /**
     * Get text justification
     *
     * @return string
     */
    public function getJustification()
    {
        return $this->justification;
    }

    /**
     * Get character wrap boundary
     *
     * @return int
     */
    public function getCharWrap()
    {
        return $this->charWrap;
    }

    /**
     * Get page wrap boundary
     *
     * @return int
     */
    public function getPageWrap()
    {
        return $this->pageWrap;
    }

    /**
     * Get the leading
     *
     * @return int
     */
    public function getLeading()
    {
        return $this->leading;
    }

}