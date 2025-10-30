<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
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
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.3
 */
class Metadata
{

    /**
     * PDF info object title
     * @var string
     */
    protected string $title = 'Pop PDF';

    /**
     * PDF info object author
     * @var string
     */
    protected string $author = 'Pop PDF';

    /**
     * PDF info object subject
     * @var string
     */
    protected string $subject = 'Pop PDF';

    /**
     * PDF info object creator
     * @var string
     */
    protected string $creator = 'Pop PDF';

    /**
     * PDF info object producer
     * @var string
     */
    protected string $producer = 'Pop PDF';

    /**
     * PDF info object creation date
     * @var ?string
     */
    protected ?string $creationDate = null;

    /**
     * PDF info object modification date
     * @var ?string
     */
    protected ?string $modDate = null;

    /**
     * Set the info object title
     *
     * @param  string $title
     * @return Metadata
     */
    public function setTitle(string $title): Metadata
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
    public function setAuthor(string $author): Metadata
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
    public function setSubject(string $subject): Metadata
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
    public function setCreator(string $creator): Metadata
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
    public function setProducer(string $producer): Metadata
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
    public function setCreationDate(string $date): Metadata
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
    public function setModDate(string $date): Metadata
    {
        $this->modDate = $date;
        return $this;
    }

    /**
     * Get the info object title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the info object author
     *
     * @return string
     */
    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * Get the info object subject
     *
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Get the info object creator
     *
     * @return string
     */
    public function getCreator(): string
    {
        return $this->creator;
    }

    /**
     * Get the info object producer
     *
     * @return string
     */
    public function getProducer(): string
    {
        return $this->producer;
    }

    /**
     * Get the info object creation date
     *
     * @return ?string
     */
    public function getCreationDate(): ?string
    {
        return $this->creationDate;
    }

    /**
     * Get the info object modification date
     *
     * @return ?string
     */
    public function getModDate(): ?string
    {
        return $this->modDate;
    }

}
