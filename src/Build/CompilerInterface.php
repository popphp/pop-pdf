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
namespace Pop\Pdf\Build;

use Pop\Pdf\Document;

/**
 * Compiler interface
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.0.0
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
     * @param  Document\AbstractDocument $document
     * @return Compiler
     */
    public function setDocument(Document\AbstractDocument $document): Compiler;

    /**
     * Compile and finalize the PDF document
     *
     * @param  Document\AbstractDocument $document
     * @return void
     */
    public function finalize(Document\AbstractDocument $document): void;

}