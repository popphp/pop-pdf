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
$pdf->draw()->setFillColor(255, 0, 0)->rectangle(100, 400, 200, 100);

$pdf->type()->embedFont('myfont.tff')->size(24)->xy(100, 300)->text('Hello World!');

$pdf->addImage('image.jpg', 100, 100);
```
