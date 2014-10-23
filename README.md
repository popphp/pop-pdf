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

$pdf->draw()->circle(100, 200, 50); // Will use the previously set fill color

$pdf->type()->embedFont('myfont.tff')->size(20)->xy(100, 300); // Set the base text parameters then
$pdf->type()->text('Hello World! Line 1.')->y(280)             // add lines of text, only changing
            ->text('Hello World! Line 2.')->y(260)             // the Y as needed.
            ->text('Hello World! Line 3.')->y(240);

// Add a paragraph of text, setting the wrap length and line height.
$pdf->type()->size(12);
$pdf->type()->paragraph($longString, 75, 12);

// Search a PDF for text
$pdf = new Pdf('doc.pdf');
$results = $pdf->searchAll('some keywords'); // Will return false in not found, or an array
                                             // of pages that the keywords are found on.

```
