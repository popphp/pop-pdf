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
namespace Pop\Pdf\Build\Import;

use Pop\Pdf\Extract\Value;

/**
 * Pdf build import imported form class
 *
 * One source PDF's AcroForm, kept as structured data rather than serialized
 * text: every node of its field tree (fields and their widgets, already
 * renumbered), the top-level fields, and the form's document-level settings.
 * The compiler writes only the nodes whose widgets are on pages the
 * document still has, so a field tree can lose widgets to dropped or
 * deleted pages without listing objects that are no longer written.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
 */
class ImportedForm
{

    /**
     * Field tree node dictionaries, keyed by object index
     * @var array
     */
    protected array $nodes = [];

    /**
     * Each node's parent node index, null for a top-level field
     * @var array
     */
    protected array $parents = [];

    /**
     * Indices of the imported objects each node uses - appearance streams,
     * actions and the like, but not other nodes
     * @var array
     */
    protected array $nodeObjects = [];

    /**
     * Top-level field indices, in the source's /Fields order
     * @var array
     */
    protected array $fields = [];

    /**
     * Document-level form settings (/DR, /DA, /Q, /SigFlags, /NeedAppearances)
     * @var array
     */
    protected array $settings = [];

    /**
     * Indices of the imported objects the settings use (e.g. /DR fonts)
     * @var array
     */
    protected array $settingsObjects = [];

    /**
     * Add a field tree node
     *
     * @param  int   $index
     * @param  array $dict
     * @param  ?int  $parent
     * @param  array $objects
     * @return ImportedForm
     */
    public function addNode(int $index, array $dict, ?int $parent, array $objects): ImportedForm
    {
        $this->nodes[$index]       = $dict;
        $this->parents[$index]     = $parent;
        $this->nodeObjects[$index] = $objects;
        return $this;
    }

    /**
     * Set the top-level field indices
     *
     * @param  array $fields
     * @return ImportedForm
     */
    public function setFields(array $fields): ImportedForm
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * Set the document-level form settings, and the objects they use
     *
     * @param  array $settings
     * @param  array $objects
     * @return ImportedForm
     */
    public function setSettings(array $settings, array $objects): ImportedForm
    {
        $this->settings        = $settings;
        $this->settingsObjects = $objects;
        return $this;
    }

    /**
     * Get the document-level form settings
     *
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Get the nodes to keep for a set of annotation indices - every node
     * that is one of those annotations (a widget on a remaining page), and
     * every ancestor of one
     *
     * @param  array $annotIndices
     * @return array
     */
    public function getKeptNodes(array $annotIndices): array
    {
        $kept = [];

        foreach ($annotIndices as $index) {
            while (($index !== null) && isset($this->nodes[$index]) && !isset($kept[$index])) {
                $kept[$index] = true;
                $index        = $this->parents[$index];
            }
        }

        return $kept;
    }

    /**
     * Get the kept top-level field indices, in the source's /Fields order
     *
     * @param  array $kept
     * @return array
     */
    public function getKeptFields(array $kept): array
    {
        return array_values(array_filter($this->fields, fn ($index) => isset($kept[$index])));
    }

    /**
     * Get the indices of the imported objects the kept nodes use, including
     * the settings' objects if any node is kept
     *
     * @param  array $kept
     * @return array
     */
    public function getUsedObjectIndices(array $kept): array
    {
        $used = [];

        foreach (array_keys($kept) as $index) {
            foreach ($this->nodeObjects[$index] as $objIndex) {
                $used[$objIndex] = true;
            }
        }

        if (count($kept) > 0) {
            foreach ($this->settingsObjects as $objIndex) {
                $used[$objIndex] = true;
            }
        }

        return array_keys($used);
    }

    /**
     * Get a kept node's dictionary, with its /Kids narrowed to kept nodes
     *
     * @param  int   $index
     * @param  array $kept
     * @return array
     */
    public function getNodeDictionary(int $index, array $kept): array
    {
        $dict = $this->nodes[$index];

        if (isset($dict['Kids']) && is_array($dict['Kids'])) {
            $dict['Kids'] = array_values(array_filter(
                $dict['Kids'], fn ($kid) => ($kid instanceof Value\Reference) && isset($kept[$kid->objNum])
            ));
        }

        return $dict;
    }

}
