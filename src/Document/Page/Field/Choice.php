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
class Choice extends AbstractField
{

    /**
     * Set combo
     *
     * @return Text
     */
    public function setCombo()
    {
        if (!in_array(18, $this->flagBits)) {
            $this->flagBits[] = 18;
        }
        return $this;
    }

    /**
     * Set edit
     *
     * @return Text
     */
    public function setEdit()
    {
        if (!in_array(19, $this->flagBits)) {
            $this->flagBits[] = 19;
        }
        return $this;
    }

    /**
     * Set sort
     *
     * @return Text
     */
    public function setSort()
    {
        if (!in_array(20, $this->flagBits)) {
            $this->flagBits[] = 20;
        }
        return $this;
    }

    /**
     * Set multiselect
     *
     * @return Text
     */
    public function setMultiSelect()
    {
        if (!in_array(22, $this->flagBits)) {
            $this->flagBits[] = 22;
        }
        return $this;
    }

    /**
     * Set do not spell check
     *
     * @return Text
     */
    public function setDoNotSpellCheck()
    {
        if (!in_array(23, $this->flagBits)) {
            $this->flagBits[] = 23;
        }
        return $this;
    }

    /**
     * Set commit on select change
     *
     * @return Text
     */
    public function setCommitOnSelChange()
    {
        if (!in_array(27, $this->flagBits)) {
            $this->flagBits[] = 27;
        }
        return $this;
    }

}
