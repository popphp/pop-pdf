Pop PDF
=======
Part of the Pop PHP Framework (http://github.com/popphp/popphp2)

OVERVIEW
--------
Pop PDF is a component of the Pop PHP Framework 2. It is a powerful and robust PDF processing
component that's simple to use. With it, you can create PDF documents from scratch, or import
existing ones and add to or modify them. It supports embedding images, fonts and URLs, as well
as a set of drawing, effect and type features.

NOTE
----
The PDF coordinate system starts with x, y (0, 0) at the bottom left.

INSTALL
-------

Install `Pop Pdf` using Composer.

    composer require popphp/pop-pdf

QUICK USE
---------

```php
use Pop\Pdf\Pdf;
    
$pdf = new Pdf('doc.pdf');
$pdf->addPage(Pdf::SIZE_LETTER);
$pdf->addImage('image.jpg', 100, 100);
$pdf->addUrl(100, 100, 320, 240);
$pdf->output();
```

ADVANCED USE
------------
There are 3 available manipulation objects. They are:

 - Draw
 - Effect
 - Type

```php
use Pop\Pdf\Pdf;

$pdf = new Pdf('doc.pdf');
$pdf->addPage(Pdf::SIZE_LETTER);

$pdf->draw()->setFillColor(255, 0, 0)
            ->rectangle(100, 400, 200, 100);

// Will use the previously set fill color.
$pdf->draw()->circle(100, 200, 50);

// Set the base text parameters then add lines of text,
// only changing the Y as needed.
$pdf->type()->embedFont('myfont.tff')->size(20)->xy(100, 300);
$pdf->type()->text('Hello World! Line 1.')->y(280)
            ->text('Hello World! Line 2.')->y(260)
            ->text('Hello World! Line 3.')->y(240);

// Add a paragraph of text, setting the wrap length and line height.
$pdf->type()->size(12);
$pdf->type()->paragraph($longString, 75, 12);
```

SEARCHING A PDF
---------------
```php
// Search a PDF for text. Will return false in not found,
// or an array of pages that the keywords are found on.
$pdf = new Pdf('doc.pdf');
$results = $pdf->searchAll('some keywords');

// You can extract the text from a PDF as well.
// This will return and array of pages, each containing
// an array of all the text found on that page.
$pdf = new Pdf('doc.pdf');
$text = $pdf->extractText();
```
