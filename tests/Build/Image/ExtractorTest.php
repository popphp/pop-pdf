<?php

namespace Pop\Pdf\Test\Build\Image;

use Pop\Pdf\Build\Image\Extractor;
use Pop\Pdf\Build\Exception;
use PHPUnit\Framework\TestCase;

class ExtractorTest extends TestCase
{

    protected string $location;

    protected function setUp(): void
    {
        if (!class_exists('Imagick', false)) {
            $this->markTestSkipped('The Imagick extension is not available.');
        }

        $this->location = sys_get_temp_dir() . '/pop-pdf-extractor-test-' . uniqid();
        mkdir($this->location);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->location)) {
            foreach (glob($this->location . '/*') as $file) {
                unlink($file);
            }
            rmdir($this->location);
        }
    }

    public function testCountPagesReturnsTotalPageCount()
    {
        $extractor = new Extractor();
        $this->assertEquals(3, $extractor->countPages(__DIR__ . '/../../tmp/image-only-3page.pdf'));
    }

    public function testCountPagesThrowsForMissingFile()
    {
        $this->expectException(Exception::class);
        (new Extractor())->countPages(__DIR__ . '/../../tmp/does-not-exist.pdf');
    }

    public function testExtractWritesOneImageFilePerRequestedPage()
    {
        $extractor = new Extractor();
        $result    = $extractor->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location, 'jpg', 72, [1, 2, 3]);

        $this->assertCount(3, $result);
        foreach ($result as $path) {
            $this->assertFileExists($path);
            $this->assertStringEndsWith('.jpg', $path);
        }
    }

    public function testExtractFilenamesAreZeroPaddedWithoutThePageWord()
    {
        $extractor = new Extractor();
        $result    = $extractor->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location, 'jpg', 72, [1, 2, 3]);

        $this->assertEquals('image-only-3page-01.jpg', basename($result[1]));
        $this->assertEquals('image-only-3page-02.jpg', basename($result[2]));
        $this->assertEquals('image-only-3page-03.jpg', basename($result[3]));
    }

    public function testExtractOnlyWritesRequestedPages()
    {
        $extractor = new Extractor();
        $result    = $extractor->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location, 'png', 72, [2]);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertFileExists($result[2]);
    }

    public function testExtractWritesValidPngBytes()
    {
        $extractor = new Extractor();
        $result    = $extractor->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location, 'png', 72, [1]);

        $bytes = file_get_contents($result[1]);
        $this->assertStringStartsWith("\x89PNG", $bytes);
    }

    public function testExtractWritesValidWebpBytes()
    {
        $extractor = new Extractor();
        $result    = $extractor->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location, 'webp', 72, [1]);

        $bytes = file_get_contents($result[1]);
        $this->assertStringStartsWith('RIFF', $bytes);
        $this->assertStringContainsString('WEBP', substr($bytes, 0, 12));
    }

    public function testExtractWritesValidTiffBytes()
    {
        $extractor = new Extractor();
        $result    = $extractor->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location, 'tiff', 72, [1]);

        $bytes = file_get_contents($result[1]);
        // TIFF starts with either little-endian "II" or big-endian "MM" byte order marker
        $this->assertTrue(str_starts_with($bytes, "II") || str_starts_with($bytes, "MM"));
    }

    public function testExtractSupportsTifAsTiffAlias()
    {
        $extractor = new Extractor();
        $result    = $extractor->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location, 'tif', 72, [1]);

        $bytes = file_get_contents($result[1]);
        $this->assertStringEndsWith('.tif', $result[1]);
        $this->assertTrue(str_starts_with($bytes, "II") || str_starts_with($bytes, "MM"));
    }

    public function testExtractHigherResolutionProducesLargerPixelDimensions()
    {
        $extractor = new Extractor();

        $low      = $extractor->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location, 'png', 72, [1]);
        $lowImage = new \Imagick($low[1]);
        $lowWidth = $lowImage->getImageWidth();
        $lowImage->clear();
        unlink($low[1]);

        $high      = $extractor->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location, 'png', 144, [1]);
        $highImage = new \Imagick($high[1]);
        $highWidth = $highImage->getImageWidth();
        $highImage->clear();

        $this->assertGreaterThan($lowWidth, $highWidth);
    }

    public function testExtractThrowsForUnsupportedFormat()
    {
        $this->expectException(Exception::class);
        (new Extractor())->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location, 'bmp', 72, [1]);
    }

    public function testExtractThrowsForMissingSourceFile()
    {
        $this->expectException(Exception::class);
        (new Extractor())->extract(__DIR__ . '/../../tmp/does-not-exist.pdf', $this->location, 'jpg', 72, [1]);
    }

    public function testExtractThrowsForNonExistentLocation()
    {
        $this->expectException(Exception::class);
        (new Extractor())->extract(__DIR__ . '/../../tmp/image-only-3page.pdf', $this->location . '/nope', 'jpg', 72, [1]);
    }

}
