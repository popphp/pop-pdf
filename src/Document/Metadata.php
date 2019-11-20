<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document;

/**
 * Pdf document metadata class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Metadata
{

    /**
     * PDF info object title
     * @var string
     */
    protected $title = 'Pop PDF';

    /**
     * PDF info object author
     * @var string
     */
    protected $author = 'Pop PDF';

    /**
     * PDF info object subject
     * @var string
     */
    protected $subject = 'Pop PDF';

    /**
     * PDF info object creator
     * @var string
     */
    protected $creator = 'Pop PDF';

    /**
     * PDF info object producer
     * @var string
     */
    protected $producer = 'Pop PDF';

    /**
     * PDF info object creation date
     * @var string
     */
    protected $creationDate = null;

    /**
     * PDF info object modification date
     * @var string
     */
    protected $modDate = null;

    /**
     * Set the info object title
     *
     * @param  string $title
     * @return Metadata
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set the info object author
     *
     * @param  string $author
     * @return Metadata
     */
    public function setAuthor($author)
    {
        $this->author = $author;
        return $this;
    }

    /**
     * Set the info object subject
     *
     * @param  string $subject
     * @return Metadata
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set the info object creator
     *
     * @param  string $creator
     * @return Metadata
     */
    public function setCreator($creator)
    {
        $this->creator = $creator;
        return $this;
    }

    /**
     * Set the info object producer
     *
     * @param  string $producer
     * @return Metadata
     */
    public function setProducer($producer)
    {
        $this->producer = $producer;
        return $this;
    }

    /**
     * Set the info object creation date
     *
     * @param  string $date
     * @return Metadata
     */
    public function setCreationDate($date)
    {
        $this->creationDate = $date;
        return $this;
    }

    /**
     * Set the info object modification date
     *
     * @param  string $date
     * @return Metadata
     */
    public function setModDate($date)
    {
        $this->modDate = $date;
        return $this;
    }

    /**
     * Get the info object title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get the info object author
     *
     * @return string
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Get the info object subject
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Get the info object creator
     *
     * @return string
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * Get the info object producer
     *
     * @return string
     */
    public function getProducer()
    {
        return $this->producer;
    }

    /**
     * Get the info object creation date
     *
     * @return string
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * Get the info object modification date
     *
     * @return string
     */
    public function getModDate()
    {
        return $this->modDate;
    }

}