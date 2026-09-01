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
namespace Pop\Pdf\Extract\Filter;

use Pop\Pdf\Extract\Exception;

/**
 * Pdf extract flate filter class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class Flate implements FilterInterface
{

    /**
     * Maximum decoded stream size in bytes
     */
    protected const MAX_DECODED_LENGTH = 67108864;

    /**
     * Decode a FlateDecode stream, applying a PNG/TIFF predictor if specified
     *
     * @param  string $data
     * @param  array  $params
     * @throws Exception
     * @return string
     */
    public function decode(string $data, array $params = []): string
    {
        $decoded = @gzuncompress($data, self::MAX_DECODED_LENGTH);

        if ($decoded === false) {
            $decoded = @gzinflate(substr($data, 2), self::MAX_DECODED_LENGTH);
        }

        if ($decoded === false) {
            throw new Exception('Error: Unable to decode a FlateDecode stream.');
        }

        return $this->applyPredictor($decoded, $params);
    }

    /**
     * Apply the PNG (per-row) or TIFF predictor to decoded stream data
     *
     * @param  string $data
     * @param  array  $params
     * @throws Exception
     * @return string
     */
    protected function applyPredictor(string $data, array $params): string
    {
        $predictor = $params['Predictor'] ?? 1;

        if ($predictor <= 1) {
            return $data;
        }

        $columns = $params['Columns'] ?? 1;
        $colors  = $params['Colors'] ?? 1;
        $bpc     = $params['BitsPerComponent'] ?? 8;

        // Columns/Colors/BitsPerComponent come straight out of a
        // producer-controlled /DecodeParms dict - a non-numeric value would
        // throw a TypeError at the applyTiffPredictor() call boundary below,
        // and a zero/negative Columns turns $rowLen into a value that never
        // advances the loop offset in applyTiffPredictor(), hanging forever
        // on otherwise-legitimate data. Reject all of that up front instead.
        if (!is_numeric($columns) || !is_numeric($colors) || !is_numeric($bpc) ||
            ($columns <= 0) || ($colors <= 0) || ($bpc <= 0)) {
            throw new Exception('Error: Invalid predictor parameters for a FlateDecode stream.');
        }

        $bpp     = (int) ceil(($colors * $bpc) / 8);
        $rowLen  = (int) ceil(($colors * $bpc * $columns) / 8);

        if ($predictor == 2) {
            return $this->applyTiffPredictor($data, $columns, $colors, $bpc);
        }

        $out    = '';
        $prev   = str_repeat("\0", $rowLen);
        $offset = 0;
        $length = strlen($data);

        while (($offset + 1 + $rowLen) <= $length) {
            $filterType = ord($data[$offset]);
            $row        = substr($data, $offset + 1, $rowLen);
            $offset    += 1 + $rowLen;
            $decodedRow = '';

            for ($i = 0; $i < $rowLen; $i++) {
                $raw = ord($row[$i]);
                $a   = ($i >= $bpp) ? ord($decodedRow[$i - $bpp]) : 0;
                $b   = ord($prev[$i]);
                $c   = ($i >= $bpp) ? ord($prev[$i - $bpp]) : 0;

                if ($filterType === 0) {
                    $value = $raw;
                } elseif ($filterType === 1) {
                    $value = $raw + $a;
                } elseif ($filterType === 2) {
                    $value = $raw + $b;
                } elseif ($filterType === 3) {
                    $value = $raw + (int) floor(($a + $b) / 2);
                } elseif ($filterType === 4) {
                    $value = $raw + $this->paeth($a, $b, $c);
                } else {
                    $value = $raw;
                }

                $decodedRow .= chr($value & 0xFF);
            }

            $out .= $decodedRow;
            $prev = $decodedRow;
        }

        return $out;
    }

    /**
     * Compute the PNG Paeth predictor value
     *
     * @param  int $a
     * @param  int $b
     * @param  int $c
     * @return int
     */
    protected function paeth(int $a, int $b, int $c): int
    {
        $p  = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        if (($pa <= $pb) && ($pa <= $pc)) {
            return $a;
        } elseif ($pb <= $pc) {
            return $b;
        }

        return $c;
    }

    /**
     * Apply the TIFF (horizontal differencing) predictor to decoded stream data
     *
     * @param  string $data
     * @param  int    $columns
     * @param  int    $colors
     * @param  int    $bpc
     * @return string
     */
    protected function applyTiffPredictor(string $data, int $columns, int $colors, int $bpc): string
    {
        if ($bpc != 8) {
            return $data;
        }

        $rowLen = $columns * $colors;
        $out    = '';
        $length = strlen($data);

        for ($offset = 0; $offset < $length; $offset += $rowLen) {
            $row = substr($data, $offset, $rowLen);
            for ($i = $colors; $i < strlen($row); $i++) {
                $row[$i] = chr((ord($row[$i]) + ord($row[$i - $colors])) & 0xFF);
            }
            $out .= $row;
        }

        return $out;
    }

}
