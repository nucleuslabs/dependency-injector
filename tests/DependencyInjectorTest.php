<?php

use mpen\DI\DependencyInjector;

class DependencyInjectorTest extends \PHPUnit\Framework\TestCase {


    public function testBasic() {
        $di = new DependencyInjector();
        $foo = $di->construct(Foo::class);
        $this->assertInstanceOf(Foo::class, $foo);
    }

    public function testNested() {
        $di = new DependencyInjector();
        $bar = $di->construct(Bar::class);
        $this->assertInstanceOf(Bar::class, $bar);
        $this->assertInstanceOf(Foo::class, $bar->foo);
    }

    public function testCaching() {
        $di = new DependencyInjector(['cache' => true]);
        $baz = $di->construct(Baz::class);
        $this->assertSame($baz->foo, $baz->bar->foo);

        $di = new DependencyInjector(['cache' => false]);
        $baz = $di->construct(Baz::class);
        $this->assertNotSame($baz->foo, $baz->bar->foo);
    }

    public function testPositionalArgs() {
        $di = new DependencyInjector();
        $quux = $di->construct(Quux::class, [1]);
        $this->assertSame(1, $quux->x);
    }

    public function testCascadingPositionalArgs() {
        $di = new DependencyInjector();
        $corge = $di->construct(Corge::class, [2]);
        $this->assertSame(2, $corge->q->x);
    }

    public function testMissingRequiredPositionalArg() {
        $di = new DependencyInjector();
        $this->expectException(\Exception::class);
        $di->construct(Quux::class);
    }

    public function testMissingOptionalPositionalArg() {
        $di = new DependencyInjector();
        $corge = $di->construct(Corge::class);
        $this->assertNull($corge->q);

        $grault = $di->construct(Grault::class);
        $this->assertSame(Grault::GROOT, $grault->x);
    }

    public function testVariadic() {
        $di = new DependencyInjector();
        $garply = $di->construct(Garply::class);
        $this->assertInstanceOf(Garply::class, $garply);
        $this->assertInstanceOf(Grault::class, $garply->g);
        $this->assertCount(0, $garply->v);

        $garply2 = $di->construct(Garply::class, [1, 2, 3]);
        $this->assertSame(1, $garply2->g->x);
        $this->assertCount(2, $garply2->v);
    }

    public function testOptional() {
        $di = new DependencyInjector();
        $garply = $di->construct(Garply::class, [null, 2, 3]);
        $this->assertInstanceOf(Grault::class, $garply->g);
        $waldo = $di->construct(Waldo::class, [null, 2, 3]);
        $this->assertNull($waldo->g);
        $this->assertCount(2, $waldo->v);
    }
}


class Foo {
    public function __construct() {
    }

}

class Bar {
    public $foo;

    public function __construct(Foo $foo) {
        $this->foo = $foo;
    }
}

class Baz {
    public $bar;
    public $foo;

    public function __construct(Bar $bar, Foo $foo) {
        $this->bar = $bar;
        $this->foo = $foo;
    }
}

class Quux {
    public $x;

    public function __construct($x) {
        $this->x = $x;
        // throw new \Exception('quux fail');
    }
}

class Corge {
    public $q;

    public function __construct(Quux $q = null) {
        $this->q = $q;
    }

}

class Grault {
    const GROOT = 'groot';
    public $x;

    public function __construct($x = self::GROOT) {
        $this->x = $x;
    }
}

class Garply {
    public $g;
    public $v;
    
    public function __construct(Grault $grault, ...$variadic) {
        $this->g = $grault;
        $this->v = $variadic;
    }
}

class Waldo {
    public $g;
    public $v;

    public function __construct(Grault $grault=null, ...$variadic) {
        $this->g = $grault;
        $this->v = $variadic;
    }
}