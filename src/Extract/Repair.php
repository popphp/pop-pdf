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
namespace Pop\Pdf\Extract;

/**
 * Pdf extract repair class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Repair
{

    /**
     * Scan raw PDF data for object offsets and a trailer, bypassing xref entirely
     *
     * @param  string $data
     * @return array
     */
    public static function scan(string $data): array
    {
        $offsets = [];
        $matches = [];

        preg_match_all('/(?<![0-9])(\d+)[ \t\r\n]+(\d+)[ \t\r\n]+obj\b/', $data, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $i => $objMatch) {
            $objNum = (int) $objMatch[0];
            $offset = $matches[0][$i][1];
            $offsets[$objNum] = ['offset' => $offset];
        }

        $trailer    = [];
        $trailerPos = strrpos($data, 'trailer');

        if ($trailerPos !== false) {
            try {
                $tokenizer = new Tokenizer($data, $trailerPos + strlen('trailer'));
                $parser    = new ObjectParser($tokenizer);
                $candidate = $parser->parseValue();
                if (is_array($candidate)) {
                    $trailer = $candidate;
                }
            } catch (Exception $e) {
                // Malformed/truncated trailer dict - degrade to an empty
                // trailer rather than losing the offsets already scanned.
            }
        }

        return ['offsets' => $offsets, 'trailer' => $trailer];
    }

}
