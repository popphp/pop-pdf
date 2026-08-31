<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\PdfObject;

use Pop\Pdf\Document\Metadata;
use Pop\Pdf\Document\Page\Text;

/**
 * Pdf info object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
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
     * Encrypted, PDF-literal-string-escaped overrides for this object's
     * fields, keyed by the same placeholder name used in $this->data (e.g.
     * 'title', 'creation_date'). Populated by encryptWith() and consumed by
     * __toString(); empty when the document has no security configured, in
     * which case __toString() falls back to the raw, unescaped metadata
     * values exactly as it always has.
     * @var array<string, string>
     */
    protected array $encrypted = [];

    /**
     * Constructor
     *
     * Instantiate a PDF info object.
     *
     * @param  int       $index
     * @param  ?Metadata $metadata
     */
    public function __construct(int $index = 3, ?Metadata $metadata = null)
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
        $info->setIndex((int)substr($stream, 0, strpos($stream, ' ')));
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
     * Run this object's literal string fields (title, author, subject,
     * creator, producer, and the CreationDate/ModDate timestamps) through an
     * encryption callback.
     *
     * Invoked from Compiler's encryption pass, alongside the StreamObject
     * loop, since this object builds its literal PDF strings directly rather
     * than going through StreamObject and so isn't covered by that pass.
     * $encryptor receives each field's raw string value and must return its
     * encrypted bytes; those bytes are then escaped for PDF literal-string
     * syntax the same way an unencrypted value's reserved bytes would be.
     *
     * The CreationDate/ModDate defaults are resolved here (rather than left
     * for __toString() to fill in lazily) so the value that gets encrypted
     * is exactly the value __toString() later emits - otherwise a still-null
     * date would be encrypted as an empty string here and then overwritten
     * with a plaintext default in __toString(), leaving a date a decrypting
     * reader would garble.
     *
     * @param  callable $encryptor
     * @return InfoObject
     */
    public function encryptWith(callable $encryptor): InfoObject
    {
        $metadata = $this->getMetadata();

        if ($metadata->getCreationDate() === null) {
            $metadata->setCreationDate(date('D, M j, Y h:i A'));
        }
        if ($metadata->getModDate() === null) {
            $metadata->setModDate(date('D, M j, Y h:i A'));
        }

        $fields = [
            'title'         => $metadata->getTitle(),
            'subject'       => $metadata->getSubject(),
            'author'        => $metadata->getAuthor(),
            'creator'       => $metadata->getCreator(),
            'producer'      => $metadata->getProducer(),
            'mod_date'      => $metadata->getModDate(),
            'creation_date' => $metadata->getCreationDate(),
        ];

        foreach ($fields as $key => $value) {
            if ($value !== null) {
                // AES-encrypted bytes are arbitrary binary and, unlike a
                // human-authored title/author string, routinely contain raw
                // 0x0D/0x0A bytes among other reserved bytes. A compliant
                // literal-string reader normalizes any *unescaped* raw CR
                // (and CR/LF pairs) to a bare LF per ISO 32000-1 7.3.4.2,
                // silently altering that byte - which corrupts not just that
                // byte but the whole 16-byte AES-CBC block it's part of once
                // decrypted. Text::escape() (already used everywhere else in
                // this codebase that emits literal PDF strings) backslash-
                // escapes CR/LF/tab/backspace/form-feed in addition to
                // backslash/parens, which is what prevents that
                // normalization from ever touching the byte in the first
                // place - a plain backslash/parens-only escape (sufficient
                // for ordinary text) is not enough here.
                $this->encrypted[$key] = Text::escape($encryptor((string)$value));
            }
        }

        return $this;
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

        // Encrypted (and already literal-string-escaped) overrides take
        // precedence, field by field, over the raw metadata value - set by
        // encryptWith() when the document has security configured, and
        // empty otherwise, in which case behavior is unchanged from before
        // encryption support existed.
        $title        = $this->encrypted['title']         ?? (string)$this->metadata->getTitle();
        $subject      = $this->encrypted['subject']        ?? (string)$this->metadata->getSubject();
        $author       = $this->encrypted['author']         ?? (string)$this->metadata->getAuthor();
        $creator      = $this->encrypted['creator']        ?? (string)$this->metadata->getCreator();
        $producer     = $this->encrypted['producer']       ?? (string)$this->metadata->getProducer();
        $modDate      = $this->encrypted['mod_date']       ?? (string)$this->metadata->getModDate();
        $creationDate = $this->encrypted['creation_date']  ?? (string)$this->metadata->getCreationDate();

        return str_replace(
            [
                '[{info_index}]', '[{title}]', '[{subject}]', '[{author}]',
                '[{creator}]', '[{producer}]', '[{mod_date}]', '[{creation_date}]'
            ],
            [
                (string)$this->index, $title, $subject, $author,
                $creator, $producer, $modDate, $creationDate
            ],
            $this->data
        );
    }

}
