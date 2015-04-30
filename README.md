Pop PDF
=======
Part of the Pop PHP Framework (http://github.com/popphp/popphp)

OVERVIEW
--------
Pop PDF is a component of the Pop PHP Framework 2. It is a powerful and robust PDF processing
component that's simple to use. With it, you can create PDF documents from scratch, or import
existing ones and add to or modify them. It supports embedding images, fonts and URLs, as well
as a set of drawing, effect and type features.

NOTE
----
The PDF coordinate system starts with x, y (0, 0) at the bottom left.

QUICK USE
---------

Add a standard font and some text:

```php

use Pop\Pdf\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;

$doc = new Document();
$doc->addFont(new Font(Font::ARIAL));

$page = new Page(Page::LETTER);
$page->addText(new Page\Text('Hello World', 36), Font::ARIAL, 50, 600);

$doc->addPage($page);

$pdf = new Pdf();
$pdf->outputToHttp($doc);

```

Embed a font and add some text:

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

Embed an image:

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
