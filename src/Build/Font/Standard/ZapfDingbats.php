<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Font\Standard;


/**
 * Pdf standard ZapfDingbats font class
 *
 * The values in this class have been forked from ZendPdf's values in the repository's standard font classes:
 * https://github.com/zendframework/ZendPdf/tree/master/library/ZendPdf/Resource/Font/Simple/Standard
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.0
 */
class ZapfDingbats extends AbstractStandard
{

    /**
     * Font units per em
     * @var int
     */
    protected int $unitsPerEm = 1000;

    /**
     * Font glyph widths
     * @var array
     */
    protected array $glyphWidths = [
        0x00 => 0x01f4,   0x01 => 0x0116,   0x02 => 0x03ce,   0x03 => 0x03c1,
        0x04 => 0x03ce,   0x05 => 0x03d4,   0x06 => 0x02cf,   0x07 => 0x0315,
        0x08 => 0x0316,   0x09 => 0x0317,   0x0a => 0x02b2,   0x0b => 0x03c0,
        0x0c => 0x03ab,   0x0d => 0x0225,   0x0e => 0x0357,   0x0f => 0x038f,
        0x10 => 0x03a5,   0x11 => 0x038f,   0x12 => 0x03b1,   0x13 => 0x03ce,
        0x14 => 0x02f3,   0x15 => 0x034e,   0x16 => 0x02fa,   0x17 => 0x02f9,
        0x18 => 0x023b,   0x19 => 0x02a5,   0x1a => 0x02fb,   0x1b => 0x02f8,
        0x1c => 0x02f7,   0x1d => 0x02f2,   0x1e => 0x01ee,   0x1f => 0x0228,
        0x20 => 0x0219,   0x21 => 0x0241,   0x22 => 0x02b4,   0x23 => 0x0312,
        0x24 => 0x0314,   0x25 => 0x0314,   0x26 => 0x0316,   0x27 => 0x0319,
        0x28 => 0x031a,   0x29 => 0x0330,   0x2a => 0x0337,   0x2b => 0x0315,
        0x2c => 0x0349,   0x2d => 0x0337,   0x2e => 0x0341,   0x2f => 0x0330,
        0x30 => 0x033f,   0x31 => 0x039b,   0x32 => 0x02e8,   0x33 => 0x02d3,
        0x34 => 0x02ed,   0x35 => 0x0316,   0x36 => 0x0318,   0x37 => 0x02b7,
        0x38 => 0x0308,   0x39 => 0x0300,   0x3a => 0x0318,   0x3b => 0x02f7,
        0x3c => 0x02c3,   0x3d => 0x02c4,   0x3e => 0x02aa,   0x3f => 0x02bd,
        0x40 => 0x033a,   0x41 => 0x032f,   0x42 => 0x0315,   0x43 => 0x0315,
        0x44 => 0x02c3,   0x45 => 0x02af,   0x46 => 0x02b8,   0x47 => 0x02b1,
        0x48 => 0x0312,   0x49 => 0x0313,   0x4a => 0x02c9,   0x4b => 0x0317,
        0x4c => 0x0311,   0x4d => 0x0317,   0x4e => 0x0369,   0x4f => 0x02f9,
        0x50 => 0x02fa,   0x51 => 0x02fa,   0x52 => 0x02f7,   0x53 => 0x02f7,
        0x54 => 0x037c,   0x55 => 0x037c,   0x56 => 0x0314,   0x57 => 0x0310,
        0x58 => 0x01b6,   0x59 =>   0x8a,   0x5a => 0x0115,   0x5b => 0x019f,
        0x5c => 0x0188,   0x5d => 0x0188,   0x5e => 0x029c,   0x5f => 0x029c,
        0x60 => 0x0186,   0x61 => 0x0186,   0x62 => 0x013d,   0x63 => 0x013d,
        0x64 => 0x0114,   0x65 => 0x0114,   0x66 => 0x01fd,   0x67 => 0x01fd,
        0x68 => 0x019a,   0x69 => 0x019a,   0x6a =>   0xea,   0x6b =>   0xea,
        0x6c => 0x014e,   0x6d => 0x014e,   0x6e => 0x02dc,   0x6f => 0x0220,
        0x70 => 0x0220,   0x71 => 0x038e,   0x72 => 0x029b,   0x73 => 0x02f8,
        0x74 => 0x02f8,   0x75 => 0x0308,   0x76 => 0x0253,   0x77 => 0x02b6,
        0x78 => 0x0272,   0x79 => 0x0314,   0x7a => 0x0314,   0x7b => 0x0314,
        0x7c => 0x0314,   0x7d => 0x0314,   0x7e => 0x0314,   0x7f => 0x0314,
        0x80 => 0x0314,   0x81 => 0x0314,   0x82 => 0x0314,   0x83 => 0x0314,
        0x84 => 0x0314,   0x85 => 0x0314,   0x86 => 0x0314,   0x87 => 0x0314,
        0x88 => 0x0314,   0x89 => 0x0314,   0x8a => 0x0314,   0x8b => 0x0314,
        0x8c => 0x0314,   0x8d => 0x0314,   0x8e => 0x0314,   0x8f => 0x0314,
        0x90 => 0x0314,   0x91 => 0x0314,   0x92 => 0x0314,   0x93 => 0x0314,
        0x94 => 0x0314,   0x95 => 0x0314,   0x96 => 0x0314,   0x97 => 0x0314,
        0x98 => 0x0314,   0x99 => 0x0314,   0x9a => 0x0314,   0x9b => 0x0314,
        0x9c => 0x0314,   0x9d => 0x0314,   0x9e => 0x0314,   0x9f => 0x0314,
        0xa0 => 0x0314,   0xa1 => 0x037e,   0xa2 => 0x0346,   0xa3 => 0x03f8,
        0xa4 => 0x01ca,   0xa5 => 0x02ec,   0xa6 => 0x039c,   0xa7 => 0x02ec,
        0xa8 => 0x0396,   0xa9 => 0x039f,   0xaa => 0x03a0,   0xab => 0x03a0,
        0xac => 0x0342,   0xad => 0x0369,   0xae => 0x033c,   0xaf => 0x039c,
        0xb0 => 0x039c,   0xb1 => 0x0395,   0xb2 => 0x03a2,   0xb3 => 0x03a3,
        0xb4 => 0x01cf,   0xb5 => 0x0373,   0xb6 => 0x0344,   0xb7 => 0x0344,
        0xb8 => 0x0363,   0xb9 => 0x0363,   0xba => 0x02b8,   0xbb => 0x02b8,
        0xbc => 0x036a,   0xbd => 0x036a,   0xbe => 0x02f8,   0xbf => 0x03b2,
        0xc0 => 0x0303,   0xc1 => 0x0361,   0xc2 => 0x0303,   0xc3 => 0x0378,
        0xc4 => 0x03c7,   0xc5 => 0x0378,   0xc6 => 0x033f,   0xc7 => 0x0369,
        0xc8 => 0x039f,   0xc9 => 0x03ca,   0xca => 0x0396
    ];

    /**
     * Font character map
     * @var array
     */
    protected array $cmap = [
        0x20 =>   0x01, 0x2701 =>   0x02, 0x2702 =>   0x03, 0x2703 =>   0x04,
        0x2704 =>   0x05, 0x260e =>   0x06, 0x2706 =>   0x07, 0x2707 =>   0x08,
        0x2708 =>   0x09, 0x2709 =>   0x0a, 0x261b =>   0x0b, 0x261e =>   0x0c,
        0x270c =>   0x0d, 0x270d =>   0x0e, 0x270e =>   0x0f, 0x270f =>   0x10,
        0x2710 =>   0x11, 0x2711 =>   0x12, 0x2712 =>   0x13, 0x2713 =>   0x14,
        0x2714 =>   0x15, 0x2715 =>   0x16, 0x2716 =>   0x17, 0x2717 =>   0x18,
        0x2718 =>   0x19, 0x2719 =>   0x1a, 0x271a =>   0x1b, 0x271b =>   0x1c,
        0x271c =>   0x1d, 0x271d =>   0x1e, 0x271e =>   0x1f, 0x271f =>   0x20,
        0x2720 =>   0x21, 0x2721 =>   0x22, 0x2722 =>   0x23, 0x2723 =>   0x24,
        0x2724 =>   0x25, 0x2725 =>   0x26, 0x2726 =>   0x27, 0x2727 =>   0x28,
        0x2605 =>   0x29, 0x2729 =>   0x2a, 0x272a =>   0x2b, 0x272b =>   0x2c,
        0x272c =>   0x2d, 0x272d =>   0x2e, 0x272e =>   0x2f, 0x272f =>   0x30,
        0x2730 =>   0x31, 0x2731 =>   0x32, 0x2732 =>   0x33, 0x2733 =>   0x34,
        0x2734 =>   0x35, 0x2735 =>   0x36, 0x2736 =>   0x37, 0x2737 =>   0x38,
        0x2738 =>   0x39, 0x2739 =>   0x3a, 0x273a =>   0x3b, 0x273b =>   0x3c,
        0x273c =>   0x3d, 0x273d =>   0x3e, 0x273e =>   0x3f, 0x273f =>   0x40,
        0x2740 =>   0x41, 0x2741 =>   0x42, 0x2742 =>   0x43, 0x2743 =>   0x44,
        0x2744 =>   0x45, 0x2745 =>   0x46, 0x2746 =>   0x47, 0x2747 =>   0x48,
        0x2748 =>   0x49, 0x2749 =>   0x4a, 0x274a =>   0x4b, 0x274b =>   0x4c,
        0x25cf =>   0x4d, 0x274d =>   0x4e, 0x25a0 =>   0x4f, 0x274f =>   0x50,
        0x2750 =>   0x51, 0x2751 =>   0x52, 0x2752 =>   0x53, 0x25b2 =>   0x54,
        0x25bc =>   0x55, 0x25c6 =>   0x56, 0x2756 =>   0x57, 0x25d7 =>   0x58,
        0x2758 =>   0x59, 0x2759 =>   0x5a, 0x275a =>   0x5b, 0x275b =>   0x5c,
        0x275c =>   0x5d, 0x275d =>   0x5e, 0x275e =>   0x5f, 0x2768 =>   0x60,
        0x2769 =>   0x61, 0x276a =>   0x62, 0x276b =>   0x63, 0x276c =>   0x64,
        0x276d =>   0x65, 0x276e =>   0x66, 0x276f =>   0x67, 0x2770 =>   0x68,
        0x2771 =>   0x69, 0x2772 =>   0x6a, 0x2773 =>   0x6b, 0x2774 =>   0x6c,
        0x2775 =>   0x6d, 0x2761 =>   0x6e, 0x2762 =>   0x6f, 0x2763 =>   0x70,
        0x2764 =>   0x71, 0x2765 =>   0x72, 0x2766 =>   0x73, 0x2767 =>   0x74,
        0x2663 =>   0x75, 0x2666 =>   0x76, 0x2665 =>   0x77, 0x2660 =>   0x78,
        0x2460 =>   0x79, 0x2461 =>   0x7a, 0x2462 =>   0x7b, 0x2463 =>   0x7c,
        0x2464 =>   0x7d, 0x2465 =>   0x7e, 0x2466 =>   0x7f, 0x2467 =>   0x80,
        0x2468 =>   0x81, 0x2469 =>   0x82, 0x2776 =>   0x83, 0x2777 =>   0x84,
        0x2778 =>   0x85, 0x2779 =>   0x86, 0x277a =>   0x87, 0x277b =>   0x88,
        0x277c =>   0x89, 0x277d =>   0x8a, 0x277e =>   0x8b, 0x277f =>   0x8c,
        0x2780 =>   0x8d, 0x2781 =>   0x8e, 0x2782 =>   0x8f, 0x2783 =>   0x90,
        0x2784 =>   0x91, 0x2785 =>   0x92, 0x2786 =>   0x93, 0x2787 =>   0x94,
        0x2788 =>   0x95, 0x2789 =>   0x96, 0x278a =>   0x97, 0x278b =>   0x98,
        0x278c =>   0x99, 0x278d =>   0x9a, 0x278e =>   0x9b, 0x278f =>   0x9c,
        0x2790 =>   0x9d, 0x2791 =>   0x9e, 0x2792 =>   0x9f, 0x2793 =>   0xa0,
        0x2794 =>   0xa1, 0x2192 =>   0xa2, 0x2194 =>   0xa3, 0x2195 =>   0xa4,
        0x2798 =>   0xa5, 0x2799 =>   0xa6, 0x279a =>   0xa7, 0x279b =>   0xa8,
        0x279c =>   0xa9, 0x279d =>   0xaa, 0x279e =>   0xab, 0x279f =>   0xac,
        0x27a0 =>   0xad, 0x27a1 =>   0xae, 0x27a2 =>   0xaf, 0x27a3 =>   0xb0,
        0x27a4 =>   0xb1, 0x27a5 =>   0xb2, 0x27a6 =>   0xb3, 0x27a7 =>   0xb4,
        0x27a8 =>   0xb5, 0x27a9 =>   0xb6, 0x27aa =>   0xb7, 0x27ab =>   0xb8,
        0x27ac =>   0xb9, 0x27ad =>   0xba, 0x27ae =>   0xbb, 0x27af =>   0xbc,
        0x27b1 =>   0xbd, 0x27b2 =>   0xbe, 0x27b3 =>   0xbf, 0x27b4 =>   0xc0,
        0x27b5 =>   0xc1, 0x27b6 =>   0xc2, 0x27b7 =>   0xc3, 0x27b8 =>   0xc4,
        0x27b9 =>   0xc5, 0x27ba =>   0xc6, 0x27bb =>   0xc7, 0x27bc =>   0xc8,
        0x27bd =>   0xc9, 0x27be =>   0xca
    ];

}
