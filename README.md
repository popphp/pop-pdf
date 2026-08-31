pop-pdf
=======

[![Build Status](https://github.com/popphp/pop-pdf/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-pdf/actions)
[![Coverage Status](https://cc.popphp.org/coverage.php?comp=pop-pdf)](https://cc.popphp.org/pop-pdf/)

[![Join the chat at https://discord.gg/TZjgT74U7E](https://media.popphp.org/img/discord.svg)](https://discord.gg/TZjgT74U7E)

* [Overview](#overview)
* [Install](#install)
* [Quickstart](#quickstart)
* [PDF](#pdf)
    - [Write to File](#write-to-file)
    - [Output to HTTP](#output-to-http)
    - [Import from File](#import-from-file)
    - [Import from Raw Data](#import-from-raw-data)
    - [Import from Images](#import-from-images)
    - [Extract as Images](#extract-as-images)
    - [Merge](#merge)
    - [Extract Text](#extract-text)
    - [Image-Only Detection](#image-only-detection)
* [Documents](#documents)
    - [Compression](#compression)
    - [Page Origin](#page-origin)
* [Pages](#pages)
* [Fonts](#fonts)
    - [Standard](#standard)
    - [Embedded](#embedded)
* [Text](#text)
    - [Alignment](#alignment)
    - [String Width](#string-width)
* [Styles](#styles)
* [Images](#images)
    - [Image Size](#image-size)
* [Paths](#paths)
    - [Composed Paths](#composed-paths)
* [Annotations](#annotations)
    - [URLs](#urls)
    - [Internal](#internal)
* [Forms](#forms)
* [HTML](#html)

Overview
--------
Pop PDF is a robust PDF processing component that's simple to use. With it, you can create
PDF documents from scratch, or import existing ones and add to or modify them. It supports
embedding images, fonts and URLs, as well as a set of drawing, effect and type features.

`pop-pdf` is a component of the [Pop PHP Framework](https://www.popphp.org/).

[Top](#pop-pdf)

Install
-------

Install `pop-pdf` using Composer.

    composer require popphp/pop-pdf

Or, require it in your composer.json file

    "require": {
        "popphp/pop-pdf" : "^5.2.12"
    }

[Top](#pop-pdf)

Quickstart
----------

### Create a simple PDF

Create a simple 1-page PDF document with the text "Hello World" on the page.
The page size will be letter. The text string will be positioned (50, 50)
from the top left and use the standard Arial font.

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Text;

$document = new Document();
$document->addFont(Font::ARIAL);

$page = $document->createPage(Page::LETTER);
$page->addText(new Text('Hello World', 12), Font::ARIAL, 50, 742);

Pdf::writeToFile($document, 'my-document.pdf');
```

### Embed an image

Using the same example from above, let's add an image to it:

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Text;
use Pop\Pdf\Document\Page\Image;

$document = new Document();
$document->addFont(Font::ARIAL);

$page = $document->createPage(Page::LETTER);
$page->addText(new Text('Look at this image:', 12), Font::ARIAL, 50, 742);
$page->addImage(Image::createImageFromFile('my-image.jpg'), 50, 380);

Pdf::writeToFile($document, 'my-document.pdf');
```

[Top](#pop-pdf)

PDF
---

The PDF format specification is a vast and comprehensive format that has been around for a
long time. It is comprised of other various media specifications such as fonts and images.
The `pop-pdf` attempts to present all of these various components in an intuitive, object-oriented
way so that a developer can assemble, build and compile valid PDF documents programmatically.

The main `Pop\Pdf\Pdf` class serves as a simple processing class with a set of static methods
to route the various object components to the right place to be processed.

- `writeToFile($document, $filename = 'pop.pdf'): void`
- `outputToHttp($document, $filename = 'pop.pdf', $forceDownload = false, $headers = []): void`
- `importFromFile($file, $pages = null): AbstractDocument`
- `importRawData($data, $pages = null): AbstractDocument`
- `importFromImages($images, $quality = 70): AbstractDocument`
- `extractAsImages($file, $location, $format = 'jpg', $resolution = 300, $filenameFormat = '%1$s-%2$02d', $pages = null, $pageLimit = null): array`
- `merge($files): AbstractDocument`
- `mergeRawData($data): AbstractDocument`
- `extractTextFromFile($file, $pages = null, $pageLimit = null): string`
- `extractTextFromData($data, $pages = null, $pageLimit = null): string`
- `isImageOnlyDocument($file, $pages = null, $pageLimit = null): bool`
- `isImageOnlyData($data, $pages = null, $pageLimit = null): bool`
- `getImageOnlyPages($file, $pages = null, $pageLimit = null): array`
- `getImageOnlyPagesFromData($data, $pages = null, $pageLimit = null): array`

### Write to File

Once a PDF document has been assembled, you can pass it to the `writeToFile()` method to
compile the PDF and save it to a file on disk:

```php
use Pop\Pdf\Pdf;

// Pass a valid document object and a path/filename
Pdf::writeToFile($document, 'path/to/my-document.pdf');
```

### Output to HTTP

Alternatively, you can push the PDF document out to an HTTP client. Giving it a filename
sets the `Content-Disposition` filename value. Setting `$forceDownload` to true sets
the `Content-Disposition` value to "attachment" to force a download (vs display inline.)
A fourth `$headers` parameter is available to output any additional HTTP headers. 

```php
use Pop\Pdf\Pdf;

// Pass a valid document object and a path/filename
Pdf::outputToHttp($document, 'my-document.pdf', true);
```

### Import from File

You can take an existing PDF and import it to add new content to it. It will translate
the PDF document's content into the appropriate objects such as pages, fonts, images
and text. From there, you can add more content to the PDF document object and save it.

```php
use Pop\Pdf\Pdf;

$doc = Pdf::importFromFile('path/to/document.pdf');
```

You can also choose which pages of a PDF document to import:

```php
use Pop\Pdf\Pdf;

// Import pages 2, 4 and 6 from the PDF document
$doc = Pdf::importFromFile('path/to/document.pdf', [2, 4, 6]);
```

### Import from Raw Data

If you have a stream of raw data from a PDF file, you can import that as well.
This method supports optional page selection as well

```php
use Pop\Pdf\Pdf;

$doc = Pdf::importRawData($rawData, [2, 4, 6]);
```

### Import from Images

If you have an array of images, you can convert them into a PDF document object
where each image becomes a page in the PDF document.

```php
use Pop\Pdf\Pdf;

$doc = Pdf::importFromImages($arrayOfImages);
```

### Extract as Images

The opposite of importing from images: `extractAsImages()` rasterizes the pages of an existing PDF
into standalone image files, one per page, via [Imagick](https://www.php.net/manual/en/book.imagick.php).
It requires the `ext-imagick` PHP extension (listed as a suggested dependency, not a hard requirement,
since it isn't installed everywhere) and works regardless of whether a page is text, vector graphics,
a scanned image, or any mix — Imagick's PDF delegate rasterizes the page's full content directly.

```php
use Pop\Pdf\Pdf;

// Writes one image per page into 'path/to/output/', e.g. document-01.jpg, document-02.jpg, etc.
$images = Pdf::extractAsImages('path/to/document.pdf', 'path/to/output/');

// $images is keyed by 1-based page number:
// [1 => 'path/to/output/document-01.jpg', 2 => 'path/to/output/document-02.jpg', ...]
```

`$format` (`jpg`, `jpeg`, `png`, `webp`, `tiff` or `tif`) and `$resolution` (output DPI, defaulting to 300 - a
good baseline for OCR) can be customized, and the same `$pages`/`$pageLimit` filters as the text-extraction
methods below are supported:

```php
use Pop\Pdf\Pdf;

// Extract pages 1 and 3 only, as PNGs
$images = Pdf::extractAsImages('path/to/document.pdf', 'path/to/output/', 'png', 300, pages: [1, 3]);
```

`$filenameFormat` is a `sprintf()` format string given the source file's basename and the 1-indexed page
number, letting you control the output filenames without the basename or with different separators/padding:

```php
// 'page-01.jpg', 'page-02.jpg', ...
$images = Pdf::extractAsImages('path/to/document.pdf', 'path/to/output/', filenameFormat: 'page-%2$02d');

// 'page_1.jpg', 'page_2.jpg', ... (no zero-padding)
$images = Pdf::extractAsImages('path/to/document.pdf', 'path/to/output/', filenameFormat: 'page_%2$d');
```

JPEG and WebP output (both lossy by default) are written at a fixed high compression quality (90) to avoid
artifacts that hurt OCR accuracy; PNG and TIFF are already lossless and unaffected.

Each requested page is rasterized and written to disk individually, so memory use stays flat
regardless of how many pages the source PDF has.

### Merge

You can merge multiple, separate PDF files into a single document object. Each source file's pages
are appended in order:

```php
use Pop\Pdf\Pdf;

$doc = Pdf::merge(['path/to/one.pdf', 'path/to/two.pdf', 'path/to/three.pdf']);

Pdf::writeToFile($doc, 'merged.pdf');
```

You can also merge from an array of raw PDF data streams instead of file paths:

```php
use Pop\Pdf\Pdf;

$doc = Pdf::mergeRawData([$pdfStream1, $pdfStream2]);
```

### Extract Text

If you just want to extract the text from a PDF that contains text (not a PDF comprised of images with text in them),
you can do so like this:

```php
use Pop\Pdf\Pdf;

$text = Pdf::extractTextFromFile('path/to/document.pdf');
```

```php
use Pop\Pdf\Pdf;

$text = Pdf::extractTextFromData($pdfStream);
```

Text extraction is entirely native to `pop-pdf` — including PDFs that use embedded fonts, not just
the 14 standard PDF fonts — so no third-party PDF-parsing library is required.

Both methods also accept an optional `$pages` parameter (an int or array of 1-based page numbers) to
extract text from only certain pages, and an optional `$pageLimit` to cap how many pages are walked —
useful to bound processing time/memory on very large documents:

```php
use Pop\Pdf\Pdf;

// Extract text from pages 1, 2 and 3 only
$text = Pdf::extractTextFromFile('path/to/document.pdf', [1, 2, 3]);

// Extract text, but never walk past the first 50 pages
$text = Pdf::extractTextFromFile('path/to/document.pdf', null, 50);
```

[Top](#pop-pdf)

### Image-Only Detection

You can determine whether a PDF's pages are nothing but a single scanned/drawn image with no
extractable text — useful for routing a document to OCR before attempting text extraction on it.

```php
use Pop\Pdf\Pdf;

if (Pdf::isImageOnlyDocument('path/to/document.pdf')) {
    // every page is just a single full-page image - send it to OCR
}
```

`isImageOnlyData()` performs the same check against a raw PDF data stream instead of a file:

```php
use Pop\Pdf\Pdf;

$isImageOnly = Pdf::isImageOnlyData($pdfStream);
```

Both accept the same optional `$pages` and `$pageLimit` parameters as the text-extraction methods above.

If you need a per-page breakdown rather than a single whole-document answer, use `getImageOnlyPages()`
(or `getImageOnlyPagesFromData()` for raw data). Each returns an array keyed by 0-based page index, with
a boolean value indicating whether that specific page is image-only:

```php
use Pop\Pdf\Pdf;

$pages = Pdf::getImageOnlyPages('path/to/document.pdf');
// [0 => true, 1 => false, 2 => true]
```

[Top](#pop-pdf)

Documents
---------

The document object serves as the main collection object of all of the components that go into
building and compiling a PDF document. This includes pages, fonts and forms.

### Compression

A PDF document can be compressed if needed to attempt to reduce file size.

```php
use Pop\Pdf\Document;

$document = new Document();
$document->setCompression(true);
```

### Page Origin

A potentially confusing aspect of PDF documents is that the default page origin is the bottom left.
This means that all coordinates and any math based on the coordinates has to be calculated from the
bottom left.

If you'd prefer to calculate the origin from a different place, you can set that with the `setOrigin()`
method on the document object. This will automatically translate your preferred origin to the native
PDF origin.

Options for setting the origin of the document are:

* `ORIGIN_TOP_LEFT`
* `ORIGIN_TOP_RIGHT`
* `ORIGIN_BOTTOM_LEFT`
* `ORIGIN_BOTTOM_RIGHT`
* `ORIGIN_CENTER`

```php
use Pop\Pdf\Document;

$document = new Document();
$document->setOrigin(Document::ORIGIN_TOP_LEFT);
```

[Top](#pop-pdf)

Pages
-----

Pages can be virtually any size, but there are a number of pre-defined sizes available
as constants in the `Pop\Pdf\Document\Page` class:

| Page          | (W x H)       | Page  | (W x H)       | Page  | (W x H)       |
|---------------|---------------|-------|---------------|-------|---------------|
| `ENVELOPE_10` | (297  x 684)  | `A1`  | (1684 x 2384) | `B1`  | (2064 x 2920) |
| `ENVELOPE_C5` | (461  x 648)  | `A2`  | (1191 x 1684) | `B2`  | (1460 x 2064) |
| `ENVELOPE_DL` | (312  x 624)  | `A3`  | (842  x 1191) | `B3`  | (1032 x 1460) |
| `FOLIO`       | (595  x 935)  | `A4`  | (595  x 842)  | `B4`  | (729  x 1032) |
| `EXECUTIVE`   | (522  x 756)  | `A5`  | (420  x 595)  | `B5`  | (516  x 729)  |
| `LETTER`      | (612  x 792)  | `A6`  | (297  x 420)  | `B6`  | (363  x 516)  |
| `LEGAL`       | (612  x 1008) | `A7`  | (210  x 297)  | `B7`  | (258  x 363)  |
| `LEDGER`      | (1224 x 792)  | `A8`  | (148  x 210)  | `B8`  | (181  x 258)  |
| `TABLOID`     | (792  x 1224) | `A9`  | (105  x 148)  | `B9`  | (127  x 181)  |
| `A0`          | (2384 x 3370) | `B0`  | (2920 x 4127) | `B10` | (91   x 127)  |

```php
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;

$pageLetter = new Page(Page::LETTER);
$pageCustom = new Page(500, 1000); // Custom width and height

$document = new Document();
$document->addPages([$pageLetter, $pageCustom]);
```

Alternatively, you can use the document object as a page factory, which will create a page
object, automatically add the page to the document object and return the new page:

```php
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;

$document  = new Document();
$pageLegal = $document->createPage(Page::LEGAL);
```

There are a number of other methods within the document object to assist with managing
various components:

- `addPage(Page $page): Document`
- `addPages(array $pages): Document`
- `createPage(mixed $size, ?int $height = null): Page`
- `copyPage(int $p, bool $preserveContent = true): Page`
- `orderPages(array $pages): Document`
- `deletePage(int $p): Document`
- `addFont(Font|string $font, bool $embedOverride = false): Document`
- `embedFont(Font $font, bool $embedOverride = false): Document`
- `setCurrentPage(int $p): Document`
- `setCurrentFont(string $name): Document`

[Top](#pop-pdf)

Fonts
-----

Fonts are required to be added to a document for any text that might be added to any page.
The font that a text object uses will be defined when adding the text to a page object, but
that font will need to be present in the document object. Once fonts are added to a document,
they can be used repeatedly by any text objects on any pages of the document.

There are two types of supported fonts: **standard** and **embedded**.

[Top](#pop-pdf)

### Standard

Part of the PDF specification is that a total of 25 standard fonts that are supported
by PDF and PDF readers. This means that no additional font files have to be embedded and
the fonts are available by default.

|                 | Standard PDF Fonts  |                        |
|-----------------|---------------------|------------------------|
|Arial            |CourierNew,Bold      |Times-Bold              |
|Arial,Italic     |Courier-BoldOblique  |Times-Italic            |
|Arial,Bold       |CourierNew,BoldItalic|Times-BoldItalic        |
|Arial,BoldItalic |Helvetica            |TimesNewRoman           |
|Courier          |Helvetica-Oblique    |TimesNewRoman,Italic    |
|CourierNew       |Helvetica-Bold       |TimesNewRoman,Bold      |
|Courier-Oblique  |Helvetica-BoldOblique|TimesNewRoman,BoldItalic|
|CourierNew,Italic|Symbol               |ZapfDingbats            |
|Courier-Bold     |Times-Roman          |                        |

References to each of these standard fonts are available as constants on the main
font class, `Pop\Pdf\Document\Font`:

- `Font::ARIAL`
- `Font::ARIAL_ITALIC`
- `Font::ARIAL_BOLD`
- `Font::ARIAL_BOLD_ITALIC`
- `Font::COURIER`
- `Font::COURIER_OBLIQUE`
- `Font::COURIER_BOLD`
- `Font::COURIER_BOLD_OBLIQUE`
- `Font::COURIER_NEW`
- `Font::COURIER_NEW_ITALIC`
- `Font::COURIER_NEW_BOLD`
- `Font::COURIER_NEW_BOLD_ITALIC`
- `Font::HELVETICA`
- `Font::HELVETICA_OBLIQUE`
- `Font::HELVETICA_BOLD`
- `Font::HELVETICA_BOLD_OBLIQUE`
- `Font::SYMBOL`
- `Font::TIMES_ROMAN`
- `Font::TIMES_BOLD`
- `Font::TIMES_ITALIC`
- `Font::TIMES_BOLD_ITALIC`
- `Font::TIMES_NEW_ROMAN`
- `Font::TIMES_NEW_ROMAN_ITALIC`
- `Font::TIMES_NEW_ROMAN_BOLD`
- `Font::TIMES_NEW_ROMAN_BOLDITALIC`
- `Font::ZAPF_DINGBATS`

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Text;

$document = new Document();
$document->addFont(Font::HELVETICA_BOLD);

$page = $document->createPage(Page::LETTER);
$page->addText(new Text('Hello World', 12), Font::HELVETICA_BOLD, 50, 742);

Pdf::writeToFile($document, 'my-document.pdf');
```

[Top](#pop-pdf)

### Embedded

If you require a font outside of the set of standard fonts, the PDF specification
supports embedding a number of different external font formats:

* TrueType (ttf)
* OpenType (otf)
* Type1 (pfb)

Most fonts of these types should work, but there are situations were the font may not
be parsable, such as when a font's embeddable flag is set to false.

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;

$font     = new Font('/path/to/some/font.ttf');
$document = new Document();
$document->embedFont($font);

$page = new Page(Page::LETTER);
$page->addText(new Page\Text('Hello World', 36), $font->getName(), 50, 600);

$document->addPage($page);

Pdf::writeToFile($document, 'my-document.pdf');
```

#### Unicode and embedded fonts

Embedded TrueType/OpenType fonts are compiled into the PDF as composite
(`/Type0` + `/Identity-H` + `/CIDFontType2`) fonts, with a `/ToUnicode` CMap so the
resulting text still copies and extracts correctly. Text is emitted as glyph-ID hex
strings rather than single-byte literals.

The practical effect is that **any script the embedded font has glyphs for now renders
correctly** — Cyrillic, Greek, and so on — instead of the mojibake produced by the old
single-byte (`/WinAnsiEncoding`) output:

```php
$font     = new Font('/path/to/DejaVuSans.ttf'); // a Unicode-capable font
$document = new Document();
$document->embedFont($font);

$page = new Page(Page::LETTER);
$page->addText(new Page\Text('123 ПРИВІТ:', 36), $font->getName(), 50, 600);
```

Notes and caveats:

* This changes the **compiled byte structure** of any document that uses an embedded
  TrueType/OpenType font. No PHP method signatures changed, but code that diffs or
  post-processes exact generated PDF bytes will see different (more correct) output.
* Embedded **Type1** (`.pfb`) fonts and the standard fonts are unchanged — they stay on
  the single-byte encoding path.
* Fonts are embedded in full; there is no glyph subsetting, so a large Unicode font
  makes for a large PDF.
* Text alignment (`Text\Alignment`) and box wrapping (`Text\Wrap`) work with embedded
  Unicode fonts and non-Latin text. Character wrapping (`Text::setCharWrap()`) does not —
  it splits on byte counts and throws `Build\Font\Exception` for a CID font.

#### Unsupported characters now throw

If text contains a character the active font has no glyph for, a
`Pop\Pdf\Build\Font\Exception` is thrown at compile time, naming the font, the character
and its codepoint:

```
Error: The font 'Arial' does not contain a glyph for character 'П' (U+041F).
```

This is a deliberate behavior change: previously such characters were silently written
out and rendered as garbage. The standard (base-14) fonts contain no Cyrillic, Greek or
CJK glyphs at all, so non-Latin text now requires an embedded Unicode font.

Standard fonts also now correctly render any character their `/WinAnsiEncoding` cmap
covers — accented Latin letters, curly quotes, en/em dashes, and similar Latin-1/
Windows-1252 punctuation (`café`, `'quoted'`, `†`, etc.). Previously these were silently
written as raw UTF-8 bytes into a single-byte string and mojibaked in any PDF viewer even
though the font could represent them; text is now transcoded to `/WinAnsiEncoding` before
being written. Only characters truly outside the font's encoding throw.

Also note that importing HTML containing non-Latin text (`Pdf::importFromHtml()`) does
not currently work, due to a double-encoding bug in the upstream `popphp/pop-dom`
dependency that mangles non-ASCII text before `pop-pdf` receives it.

[Top](#pop-pdf)

Text
----

Once font objects have been added to a document object, text objects can then be added
to page objects, while referencing the available font objects in the document.

The constructor of the text object takes the string and the size:

```php
use Pop\Pdf\Document\Page\Text;

$text = new Text('Hello World', 12);
```

There are a number of methods to assist in modifying the text object:

- `setSize(int|float $size): Text`
- `setFillColor(ColorInterface $color): Text`
- `setStrokeColor(ColorInterface $color): Text`
- `setStroke(int $width, ?int $dashLength = null, ?int $dashGap = null): Text`
- `setRotation(int $rotation): Text`
- `setCharWrap(int $charWrap, ?int $leading = null): Text`
- `setLeading(int $leading): Text`

A basic character wrap can be set with the `setCharWrap()` method. The leading of the
wrapped text can be either set with the second parameter or by the `setLeading()` method.

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Text;

$document = new Document();
$document->addFont(Font::ARIAL);

$longString = 'Lorem ipsum [...really long string...] anim id est laborum.';
$text = new Text($longString, 12);
$text->setCharWrap(80, 16); // Set the wrap at 80 characters and a leading of 16

$page = $document->createPage(Page::LETTER);
$page->addText($text, Font::ARIAL, 50, 742);

Pdf::writeToFile($document, 'my-document.pdf');
```

[Top](#pop-pdf)

Styles
------

Style objects can be added to the document to provide easier management of text and font styles used in multiple
places across the PDF document and its pages.

```php
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;

$document = new Document();
$document->addFont(Font::ARIAL);
$document->createStyle('normal', Font::ARIAL, 12);

$page = $document->createPage(Page::LETTER);
$page->addText($text, 'normal', 50, 742); // The second parameter can either be a font or a reference to a style
```

So any text added to any page referencing the same style can easily be changed across the entire document by only
changing the style object.

[Top](#pop-pdf)

### Alignment

Alignment objects are objects that assist with handling more advanced alignment
and wrapping of text based on geometric positioning. When creating an alignment
object, you define a bounding areas to which the text will be confined.

**Left-aligned box**

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Text;
use Pop\Pdf\Document\Page\Text\Alignment;

$document = new Document();
$document->addFont(Font::ARIAL);

$longString = 'Lorem ipsum [...really long string...] anim id est laborum.';
$text = new Text($longString, 12);

// Create a left-aligned bounding area with the
// X between 50 and 350; leading set 16
$text->setAlignment(Alignment::createLeft(50, 350, 16));

$page = $document->createPage(Page::LETTER);
$page->addText($text, Font::ARIAL, 50, 742);

Pdf::writeToFile($document, 'my-document.pdf');
```

**Right-aligned box**

```php
// Create a right-aligned bounding area with the
// X between 250 and 550; leading set 16
$text->setAlignment(Alignment::createRight(250, 550, 16));
```

**Center-aligned box**

```php
// Create a center-aligned bounding area with the
// X between 50 and 350; leading set 16
$text->setAlignment(Alignment::createCenter(200, 412, 16));
```

[Top](#pop-pdf)

### String Width

An important and useful tool with working with text and fonts to the ability
to calculate the width of a string of characters rendered in a particular font.
This is very helpful when attempting to correctly position text on the page.

There is a method on the font object that will allow you pass a string of text
to it, as well as the desired size, to give you the approximate width those characters
will take up rendered in that font at that size.

This works for both standard and embedded fonts.

```php
use Pop\Pdf\Document\Font;

$font  = new Font(Font::HELVETICA_BOLD);
$width = $font->getStringWidth('Hello World', 12);
var_dump($width);
```

This will give us the approximate width in points of the string `Hello World` in
12pt Helvetica Bold:

```text
float(66.672)
```

[Top](#pop-pdf)

Images
------

Images can be easily added to page objects. However, in a PDF document, the origin of an
image is the bottom of the image. You will have to consider how the image's height affects
the placement of the image on the page in relation to the page origin.

In this example below, the image is 320 x 320. If you place the `$y` value at 742
(top origin 792 - 50), then only the bottom 50 pixels of the image would display
at the top of the page, while the remainder bleeds off the top page border. Therefore,
the height should be taken into account and the `$y` value should be a value like 422
(top origin 792 - 50 - 320). This would make the image appear with the top of it starting
at 50 pixels from the top of the page, and you would be able to safely see the entire
image on the page.

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Image;

$document = new Document();
$page     = $document->createPage(Page::LETTER);
$page->addImage(Image::createImageFromFile('my-image.jpg'), 50, 422);

Pdf::writeToFile($document, 'my-document.pdf');
```

In the above example, the image is pulled from a file. You can also import an image from a
raw stream:

```php
$page->addImage(Image::loadImageFromStream($imageContents), 50, 422);
```

As a shortcut, `addImage()` also accepts a file path directly, creating the `Image` object
for you:

```php
$page->addImage('my-image.jpg', 50, 422);
```

[Top](#pop-pdf)

### Image Size

You can resize a larger image when adding it to a page. 

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Image;

$image = Image::createImageFromFile('my-image.jpg');
$image->resizeToWidth(120);

$document = new Document();
$page     = $document->createPage(Page::LETTER);
$page->addImage($image, 50, 622);

Pdf::writeToFile($document, 'my-document.pdf');
```

The following methods are available to resize an image:

- `resizeToWidth(int $width, bool $preserveResolution = false): Image`
- `resizeToHeight(int $height, bool $preserveResolution = false): Image`
- `resize(int $pixel, bool $preserveResolution = false): Image`
- `scale(float $scale, bool $preserveResolution = false): Image`

The `$preserveResolution` flag is set to `false` by default. This will
resize the image resource, which will reduce it in not only dimensional size,
but also reduce its data size as well.

If you wish to keep the image in its original higher quality, and
only reduce the dimensions, you can set the `$preserveResolution` flag
to `true`. This is typically a good method to keep the image clean and crisp
when being reduced to a smaller dimension.

[Top](#pop-pdf)

Paths
-----

You can add path objects to a page to draw vector lines and shapes on the page object.

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Path;
use Pop\Color\Color;

$document = new Document();

$path = new Path(Path::FILL_STROKE);
$path->setFillColor(Color::rgb(155, 20, 20))
    ->setStrokeColor(Color::rgb(81, 125, 153))
    ->setStroke(5)
    ->drawRectangle(50, 400, 320, 240);

$page = new Page(Page::LETTER);
$page->addPath($path);

$document->addPage($page);

Pdf::writeToFile($document, 'my-document.pdf');
```

The methods to control color and style include:

- `setFillColor(Color\ColorInterface $color): Path`
- `setStrokeColor(Color\ColorInterface $color): Path`
- `setStroke(int $width, ?int $dashLength = null, ?int $dashGap = null): Path`
- `setStyle(string $style): Path`

The `setStyle()` method can take one of the available style constants as its parameter:

- `Path::STROKE`
- `Path::STROKE_CLOSE`
- `Path::FILL`
- `Path::FILL_EVEN_ODD`
- `Path::FILL_STROKE`
- `Path::FILL_STROKE_EVEN_ODD`
- `Path::FILL_STROKE_CLOSE`
- `Path::FILL_STROKE_CLOSE_EVEN_ODD`
- `Path::CLIPPING`
- `Path::CLIPPING_FILL`
- `Path::CLIPPING_NO_STYLE`
- `Path::CLIPPING_EVEN_ODD`
- `Path::CLIPPING_EVEN_ODD_FILL`
- `Path::CLIPPING_EVEN_ODD_NO_STYLE`
- `Path::NO_STYLE`

The basic methods available to draw paths and shapes are:

- `drawLine(int $x1, int $y1, int $x2, int $y2): Path`
- `drawRectangle(int $x, int $y, int $w, ?int $h = null): Path`
- `drawRoundedRectangle(int $x, int $y, int $w, ?int $h = null, int $rx = 10, ?int $ry = null): Path`
- `drawSquare(int $x, int $y, int $w): Path`
- `drawRoundedSquare(int $x, int $y, int $w, int $rx = 10, ?int $ry = null): Path`
- `drawPolygon(array $points): Path`
- `drawEllipse(int $x, int $y, int $w, ?int $h = null): Path`
- `drawCircle(int $x, int $y, int $w): Path`
- `drawArc(int $x, int $y, int $start, int $end, int $w, ?int $h = null): Path`
- `drawChord(int $x, int $y, int $start, int $end, int $w, ?int $h = null): Path`
- `drawPie(int $x, int $y, int $start, int $end, int $w, ?int $h = null): Path`

Each shape paints itself in the path's style as it is drawn.

### Composed Paths

Several outlines can be painted together as a single path instead, which is what an even-odd fill
needs to cut a hole - the rule reads every outline on the same path to decide what is inside. Open
the path, draw the outlines, and paint them in one go:

```php
use Pop\Pdf\Document\Page\Path;
use Pop\Color\Color;

$path = new Path(Path::FILL_EVEN_ODD);
$path->setFillColor(Color::rgb(0, 102, 204));

$path->openPath();
$path->drawPolygon([
    ['x' => 150, 'y' => 250], ['x' => 450, 'y' => 250],
    ['x' => 450, 'y' => 550], ['x' => 150, 'y' => 550]
]);
$path->drawPolygon([
    ['x' => 250, 'y' => 350], ['x' => 350, 'y' => 350],
    ['x' => 350, 'y' => 450], ['x' => 250, 'y' => 450]
]);
$path->paintPath();
```

That fills the outer 300-point square and leaves the inner 100-point square blank. Swapping the style
for `Path::FILL` fills the same two outlines solid.

- `openPath(): Path`
- `paintPath(): Path`

[Top](#pop-pdf)

Annotations
-----------

Annotation objects provide a way to link to external URLs or an internal pointer 
within the document.

### URLs

The following example will generate an invisible annotation box area over the text
`Visit Google` that links to Google's home page:

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Text;
use Pop\Pdf\Document\Page\Annotation\Url;

$document = new Document();
$document->addFont(Font::ARIAL);

$page = $document->createPage(Page::LETTER);
$page->addText(new Text('Visit Google', 12), Font::ARIAL, 50, 742);

$page->addUrl(new Url(100, 15, 'https://www.google.com/'), 50, 742);
Pdf::writeToFile($document, 'my-document.pdf');
```

[Top](#pop-pdf)

### Internal

The following example will add 2 pages to the document and link from the first page to
the second page. When creating an internal link, you can define the following:

- The X and Y coordinates to navigate to
- The Z (Zoom) target
- The page target

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Text;
use Pop\Pdf\Document\Page\Annotation\Link;

$document = new Document();
$document->addFont(Font::ARIAL);

$page1 = $document->createPage(Page::LETTER);
$page1->addText(new Text('This is an internal link', 12), Font::ARIAL, 50, 742);
$page2 = $document->createPage(Page::LETTER);
$page2->addText(new Text('This is the destination', 12), Font::ARIAL, 50, 742);

// Create a link to page 2 and set the zoom to 110%
$link = new Link(120, 15, 10, 752);
$link->setPageTarget(2)
    ->setZTarget(110);
    
$page1->addLink($link, 50, 742);

Pdf::writeToFile($document, 'my-document.pdf');
```

[Top](#pop-pdf)

Forms
-----

Forms and form fields are supported in Pop PDF, however, please note that not all browsers
consistently support forms and form fields in their default PDF readers. It is recommended
that if you generate a PDF with a form in it using Pop PDF, that your end user views it
in an Adobe product.

The types of fields that are currently supported in Pop PDF are:

- Single-line text fields
- Multi-line text fields
- Single-select choice fields (e.g., an HTML select drop-down)
- Multi-select choice fields (e.g., an HTML multi-select drop-down)
- Push buttons (by default, display and act like a checkbox)
- Radio buttons

*NOTE: A group of radio buttons is not supported at this time.*

The following script below demonstrates how to add the various fields to a form in a
PDF object. While lengthy, it includes text and graphic support for field names and
borders:

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Form;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Path;
use Pop\Pdf\Document\Page\Text;

$form = new Form('contact_form');

$document = new Document();
$document->addForm($form);
$document->addFont(new Font(Font::ARIAL));
$document->addFont(new Font(Font::ARIAL_BOLD));

$firstName = new Page\Field\Text('first_name');
$firstName->setWidth(200)
    ->setHeight(20);

$lastName = new Page\Field\Text('last_name');
$lastName->setWidth(200)
    ->setHeight(20);

$colors = new Page\Field\Choice('colors');
$colors->addOption('Red')
    ->addOption('Green')
    ->addOption('Blue')
    ->setMultiSelect()
    ->setWidth(200)
    ->setHeight(50)
    ->setFont(Font::ARIAL)
    ->setSize(11);

$city = new Page\Field\Choice('city');
$city->addOption('New Orleans')
    ->addOption('New York')
    ->addOption('Los Angeles')
    ->setCombo()
    ->setWidth(200)
    ->setHeight(20)
    ->setFont(Font::ARIAL)
    ->setSize(11);

$lovePhp = new Page\Field\Button('love_php');
$lovePhp->addOption('PHP')->setWidth(20)
    ->setHeight(20);

$lovePdf = new Page\Field\Button('love_pdf');
$lovePdf->addOption('PDF')->setRadio()
    ->setWidth(20)
    ->setHeight(20);

$comments = new Page\Field\Text('comments');
$comments->setWidth(500)
    ->setHeight(150)
    ->setMultiline();

$page = new Page(Page::LETTER);

$page->addText(new Text('First Name:', 14), Font::ARIAL_BOLD, 50, 680);
$page->addText(new Text('Last Name:', 14), Font::ARIAL_BOLD, 300, 680);
$page->addText(new Text('Favorite Colors?', 14), Font::ARIAL_BOLD, 50, 580);
$page->addText(new Text('Favorite City?', 14), Font::ARIAL_BOLD, 300, 580);
$page->addText(new Text('Love PHP?', 14), Font::ARIAL_BOLD, 80, 330);
$page->addText(new Text('Love PDF?', 14), Font::ARIAL_BOLD, 80, 290);
$page->addText(new Text('Comments:', 14), Font::ARIAL_BOLD, 50, 260);
$page->addPath((new Path())->drawRectangle(50, 650, 200, 20));
$page->addPath((new Path())->drawRectangle(300, 650, 200, 20));
$page->addPath((new Path())->drawRectangle(50, 520, 200, 50));
$page->addPath((new Path())->drawRectangle(300, 550, 200, 20));
$page->addPath((new Path())->drawSquare(50, 325, 20));
$page->addPath((new Path())->drawCircle(60, 295, 10));
$page->addPath((new Path())->drawRectangle(50, 100, 500, 150));

$page->addField($firstName, 'contact_form', 50, 650)
    ->addField($lastName, 'contact_form', 300, 650)
    ->addField($colors, 'contact_form', 50, 520)
    ->addField($city, 'contact_form', 300, 550)
    ->addField($lovePhp, 'contact_form', 50, 325)
    ->addField($lovePdf, 'contact_form', 50, 285)
    ->addField($comments, 'contact_form', 50, 100);

$document->addPage($page);

Pdf::writeToFile($document, 'my-document.pdf');
```

The above code produces a PDF with a form like this:

![Pop PDF Form](tests/tmp/pop-pdf-form.jpg)

[Top](#pop-pdf)

HTML
-----

HTML rendering is available in `pop-pdf`, however, please note that while it will attempt preserve the style and flow
of the HTML within the PDF pages, there are limitations in attempting to constrain and conform HTML that renders
fluidly with in a browser window to the boundaries of a PDF page. Support is broadest for the patterns shown below.
Less common CSS properties and nested block markup may not be fully supported yet.

### Parsing HTML from a file:

If you have an HTML file, it will parse all of the HTML in it, as well as any linked CSS and images:

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Build\Html\Parser;

$document = new Document();
$document->addFont(Font::ARIAL);
$page = $document->createPage(Page::LETTER);

$parser = new Parser($document);
$parser->parseHtmlFile('test.html');
$parser->process();

Pdf::writeToFile($parser->document(), 'my-document.pdf');
```

You can also parse HTML and CSS strings directly. The directory path is needed to give the
parser a base folder to attempt to access other assets, such as images.

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Build\Html\Parser;

$html = <<<HTML
<h1>Hello World!</h1>
HTML;

$css = <<<CSS
h1 {
font-family: sans-serif;
color: #f00;
font-weight: normal;
}
h3 {
font-family: serif;
color: #009dff;
}
.red {
font-weight: bold;
color: #f00;
}
.img-med {
    width: 200px;
}
CSS;

$document = new Document();
$document->addFont(Font::ARIAL);
$page = $document->createPage(Page::LETTER);

$parser = new Parser($document);
$parser->parseHtml($html, __DIR__);
$parser->parseCss($css);
$parser->process();

Pdf::writeToFile($parser->document(), 'my-document.pdf');
```

CSS can also be embedded directly in the HTML - either in a `<style>` block or as an inline `style="..."`
attribute on an element, with inline styles taking precedence over `<style>`/linked/tag/class/id rules:

```php
$html = <<<HTML
<style>
h1 { color: #f00; }
</style>
<h1>Hello World!</h1>
<p style="color: #009dff; font-weight: bold;">Inline styles are supported too.</p>
HTML;

$parser = new Parser($document);
$parser->parseHtml($html, __DIR__);
$parser->process();
```

### Tables

`<table>` elements are supported, including `colspan`/`rowspan`, and header rows (`<thead>`, or a row whose
cells are all `<th>`) that automatically repeat if the table splits across a page break. Column widths are
calculated from cell content, honoring an explicit CSS or HTML `width` first and distributing the remaining
space proportionally across the rest.

```php
$html = <<<HTML
<table>
<thead>
<tr><th>Item</th><th>Quantity</th><th>Price</th></tr>
</thead>
<tbody>
<tr><td colspan="2">Widgets (assorted)</td><td>$19.99</td></tr>
<tr><td rowspan="2">Bulk Order</td><td>100</td><td>$4.50</td></tr>
<tr><td>250</td><td>$4.00</td></tr>
</tbody>
</table>
HTML;

$css = <<<CSS
th { background-color: #dddddd; border-width: 1px; border-color: #333333; }
td { border-width: 1px; border-color: #333333; }
CSS;

$document = new Document();
$document->addFont(Font::ARIAL);
$page = $document->createPage(Page::LETTER);

$parser = new Parser($document);
$parser->parseHtml($html, __DIR__);
$parser->parseCss($css);
$parser->process();

Pdf::writeToFile($parser->document(), 'my-document.pdf');
```

`border-width`/`border-color`/`background-color` CSS properties work the same way on any element, not just
table cells (e.g. a bordered `<div>`). Given a PDF page's fixed size (unlike a browser's scrolling viewport),
a few limitations apply: no `border-collapse` (each cell's border is drawn independently), no nested tables,
and a single row/cell taller than a full page renders best-effort on that page rather than splitting across a
page break.

[Top](#pop-pdf)
