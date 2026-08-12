<?php

namespace Pop\Pdf\Test\Extract;

use Pop\Pdf\Extract\Repair;
use PHPUnit\Framework\TestCase;

class RepairTest extends TestCase
{

    public function testScanFindsAllObjectsAndTrailer()
    {
        $data = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
            "2 0 obj\n<< /Type /Pages /Count 0 >>\nendobj\n" .
            "trailer\n<< /Size 3 /Root 1 0 R >>";

        $result = Repair::scan($data);

        $this->assertArrayHasKey(1, $result['offsets']);
        $this->assertArrayHasKey(2, $result['offsets']);
        $this->assertEquals(3, $result['trailer']['Size']);
        $this->assertInstanceOf(\Pop\Pdf\Extract\Value\Reference::class, $result['trailer']['Root']);
    }

    public function testScanWithoutTrailerReturnsEmptyTrailer()
    {
        $data = "1 0 obj\n<< /Type /Catalog >>\nendobj\n";
        $result = Repair::scan($data);

        $this->assertArrayHasKey(1, $result['offsets']);
        $this->assertEquals([], $result['trailer']);
    }

    public function testLaterObjectSupersedesEarlierDuplicate()
    {
        $data = "1 0 obj\n<< /Old true >>\nendobj\n" .
            "1 0 obj\n<< /New true >>\nendobj\n";

        $result = Repair::scan($data);

        // The later occurrence's offset should win (incremental update semantics).
        $this->assertGreaterThan(10, $result['offsets'][1]['offset']);
    }

    public function testScanWithMalformedTrailerDegradesGracefully()
    {
        $data = "1 0 obj\n<< /Type /Catalog >>\nendobj\n" .
            "trailer\n<< /Size 3 /Root 1 0 R";

        $result = Repair::scan($data);

        $this->assertArrayHasKey(1, $result['offsets']);
        $this->assertEquals([], $result['trailer']);
    }

}
