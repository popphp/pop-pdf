<?php

namespace Pop\Pdf\Test\Build\Font;

use Pop\Pdf\Build\Font\Type1;
use PHPUnit\Framework\TestCase;

class Type1Test extends TestCase
{

    protected string $scratchDir;

    protected function setUp(): void
    {
        $this->scratchDir = sys_get_temp_dir() . '/pop-pdf-type1-test-' . uniqid();
        mkdir($this->scratchDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->scratchDir . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->scratchDir);
    }

    /**
     * None of the checked-in .pfb/.afm fixtures declare a non-zero italic
     * angle, a fixed-pitch flag, or ForceBold, so those branches in
     * Type1::parsePfb()/parseAfm() are never hit. The dict/AFM fields are
     * plain text read via string search (not fixed binary offsets), so a
     * copy with those fields text-replaced parses identically otherwise.
     *
     * PFB segment 1 (the ASCII cleartext dict) is framed by a 6-byte header
     * - marker, type, little-endian byte length - that Type1::parsePfb()
     * trusts literally to know how many bytes to read. The text patches below
     * change that segment's length, so the header's declared length is
     * rewritten to match; otherwise the reader stays misaligned for every
     * byte after the patch and misreads segment 2's header out of what's now
     * the wrong offset.
     */
    protected function patchedPfbPath(): string
    {
        $data = file_get_contents(__DIR__ . '/../../tmp/fonts/s050000l.pfb');

        $segment1Header = unpack('Cmarker/Ctype/Vsize', substr($data, 0, 6));
        $segment1       = substr($data, 6, $segment1Header['size']);
        $rest           = substr($data, 6 + $segment1Header['size']);

        $segment1 = str_replace('/ItalicAngle 0.0 def', '/ItalicAngle -12.0 def', $segment1);
        $segment1 = str_replace('/isFixedPitch false def', '/isFixedPitch true def', $segment1);
        $segment1 = str_replace('/FontName /StandardSymL def', "/ForceBold true def\n/FontName /StandardSymL def", $segment1);

        $newSegment1Header = pack('CCV', $segment1Header['marker'], $segment1Header['type'], strlen($segment1));
        $data              = $newSegment1Header . $segment1 . $rest;

        $path = $this->scratchDir . '/patched.pfb';
        file_put_contents($path, $data);
        return $path;
    }

    protected function patchedAfmPath(): string
    {
        $data = file_get_contents(__DIR__ . '/../../tmp/fonts/s050000l.afm');
        $data = str_replace('ItalicAngle 0.0', 'ItalicAngle -12.0', $data);
        $data = str_replace('IsFixedPitch false', 'IsFixedPitch true', $data);

        $path = $this->scratchDir . '/patched.afm';
        file_put_contents($path, $data);
        return $path;
    }

    public function testParsePfbItalicFixedPitchAndForceBoldFlags()
    {
        $font = new Type1($this->patchedPfbPath());

        $this->assertEquals(-12.0, $font->italicAngle);
        $this->assertTrue($font->flags->isItalic);
        $this->assertTrue($font->flags->isFixedPitch);
        $this->assertTrue($font->flags->isForceBold);
    }

    public function testParseAfmItalicAndFixedPitchFlags()
    {
        $font = new Type1($this->patchedAfmPath());

        $this->assertEquals(-12.0, $font->italicAngle);
        $this->assertTrue($font->flags->isItalic);
        $this->assertTrue($font->flags->isFixedPitch);
    }

    /**
     * s050000l.afm/.pfb are a genuine matching pair already sitting side by
     * side in the fixtures directory. Constructing directly from the .afm
     * exercises the branch where the same-case .pfb pair is found without
     * needing the uppercase fallback.
     */
    public function testConstructorFindsLowercasePfbPair()
    {
        $font = new Type1(__DIR__ . '/../../tmp/fonts/s050000l.afm');

        $this->assertEquals(realpath(__DIR__ . '/../../tmp/fonts/s050000l.pfb'), $font->pfbPath);
    }

    /**
     * Constructing from a .pfb whose paired .afm doesn't exist in lowercase
     * but does in uppercase exercises the case-fallback branch.
     */
    public function testConstructorFindsUppercaseAfmPair()
    {
        copy(__DIR__ . '/../../tmp/fonts/s050000l.pfb', $this->scratchDir . '/upper.pfb');
        copy(__DIR__ . '/../../tmp/fonts/s050000l.afm', $this->scratchDir . '/upper.AFM');

        $font = new Type1($this->scratchDir . '/upper.pfb');

        $this->assertEquals($this->scratchDir . '/upper.AFM', $font->afmPath);
    }

    /**
     * Constructing from a .afm whose paired .pfb doesn't exist in lowercase
     * but does in uppercase exercises the case-fallback branch.
     */
    public function testConstructorFindsUppercasePfbPair()
    {
        copy(__DIR__ . '/../../tmp/fonts/s050000l.afm', $this->scratchDir . '/upper2.afm');
        copy(__DIR__ . '/../../tmp/fonts/s050000l.pfb', $this->scratchDir . '/upper2.PFB');

        $font = new Type1($this->scratchDir . '/upper2.afm');

        $this->assertEquals($this->scratchDir . '/upper2.PFB', $font->pfbPath);
    }

    public function testParsePfbThrowsWhenFileDoesNotExist()
    {
        $font   = new Type1(__DIR__ . '/../../tmp/fonts/s050000l.pfb');
        $method = new \ReflectionMethod($font, 'parsePfb');
        $method->setAccessible(true);

        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $this->expectExceptionMessage('Error: The PFB file does not exist.');
        $method->invoke($font, '/no/such/file.pfb');
    }

    /**
     * A PFB segment header's declared byte length is attacker/corruption
     * controlled - Type1::parsePfb() must not trust it past the actual
     * remaining file size, or a corrupt/malicious length (here ~1GB) drives
     * an unbounded fread() that exhausts memory instead of failing cleanly.
     */
    public function testParsePfbThrowsWhenSegment1LengthExceedsFileSize()
    {
        $font   = new Type1(__DIR__ . '/../../tmp/fonts/s050000l.pfb');
        $method = new \ReflectionMethod($font, 'parsePfb');
        $method->setAccessible(true);

        $path = $this->scratchDir . '/corrupt-segment1.pfb';
        file_put_contents($path, pack('CCV', 128, 1, 999999999) . 'not nearly that many bytes');

        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $this->expectExceptionMessage('Error: The PFB file is not in the correct format (segment 1 length is invalid).');
        $method->invoke($font, $path);
    }

    public function testParsePfbThrowsWhenSegment2LengthExceedsFileSize()
    {
        $font   = new Type1(__DIR__ . '/../../tmp/fonts/s050000l.pfb');
        $method = new \ReflectionMethod($font, 'parsePfb');
        $method->setAccessible(true);

        $segment1 = 'FontDirectory currentdict end';
        $content  = pack('CCV', 128, 1, strlen($segment1)) . $segment1
            . pack('CCV', 128, 2, 999999999) . 'short';

        $path = $this->scratchDir . '/corrupt-segment2.pfb';
        file_put_contents($path, $content);

        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $this->expectExceptionMessage('Error: The PFB file is not in the correct format (segment 2 length is invalid).');
        $method->invoke($font, $path);
    }

    public function testParseAfmThrowsWhenFileDoesNotExist()
    {
        $font   = new Type1(__DIR__ . '/../../tmp/fonts/s050000l.pfb');
        $method = new \ReflectionMethod($font, 'parseAfm');
        $method->setAccessible(true);

        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $this->expectExceptionMessage('Error: The AFM file does not exist.');
        $method->invoke($font, '/no/such/file.afm');
    }

}
