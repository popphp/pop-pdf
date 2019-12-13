<?php

namespace Pop\Pdf\Test\Build\Font;

use Pop\Pdf\Build\Font\Data;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{

    public function testData()
    {
        $fontData = new Data([
            'foo' => 1,
            'bar' => 2,
            'baz' => 3
        ]);

        $this->assertEquals(3, $fontData->count());
        $this->assertEquals(3, count($fontData));
        $this->assertIsArray($fontData->toArray());

        $string = '';
        foreach ($fontData as $data) {
            $string .= $data;
        }

        $this->assertEquals('123', $string);
        unset($fontData['baz']);
        unset($fontData->bar);

        $this->assertEquals(1, $fontData->count());
        $this->assertEquals(1, count($fontData));
    }
}