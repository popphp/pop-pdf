<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Extract\Filter;

use Pop\Pdf\Extract\Exception;

/**
 * Pdf extract filter decode budget class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Budget
{

    /**
     * Remaining bytes available before the budget is exhausted
     * @var int
     */
    protected int $remaining;

    /**
     * Constructor
     *
     * Instantiate a decode budget with a total byte allowance.
     *
     * @param int $totalBytes
     */
    public function __construct(int $totalBytes)
    {
        $this->remaining = $totalBytes;
    }

    /**
     * Charge a number of bytes against the remaining budget
     *
     * @param  int $bytes
     * @throws Exception
     * @return void
     */
    public function charge(int $bytes): void
    {
        $this->remaining -= $bytes;

        if ($this->remaining < 0) {
            throw new Exception('Error: Exceeded the maximum total decoded size for this document.');
        }
    }

}
