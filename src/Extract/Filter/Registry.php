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
use Pop\Pdf\Extract\Value;

/**
 * Pdf extract filter registry class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Registry
{

    /**
     * Decode stream data through one or more filters
     *
     * @param  string  $data
     * @param  mixed   $filter
     * @param  mixed   $decodeParms
     * @param  ?Budget $budget
     * @throws Exception
     * @return string
     */
    public static function decode(string $data, mixed $filter, mixed $decodeParms = null, ?Budget $budget = null): string
    {
        // A zero-cost charge fails fast against an already-exhausted budget
        // before any filter work runs - without this, every call after
        // exhaustion still pays the full decode cost (CPU + a transient
        // buffer) only to throw afterward, which a caller that loops (many
        // /Contents refs, many Do operators) turns back into unbounded work.
        $budget?->charge(0);

        if ($filter === null) {
            // An unfiltered stream is still attacker-controlled size that
            // must count toward the aggregate budget - omitting /Filter
            // entirely (a valid, common case for genuinely uncompressed
            // streams) must not be a free way to bypass it, since the real
            // risk here is many streams accumulating, not per-call
            // compression amplification.
            $budget?->charge(strlen($data));
            return $data;
        }

        $filters   = ($filter instanceof Value\Name) ? [$filter] : $filter;
        $parmsList = (($decodeParms === null) || ($decodeParms instanceof Value\Name) ||
            (is_array($decodeParms) && !array_is_list($decodeParms))) ? [$decodeParms] : $decodeParms;

        foreach ($filters as $i => $filterName) {
            $name  = self::filterName($filterName);
            $parms = self::normalizeParams($parmsList[$i] ?? null);
            $data  = self::resolve($name)->decode($data, $parms);
            $budget?->charge(strlen($data));
        }

        return $data;
    }

    /**
     * Resolve a filter value (Name or raw string) to its filter name
     *
     * @param  mixed $filter
     * @return string
     */
    protected static function filterName(mixed $filter): string
    {
        return ($filter instanceof Value\Name) ? $filter->name : (string) $filter;
    }

    /**
     * Normalize a /DecodeParms value into a plain array of scalar params
     *
     * @param  mixed $parms
     * @return array
     */
    protected static function normalizeParams(mixed $parms): array
    {
        if (!is_array($parms)) {
            return [];
        }

        $out = [];
        foreach ($parms as $key => $value) {
            $out[$key] = ($value instanceof Value\Name) ? $value->name : $value;
        }

        return $out;
    }

    /**
     * Resolve a filter name to a filter instance
     *
     * @param  string $name
     * @throws Exception
     * @return FilterInterface
     */
    protected static function resolve(string $name): FilterInterface
    {
        if (($name === 'FlateDecode') || ($name === 'Fl')) {
            return new Flate();
        } elseif (($name === 'ASCIIHexDecode') || ($name === 'AHx')) {
            return new AsciiHex();
        } elseif (($name === 'ASCII85Decode') || ($name === 'A85')) {
            return new Ascii85();
        } elseif (($name === 'RunLengthDecode') || ($name === 'RL')) {
            return new RunLength();
        } elseif (($name === 'LZWDecode') || ($name === 'LZW')) {
            return new Lzw();
        }

        throw new Exception("Error: Unsupported stream filter '{$name}'.");
    }

}
