<?php

namespace Pop\Pdf\Test\Build\Font\TrueType\Table\Cmap;

use Pop\Pdf\Build\Font\TrueType\Table\Cmap\SegmentToDelta;
use PHPUnit\Framework\TestCase;

class SegmentToDeltaTest extends TestCase
{

    /**
     * Internally, SegmentToDelta::parseData() only ever calls shiftToSigned()
     * with a scalar. The array branch is public API surface reachable by
     * calling it directly with a batch of values.
     */
    public function testShiftToSignedArrayBranch()
    {
        $result = SegmentToDelta::shiftToSigned([30000, 40000, 1000]);

        $this->assertEquals([
            30000,
            40000 - 65536,
            1000,
        ], $result);
    }

    public function testShiftToSignedScalarBranch()
    {
        $this->assertEquals(40000 - 65536, SegmentToDelta::shiftToSigned(40000));
        $this->assertEquals(1000, SegmentToDelta::shiftToSigned(1000));
    }

}
