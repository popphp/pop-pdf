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
namespace Pop\Pdf\Build\PdfObject;

/**
 * Pdf info object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class InfoObject extends AbstractObject
{

    /**
     * PDF info object index
     * @var int
     */
    protected $index = 3;

    /**
     * PDF metadata for the info object
     * @var \Pop\Pdf\Document\Metadata
     */
    protected $metadata = null;

    /**
     * Constructor
     *
     * Instantiate a PDF info object.
     *
     * @param  int $index
     * @param  \Pop\Pdf\Document\Metadata $metadata
     */
    public function __construct($index = 3, \Pop\Pdf\Document\Metadata $metadata = null)
    {
        $this->setIndex($index);
        $this->setData("[{info_index}] 0 obj\n<</Creator([{creator}])/CreationDate([{creation_date}])/ModDate" .
            "([{mod_date}])/Author([{author}])/Title([{title}])/Subject([{subject}])/Producer([{producer}])>>\nendobj\n");

        if (null !== $metadata) {
            $this->setMetadata($metadata);
        }
    }

    /**
     * Parse a info object from a string
     *
     * @param  string $stream
     * @return InfoObject
     */
    public static function parse($stream)
    {
        $info = new self();
        $info->setIndex(substr($stream, 0, strpos($stream, ' ')));
        $stream = str_replace($info->getIndex() . ' 0 obj', '[{info_index}] 0 obj', $stream);

        // Determine the Creator
        if (strpos($stream, '/Creator') !== false) {
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
        if (strpos($stream, '/CreationDate') !== false) {
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
        if (strpos($stream, '/ModDate') !== false) {
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
        if (strpos($stream, '/Author') !== false) {
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
        if (strpos($stream, '/Title') !== false) {
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
        if (strpos($stream, '/Subject') !== false) {
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
        if (strpos($stream, '/Producer') !== false) {
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
     * @param  \Pop\Pdf\Document\Metadata $metadata
     * @return InfoObject
     */
    public function setMetadata(\Pop\Pdf\Document\Metadata $metadata)
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Get the info object metadata
     *
     * @return \Pop\Pdf\Document\Metadata
     */
    public function getMetadata()
    {
        if (null === $this->metadata) {
            $this->metadata = new \Pop\Pdf\Document\Metadata();
        }
        return $this->metadata;
    }

    /**
     * Method to print the PDF info object.
     *
     * @return string
     */
    public function __toString()
    {
        if (null === $this->metadata) {
            $this->metadata = new \Pop\Pdf\Document\Metadata();
        }

        // Set the CreationDate and the ModDate if they are null.
        if (null === $this->metadata->getCreationDate()) {
            $this->metadata->setCreationDate(date('D, M j, Y h:i A'));
        }
        if (null === $this->metadata->getModDate()) {
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