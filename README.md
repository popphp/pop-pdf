Pop PDF
=======

[![Build Status](https://travis-ci.org/popphp/pop-pdf.svg?branch=master)](https://travis-ci.org/popphp/pop-pdf)

OVERVIEW
--------
Pop PDF is a component of the Pop PHP Framework 2. It is a powerful and robust PDF processing
component that's simple to use. With it, you can create PDF documents from scratch, or import
existing ones and add to or modify them. It supports embedding images, fonts and URLs, as well
as a set of drawing, effect and type features.

INSTALL
-------

Install `Pop PDF` using Composer.

    composer require popphp/pop-pdf

##### A Note About Document Origin
The PDF coordinate system starts with x, y origin (0, 0) at the bottom left. This can be changed by the
user if the user prefers to set the origin to a different point for the purpose of the application.
See the [Set Origin](#set-origin) section for more details on that.

## BASIC USAGE

* [Add a standard font](#add-a-standard-font-and-add-some-text)
* [Embed a font](#embed-a-font-and-add-some-text)
* [Embed an image](#embed-an-image)
* [Draw a Shape](#draw-a-shape)
* [Add a URL link](#add-a-url-link)
* [Import another PDF](#import-from-another-pdf-document)
* [Set Origin](#set-origin)

### Add a standard font and add some text

The standard fonts that are available by default with a PDF document are:

* Arial
* Arial,Italic
* Arial,Bold
* Arial,BoldItalic
* Courier
* CourierNew
* Courier-Oblique
* CourierNew,Italic
* Courier-Bold
* CourierNew,Bold
* Courier-BoldOblique
* CourierNew,BoldItalic
* Helvetica
* Helvetica-Oblique
* Helvetica-Bold
* Helvetica-BoldOblique
* Symbol
* Times-Roman
* Times-Bold
* Times-Italic
* Times-BoldItalic
* TimesNewRoman
* TimesNewRoman,Italic
* TimesNewRoman,Bold
* TimesNewRoman,BoldItalic
* ZapfDingbats

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;

$doc = new Document();
$doc->addFont(new Font('Arial'));

$page = new Page(Page::LETTER);
$page->addText(new Page\Text('Hello World', 36), 'Arial', 50, 600);

$doc->addPage($page);

$pdf = new Pdf();
$pdf->outputToHttp($doc);
```

[Top](#basic-usage)

### Embed a font and add some text

You can embed an external font into a PDF documents. The font types that are supported are:

* TrueType (ttf)
* OpenType (otf)
* Type1 (pfb)

Most fonts of these types should work, but there are situations were the font may not be parsable,
such as when a font's embeddable flag is set to false.

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;

$font = new Font('/path/to/some/font.ttf');

$doc = new Document();
$doc->embedFont($font);

$page = new Page(Page::LETTER);
$page->addText(new Page\Text('Hello World', 36), $font->getName(), 50, 600);

$doc->addPage($page);

$pdf = new Pdf();
$pdf->outputToHttp($doc);
```

[Top](#basic-usage)

### Embed an image

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Image;

$doc = new Document();

$page = new Page(Page::LETTER);
$page->addImage(new Image('/path/to/some/image.jpg'), 100, 600);

$doc->addPage($page);

$pdf = new Pdf();
$pdf->outputToHttp($doc);
```

[Top](#basic-usage)

### Draw a shape

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Path;
use Pop\Pdf\Document\Page\Color;

$doc = new Document();

$path = new Path(Path::FILL_STROKE);
$path->setFillColor(new Color\Rgb(155, 20, 20))
     ->setStrokeColor(new Color\Rgb(81, 125, 153))
     ->setStroke(5)
     ->drawRectangle(50, 400, 320, 240);

$page = new Page(Page::LETTER);
$page->addPath($path);

$doc->addPage($page);

$pdf = new Pdf();
$pdf->outputToHttp($doc);
```

[Top](#basic-usage)

### Add a URL link

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Annotation;

$doc = new Document();

$url = new Annotation\Url(120, 20, 'http://www.google.com/');

$page = new Page(Page::LETTER);
$page->addUrl($url, 50, 500);

$doc->addPage($page);

$pdf = new Pdf();
$pdf->outputToHttp($doc);
```

[Top](#basic-usage)

### Import from another PDF document

```php
use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Image;

// Import pages 2, 4 and 6 from a six page document
$pdf = new Pdf();
$doc = $pdf->importFromFile('/path/to/six-page-document.pdf', [2, 4, 6]);

// Add an image to page 3 (formerly page 6) 
$doc->getPage(3)->addImage(new Image('/path/to/some/image.jpg'), 100, 600);

$pdf->outputToHttp($doc);
```

[Top](#basic-usage)

### Set Origin

Options for setting the origin of the document are:

* ORIGIN_TOP_LEFT
* ORIGIN_TOP_RIGHT
* ORIGIN_BOTTOM_LEFT
* ORIGIN_BOTTOM_RIGHT
* ORIGIN_CENTER

```php
use Pop\Pdf\Document;

$doc = new Document();
$doc->setOrigin(Document::ORIGIN_TOP_LEFT);
```

[Top](#basic-usage)
