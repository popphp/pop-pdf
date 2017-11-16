<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2017 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Font\Standard;


/**
 * Pdf standard Symbol font class
 *
 * The values in this class have been forked from ZendPdf's values in the repository's standard font classes:
 * https://github.com/zendframework/ZendPdf/tree/master/library/ZendPdf/Resource/Font/Simple/Standard
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2017 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.1.0
 */
class Symbol extends AbstractStandard
{

    /**
     * Font units per em
     * @var int
     */
    protected $unitsPerEm = 1000;

    /**
     * Font glyph widths
     * @var array
     */
    protected $glyphWidths = [
        0x00 => 0x01f4,   0x01 =>   0xfa,   0x02 => 0x014d,   0x03 => 0x02c9,
        0x04 => 0x01f4,   0x05 => 0x0225,   0x06 => 0x0341,   0x07 => 0x030a,
        0x08 => 0x01b7,   0x09 => 0x014d,   0x0a => 0x014d,   0x0b => 0x01f4,
        0x0c => 0x0225,   0x0d =>   0xfa,   0x0e => 0x0225,   0x0f =>   0xfa,
        0x10 => 0x0116,   0x11 => 0x01f4,   0x12 => 0x01f4,   0x13 => 0x01f4,
        0x14 => 0x01f4,   0x15 => 0x01f4,   0x16 => 0x01f4,   0x17 => 0x01f4,
        0x18 => 0x01f4,   0x19 => 0x01f4,   0x1a => 0x01f4,   0x1b => 0x0116,
        0x1c => 0x0116,   0x1d => 0x0225,   0x1e => 0x0225,   0x1f => 0x0225,
        0x20 => 0x01bc,   0x21 => 0x0225,   0x22 => 0x02d2,   0x23 => 0x029b,
        0x24 => 0x02d2,   0x25 => 0x0264,   0x26 => 0x0263,   0x27 => 0x02fb,
        0x28 => 0x025b,   0x29 => 0x02d2,   0x2a => 0x014d,   0x2b => 0x0277,
        0x2c => 0x02d2,   0x2d => 0x02ae,   0x2e => 0x0379,   0x2f => 0x02d2,
        0x30 => 0x02d2,   0x31 => 0x0300,   0x32 => 0x02e5,   0x33 => 0x022c,
        0x34 => 0x0250,   0x35 => 0x0263,   0x36 => 0x02b2,   0x37 => 0x01b7,
        0x38 => 0x0300,   0x39 => 0x0285,   0x3a => 0x031b,   0x3b => 0x0263,
        0x3c => 0x014d,   0x3d => 0x035f,   0x3e => 0x014d,   0x3f => 0x0292,
        0x40 => 0x01f4,   0x41 => 0x01f4,   0x42 => 0x0277,   0x43 => 0x0225,
        0x44 => 0x0225,   0x45 => 0x01ee,   0x46 => 0x01b7,   0x47 => 0x0209,
        0x48 => 0x019b,   0x49 => 0x025b,   0x4a => 0x0149,   0x4b => 0x025b,
        0x4c => 0x0225,   0x4d => 0x0225,   0x4e => 0x0240,   0x4f => 0x0209,
        0x50 => 0x0225,   0x51 => 0x0225,   0x52 => 0x0209,   0x53 => 0x0225,
        0x54 => 0x025b,   0x55 => 0x01b7,   0x56 => 0x0240,   0x57 => 0x02c9,
        0x58 => 0x02ae,   0x59 => 0x01ed,   0x5a => 0x02ae,   0x5b => 0x01ee,
        0x5c => 0x01e0,   0x5d =>   0xc8,   0x5e => 0x01e0,   0x5f => 0x0225,
        0x60 => 0x02ee,   0x61 => 0x026c,   0x62 =>   0xf7,   0x63 => 0x0225,
        0x64 =>   0xa7,   0x65 => 0x02c9,   0x66 => 0x01f4,   0x67 => 0x02f1,
        0x68 => 0x02f1,   0x69 => 0x02f1,   0x6a => 0x02f1,   0x6b => 0x0412,
        0x6c => 0x03db,   0x6d => 0x025b,   0x6e => 0x03db,   0x6f => 0x025b,
        0x70 => 0x0190,   0x71 => 0x0225,   0x72 => 0x019b,   0x73 => 0x0225,
        0x74 => 0x0225,   0x75 => 0x02c9,   0x76 => 0x01ee,   0x77 => 0x01cc,
        0x78 => 0x0225,   0x79 => 0x0225,   0x7a => 0x0225,   0x7b => 0x0225,
        0x7c => 0x03e8,   0x7d => 0x025b,   0x7e => 0x03e8,   0x7f => 0x0292,
        0x80 => 0x0337,   0x81 => 0x02ae,   0x82 => 0x031b,   0x83 => 0x03db,
        0x84 => 0x0300,   0x85 => 0x0300,   0x86 => 0x0337,   0x87 => 0x0300,
        0x88 => 0x0300,   0x89 => 0x02c9,   0x8a => 0x02c9,   0x8b => 0x02c9,
        0x8c => 0x02c9,   0x8d => 0x02c9,   0x8e => 0x02c9,   0x8f => 0x02c9,
        0x90 => 0x0300,   0x91 => 0x02c9,   0x92 => 0x0316,   0x93 => 0x0316,
        0x94 => 0x037a,   0x95 => 0x0337,   0x96 => 0x0225,   0x97 =>   0xfa,
        0x98 => 0x02c9,   0x99 => 0x025b,   0x9a => 0x025b,   0x9b => 0x0412,
        0x9c => 0x03db,   0x9d => 0x025b,   0x9e => 0x03db,   0x9f => 0x025b,
        0xa0 => 0x01ee,   0xa1 => 0x0149,   0xa2 => 0x0316,   0xa3 => 0x0316,
        0xa4 => 0x0312,   0xa5 => 0x02c9,   0xa6 => 0x0180,   0xa7 => 0x0180,
        0xa8 => 0x0180,   0xa9 => 0x0180,   0xaa => 0x0180,   0xab => 0x0180,
        0xac => 0x01ee,   0xad => 0x01ee,   0xae => 0x01ee,   0xaf => 0x01ee,
        0xb0 => 0x0149,   0xb1 => 0x0112,   0xb2 => 0x02ae,   0xb3 => 0x02ae,
        0xb4 => 0x02ae,   0xb5 => 0x0180,   0xb6 => 0x0180,   0xb7 => 0x0180,
        0xb8 => 0x0180,   0xb9 => 0x0180,   0xba => 0x0180,   0xbb => 0x01ee,
        0xbc => 0x01ee,   0xbd => 0x01ee,   0xbe => 0x0316
    ];

    /**
     * Font character map
     * @var array
     */
    protected $cmap = [
        0x20 =>   0x01,   0x21 =>   0x02, 0x2200 =>   0x03,   0x23 =>   0x04,
        0x2203 =>   0x05,   0x25 =>   0x06,   0x26 =>   0x07, 0x220b =>   0x08,
        0x28 =>   0x09,   0x29 =>   0x0a, 0x2217 =>   0x0b,   0x2b =>   0x0c,
        0x2c =>   0x0d, 0x2212 =>   0x0e,   0x2e =>   0x0f,   0x2f =>   0x10,
        0x30 =>   0x11,   0x31 =>   0x12,   0x32 =>   0x13,   0x33 =>   0x14,
        0x34 =>   0x15,   0x35 =>   0x16,   0x36 =>   0x17,   0x37 =>   0x18,
        0x38 =>   0x19,   0x39 =>   0x1a,   0x3a =>   0x1b,   0x3b =>   0x1c,
        0x3c =>   0x1d,   0x3d =>   0x1e,   0x3e =>   0x1f,   0x3f =>   0x20,
        0x2245 =>   0x21, 0x0391 =>   0x22, 0x0392 =>   0x23, 0x03a7 =>   0x24,
        0x2206 =>   0x25, 0x0395 =>   0x26, 0x03a6 =>   0x27, 0x0393 =>   0x28,
        0x0397 =>   0x29, 0x0399 =>   0x2a, 0x03d1 =>   0x2b, 0x039a =>   0x2c,
        0x039b =>   0x2d, 0x039c =>   0x2e, 0x039d =>   0x2f, 0x039f =>   0x30,
        0x03a0 =>   0x31, 0x0398 =>   0x32, 0x03a1 =>   0x33, 0x03a3 =>   0x34,
        0x03a4 =>   0x35, 0x03a5 =>   0x36, 0x03c2 =>   0x37, 0x2126 =>   0x38,
        0x039e =>   0x39, 0x03a8 =>   0x3a, 0x0396 =>   0x3b,   0x5b =>   0x3c,
        0x2234 =>   0x3d,   0x5d =>   0x3e, 0x22a5 =>   0x3f,   0x5f =>   0x40,
        0xf8e5 =>   0x41, 0x03b1 =>   0x42, 0x03b2 =>   0x43, 0x03c7 =>   0x44,
        0x03b4 =>   0x45, 0x03b5 =>   0x46, 0x03c6 =>   0x47, 0x03b3 =>   0x48,
        0x03b7 =>   0x49, 0x03b9 =>   0x4a, 0x03d5 =>   0x4b, 0x03ba =>   0x4c,
        0x03bb =>   0x4d,   0xb5 =>   0x4e, 0x03bd =>   0x4f, 0x03bf =>   0x50,
        0x03c0 =>   0x51, 0x03b8 =>   0x52, 0x03c1 =>   0x53, 0x03c3 =>   0x54,
        0x03c4 =>   0x55, 0x03c5 =>   0x56, 0x03d6 =>   0x57, 0x03c9 =>   0x58,
        0x03be =>   0x59, 0x03c8 =>   0x5a, 0x03b6 =>   0x5b,   0x7b =>   0x5c,
        0x7c =>   0x5d,   0x7d =>   0x5e, 0x223c =>   0x5f, 0x20ac =>   0x60,
        0x03d2 =>   0x61, 0x2032 =>   0x62, 0x2264 =>   0x63, 0x2044 =>   0x64,
        0x221e =>   0x65, 0x0192 =>   0x66, 0x2663 =>   0x67, 0x2666 =>   0x68,
        0x2665 =>   0x69, 0x2660 =>   0x6a, 0x2194 =>   0x6b, 0x2190 =>   0x6c,
        0x2191 =>   0x6d, 0x2192 =>   0x6e, 0x2193 =>   0x6f,   0xb0 =>   0x70,
        0xb1 =>   0x71, 0x2033 =>   0x72, 0x2265 =>   0x73,   0xd7 =>   0x74,
        0x221d =>   0x75, 0x2202 =>   0x76, 0x2022 =>   0x77,   0xf7 =>   0x78,
        0x2260 =>   0x79, 0x2261 =>   0x7a, 0x2248 =>   0x7b, 0x2026 =>   0x7c,
        0xf8e6 =>   0x7d, 0xf8e7 =>   0x7e, 0x21b5 =>   0x7f, 0x2135 =>   0x80,
        0x2111 =>   0x81, 0x211c =>   0x82, 0x2118 =>   0x83, 0x2297 =>   0x84,
        0x2295 =>   0x85, 0x2205 =>   0x86, 0x2229 =>   0x87, 0x222a =>   0x88,
        0x2283 =>   0x89, 0x2287 =>   0x8a, 0x2284 =>   0x8b, 0x2282 =>   0x8c,
        0x2286 =>   0x8d, 0x2208 =>   0x8e, 0x2209 =>   0x8f, 0x2220 =>   0x90,
        0x2207 =>   0x91, 0xf6da =>   0x92, 0xf6d9 =>   0x93, 0xf6db =>   0x94,
        0x220f =>   0x95, 0x221a =>   0x96, 0x22c5 =>   0x97,   0xac =>   0x98,
        0x2227 =>   0x99, 0x2228 =>   0x9a, 0x21d4 =>   0x9b, 0x21d0 =>   0x9c,
        0x21d1 =>   0x9d, 0x21d2 =>   0x9e, 0x21d3 =>   0x9f, 0x25ca =>   0xa0,
        0x2329 =>   0xa1, 0xf8e8 =>   0xa2, 0xf8e9 =>   0xa3, 0xf8ea =>   0xa4,
        0x2211 =>   0xa5, 0xf8eb =>   0xa6, 0xf8ec =>   0xa7, 0xf8ed =>   0xa8,
        0xf8ee =>   0xa9, 0xf8ef =>   0xaa, 0xf8f0 =>   0xab, 0xf8f1 =>   0xac,
        0xf8f2 =>   0xad, 0xf8f3 =>   0xae, 0xf8f4 =>   0xaf, 0x232a =>   0xb0,
        0x222b =>   0xb1, 0x2320 =>   0xb2, 0xf8f5 =>   0xb3, 0x2321 =>   0xb4,
        0xf8f6 =>   0xb5, 0xf8f7 =>   0xb6, 0xf8f8 =>   0xb7, 0xf8f9 =>   0xb8,
        0xf8fa =>   0xb9, 0xf8fb =>   0xba, 0xf8fc =>   0xbb, 0xf8fd =>   0xbc,
        0xf8fe =>   0xbd, 0xf8ff =>   0xbe
    ];

}