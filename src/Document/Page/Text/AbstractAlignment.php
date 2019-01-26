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
abstract class AbstractAlignment implements AlignmentInterface
{

    /**
     * Alignment constants
     */
    const LEFT   = 'LEFT';
    const RIGHT  = 'RIGHT';

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
     * @param int $charWrap
     * @param int $pageWrap
     * @param int $leading
     */
    public function __construct($charWrap = 0, $pageWrap = 0, $leading = 0)
    {
        $this->setCharWrap($charWrap);
        $this->setPageWrap($pageWrap);
        $this->setLeading($leading);
    }

    /**
     * Set character wrap boundary
     *
     * @param  int $charWrap
     * @return AbstractAlignment
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
     * @return AbstractAlignment
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
     * @return AbstractAlignment
     */
    public function setLeading($leading)
    {
        $this->leading = $leading;
        return $this;
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