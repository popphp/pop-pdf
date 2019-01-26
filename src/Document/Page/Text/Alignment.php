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
     * Text justification
     * @var string
     */
    protected $justification = 'LEFT';

    /**
     * Constructor
     *
     * Instantiate a PDF text alignment object.
     *
     * @param string $justification
     * @param int    $charWrap
     * @param int    $pageWrap
     * @param int    $leading
     */
    public function __construct($justification = 'LEFT', $charWrap = 0, $pageWrap = 0, $leading = 0)
    {
        parent::__construct($charWrap, $pageWrap, $leading);
        $this->setJustification($justification);
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
     * Get text justification
     *
     * @return string
     */
    public function getJustification()
    {
        return $this->justification;
    }

}