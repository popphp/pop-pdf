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
 * Pdf form class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.3
 */
class Form
{

    /**
     * Form name
     * @var ?string
     */
    protected ?string $name = null;

    /**
     * Form field indices
     * @var array
     */
    protected array $fields = [];

    /**
     * Constructor
     *
     * Instantiate a PDF form object.
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->setName($name);
    }

    /**
     * Set form name
     *
     * @param  string $name
     * @return Form
     */
    public function setName(string $name): Form
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the form name
     *
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Add field index
     *
     * @param  int $i
     * @return Form
     */
    public function addFieldIndex(int $i): Form
    {
        $this->fields[] = $i;
        return $this;
    }

    /**
     * Get field indices
     *
     * @return array
     */
    public function getFieldIndices(): array
    {
        return $this->fields;
    }

    /**
     * Get number of fields
     *
     * @return int
     */
    public function getNumberOfFields(): int
    {
        return count($this->fields);
    }

    /**
     * Get the form stream
     *
     * @param  int $i
     * @return string
     */
    public function getStream(int $i): string
    {
        // Return the stream
        $stream = "{$i} 0 obj\n<</Fields[";

        $fields = '';
        foreach ($this->fields as $value) {
            $fields .= $value . ' 0 R ';
        }
        $fields = substr($fields, 0, -1);

        $stream .= $fields . "]>>\nendobj\n\n";

        return $stream;
    }

}
