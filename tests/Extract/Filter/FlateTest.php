<?php

namespace Pop\Pdf\Test\Extract\Filter;

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Filter\Flate;
use Pop\Pdf\Extract\Value\Reference;
use PHPUnit\Framework\TestCase;

class FlateTest extends TestCase
{

    public function testDecodeNoPredictor()
    {
        $original = 'Hello, this is plain stream data.';
        $filter   = new Flate();
        $this->assertEquals($original, $filter->decode(gzcompress($original)));
    }

    public function testDecodeWithPngUpPredictor()
    {
        // Two 4-byte rows, PNG "Up" filter (type 2): row2[i] = raw2[i] - row1[i] (mod 256).
        $row1 = "\x01\x02\x03\x04";
        $row2 = "\x05\x06\x07\x08";

        $encodedRow1 = "\x00" . $row1; // filter type 0 (None) for the first row
        $encodedRow2 = "\x02";
        for ($i = 0; $i < 4; $i++) {
            $encodedRow2 .= chr((ord($row2[$i]) - ord($row1[$i])) & 0xFF);
        }

        $compressed = gzcompress($encodedRow1 . $encodedRow2);
        $filter     = new Flate();

        $result = $filter->decode($compressed, [
            'Predictor' => 12,
            'Columns'   => 4,
            'Colors'    => 1,
            'BitsPerComponent' => 8,
        ]);

        $this->assertEquals($row1 . $row2, $result);
    }

    public function testDecodeUnderCapSucceeds()
    {
        $original = str_repeat('X', 1024 * 1024);
        $filter   = new Flate();

        $this->assertEquals($original, $filter->decode(gzcompress($original, 9)));
    }

    public function testDecodeAdversarialBombThrows()
    {
        // A small, highly-compressed payload that decompresses to more than
        // MAX_DECODED_LENGTH (67108864 bytes / 64MB) - proves the cap is
        // enforced. Built via zlib's incremental deflate_init()/deflate_add()
        // API instead of gzcompress(str_repeat('A', 100MB)) so the test never
        // materializes the full 80MB decompressed payload in memory - only a
        // 1MB working chunk plus the tiny compressed output. The previous
        // approach's own ~217MB memory footprint ran close enough to
        // PHPUnit's coverage-run memory ceiling to intermittently crash the
        // process (see "Fatal error: Premature end of PHP process").
        $this->expectException(Exception::class);

        $target = 80 * 1024 * 1024;
        $chunk  = str_repeat('A', 1024 * 1024);
        $ctx    = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        $bomb   = '';
        $written = 0;

        while ($written < $target) {
            $piece    = (($target - $written) >= strlen($chunk)) ? $chunk : substr($chunk, 0, $target - $written);
            $bomb    .= deflate_add($ctx, $piece, ZLIB_NO_FLUSH);
            $written += strlen($piece);
        }
        $bomb .= deflate_add($ctx, '', ZLIB_FINISH);

        $filter = new Flate();

        $filter->decode($bomb);
    }

    public function testDecodeWithZeroColumnsThrows()
    {
        // Regression for an infinite loop: Predictor 2 (TIFF) with
        // Columns=0 makes applyTiffPredictor()'s row length 0, so its loop
        // offset never advances.
        $this->expectException(Exception::class);

        $data       = '0123456789012345';
        $compressed = gzcompress($data, 9);
        $filter     = new Flate();

        $filter->decode($compressed, ['Predictor' => 2, 'Columns' => 0, 'Colors' => 1, 'BitsPerComponent' => 8]);
    }

    public function testDecodeWithNegativeColumnsThrows()
    {
        // Regression for an uncaught ValueError: negative Columns makes
        // the PNG predictor's row length negative, and str_repeat() with a
        // negative count throws a PHP ValueError instead of a catchable
        // Extract\Exception.
        $this->expectException(Exception::class);

        $data       = '0123456789012345';
        $compressed = gzcompress($data, 9);
        $filter     = new Flate();

        $filter->decode($compressed, ['Predictor' => 12, 'Columns' => -1, 'Colors' => 1, 'BitsPerComponent' => 8]);
    }

    public function testDecodeWithNonNumericColumnsThrows()
    {
        // Regression for an uncaught TypeError: a non-numeric Columns (e.g.
        // an unresolved Value\Reference from a malformed dict) would
        // otherwise reach applyTiffPredictor()'s int-typed parameter.
        $this->expectException(Exception::class);

        $data       = '0123456789012345';
        $compressed = gzcompress($data, 9);
        $filter     = new Flate();

        $filter->decode($compressed, ['Predictor' => 2, 'Columns' => new Reference(9, 0), 'Colors' => 1, 'BitsPerComponent' => 8]);
    }

    public function testDecodeWithPngSubAveragePaethPredictors()
    {
        // Four 3-byte rows exercising every PNG filter type this decoder
        // recognizes: None(0, already covered elsewhere), Sub(1),
        // Average(3), and Paeth(4). Expected decoded bytes and their raw
        // encodings were hand-derived from the PNG predictor formulas:
        //   Sub:     value = raw + decodedRow[i - bpp]
        //   Average: value = raw + floor((a + b) / 2)
        //   Paeth:   value = raw + paeth(a, b, c)
        $row0Decoded = [10, 20, 30];             // filter 0 (None)
        $row1Decoded = [15, 25, 5];               // filter 1 (Sub)
        $row1Raw     = [15, 10, 236];              // 25-15=10, (5-25)&0xFF=236
        $row2Decoded = [50, 60, 70];               // filter 3 (Average), prev = row1
        $row2Raw     = [43, 23, 38];
        $row3Decoded = [50, 60, 70];               // filter 4 (Paeth), prev = row2
        $row3Raw     = [0, 0, 0];
        $row4Decoded = [7, 8, 9];                  // filter 5 (unrecognized -> passthrough)
        $row4Raw     = [7, 8, 9];

        $encoded = chr(0) . implode('', array_map('chr', $row0Decoded))
                 . chr(1) . implode('', array_map('chr', $row1Raw))
                 . chr(3) . implode('', array_map('chr', $row2Raw))
                 . chr(4) . implode('', array_map('chr', $row3Raw))
                 . chr(5) . implode('', array_map('chr', $row4Raw));

        $expected = implode('', array_map('chr', $row0Decoded))
                  . implode('', array_map('chr', $row1Decoded))
                  . implode('', array_map('chr', $row2Decoded))
                  . implode('', array_map('chr', $row3Decoded))
                  . implode('', array_map('chr', $row4Decoded));

        $compressed = gzcompress($encoded);
        $filter     = new Flate();

        $result = $filter->decode($compressed, [
            'Predictor' => 12,
            'Columns'   => 3,
            'Colors'    => 1,
            'BitsPerComponent' => 8,
        ]);

        $this->assertEquals($expected, $result);
    }

    public function testDecodeWithTiffPredictorSucceeds()
    {
        // TIFF (horizontal differencing) predictor, bpc=8: decoded[i] =
        // decoded[i-colors] + raw[i], cumulative within each row.
        $raw = chr(10) . chr(5) . chr(3); // decodes to 10, 15, 18
        $compressed = gzcompress($raw);
        $filter     = new Flate();

        $result = $filter->decode($compressed, [
            'Predictor' => 2,
            'Columns'   => 3,
            'Colors'    => 1,
            'BitsPerComponent' => 8,
        ]);

        $this->assertEquals(chr(10) . chr(15) . chr(18), $result);
    }

    public function testDecodeWithTiffPredictorAndNonEightBitsPerComponentReturnsDataUnchanged()
    {
        // applyTiffPredictor() only knows how to differentiate 8-bit
        // samples - anything else is returned untouched rather than
        // mis-decoded.
        $raw        = 'abcdef';
        $compressed = gzcompress($raw);
        $filter     = new Flate();

        $result = $filter->decode($compressed, [
            'Predictor' => 2,
            'Columns'   => 3,
            'Colors'    => 1,
            'BitsPerComponent' => 1,
        ]);

        $this->assertEquals($raw, $result);
    }

    public function testPaethPredictorReturnsA()
    {
        // a=0, b=100, c=100: pa=0, pb=100, pc=100 -> pa<=pb && pa<=pc -> a.
        $filter = new Flate();
        $method = new \ReflectionMethod(Flate::class, 'paeth');
        $method->setAccessible(true);

        $this->assertEquals(0, $method->invoke($filter, 0, 100, 100));
    }

    public function testPaethPredictorReturnsB()
    {
        // a=0, b=50, c=0: pa=50, pb=0, pc=50 -> first condition fails,
        // pb<=pc -> b.
        $filter = new Flate();
        $method = new \ReflectionMethod(Flate::class, 'paeth');
        $method->setAccessible(true);

        $this->assertEquals(50, $method->invoke($filter, 0, 50, 0));
    }

    public function testPaethPredictorReturnsC()
    {
        // a=0, b=10, c=6: pa=4, pb=6, pc=2 -> pa<=pc fails, pb<=pc fails
        // -> falls through to c.
        $filter = new Flate();
        $method = new \ReflectionMethod(Flate::class, 'paeth');
        $method->setAccessible(true);

        $this->assertEquals(6, $method->invoke($filter, 0, 10, 6));
    }

}
