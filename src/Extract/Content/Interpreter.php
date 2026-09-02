<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Extract\Content;

use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Filter\Registry;
use Pop\Pdf\Extract\ObjectParser;
use Pop\Pdf\Extract\Tokenizer;
use Pop\Pdf\Extract\Value;

/**
 * Pdf extract content interpreter class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
 */
class Interpreter
{

    /**
     * Maximum Do-operator (Form XObject) recursion depth
     */
    protected const MAX_DO_DEPTH = 32;

    /**
     * Document being interpreted against
     * @var Document
     */
    protected Document $doc;

    /**
     * Text runs emitted so far
     * @var array
     */
    protected array $runs = [];

    /**
     * Text matrix
     * @var array
     */
    protected array $tm = ['a' => 1.0, 'b' => 0.0, 'i' => 0.0, 'j' => 1.0, 'x' => 0.0, 'y' => 0.0];

    /**
     * Text line matrix translation
     * @var array
     */
    protected array $td = ['x' => 0.0, 'y' => 0.0];

    /**
     * Current transformation matrix
     * @var array
     */
    protected array $cm = ['a' => 1.0, 'b' => 0.0, 'i' => 0.0, 'j' => 1.0, 'x' => 0.0, 'y' => 0.0];

    /**
     * Text leading
     * @var float
     */
    protected float $leading = 0.0;

    /**
     * Active font resource name
     * @var ?string
     */
    protected ?string $fontName = null;

    /**
     * Active font size
     * @var float
     */
    protected float $fontSize = 0.0;

    /**
     * Character spacing
     * @var float
     */
    protected float $charSpace = 0.0;

    /**
     * Word spacing
     * @var float
     */
    protected float $wordSpace = 0.0;

    /**
     * Horizontal scaling percentage
     * @var float
     */
    protected float $horizScale = 100.0;

    /**
     * Graphics state stack, pushed/popped by q/Q
     * @var array
     */
    protected array $qStack = [];

    /**
     * Marked-content stack, pushed/popped by BDC/BMC/EMC
     * @var array
     */
    protected array $markedStack = [];

    /**
     * Position captured for a pending ActualText replacement run
     * @var mixed
     */
    protected mixed $actualTextPos = null;

    /**
     * Last emitted run's x position, used to compute the next run's separator
     * @var ?float
     */
    protected ?float $lastX = null;

    /**
     * Last emitted run's y position, used to compute the next run's separator
     * @var ?float
     */
    protected ?float $lastY = null;

    /**
     * Active font's resolved dictionary
     * @var mixed
     */
    protected mixed $fontResolved = null;

    /**
     * Active font's precomputed cache key, computed once per Tf
     * @var ?string
     */
    protected ?string $fontResolvedKey = null;

    /**
     * Interpret a page's content stream against a document/resources, returning the extracted text runs
     *
     * @param  Document $doc
     * @param  string   $content
     * @param  array    $resources
     * @return array
     */
    public function run(Document $doc, string $content, array $resources): array
    {
        $this->doc = $doc;
        $this->resetState();

        $this->interpret($content, $resources, [], 0);

        return $this->runs;
    }

    /**
     * Reset all interpreter state to its initial values
     *
     * @return void
     */
    protected function resetState(): void
    {
        $this->runs = [];

        $this->tm = ['a' => 1.0, 'b' => 0.0, 'i' => 0.0, 'j' => 1.0, 'x' => 0.0, 'y' => 0.0];
        $this->td = ['x' => 0.0, 'y' => 0.0];
        $this->cm = ['a' => 1.0, 'b' => 0.0, 'i' => 0.0, 'j' => 1.0, 'x' => 0.0, 'y' => 0.0];

        $this->leading         = 0.0;
        $this->fontName        = null;
        $this->fontResolved    = null;
        $this->fontResolvedKey = null;
        $this->fontSize        = 0.0;
        $this->charSpace  = 0.0;
        $this->wordSpace  = 0.0;
        $this->horizScale = 100.0;

        $this->qStack        = [];
        $this->markedStack   = [];
        $this->actualTextPos = null;

        $this->lastX = null;
        $this->lastY = null;
    }

    /**
     * Tokenize and dispatch a content stream's operators
     *
     * @param  string $content
     * @param  array  $resources
     * @param  array  $doStack
     * @param  int    $depth
     * @return void
     */
    protected function interpret(string $content, array $resources, array $doStack, int $depth): void
    {
        $tokenizer    = new Tokenizer($content);
        $objectParser = new ObjectParser($tokenizer);
        $operandStack = [];

        while (true) {
            $savedPos = $tokenizer->getPosition();
            $peek     = $tokenizer->next();

            if ($peek['type'] === 'eof') {
                break;
            }

            if (($peek['type'] === 'keyword') && ($peek['value'] === 'BI')) {
                $this->skipInlineImage($tokenizer);
                $operandStack = [];
                continue;
            }

            $tokenizer->setPosition($savedPos);

            try {
                $value = $objectParser->parseValue();
            } catch (\Pop\Pdf\Extract\Exception $e) {
                // A malformed operand (stray delimiter, truncated array/dict,
                // etc.) must not lose every run already extracted on this
                // page - skip it and keep going. The tokenizer's position
                // has already advanced past the offending token, so this
                // always makes forward progress.
                $operandStack = [];
                continue;
            }

            if ($value instanceof Value\Keyword) {
                try {
                    $this->dispatch($value->keyword, $operandStack, $resources, $doStack, $depth);
                } catch (\Pop\Pdf\Extract\Exception $e) {
                    // A resolve() failure inside any operator handler (e.g.
                    // a circular reference in /Resources /Font or /XObject)
                    // must not lose every run already extracted on this
                    // page - degrade this operator to a no-op and keep
                    // going, matching the malformed-operand handling above.
                }
                $operandStack = [];
            } else {
                $operandStack[] = $value;
            }
        }
    }

    /**
     * Skip over an inline image (BI...ID...EI) without interpreting it
     *
     * @param  Tokenizer $tokenizer
     * @return void
     */
    protected function skipInlineImage(Tokenizer $tokenizer): void
    {
        $objectParser = new ObjectParser($tokenizer);

        while (true) {
            $savedPos = $tokenizer->getPosition();
            $peek     = $tokenizer->next();

            if ($peek['type'] === 'eof') {
                return;
            }

            if (($peek['type'] === 'keyword') && ($peek['value'] === 'ID')) {
                break;
            }

            $tokenizer->setPosition($savedPos);

            try {
                $objectParser->parseValue();
            } catch (\Pop\Pdf\Extract\Exception $e) {
                // Malformed BI key/value pair - keep scanning for ID rather
                // than aborting the whole page.
                continue;
            }
        }

        $data = $tokenizer->getData();
        $pos  = $tokenizer->getPosition();

        if (($pos < strlen($data)) && Tokenizer::isWhitespace($data[$pos])) {
            $pos++;
        }

        $length = strlen($data);
        $eiPos  = $pos;

        while (true) {
            $eiPos = strpos($data, 'EI', $eiPos);

            if ($eiPos === false) {
                $tokenizer->setPosition($length);
                return;
            }

            $beforeOk = ($eiPos === 0) || Tokenizer::isWhitespace($data[$eiPos - 1]);
            $afterPos = $eiPos + 2;
            $afterOk  = ($afterPos >= $length) || Tokenizer::isWhitespace($data[$afterPos]);

            if ($beforeOk && $afterOk) {
                $tokenizer->setPosition($afterPos);
                return;
            }

            $eiPos += 2;
        }
    }

    /**
     * Dispatch one operator with its operands, updating interpreter state and/or emitting runs
     *
     * @param  string $op
     * @param  array  $operands
     * @param  array  $resources
     * @param  array  $doStack
     * @param  int    $depth
     * @return void
     */
    protected function dispatch(string $op, array $operands, array $resources, array $doStack, int $depth): void
    {
        switch ($op) {
            case 'q':
                $this->qStack[] = [
                    'fontName'        => $this->fontName,
                    'fontResolved'    => $this->fontResolved,
                    'fontResolvedKey' => $this->fontResolvedKey,
                    'fontSize'        => $this->fontSize,
                    'cm'              => $this->cm,
                ];
                break;

            case 'Q':
                $saved = array_pop($this->qStack);
                if ($saved !== null) {
                    $this->fontName        = $saved['fontName'];
                    $this->fontResolved    = $saved['fontResolved'];
                    $this->fontResolvedKey = $saved['fontResolvedKey'];
                    $this->fontSize        = $saved['fontSize'];
                    $this->cm              = $saved['cm'];
                }
                break;

            case 'cm':
                if (count($operands) >= 6) {
                    [$a, $b, $c, $d, $e, $f] = array_slice($operands, -6);
                    $this->cm = [
                        'a' => is_numeric($a) ? (float) $a : 0.0,
                        'b' => is_numeric($b) ? (float) $b : 0.0,
                        'i' => is_numeric($c) ? (float) $c : 0.0,
                        'j' => is_numeric($d) ? (float) $d : 0.0,
                        'x' => is_numeric($e) ? (float) $e : 0.0,
                        'y' => is_numeric($f) ? (float) $f : 0.0,
                    ];
                }
                break;

            case 'BT':
                $this->tm = ['a' => 1.0, 'b' => 0.0, 'i' => 0.0, 'j' => 1.0, 'x' => 0.0, 'y' => 0.0];
                $this->td = ['x' => 0.0, 'y' => 0.0];
                break;

            case 'ET':
                break;

            case 'BDC':
                $tag  = $operands[count($operands) - 2] ?? null;
                $prop = $operands[count($operands) - 1] ?? null;

                $propsDict = null;
                if (is_array($prop)) {
                    $propsDict = $prop;
                } elseif ($prop instanceof Value\Name) {
                    $properties = $this->doc->resolve($resources['Properties'] ?? null);
                    if (is_array($properties) && isset($properties[$prop->name])) {
                        $resolved = $this->doc->resolve($properties[$prop->name]);
                        if (is_array($resolved)) {
                            $propsDict = $resolved;
                        }
                    }
                }

                $actualText = null;
                if (is_array($propsDict) && isset($propsDict['ActualText']) && is_string($propsDict['ActualText'])) {
                    $actualText = $this->decodePdfTextString($propsDict['ActualText']);
                }

                if ($actualText !== null) {
                    $this->markedStack[] = ['type' => 'actualText', 'text' => $actualText];
                } else {
                    $this->markedStack[] = ['type' => 'plain'];
                }
                break;

            case 'BMC':
                $tag = end($operands);
                if (($tag instanceof Value\Name) && ($tag->name === 'ReversedChars')) {
                    $this->markedStack[] = ['type' => 'reversedChars'];
                } else {
                    $this->markedStack[] = ['type' => 'plain'];
                }
                break;

            case 'EMC':
                $marker = array_pop($this->markedStack);

                if (($marker !== null) && ($marker['type'] === 'actualText') && ($this->actualTextPos !== null)) {
                    $this->emitRun(null, $marker['text'], $this->actualTextPos['x'], $this->actualTextPos['y']);
                }

                $this->actualTextPos = null;
                break;

            case 'Do':
                $name = end($operands);
                if ($name instanceof Value\Name) {
                    $this->handleDo($name->name, $resources, $doStack, $depth);
                }
                break;

            case 'Tf':
                if (count($operands) >= 2) {
                    $name = $operands[count($operands) - 2];
                    $size = $operands[count($operands) - 1];

                    $this->fontName        = ($name instanceof Value\Name) ? $name->name : null;
                    $this->fontSize        = is_numeric($size) ? (float) $size : 0.0;
                    $this->fontResolved    = null;
                    $this->fontResolvedKey = null;

                    if ($this->fontName !== null) {
                        $fonts = $this->doc->resolve($resources['Font'] ?? null);
                        if (is_array($fonts) && isset($fonts[$this->fontName])) {
                            $resolvedFont = $this->doc->resolve($fonts[$this->fontName]);
                            if (is_array($resolvedFont)) {
                                $this->fontResolved = $resolvedFont;
                                // Computed ONCE per Tf (font activation), not once per
                                // Tj/TJ run - a font may back thousands of runs before
                                // the next Tf, and hashing the full resolved dict per
                                // run would be an O(runs x dict-size) cost.
                                $this->fontResolvedKey = md5(serialize($resolvedFont));
                            }
                        }
                    }
                }
                break;

            case 'Tc':
                if (count($operands) >= 1) {
                    $val = end($operands);
                    $this->charSpace = is_numeric($val) ? (float) $val : 0.0;
                }
                break;

            case 'Tw':
                if (count($operands) >= 1) {
                    $val = end($operands);
                    $this->wordSpace = is_numeric($val) ? (float) $val : 0.0;
                }
                break;

            case 'Tz':
                if (count($operands) >= 1) {
                    $val = end($operands);
                    $this->horizScale = is_numeric($val) ? (float) $val : 100.0;
                }
                break;

            case 'TL':
                if (count($operands) >= 1) {
                    $yVal = end($operands);
                    $y = is_numeric($yVal) ? (float) $yVal : 0.0;
                    $this->leading = -$y * $this->tm['b'] - $y * $this->tm['j'];
                }
                break;

            case 'Td':
            case 'TD':
                if (count($operands) >= 2) {
                    $xVal = $operands[count($operands) - 2];
                    $yVal = $operands[count($operands) - 1];
                    $x = is_numeric($xVal) ? (float) $xVal : 0.0;
                    $y = is_numeric($yVal) ? (float) $yVal : 0.0;

                    if ($op === 'TD') {
                        $this->leading = -$y * $this->tm['b'] - $y * $this->tm['j'];
                    }

                    $this->td['x'] += $x * $this->tm['a'] + $x * $this->tm['i'];
                    $this->td['y'] += $y * $this->tm['b'] + $y * $this->tm['j'];
                }
                break;

            case 'T*':
                $this->td['x']  = 0.0;
                $this->td['y'] += $this->leading;
                break;

            case 'Tm':
                if (count($operands) >= 6) {
                    [$a, $b, $c, $d, $e, $f] = array_slice($operands, -6);
                    $this->tm = [
                        'a' => is_numeric($a) ? (float) $a : 0.0,
                        'b' => is_numeric($b) ? (float) $b : 0.0,
                        'i' => is_numeric($c) ? (float) $c : 0.0,
                        'j' => is_numeric($d) ? (float) $d : 0.0,
                        'x' => is_numeric($e) ? (float) $e : 0.0,
                        'y' => is_numeric($f) ? (float) $f : 0.0,
                    ];
                }
                break;

            case 'Tj':
                if (count($operands) >= 1) {
                    $value = end($operands);
                    if (is_string($value)) {
                        $this->showText($value);
                    }
                }
                break;

            case 'TJ':
                if (count($operands) >= 1) {
                    $array = end($operands);
                    if (is_array($array)) {
                        foreach ($array as $element) {
                            if (is_string($element)) {
                                $this->showText($element);
                            } elseif (is_numeric($element)) {
                                // TJ's numeric adjustment is a purely horizontal
                                // (writing-direction) advance - unlike Td/TD's
                                // x,y pair, it has no y-component to project.
                                $adj = -($element / 1000) * $this->fontSize;
                                $this->td['x'] += $adj * $this->tm['a'] + $adj * $this->tm['i'];
                            }
                        }
                    }
                }
                break;

            case "'":
                $this->td['x']  = 0.0;
                $this->td['y'] += $this->leading;
                if (count($operands) >= 1) {
                    $value = end($operands);
                    if (is_string($value)) {
                        $this->showText($value);
                    }
                }
                break;

            case '"':
                if (count($operands) >= 3) {
                    $wsVal = $operands[count($operands) - 3];
                    $csVal = $operands[count($operands) - 2];
                    $this->wordSpace = is_numeric($wsVal) ? (float) $wsVal : 0.0;
                    $this->charSpace = is_numeric($csVal) ? (float) $csVal : 0.0;
                    $this->td['x']   = 0.0;
                    $this->td['y']  += $this->leading;
                    $value = end($operands);
                    if (is_string($value)) {
                        $this->showText($value);
                    }
                }
                break;

            default:
                // Unrecognized/irrelevant operator (path construction, color,
                // clipping, etc.) - operands were already pushed and are
                // discarded here; no state or output effect.
                break;
        }
    }

    /**
     * Handle the Do operator, recursively interpreting a Form XObject's content stream
     *
     * @param  string $name
     * @param  array  $resources
     * @param  array  $doStack
     * @param  int    $depth
     * @return void
     */
    protected function handleDo(string $name, array $resources, array $doStack, int $depth): void
    {
        if ($depth >= self::MAX_DO_DEPTH) {
            // Hard cap regardless of the objNum cycle guard below - a
            // directly-inline (non-indirect-reference) Form stream has no
            // objNum for that guard to key on, so a self-invoking inline
            // Form would otherwise recurse unbounded.
            return;
        }

        $xobjects = $this->doc->resolve($resources['XObject'] ?? null);

        if (!is_array($xobjects) || !isset($xobjects[$name])) {
            return;
        }

        $ref    = $xobjects[$name];
        $objNum = ($ref instanceof Value\Reference) ? $ref->objNum : null;

        if (($objNum !== null) && isset($doStack[$objNum])) {
            return;
        }

        $xobject = $this->doc->resolve($ref);

        if (!($xobject instanceof Value\Stream)) {
            return;
        }

        $subtype = $xobject->dict['Subtype'] ?? null;
        if (!($subtype instanceof Value\Name) || ($subtype->name !== 'Form')) {
            return;
        }

        $formResources = $this->doc->resolve($xobject->dict['Resources'] ?? null);
        $formResources = is_array($formResources) ? $formResources : $resources;

        $content = Registry::decode($xobject->raw, $xobject->dict['Filter'] ?? null, $xobject->dict['DecodeParms'] ?? null, $this->doc->getDecodeBudget());

        $savedFontName        = $this->fontName;
        $savedFontResolved    = $this->fontResolved;
        $savedFontResolvedKey = $this->fontResolvedKey;
        $savedFontSize        = $this->fontSize;
        $savedCm              = $this->cm;
        $savedQStack          = $this->qStack;
        $savedMarkedStack     = $this->markedStack;

        if ($objNum !== null) {
            $doStack[$objNum] = true;
        }

        try {
            $this->interpret($content, $formResources, $doStack, $depth + 1);
        } finally {
            $this->fontName        = $savedFontName;
            $this->fontResolved    = $savedFontResolved;
            $this->fontResolvedKey = $savedFontResolvedKey;
            $this->fontSize        = $savedFontSize;
            $this->cm              = $savedCm;
            // A Form's content stream must not be able to corrupt the
            // caller's graphics-state/marked-content stacks - an unbalanced
            // q/Q or BDC/EMC inside the form (malformed or adversarial)
            // would otherwise pop/leave-open the CALLER's frames, since
            // these stacks are shared instance state across the recursive
            // interpret() call. Restore full snapshots, not just depth.
            $this->qStack      = $savedQStack;
            $this->markedStack = $savedMarkedStack;
        }
    }

    /**
     * Show text (Tj/TJ/'/"), either emitting a run or capturing an ActualText substitution's position
     *
     * @param  string $bytes
     * @return void
     */
    protected function showText(string $bytes): void
    {
        $x = $this->cm['x'] + $this->tm['x'] + $this->td['x'];
        $y = $this->cm['y'] + $this->tm['y'] + $this->td['y'];

        if ($this->isSuppressingForActualText()) {
            if ($this->actualTextPos === null) {
                $this->actualTextPos = ['x' => $x, 'y' => $y];
            }
            return;
        }

        $this->emitRun($bytes, null, $x, $y);
    }

    /**
     * Emit a text run, computing its separator relative to the previous run
     *
     * @param  ?string $rawBytes
     * @param  ?string $decodedText
     * @param  float   $x
     * @param  float   $y
     * @return void
     */
    protected function emitRun(?string $rawBytes, ?string $decodedText, float $x, float $y): void
    {
        $factorX = -$this->fontSize * $this->tm['a'] - $this->fontSize * $this->tm['i'];
        $factorY =  $this->fontSize * $this->tm['b'] + $this->fontSize * $this->tm['j'];

        $separator = TextRun::SEPARATOR_NONE;

        if ($this->lastX !== null) {
            $deltaY = $y - $this->lastY;

            if (abs($deltaY) >= (abs($factorY) / 4)) {
                $separator = TextRun::SEPARATOR_NEWLINE;
            } else {
                $deltaX = $x - $this->lastX;

                if ($deltaX >= abs($factorX * 7)) {
                    $separator = TextRun::SEPARATOR_TAB;
                } elseif ($deltaX >= abs($factorX * 2)) {
                    $separator = TextRun::SEPARATOR_SPACE;
                }
            }
        }

        $fontResourceName = ($decodedText !== null) ? null : $this->fontName;
        $font             = ($decodedText !== null) ? null : $this->fontResolved;
        $fontCacheKey     = ($decodedText !== null) ? null : $this->fontResolvedKey;
        $this->runs[] = new TextRun($fontResourceName, $rawBytes, $decodedText, $x, $y, $separator, $this->isReversedActive(), $font, $fontCacheKey);

        $length      = ($rawBytes !== null) ? strlen($rawBytes) : (($decodedText !== null) ? strlen($decodedText) : 0);
        $this->lastX = $x - ($length * ($factorX / 2));
        $this->lastY = $y;
    }

    /**
     * Determine if the marked-content stack is currently suppressing output for an ActualText replacement
     *
     * @return bool
     */
    protected function isSuppressingForActualText(): bool
    {
        foreach ($this->markedStack as $marker) {
            if ($marker['type'] === 'actualText') {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the marked-content stack currently has /ReversedChars active
     *
     * @return bool
     */
    protected function isReversedActive(): bool
    {
        foreach ($this->markedStack as $marker) {
            if ($marker['type'] === 'reversedChars') {
                return true;
            }
        }

        return false;
    }

    /**
     * Decode a PDF text string, converting a UTF-16BE (BOM-prefixed) value to UTF-8
     *
     * @param  string $value
     * @return string
     */
    protected function decodePdfTextString(string $value): string
    {
        if ((strlen($value) >= 2) && (substr($value, 0, 2) === "\xFE\xFF")) {
            $utf16 = substr($value, 2);

            return @mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
        }

        return $value;
    }

}
