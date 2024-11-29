<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\PdfObject;

use Pop\Pdf\Document\Metadata;

/**
 * Pdf info object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.1
 */
class InfoObject extends AbstractObject
{

    /**
     * PDF info object index
     * @var ?int
     */
    protected ?int $index = 3;

    /**
     * PDF metadata for the info object
     * @var ?Metadata
     */
    protected ?Metadata $metadata = null;

    /**
     * Constructor
     *
     * Instantiate a PDF info object.
     *
     * @param  int       $index
     * @param  ?Metadata $metadata
     */
    public function __construct(int $index = 3, \Pop\Pdf\Document\Metadata $metadata = null)
    {
        $this->setIndex($index);
        $this->setData("[{info_index}] 0 obj\n<</Creator([{creator}])/CreationDate([{creation_date}])/ModDate" .
            "([{mod_date}])/Author([{author}])/Title([{title}])/Subject([{subject}])/Producer([{producer}])>>\nendobj\n");

        if ($metadata !== null) {
            $this->setMetadata($metadata);
        }
    }

    /**
     * Parse a info object from a string
     *
     * @param  string $stream
     * @return InfoObject
     */
    public static function parse(string $stream): InfoObject
    {
        $info = new self();
        $info->setIndex(substr($stream, 0, strpos($stream, ' ')));
        $stream = str_replace($info->getIndex() . ' 0 obj', '[{info_index}] 0 obj', $stream);

        // Determine the Creator
        if (str_contains($stream, '/Creator')) {
            $creator = substr($stream, strpos($stream, '/Creator'));
            $creator = substr($creator, strpos($creator, '('));
            $creator = substr($creator, 0, strpos($creator, ')'));
            $creator =  str_replace('(', '', $creator);
            $stream =  str_replace('Creator(' . $creator . ')', 'Creator([{creator}])', $stream);
            $info->getMetadata()->setCreator($creator);
        } else {
            $stream =  str_replace('>>', '/Creator([{creator}])>>', $stream);
        }

        // Determine the CreationDate
        if (str_contains($stream, '/CreationDate')) {
            $creationDate = substr($stream, strpos($stream, '/CreationDate'));
            $creationDate = substr($creationDate, strpos($creationDate, '('));
            $creationDate = substr($creationDate, 0, strpos($creationDate, ')'));
            $creationDate =  str_replace('(', '', $creationDate);
            $stream =  str_replace('CreationDate(' . $creationDate . ')', 'CreationDate([{creation_date}])', $stream);
            $info->getMetadata()->setCreationDate($creationDate);
        } else {
            $stream =  str_replace('>>', '/CreationDate([{creation_date}])>>', $stream);
        }

        // Determine the ModDate
        if (str_contains($stream, '/ModDate')) {
            $modDate = substr($stream, strpos($stream, '/ModDate'));
            $modDate = substr($modDate, strpos($modDate, '('));
            $modDate = substr($modDate, 0, strpos($modDate, ')'));
            $modDate =  str_replace('(', '', $modDate);
            $stream =  str_replace('ModDate(' . $modDate . ')', 'ModDate([{mod_date}])', $stream);
            $info->getMetadata()->setModDate($modDate);
        } else {
            $stream =  str_replace('>>', '/ModDate([{mod_date}])>>', $stream);
        }

        // Determine the Author
        if (str_contains($stream, '/Author')) {
            $author = substr($stream, strpos($stream, '/Author'));
            $author = substr($author, strpos($author, '('));
            $author = substr($author, 0, strpos($author, ')'));
            $author =  str_replace('(', '', $author);
            $stream =  str_replace('Author(' . $author . ')', 'Author([{author}])', $stream);
            $info->getMetadata()->setAuthor($author);
        } else {
            $stream =  str_replace('>>', '/Author([{author}])>>', $stream);
        }

        // Determine the Title
        if (str_contains($stream, '/Title')) {
            $title = substr($stream, strpos($stream, '/Title'));
            $title = substr($title, strpos($title, '('));
            $title = substr($title, 0, strpos($title, ')'));
            $title =  str_replace('(', '', $title);
            $stream =  str_replace('Title(' . $title . ')', 'Title([{title}])', $stream);
            $info->getMetadata()->setTitle($title);
        } else {
            $stream =  str_replace('>>', '/Title([{title}])>>', $stream);
        }

        // Determine the Subject
        if (str_contains($stream, '/Subject')) {
            $subject = substr($stream, strpos($stream, '/Subject'));
            $subject = substr($subject, strpos($subject, '('));
            $subject = substr($subject, 0, strpos($subject, ')'));
            $subject =  str_replace('(', '', $subject);
            $stream =  str_replace('Subject(' . $subject . ')', 'Subject([{subject}])', $stream);
            $info->getMetadata()->setSubject($subject);
        } else {
            $stream =  str_replace('>>', '/Subject([{subject}])>>', $stream);
        }

        // Determine the Producer
        if (str_contains($stream, '/Producer')) {
            $producer = substr($stream, strpos($stream, '/Producer'));
            $producer = substr($producer, strpos($producer, '('));
            $producer = substr($producer, 0, strpos($producer, ')'));
            $producer =  str_replace('(', '', $producer);
            $stream =  str_replace('Producer(' . $producer . ')', 'Producer([{producer}])', $stream);
            $info->getMetadata()->setProducer($producer);
        } else {
            $stream =  str_replace('>>', '/Producer([{producer}])>>', $stream);
        }

        $info->setData($stream);
        return $info;
    }

    /**
     * Set the info object metadata
     *
     * @param  Metadata $metadata
     * @return InfoObject
     */
    public function setMetadata(Metadata $metadata): InfoObject
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Get the info object metadata
     *
     * @return ?Metadata
     */
    public function getMetadata(): ?Metadata
    {
        if ($this->metadata === null) {
            $this->metadata = new Metadata();
        }
        return $this->metadata;
    }

    /**
     * Method to print the PDF info object.
     *
     * @return string
     */
    public function __toString(): string
    {
        if ($this->metadata === null) {
            $this->metadata = new \Pop\Pdf\Document\Metadata();
        }

        // Set the CreationDate and the ModDate if they are null.
        if ($this->metadata->getCreationDate() === null) {
            $this->metadata->setCreationDate(date('D, M j, Y h:i A'));
        }
        if ($this->metadata->getModDate() === null) {
            $this->metadata->setModDate(date('D, M j, Y h:i A'));
        }

        return str_replace(
            [
                '[{info_index}]', '[{title}]', '[{subject}]', '[{author}]',
                '[{creator}]', '[{producer}]', '[{mod_date}]', '[{creation_date}]'
            ],
            [
                $this->index, $this->metadata->getTitle(), $this->metadata->getSubject(), $this->metadata->getAuthor(),
                $this->metadata->getCreator(), $this->metadata->getProducer(), $this->metadata->getModDate(), $this->metadata->getCreationDate()
            ],
            $this->data
        );
    }

}
