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
interface AlignmentInterface
{

    /**
     * Set character wrap boundary
     *
     * @param  int $charWrap
     * @return AlignmentInterface
     */
    public function setCharWrap($charWrap);

    /**
     * Set page wrap boundary
     *
     * @param  int $pageWrap
     * @return AlignmentInterface
     */
    public function setPageWrap($pageWrap);

    /**
     * Set the leading
     *
     * @param  int $leading
     * @return AlignmentInterface
     */
    public function setLeading($leading);

    /**
     * Get character wrap boundary
     *
     * @return int
     */
    public function getCharWrap();

    /**
     * Get page wrap boundary
     *
     * @return int
     */
    public function getPageWrap();

    /**
     * Get the leading
     *
     * @return int
     */
    public function getLeading();

}