<?php

namespace Pop\Pdf\Test\Extract\Filter;

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Filter\Budget;
use Pop\Pdf\Extract\Filter\Registry;
use Pop\Pdf\Extract\Value\Name;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{

    public function testNoFilterReturnsDataUnchanged()
    {
        $this->assertEquals('raw data', Registry::decode('raw data', null));
    }

    public function testSingleFilter()
    {
        $original = 'some content';
        $result   = Registry::decode(gzcompress($original), new Name('FlateDecode'));
        $this->assertEquals($original, $result);
    }

    public function testChainedFilters()
    {
        $original  = 'chained content';
        $hexEncoded = bin2hex($original) . '>';
        $flateThenHex = gzcompress($hexEncoded);

        $result = Registry::decode($flateThenHex, [new Name('FlateDecode'), new Name('ASCIIHexDecode')]);
        $this->assertEquals($original, $result);
    }

    public function testUnsupportedFilterThrows()
    {
        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        Registry::decode('data', new Name('DCTDecode'));
    }

    public function testResolvesAscii85Filter()
    {
        $result = Registry::decode('<~9jqo^~>', new Name('ASCII85Decode'));
        $this->assertEquals('Man ', $result);
    }

    public function testResolvesRunLengthFilter()
    {
        // RunLength: length byte 2 (means copy 3 literal bytes), then those
        // 3 bytes, then 128 (EOD marker).
        $encoded = chr(2) . 'abc' . chr(128);
        $result  = Registry::decode($encoded, new Name('RunLengthDecode'));
        $this->assertEquals('abc', $result);
    }

    public function testResolvesLzwFilter()
    {
        // Clear(256), 'A'(65), EOD(257) packed as 9-bit codes.
        $bits = str_pad(decbin(256), 9, '0', STR_PAD_LEFT)
              . str_pad(decbin(65), 9, '0', STR_PAD_LEFT)
              . str_pad(decbin(257), 9, '0', STR_PAD_LEFT);
        $bits = str_pad($bits, (int) (ceil(strlen($bits) / 8) * 8), '0');

        $encoded = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $encoded .= chr(bindec(substr($bits, $i, 8)));
        }

        $result = Registry::decode($encoded, new Name('LZWDecode'));
        $this->assertEquals('A', $result);
    }

    public function testNullBudgetIsUnbounded()
    {
        $original = 'some content';
        $result   = Registry::decode(gzcompress($original), new Name('FlateDecode'), null, null);

        $this->assertEquals($original, $result);
    }

    public function testBudgetChargedPerFilterStage()
    {
        // The intermediate (post-Flate, pre-AsciiHex) output is larger than
        // the final decoded output - a budget sized between the two proves
        // charging happens after EACH stage, not just once at the end.
        $this->expectException(Exception::class);

        $original     = str_repeat('A', 1000);
        $hexEncoded   = bin2hex($original) . '>'; // 2001 bytes - the intermediate stage
        $flateThenHex = gzcompress($hexEncoded);
        $budget       = new Budget(1500); // exceeds the final 1000 bytes, not the intermediate 2001

        Registry::decode($flateThenHex, [new Name('FlateDecode'), new Name('ASCIIHexDecode')], null, $budget);
    }

    public function testSufficientBudgetSucceeds()
    {
        $original     = str_repeat('A', 1000);
        $hexEncoded   = bin2hex($original) . '>';
        $flateThenHex = gzcompress($hexEncoded);
        $budget       = new Budget(10000);

        $result = Registry::decode($flateThenHex, [new Name('FlateDecode'), new Name('ASCIIHexDecode')], null, $budget);

        $this->assertEquals($original, $result);
    }

    public function testNullFilterIsChargedAgainstBudget()
    {
        // An unfiltered stream (no /Filter at all) is still
        // attacker-controlled size that must count toward the aggregate
        // budget - omitting /Filter must not be a free way to bypass it.
        $this->expectException(Exception::class);

        $budget = new Budget(100);
        Registry::decode(str_repeat('A', 1000), null, null, $budget);
    }

    public function testNullFilterUnderBudgetSucceeds()
    {
        $budget = new Budget(1000);
        $result = Registry::decode('raw data', null, null, $budget);

        $this->assertEquals('raw data', $result);
    }

    public function testExhaustedBudgetFailsFastWithoutDecoding()
    {
        // Once a budget is already exhausted (negative remaining, from an
        // earlier charge), a subsequent decode must fail before doing any
        // filter work - not pay the full decode cost first and throw only
        // afterward, which a caller that loops (many /Contents refs, many
        // Do operators) would turn back into unbounded work.
        $budget = new Budget(10);
        try {
            $budget->charge(1000);
        } catch (Exception $e) {
            // expected - budget is now exhausted (negative remaining)
        }

        $bigChunk   = str_repeat('A', 60 * 1024 * 1024);
        $compressed = gzcompress($bigChunk, 1);
        unset($bigChunk);

        $start = microtime(true);
        try {
            Registry::decode($compressed, new Name('FlateDecode'), null, $budget);
            $this->fail('Expected an exception for an already-exhausted budget.');
        } catch (Exception $e) {
            $elapsed = microtime(true) - $start;
            // A real 60MB gzuncompress takes well over 0.1s; failing fast
            // must be far faster than that.
            $this->assertLessThan(0.05, $elapsed);
        }
    }

}
