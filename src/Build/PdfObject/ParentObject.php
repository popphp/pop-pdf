<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\PdfObject;

/**
 * Pdf parent object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class ParentObject extends AbstractObject
{

    /**
     * PDF parent object index
     * @var ?int
     */
    protected ?int $index = 2;

    /**
     * PDF parent kids
     * @var array
     */
    protected array $kids = [];

    /**
     * Constructor
     *
     * Instantiate a PDF parent object.
     *
     * @param  int $index
     */
    public function __construct(int $index = 2)
    {
        $this->setIndex($index);
        $this->setData("[{parent_index}] 0 obj\n<</Type/Pages/Count [{count}]/Kids[[{kids}]]>>\nendobj\n");
    }

    /**
     * Parse a parent object from a string
     *
     * @param  string $stream
     * @return ParentObject
     */
    public static function parse(string $stream): ParentObject
    {
        $parent = new self();

        $parent->setIndex(substr($stream, 0, strpos($stream, ' ')));
        $stream = str_replace($parent->getIndex() . ' 0 obj', '[{parent_index}] 0 obj', $stream);

        // Determine the kids count.
        $matches = [];
        preg_match('/\/Count\s\d*/', $stream, $matches);
        $count  = $matches[0];
        $count  = str_replace('/Count ', '', $count);
        $stream = str_replace('Count ' . $count, 'Count [{count}]', $stream);

        // Determine the kids object indices.
        $kids = trim(substr($stream, (strpos($stream, '/Kids') + 5)));
        $kids = (str_starts_with($kids, '[')) ? substr($kids, 0, strpos($kids, ']') + 1) :
            substr($kids, 0, (strpos($kids, ' R') + 2));

        $kidIndices = $parent->getDictionaryReferences(substr($stream, (strpos($stream, '/Kids') + 5)));

        $parent->setKids($kidIndices);
        $parent->setData(str_replace($kids, '[[{kids}]]', $stream) . "\n");

        return $parent;
    }

    /**
     * Add a kid index to the parent object
     *
     * @param  int $kid
     * @return ParentObject
     */
    public function addKid(int$kid): ParentObject
    {
        $this->kids[] = (int)$kid;
        return $this;
    }

    /**
     * Remove a kid index from the parent object
     *
     * @param  int $kid
     * @return ParentObject
     */
    public function removeKid(int $kid): ParentObject
    {
        if ($this->hasKid($kid)) {
            unset($this->kids[array_search($kid, $this->kids)]);
        }
        return $this;
    }

    /**
     * Set the parent object kids
     *
     * @param  array $kids
     * @return ParentObject
     */
    public function setKids(array $kids): ParentObject
    {
        $this->kids = $kids;
        return $this;
    }

    /**
     * Get the parent object kid count
     *
     * @return int
     */
    public function getCount(): int
    {
        return count($this->kids);
    }

    /**
     * Get the parent object kid indices
     *
     * @return array
     */
    public function getKids(): array
    {
        return $this->kids;
    }

    /**
     * Determine whether the parent object contains a kid object index
     *
     * @param  int $kid
     * @return bool
     */
    public function hasKid(int $kid): bool
    {
        return (in_array($kid, $this->kids));
    }

    /**
     * Method to print the parent object.
     *
     * @return string
     */
    public function __toString(): string
    {
        return str_replace(['[{parent_index}]', '[{count}]', '[{kids}]'],
            [$this->index, count($this->kids), implode(" 0 R ", $this->kids) . " 0 R"], $this->data);
    }

}