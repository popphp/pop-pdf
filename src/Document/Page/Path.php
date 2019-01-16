<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document\Page;

/**
 * Pdf page path class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Path
{

    /**
     * Style constants
     */
    const STROKE                     = 'S';
    const STROKE_CLOSE               = 's';
    const FILL                       = 'F';
    const FILL_EVEN_ODD              = 'f*';
    const FILL_STROKE                = 'B';
    const FILL_STROKE_EVEN_ODD       = 'B*';
    const FILL_STROKE_CLOSE          = 'b';
    const FILL_STROKE_CLOSE_EVEN_ODD = 'b*';
    const CLIPPING                   = 'W';
    const CLIPPING_FILL              = 'W F';
    const CLIPPING_NO_STYLE          = 'W n';
    const CLIPPING_EVEN_ODD          = 'W*';
    const CLIPPING_EVEN_ODD_FILL     = 'W* F';
    const CLIPPING_EVEN_ODD_NO_STYLE = 'W* n';
    const NO_STYLE                   = 'n';

    /**
     * Allowed styles
     * @var array
     */
    protected $allowedStyles = [
        'S', 's', 'F', 'f*', 'B', 'B*', 'b', 'b*',
        'W', 'W F', 'W*', 'W* F', 'W* n', 'n'
    ];

    /**
     * Path streams array
     * @var array
     */
    protected $streams = null;

    /**
     * Path style
     * @var string
     */
    protected $style = 'S';

    /**
     * Constructor
     *
     * Instantiate a PDF path object
     *
     * @param  string $style
     */
    public function __construct($style = Path::STROKE)
    {
        $this->setStyle($style);
    }

    /**
     * Set the path fill color
     *
     * @param  Color\ColorInterface $color
     * @return Path
     */
    public function setFillColor(Color\ColorInterface $color)
    {
        $stream = null;
        if ($color instanceof Color\Rgb) {
            $stream .= "\n" . $color . " rg\n";
        } else if ($color instanceof Color\Cmyk) {
            $stream .= "\n" . $color . " k\n";
        } else if ($color instanceof Color\Gray) {
            $stream .= "\n" . $color . " g\n";
        }

        if (null !== $stream) {
            $this->streams[] = [
                'stream' => $stream
            ];
        }

        return $this;
    }

    /**
     * Set the path stroke color
     *
     * @param  Color\ColorInterface $color
     * @return Path
     */
    public function setStrokeColor(Color\ColorInterface $color)
    {
        $stream = null;
        if ($color instanceof Color\Rgb) {
            $stream .= "\n" . $color . " RG\n";
        } else if ($color instanceof Color\Cmyk) {
            $stream .= "\n" . $color . " K\n";
        } else if ($color instanceof Color\Gray) {
            $stream .= "\n" . $color . " G\n";
        }

        if (null !== $stream) {
            $this->streams[] = [
                'stream' => $stream
            ];
        }

        return $this;
    }

    /**
     * Set the stroke properties
     *
     * @param  int $width
     * @param  int $dashLength
     * @param  int $dashGap
     * @return Path
     */
    public function setStroke($width, $dashLength = null, $dashGap = null)
    {
        $stream = "\n" . (int)$width . "w\n";
        if ((int)$width != 0) {
            $stream .= ((null !== $dashLength) && (null !== $dashGap)) ?
                "[" . (int)$dashLength . " " . (int)$dashGap . "] 0 d\n" : "[] 0 d\n";
        }

        $this->streams[] = [
            'stream' => $stream
        ];
        return $this;
    }

    /**
     * Set the style
     *
     * @param  string $style
     * @return Path
     */
    public function setStyle($style)
    {
        if (in_array($style, $this->allowedStyles)) {
            $this->style = $style;
        }
        return $this;
    }

    /**
     * Open a graphics state layer
     *
     * @return Path
     */
    public function openLayer()
    {
        $this->streams[] = [
            'stream' => "\nq\n"
        ];
        return $this;
    }

    /**
     * Close a graphics state layer
     *
     * @return Path
     */
    public function closeLayer()
    {
        $this->streams[] = [
            'stream' => "\nQ\n"
        ];
        return $this;
    }

    /**
     * Get the streams
     *
     * @return array
     */
    public function getStreams()
    {
        return $this->streams;
    }

    /**
     * Get the current style
     *
     * @return string
     */
    public function getStyle()
    {
        return $this->style;
    }

    /**
     * Draw a line
     *
     * @param  int $x1
     * @param  int $y1
     * @param  int $x2
     * @param  int $y2
     * @return Path
     */
    public function drawLine($x1, $y1, $x2, $y2)
    {
        $this->streams[] = [
            'points' => [
                ['x1' => $x1, 'y1' => $y1],
                ['x2' => $x2, 'y2' => $y2]
            ],
            'stream' => "\n[{x1}] [{y1}] m\n[{x2}] [{y2}] l\nS\n"
        ];

        return $this;
    }

    /**
     * Draw a rectangle
     *
     * @param  int $x
     * @param  int $y
     * @param  int $w
     * @param  int $h
     * @return Path
     */
    public function drawRectangle($x, $y, $w, $h = null)
    {
        if (null === $h) {
            $h = $w;
        }

        $this->streams[] = [
            'points' => [
                ['x' => $x, 'y' => $y]
            ],
            'stream' => "\n[{x}] [{y}] {$w} {$h} re\n" . $this->style . "\n"
        ];

        return $this;
    }

    /**
     * Draw a rounded rectangle
     *
     * @param  int $x
     * @param  int $y
     * @param  int $w
     * @param  int $h
     * @param  int $rx
     * @param  int $ry
     * @return Path
     */
    public function drawRoundedRectangle($x, $y, $w, $h = null, $rx = 10, $ry = null)
    {
        if (null === $h) {
            $h = $w;
        }

        if (null === $ry) {
            $ry = $rx;
        }

        $rectangle = null;

        $bez1X = $x;
        $bez1Y = $y;
        $bez2X = $x + $w;
        $bez2Y = $y;
        $bez3X = $x + $w;
        $bez3Y = $y + $h;
        $bez4X = $x;
        $bez4Y = $y + $h;

        $points = [
            ['x1'  => $x,            'y1'  => $y + $ry],
            ['x2'  => $x + $rx,      'y2'  => $y],
            ['x3'  => $x + $w - $rx, 'y3'  => $y],
            ['x4'  => $x + $w,       'y4'  => $y + $ry],
            ['x5'  => $x + $w,       'y5'  => $y + $h - $ry],
            ['x6'  => $x + $w - $rx, 'y6'  => $y + $h],
            ['x7'  => $x + $rx,      'y7'  => $y + $h],
            ['x8'  => $x,            'y8'  => $y + $h - $ry],
            ['x9'  => $bez1X,        'y9'  => $bez1Y],
            ['x10' => $bez2X,        'y10' => $bez2Y],
            ['x11' => $bez3X,        'y11' => $bez3Y],
            ['x12' => $bez4X,        'y12' => $bez4Y]
        ];

        $rectangle .= "[{x8}] [{y8}] m\n";
        $rectangle .= "[{x1}] [{y1}] l\n";
        $rectangle .= "[{x9}] [{y9}] [{x9}] [{y9}] [{x2}] [{y2}] c\n";
        $rectangle .= "[{x3}] [{y3}] l\n";
        $rectangle .= "[{x10}] [{y10}] [{x10}] [{y10}] [{x4}] [{y4}] c\n";
        $rectangle .= "[{x5}] [{y5}] l\n";
        $rectangle .= "[{x11}] [{y11}] [{x11}] [{y11}] [{x6}] [{y6}] c\n";
        $rectangle .= "[{x7}] [{y7}] l\n";
        $rectangle .= "[{x12}] [{y12}] [{x12}] [{y12}] [{x8}] [{y8}] c\n";

        $rectangle .= "h\n";

        $this->streams[] = [
            'points' => $points,
            'stream' => "\n{$rectangle}\n" . $this->style . "\n"
        ];

        return $this;
    }

    /**
     * Draw a square
     *
     * @param  int $x
     * @param  int $y
     * @param  int $w
     * @return Path
     */
    public function drawSquare($x, $y, $w)
    {
        return $this->drawRectangle($x, $y, $w, $w);
    }

    /**
     * Draw a rounded square
     *
     * @param  int $x
     * @param  int $y
     * @param  int $w
     * @param  int $rx
     * @param  int $ry
     * @return Path
     */
    public function drawRoundedSquare($x, $y, $w, $rx = 10, $ry = null)
    {
        return $this->drawRoundedRectangle($x, $y, $w, $w, $rx, $ry);
    }

    /**
     * Draw a polygon
     *
     * @param  array $points
     * @throws Exception
     * @return Path
     */
    public function drawPolygon($points)
    {
        $i = 1;
        $polygon = null;

        $stream = [
            'points' => [],
            'stream' => null
        ];

        foreach ($points as $coord) {
            if (!isset($coord['x']) || !isset($coord['y'])) {
                throw new Exception('Error: The array of points must contain arrays with an \'x\' and \'y\' values.');
            }
            $stream['points'][] = [
                'x' . $i => $coord['x'],
                'y' . $i => $coord['y']
            ];

            if ($i == 1) {
                $stream['stream'] .= "[{x" . $i . "}] [{y" . $i . "}] m\n";
            } else if ($i <= count($points)) {
                $stream['stream'] .= "[{x" . $i . "}] [{y" . $i . "}] l\n";
            }
            $i++;
        }

        $stream['stream'] .= "h\n";
        $this->streams[] = $stream;

        return $this;
    }

    /**
     * Draw an ellipse
     *
     * @param  int $x
     * @param  int $y
     * @param  int $w
     * @param  int $h
     * @return Path
     */
    public function drawEllipse($x, $y, $w, $h = null)
    {
        if (null === $h) {
            $h = $w;
        }

        $x1 = $x + $w;
        $y1 = $y;
        $x2 = $x;
        $y2 = $y - $h;
        $x3 = $x - $w;
        $y3 = $y;
        $x4 = $x;
        $y4 = $y + $h;

        // Calculate coordinate number one's 2 bezier points.
        $coor1Bez1X = $x1;
        $coor1Bez1Y = (round(0.55 * ($y2 - $y1))) + $y1;
        $coor1Bez2X = $x1;
        $coor1Bez2Y = (round(0.45 * ($y1 - $y4))) + $y4;

        // Calculate coordinate number two's 2 bezier points.
        $coor2Bez1X = (round(0.45 * ($x2 - $x1))) + $x1;
        $coor2Bez1Y = $y2;
        $coor2Bez2X = (round(0.55 * ($x3 - $x2))) + $x2;
        $coor2Bez2Y = $y2;

        // Calculate coordinate number three's 2 bezier points.
        $coor3Bez1X = $x3;
        $coor3Bez1Y = (round(0.55 * ($y2 - $y3))) + $y3;
        $coor3Bez2X = $x3;
        $coor3Bez2Y = (round(0.45 * ($y3 - $y4))) + $y4;

        // Calculate coordinate number four's 2 bezier points.
        $coor4Bez1X = (round(0.55 * ($x3 - $x4))) + $x4;
        $coor4Bez1Y = $y4;
        $coor4Bez2X = (round(0.45 * ($x4 - $x1))) + $x1;
        $coor4Bez2Y = $y4;

        $this->streams[] = [
            'points' => [
                ['x1'  => $x1,         'y1'  => $y1],
                ['x2'  => $x2,         'y2'  => $y2],
                ['x3'  => $x3,         'y3'  => $y3],
                ['x4'  => $x4,         'y4'  => $y4],
                ['x5'  => $coor1Bez1X, 'y5'  => $coor1Bez1Y],
                ['x6'  => $coor1Bez2X, 'y6'  => $coor1Bez2Y],
                ['x7'  => $coor2Bez1X, 'y7'  => $coor2Bez1Y],
                ['x8'  => $coor2Bez2X, 'y8'  => $coor2Bez2Y],
                ['x9'  => $coor3Bez1X, 'y9'  => $coor3Bez1Y],
                ['x10' => $coor3Bez2X, 'y10' => $coor3Bez2Y],
                ['x11' => $coor4Bez1X, 'y11' => $coor4Bez1Y],
                ['x12' => $coor4Bez2X, 'y12' => $coor4Bez2Y]
            ],
            'stream' => "\n[{x1}] [{y1}] m\n[{x5}] [{y5}] [{x7}] [{y7}] [{x2}] [{y2}] c\n" .
                "[{x8}] [{y8}] [{x9}] [{y9}] [{x3}] [{y3}] c\n[{x10}] [{y10}] " .
                "[{x11}] [{y11}] [{x4}] [{y4}] c\n[{x12}] [{y12}] [{x6}] [{y6}] " .
                "[{x1}] [{y1}] c\n" . $this->style . "\n"
        ];

        return $this;
    }

    /**
     * Draw a circle
     *
     * @param  int $x
     * @param  int $y
     * @param  int $w
     * @return Path
     */
    public function drawCircle($x, $y, $w)
    {
        return $this->drawEllipse($x, $y, $w, $w);
    }

    /**
     * Draw an arc
     *
     * @param  int $x
     * @param  int $y
     * @param  int $start
     * @param  int $end
     * @param  int $w
     * @param  int $h
     * @return Path
     */
    public function drawArc($x, $y, $start, $end, $w, $h = null)
    {
        if (null === $h) {
            $h = $w;
        }
        $this->calculateArc($x, $y, $this->calculateDegrees($start, $end), $w, $h);

        return $this;
    }

    /**
     * Draw a chord
     *
     * @param  int $x
     * @param  int $y
     * @param  int $start
     * @param  int $end
     * @param  int $w
     * @param  int $h
     * @return Path
     */
    public function drawChord($x, $y, $start, $end, $w, $h = null)
    {
        if (null === $h) {
            $h = $w;
        }
        $this->calculateArc($x, $y, $this->calculateDegrees($start, $end), $w, $h, true);

        return $this;
    }

    /**
     * Draw a pie slice
     *
     * @param  int $x
     * @param  int $y
     * @param  int $start
     * @param  int $end
     * @param  int $w
     * @param  int $h
     * @return Path
     */
    public function drawPie($x, $y, $start, $end, $w, $h = null)
    {
        if (null === $h) {
            $h = $w;
        }
        $this->calculateArc($x, $y, $this->calculateDegrees($start, $end), $w, $h, true, true);

        return $this;
    }

    /**
     * Draw an open cubic bezier curve
     *
     * @param  int $x1
     * @param  int $y1
     * @param  int $x2
     * @param  int $y2
     * @param  int $bezierX1
     * @param  int $bezierY1
     * @param  int $bezierX2
     * @param  int $bezierY2
     * @return Path
     */
    public function drawOpenCubicBezierCurve($x1, $y1, $x2, $y2, $bezierX1, $bezierY1, $bezierX2, $bezierY2)
    {
        $this->streams[] = [
            'points' => [
                ['x1' => $x1, 'y1' => $y1],
                ['x2' => $bezierX1, 'y2' => $bezierY1],
                ['x3' => $bezierX2, 'y3' => $bezierY2],
                ['x4' => $x2, 'y4' => $y2]
            ],
            'stream' => "\n[{x1}] [{y1}] m\n[{x2}] [{y2}] [{x3}] [{y3}] [{x4}] [{y4}] c\n" . $this->style . "\n"
        ];

        return $this;
    }

    /**
     * Draw a closed cubic bezier curve
     *
     * @param  int $x1
     * @param  int $y1
     * @param  int $x2
     * @param  int $y2
     * @param  int $bezierX1
     * @param  int $bezierY1
     * @param  int $bezierX2
     * @param  int $bezierY2
     * @return Path
     */
    public function drawClosedCubicBezierCurve($x1, $y1, $x2, $y2, $bezierX1, $bezierY1, $bezierX2, $bezierY2)
    {
        $this->streams[] = [
            'points' => [
                ['x1' => $x1,       'y1' => $y1],
                ['x2' => $bezierX1, 'y2' => $bezierY1],
                ['x3' => $bezierX2, 'y3' => $bezierY2],
                ['x4' => $x2,       'y4' => $y2]
            ],
            'stream' => "\n[{x1}] [{y1}] m\n[{x2}] [{y2}] [{x3}] [{y3}] [{x4}] [{y4}] c\nh\n" . $this->style . "\n"
        ];

        return $this;
    }

    /**
     * Draw an open quadratic bezier curve, single control point
     *
     * @param  int  $x1
     * @param  int  $y1
     * @param  int  $x2
     * @param  int  $y2
     * @param  int  $bezierX
     * @param  int  $bezierY
     * @param  bool $first
     * @return Path
     */
    public function drawOpenQuadraticBezierCurve($x1, $y1, $x2, $y2, $bezierX, $bezierY, $first = true)
    {
        $this->streams[] = [
            'points' => [
                ['x1' => $x1,      'y1' => $y1],
                ['x2' => $bezierX, 'y2' => $bezierY],
                ['x3' => $x2,      'y3' => $y2]
            ],
            'stream' => "\n[{x1}] [{y1}] m\n[{x2}] [{y2}] [{x3}] [{y3}] " . (($first) ? "y" : "v") . "\n" . $this->style . "\n"
        ];

        return $this;
    }

    /**
     * Draw an open quadratic bezier curve, single control point
     *
     * @param  int  $x1
     * @param  int  $y1
     * @param  int  $x2
     * @param  int  $y2
     * @param  int  $bezierX
     * @param  int  $bezierY
     * @param  bool $first
     * @return Path
     */
    public function drawClosedQuadraticBezierCurve($x1, $y1, $x2, $y2, $bezierX, $bezierY, $first = true)
    {
        $this->streams[] = [
            'points' => [
                ['x1' => $x1,      'y1' => $y1],
                ['x2' => $bezierX, 'y2' => $bezierY],
                ['x3' => $x2,      'y3' => $y2]
            ],
            'stream' => "\n[{x1}] [{y1}] m\n[{x2}] [{y2}] [{x3}] [{y3}] " . (($first) ? "y" : "v") . "\nh\n" . $this->style . "\n"
        ];

        return $this;
    }

    /**
     * Calculate degrees
     *
     * @param  int $start
     * @param  int $end
     * @throws Exception
     * @return array
     */
    protected function calculateDegrees($start, $end)
    {
        if (($start < 0) || ($end > 360)) {
            throw new Exception('The start and end angles must be between 0 and 360.');
        }
        if ($start >= $end) {
            throw new Exception('The start angle must be less than the end angle.');
        }

        if (($end - $start) > 90) {
            $degrees = [];
            if ($start < 90) {
                $degrees[] = [$start, 90];
                $current = 90;
            } else {
                $current = $start;
            }
            while (($current + 90) < $end) {
                $next = ($current + 90) - ($current % 90);
                $degrees[] = [$current, $next];
                $current = $next;
            }
            $degrees[] = [$current, $end];
        } else if (($start < 180) && ($start > 90) && ($end > 180)) {
            $degrees[] = [$start, 180];
            $current = 180;
            while (($current + 90) < $end) {
                $next = ($current + 90) - ($current % 90);
                $degrees[] = [$current, $next];
                $current = $next;
            }
            $degrees[] = [$current, $end];
        } else {
            $degrees[] = [$start, $end];
        }

        return $degrees;
    }

    /**
     * Calculate arc
     *
     * @param  int     $x
     * @param  int     $y
     * @param  array   $degrees
     * @param  int     $w
     * @param  int     $h
     * @param  boolean $closed
     * @param  boolean $pie
     * @return void
     */
    protected function calculateArc($x, $y, array $degrees, $w, $h = null, $closed = false, $pie = false)
    {
        foreach ($degrees as $key => $value) {
            $start  = $value[0];
            $end    = $value[1];
            $startX = round($x + ($w * cos(deg2rad($start))));
            $startY = round($y + ($h * sin(deg2rad($start))));
            $endX   = round($x + ($w * cos(deg2rad($end))));
            $endY   = round($y + ($h * sin(deg2rad($end))));
            $n1     = acos(($startX - $x) / $w);
            $n2     = acos(($endX - $x) / $w);
            $t      = $n2 - $n1;
            $a      = sin($t) * ((sqrt(4 + (3 * pow(tan($t / 2), 2))) - 1) / 3);

            $e1x = 0 - ($w * sin($n1));
            $e1y = $h * cos($n1);

            $e2x = 0 - ($w * sin($n2));
            $e2y = $h * cos($n2);

            $q1X = round($startX + ($a * $e1x));
            $q2X = round($endX - ($a * $e2x));

            if ($end > 180) {
                $q1Y = round($startY + ((0 - $a) * $e1y));
                $q2Y = round($endY - ((0 - $a) * $e2y));
            } else {
                $q1Y = round($startY + ($a * $e1y));
                $q2Y = round($endY - ($a * $e2y));
            }

            if ($key == 0) {
                $points = [
                    ['x1' => $startX, 'y1' => $startY],
                    ['x2' => $q1X,    'y2' => $q1Y],
                    ['x3' => $q2X,    'y3' => $q2Y],
                    ['x4' => $endX,   'y4' => $endY]
                ];
                $stream = "\n[{x1}] [{y1}] m\n[{x2}] [{y2}] [{x3}] [{y3}] [{x4}] [{y4}] c\n";
                if (count($degrees) == 1) {
                    if ($pie) {
                        $points[] = ['x5' => $x,   'y5' => $y];
                        $stream .= "\n[{x5}] [{y5}] l\n" . (($closed) ? "h" : null) . "\n" . $this->style . "\n";
                    } else {
                        $stream .= (($closed) ? "h" : null) . "\n" . $this->style . "\n";
                    }
                }

                $this->streams[] = [
                    'points' => $points,
                    'stream' => $stream
                ];
            } else if ($key == (count($degrees) - 1)) {
                if ($pie) {
                    $this->streams[] = [
                        'points' => [
                            ['x2' => $q1X,  'y2' => $q1Y],
                            ['x3' => $q2X,  'y3' => $q2Y],
                            ['x4' => $endX, 'y4' => $endY],
                            ['x5' => $x,    'y5' => $y]
                        ],
                        'stream' => "\n[{x2}] [{y2}] [{x3}] [{y3}] [{x4}] [{y4}] c\n[{x5}] [{y5}] l\nh\n" . $this->style . "\n"
                    ];
                } else {
                    $this->streams[] = [
                        'points' => [
                            ['x2' => $q1X,  'y2' => $q1Y],
                            ['x3' => $q2X,  'y3' => $q2Y],
                            ['x4' => $endX, 'y4' => $endY]
                        ],
                        'stream' => "\n[{x2}] [{y2}] [{x3}] [{y3}] [{x4}] [{y4}] c\n" . (($closed) ? "h" : null) . "\n" . $this->style . "\n"
                    ];
                }
            } else {
                $this->streams[] = [
                    'points' => [
                        ['x2' => $q1X, 'y2' => $q1Y],
                        ['x3' => $q2X, 'y3' => $q2Y],
                        ['x4' => $endX, 'y4' => $endY]
                    ],
                    'stream' => "\n[{x2}] [{y2}] [{x3}] [{y3}] [{x4}] [{y4}] c\n"
                ];
            }
        }
    }

}