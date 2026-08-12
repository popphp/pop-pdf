<?php

namespace Pop\Pdf\Test\Extract\Content;

use Pop\Pdf\Extract\Content\PageInfo;
use PHPUnit\Framework\TestCase;

class PageInfoTest extends TestCase
{

    public function testConstructor()
    {
        $page      = ['Type' => 'Page'];
        $resources = ['Font' => []];
        $info      = new PageInfo($page, $resources, [0, 0, 612, 792], 0, 'BT ET');

        $this->assertSame($page, $info->page);
        $this->assertSame($resources, $info->resources);
        $this->assertEquals([0, 0, 612, 792], $info->mediaBox);
        $this->assertEquals(0, $info->rotate);
        $this->assertEquals('BT ET', $info->content);
    }

}
