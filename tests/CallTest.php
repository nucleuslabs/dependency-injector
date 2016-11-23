<?php namespace NoConflict_5835f1710cc04;

use mpen\DI\ClassNotFoundException;
use mpen\DI\DependencyInjector;

class CallTest extends \PHPUnit\Framework\TestCase {


    public function testFunction() {
        $di = new DependencyInjector();
        $this->assertSame(4, $di->call('\NoConflict_5835f1710cc04\four'));
    }

    public function testClosure() {
        $di = new DependencyInjector();
        $this->assertSame(5, $di->call(function() {
            return 5;
        }));
    }

    public function testMethod() {
        $di = new DependencyInjector();
        $foo = new Foo(6);
        $this->assertSame(6, $di->call([$foo,'getBar']));
    }

    public function testStaticMethod() {
        $di = new DependencyInjector();
        $this->assertSame(7, $di->call('\NoConflict_5835f1710cc04\Foo::quux'));
        $this->assertSame(7, $di->call('\NoConflict_5835f1710cc04\Foo->quux'));
        $this->assertSame(7, $di->call('\NoConflict_5835f1710cc04\Foo@quux'));
    }
}


class Foo {
    public $bar;
    
    public function __construct($bar=1) {
        $this->bar = $bar;
    }

    public function getBar() {
        return $this->bar;
    }
    
    public function setBar($bar) {
        $this->bar = $bar;
    }
    
    public static function quux() {
        return 7;
    }
}

function makeFoo(Foo $foo) {
    return $foo;
}

function four() {
    return 4;
}