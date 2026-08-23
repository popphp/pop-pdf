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
namespace Pop\Pdf\Extract\Xref;

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\ObjectParser;
use Pop\Pdf\Extract\Tokenizer;

/**
 * Pdf extract xref table class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Table
{

    /**
     * Parse a classic xref table at a byte position
     *
     * @param  string $data
     * @param  int    $pos
     * @throws Exception
     * @return array
     */
    public static function parse(string $data, int $pos): array
    {
        $tokenizer = new Tokenizer($data, $pos);
        $token     = $tokenizer->next();

        if (($token['type'] !== 'keyword') || ($token['value'] !== 'xref')) {
            throw new Exception('Error: Expected xref keyword.');
        }

        $offsets = [];

        while (true) {
            $savedPos   = $tokenizer->getPosition();
            $startToken = $tokenizer->next();

            if (($startToken['type'] === 'keyword') && ($startToken['value'] === 'trailer')) {
                break;
            }
            if ($startToken['type'] !== 'number') {
                $tokenizer->setPosition($savedPos);
                break;
            }

            $countToken = $tokenizer->next();
            if ($countToken['type'] !== 'number') {
                throw new Exception('Error: Malformed xref subsection header.');
            }

            $startObj = (int) $startToken['value'];
            $count    = (int) $countToken['value'];

            // Each classic xref entry is a fixed 20 bytes on disk. A
            // malformed subsection header (e.g. a huge declared $count with
            // little/no actual data behind it) must not be trusted to drive
            // the loop bound - clamp it against what could plausibly remain,
            // and bail out cleanly if we run past the end of the data before
            // reading $count entries.
            $maxPossibleEntries = intdiv(strlen($data) - $tokenizer->getPosition(), 20) + 1;
            if ($count > $maxPossibleEntries) {
                $count = $maxPossibleEntries;
            }

            for ($i = 0; $i < $count; $i++) {
                $offsetToken = $tokenizer->next();

                if ($offsetToken['type'] === 'eof') {
                    throw new Exception('Error: Unexpected end of data while parsing an xref subsection.');
                }

                $tokenizer->next(); // generation number - not needed for extraction
                $typeToken   = $tokenizer->next();

                if (($typeToken['type'] === 'keyword') && ($typeToken['value'] === 'n')) {
                    $objNum = $startObj + $i;
                    if (!isset($offsets[$objNum])) {
                        $offsets[$objNum] = ['offset' => (int) $offsetToken['value']];
                    }
                }
            }
        }

        $parser  = new ObjectParser($tokenizer);
        $trailer = $parser->parseValue();

        if (!is_array($trailer)) {
            throw new Exception('Error: Malformed xref trailer dictionary.');
        }

        return ['offsets' => $offsets, 'trailer' => $trailer, 'endPos' => $tokenizer->getPosition()];
    }

}
