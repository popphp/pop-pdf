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
namespace Pop\Pdf\Extract\Value;

/**
 * Pdf extract name value object class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Name
{

    /**
     * Constructor
     *
     * Instantiate a name value object.
     *
     * @param string $name
     */
    public function __construct(
        public readonly string $name
    ) {
    }

    /**
     * Convert the name to a string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

}
