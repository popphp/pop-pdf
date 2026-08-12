<?php

namespace Pop\Pdf\Test\Extract\Filter;

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Filter\Lzw;
use PHPUnit\Framework\TestCase;

class LzwTest extends TestCase
{

    protected function packCodes(array $codes, int $width): string
    {
        $bits = '';
        foreach ($codes as $code) {
            $bits .= str_pad(decbin($code), $width, '0', STR_PAD_LEFT);
        }
        while ((strlen($bits) % 8) !== 0) {
            $bits .= '0';
        }

        $out = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $out .= chr(bindec(substr($bits, $i, 8)));
        }

        return $out;
    }

    public function testDecodeSimpleLiteralCodes()
    {
        // Clear(256), 'A'(65), 'B'(66), EOD(257) - all 9-bit codes.
        $encoded = $this->packCodes([256, 65, 66, 257], 9);
        $filter  = new Lzw();

        $this->assertEquals('AB', $filter->decode($encoded));
    }

    public function testDecodeSelfReferencingTableEntry()
    {
        // Clear, 'A', then code 258 read BEFORE any table entry exists at
        // that index (table only holds 0-257 after processing 'A' alone) -
        // this is the genuine "KwKwK" self-referencing case:
        // entry = prev . prev[0].
        $encoded = $this->packCodes([256, 65, 258, 257], 9);
        $filter  = new Lzw();

        $this->assertEquals('AAA', $filter->decode($encoded));
    }

    protected function packVariableWidth(array $codes): string
    {
        // Mirrors Lzw::decode()'s width-growth bookkeeping (table size,
        // EarlyChange, 511/1023/2047 thresholds) so codes are packed at the
        // same width the decoder will read them at as the table grows.
        $bits        = '';
        $tableSize   = 258; // 256 literals + Clear(256) + EOD(257)
        $codeWidth   = 9;
        $earlyChange = 1;
        $boundary    = true;

        foreach ($codes as $code) {
            $bits .= str_pad(decbin($code), $codeWidth, '0', STR_PAD_LEFT);

            if ($code === 256) {
                $tableSize = 258;
                $codeWidth = 9;
                $boundary  = true;
                continue;
            }
            if ($code === 257) {
                continue;
            }

            if (!$boundary) {
                $tableSize++;
            }
            $boundary = false;

            $nextSize = $tableSize + $earlyChange;
            if ($nextSize > 2047) {
                $codeWidth = 12;
            } elseif ($nextSize > 1023) {
                $codeWidth = 11;
            } elseif ($nextSize > 511) {
                $codeWidth = 10;
            } else {
                $codeWidth = 9;
            }
        }

        while ((strlen($bits) % 8) !== 0) {
            $bits .= '0';
        }

        $out = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $out .= chr(bindec(substr($bits, $i, 8)));
        }

        return $out;
    }

    public function testDecodeAcrossCodeWidthBoundary()
    {
        // Clear, then the 256 literal codes 0..255 in order (each one after
        // the first adds a new table entry, growing the table from 258 to
        // 513 - crossing the 511 early-change threshold partway through, so
        // later codes and EOD are packed at 10 bits instead of 9), then EOD.
        $codes    = array_merge([256], range(0, 255), [257]);
        $encoded  = $this->packVariableWidth($codes);
        $filter   = new Lzw();

        $expected = '';
        for ($i = 0; $i < 256; $i++) {
            $expected .= chr($i);
        }

        $this->assertEquals($expected, $filter->decode($encoded));
    }

    /**
     * Build a stream that exploits the table's implicit-overflow reset (at
     * 4096 entries) rather than an explicit Clear code. Unlike an explicit
     * Clear, the implicit reset does not null out $prev - each loop's
     * final code references the longest entry from the previous loop right
     * before the reset, so $prev survives it, and the next loop's entries
     * keep growing from that length instead of restarting at 1. This is
     * the exact adversarial pattern found during design: entry-length
     * growth compounds across loops instead of resetting each time,
     * producing far more decoded output than the loop count alone would
     * suggest (verified: 1 loop -> ~7.4MB, 2 -> ~29.5MB, 3 -> ~66.3MB,
     * 4 -> exceeds the 64MB cap).
     */
    protected function packWithImplicitReset(int $loops): string
    {
        $bits        = '';
        $tableSize   = 258;
        $codeWidth   = 9;
        $earlyChange = 1;

        $emit = function (int $code) use (&$bits, &$codeWidth) {
            $bits .= str_pad(decbin($code), $codeWidth, '0', STR_PAD_LEFT);
        };
        $updateWidth = function () use (&$tableSize, &$codeWidth, $earlyChange) {
            $nextSize = $tableSize + $earlyChange;
            if ($nextSize > 2047) {
                $codeWidth = 12;
            } elseif ($nextSize > 1023) {
                $codeWidth = 11;
            } elseif ($nextSize > 511) {
                $codeWidth = 10;
            } else {
                $codeWidth = 9;
            }
        };

        $emit(97); // seed with a single literal 'a'

        for ($loop = 0; $loop < $loops; $loop++) {
            // Sequentially emitting "the code about to be created" exploits
            // the KwKwK self-reference case every time, growing the newest
            // entry's length by exactly 1 per code - the fastest possible
            // entry growth in this decoder.
            for ($code = 258; $code < 4096; $code++) {
                $emit($code);
                $tableSize++;
                $updateWidth();
            }
            // One more code referencing the longest entry (4095) triggers
            // the implicit reset on the NEXT code processed, while leaving
            // $prev set to this loop's longest entry.
            $emit(4095);
            $tableSize = 258;
            $updateWidth();
        }

        $emit(257); // EOD

        while ((strlen($bits) % 8) !== 0) {
            $bits .= '0';
        }

        $out = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $out .= chr(bindec(substr($bits, $i, 8)));
        }

        return $out;
    }

    public function testDecodeUnderCapSucceeds()
    {
        $encoded = $this->packWithImplicitReset(1);
        $filter  = new Lzw();

        $result = $filter->decode($encoded);

        $this->assertLessThan(67108864, strlen($result));
    }

    public function testDecodeAdversarialCompoundingResetThrows()
    {
        // 4 loops compounds past the 64MB cap (verified: 3 loops decodes to
        // ~66.3MB, still under the 67108864-byte cap; 4 loops exceeds it).
        $this->expectException(Exception::class);

        $encoded = $this->packWithImplicitReset(4);
        $filter  = new Lzw();

        $filter->decode($encoded);
    }

    public function testDecodeInvalidCodeThrows()
    {
        // Clear, then code 300 - not a literal, not Clear/EOD, not equal to
        // the current table size (258, so not a valid KwKwK self-reference
        // either) - there is no rule under which this code is defined.
        $this->expectException(Exception::class);

        $encoded = $this->packCodes([256, 300], 9);
        $filter  = new Lzw();

        $filter->decode($encoded);
    }

    public function testDecodeTerminatesCleanlyOnTruncatedStreamWithoutEod()
    {
        // No EOD code, and only enough trailing bits for a partial code -
        // readCode() must return null (rather than reading garbage) once it
        // runs out of full bytes, and decode() must return what it decoded
        // so far instead of throwing.
        $encoded = $this->packCodes([256, 65], 9); // Clear, 'A' - no EOD
        $filter  = new Lzw();

        $this->assertEquals('A', $filter->decode($encoded));
    }

}
