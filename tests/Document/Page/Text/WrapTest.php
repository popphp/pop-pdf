<?php

namespace Pop\Pdf\Test\Document\Page\Text;

use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use PHPUnit\Framework\TestCase;

class WrapTest extends TestCase
{

    public function testConstructor()
    {
        $wrap = Page\Text\Wrap::createLeft(50, 550, ['left' => 300, 'right' => 550, 'top' => 732, 'bottom' => 580]);
        $this->assertInstanceOf('Pop\Pdf\Document\Page\Text\Wrap', $wrap);
        $this->assertEquals(4, count($wrap->getBox()));
    }

    public function testCreateRight()
    {
        $wrap = Page\Text\Wrap::createRight(50, 550, ['left' => 50, 'right' => 300, 'top' => 732, 'bottom' => 580]);
        $this->assertTrue($wrap->isRight());
    }

    public function testSetBoxCoordinates()
    {
        $wrap = Page\Text\Wrap::createRight(50, 550);
        $wrap->setBoxCoordinates(50, 300, 732, 580);
        $this->assertEquals(50, $wrap->getBox()['left']);
        $this->assertEquals(300, $wrap->getBox()['right']);
        $this->assertEquals(732, $wrap->getBox()['top']);
        $this->assertEquals(580, $wrap->getBox()['bottom']);
    }

    public function testSetBoxException()
    {
        $this->expectException('InvalidArgumentException');
        $wrap = Page\Text\Wrap::createRight(50, 550, ['left' => 50, 'right' => 300, 'bad' => 732]);
    }

    public function testGetStrings()
    {
        $string    = 'Phasellus vel enim rhoncus, varius arcu vel, malesuada erat. Curabitur tempus ullamcorper magna, non lobortis diam placerat ut. Nulla laoreet ullamcorper purus, consectetur pretium eros porta at. Vivamus nec rutrum leo. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed ut mi eget est eleifend varius ac non dolor. Mauris tincidunt, odio non commodo convallis, ligula nunc congue felis, id molestie metus neque ut orci. Pellentesque eu sagittis eros. Fusce vel luctus erat, a consequat sapien. Maecenas molestie risus quam, in mollis arcu eleifend sed. Integer in libero nisi. Phasellus faucibus pulvinar diam quis porta. Nulla facilisi. Integer aliquam pharetra sapien, eget porta libero ornare facilisis. Etiam condimentum justo massa, nec condimentum nulla lobortis sit amet. Morbi enim arcu, laoreet vitae diam hendrerit, rhoncus luctus tellus. Cras non ultrices dui. Maecenas ornare blandit tellus. Nam eget viverra nisl. Sed accumsan varius tortor non sagittis. Etiam sit amet mollis nunc. Integer mattis elit sed leo tempor tristique. Sed porta metus in efficitur maximus. Curabitur ac diam mattis ex facilisis vehicula nec eu nulla. Duis fringilla viverra massa, id porta diam vulputate eu. Etiam at volutpat quam. Proin lobortis ex sed egestas tincidunt. Donec tincidunt nibh vel tempor mollis. Curabitur metus quam, tristique in eros nec, commodo gravida sem. Integer viverra tristique sodales. Curabitur vehicula volutpat lorem at consectetur. Morbi dapibus purus sed ligula eleifend blandit. Morbi aliquam iaculis arcu sit amet euismod. Donec faucibus risus vitae nisl maximus rhoncus. Duis eu purus non metus ultricies gravida eu vel massa. In varius nisl quis neque interdum, nec fermentum nunc sollicitudin. Fusce tempor scelerisque tortor, sit amet gravida nibh facilisis sed. Aliquam et tempus nulla. Sed ultrices iaculis nibh, eu scelerisque lorem sodales eu. Integer a nibh neque. Suspendisse lorem massa, aliquam ac venenatis nec, blandit eget justo. Morbi malesuada lacinia dui, ut laoreet ligula pharetra sit amet. Mauris tellus tellus, fermentum quis leo ut, pharetra tincidunt quam. Duis quis mi ac orci dictum iaculis. Aenean bibendum ut dui eu sagittis. Etiam lacus sem, mollis dapibus imperdiet ut, facilisis eu ante. Donec non ornare nibh. Nulla placerat, sem at consectetur pharetra, elit purus aliquam tortor, eu consequat nisl nulla nec leo. Nulla facilisi. Cras commodo lacinia magna, eu volutpat sem sollicitudin ac. Etiam tempor ante sed tellus euismod, nec efficitur lectus condimentum. Curabitur a gravida tellus.';
        $text      = new Page\Text($string, 10);
        $arial     = new Font('Arial');
        $wrap      = Page\Text\Wrap::createLeft(50, 550, ['left' => 300, 'right' => 550, 'top' => 732, 'bottom' => 580]);
        $strings   = $wrap->getStrings($text, $arial, 732);

        $this->assertEquals(32, count($strings));
    }

    public function testGetStringsRight()
    {
        $string    = 'Phasellus vel enim rhoncus, varius arcu vel, malesuada erat. Curabitur tempus ullamcorper magna, non lobortis diam placerat ut. Nulla laoreet ullamcorper purus, consectetur pretium eros porta at. Vivamus nec rutrum leo. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed ut mi eget est eleifend varius ac non dolor. Mauris tincidunt, odio non commodo convallis, ligula nunc congue felis, id molestie metus neque ut orci. Pellentesque eu sagittis eros. Fusce vel luctus erat, a consequat sapien. Maecenas molestie risus quam, in mollis arcu eleifend sed. Integer in libero nisi. Phasellus faucibus pulvinar diam quis porta. Nulla facilisi. Integer aliquam pharetra sapien, eget porta libero ornare facilisis. Etiam condimentum justo massa, nec condimentum nulla lobortis sit amet. Morbi enim arcu, laoreet vitae diam hendrerit, rhoncus luctus tellus. Cras non ultrices dui. Maecenas ornare blandit tellus. Nam eget viverra nisl. Sed accumsan varius tortor non sagittis. Etiam sit amet mollis nunc. Integer mattis elit sed leo tempor tristique. Sed porta metus in efficitur maximus. Curabitur ac diam mattis ex facilisis vehicula nec eu nulla. Duis fringilla viverra massa, id porta diam vulputate eu. Etiam at volutpat quam. Proin lobortis ex sed egestas tincidunt. Donec tincidunt nibh vel tempor mollis. Curabitur metus quam, tristique in eros nec, commodo gravida sem. Integer viverra tristique sodales. Curabitur vehicula volutpat lorem at consectetur. Morbi dapibus purus sed ligula eleifend blandit. Morbi aliquam iaculis arcu sit amet euismod. Donec faucibus risus vitae nisl maximus rhoncus. Duis eu purus non metus ultricies gravida eu vel massa. In varius nisl quis neque interdum, nec fermentum nunc sollicitudin. Fusce tempor scelerisque tortor, sit amet gravida nibh facilisis sed. Aliquam et tempus nulla. Sed ultrices iaculis nibh, eu scelerisque lorem sodales eu. Integer a nibh neque. Suspendisse lorem massa, aliquam ac venenatis nec, blandit eget justo. Morbi malesuada lacinia dui, ut laoreet ligula pharetra sit amet. Mauris tellus tellus, fermentum quis leo ut, pharetra tincidunt quam. Duis quis mi ac orci dictum iaculis. Aenean bibendum ut dui eu sagittis. Etiam lacus sem, mollis dapibus imperdiet ut, facilisis eu ante. Donec non ornare nibh. Nulla placerat, sem at consectetur pharetra, elit purus aliquam tortor, eu consequat nisl nulla nec leo. Nulla facilisi. Cras commodo lacinia magna, eu volutpat sem sollicitudin ac. Etiam tempor ante sed tellus euismod, nec efficitur lectus condimentum. Curabitur a gravida tellus.';
        $text      = new Page\Text($string, 10);
        $arial     = new Font('Arial');
        $wrap      = Page\Text\Wrap::createRight(50, 550, ['left' => 50, 'right' => 300, 'top' => 732, 'bottom' => 580]);
        $strings   = $wrap->getStrings($text, $arial, 732);

        $this->assertEquals(32, count($strings));
    }

}
