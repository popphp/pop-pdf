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
 * Pdf build import reference rewriter class
 *
 * Recursively rewrites every indirect reference in a decoded Extract\Value
 * tree through a per-source object-number renumbering map. Stream bytes are
 * never touched - only a stream's own dictionary is walked.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class ReferenceRewriter
{

    /**
     * Rewrite every reference in a value through a renumbering map
     *
     * @param  mixed $value
     * @param  array $map
     * @return mixed
     */
    public static function rewrite(mixed $value, array $map): mixed
    {
        if ($value instanceof Value\Reference) {
            $newObjNum = $map[$value->objNum] ?? $value->objNum;
            return new Value\Reference($newObjNum, 0);
        }

        if ($value instanceof Value\Stream) {
            return new Value\Stream(self::rewrite($value->dict, $map), $value->raw);
        }

        if (is_array($value)) {
            $rewritten = [];
            foreach ($value as $key => $item) {
                $rewritten[$key] = self::rewrite($item, $map);
            }
            return $rewritten;
        }

        return $value;
    }

}
