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
namespace Pop\Pdf\Build;

use Pop\Pdf\Document;

/**
 * Compiler interface
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
interface CompilerInterface
{

    /**
     * Get the document object
     *
     * @return ?Document
     */
    public function getDocument(): ?Document;

    /**
     * Get the root object
     *
     * @return ?PdfObject\RootObject
     */
    public function getRoot(): ?PdfObject\RootObject;

    /**
     * Get the parent object
     *
     * @return ?PdfObject\ParentObject
     */
    public function getParent(): ?PdfObject\ParentObject;

    /**
     * Get the info object
     *
     * @return ?PdfObject\InfoObject
     */
    public function getInfo(): ?PdfObject\InfoObject;

    /**
     * Return the last object index.
     *
     * @return int
     */
    public function lastIndex(): int;

    /**
     * Get the compiled output
     *
     * @return string
     */
    public function getOutput(): string;

    /**
     * Set the document object
     *
     * @param  Document $document
     * @return Compiler
     */
    public function setDocument(Document $document): Compiler;

    /**
     * Compile and finalize the PDF document
     *
     * @param  Document $document
     * @return void
     */
    public function finalize(Document $document): void;

}
