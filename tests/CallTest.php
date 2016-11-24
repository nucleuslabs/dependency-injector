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

    public function testInvoke() {
        $di = new DependencyInjector();
        $inv = new Invokable();
        $this->assertSame(99, $di->call($inv));
    }

    public function testClosureInjection() {
        $di = new DependencyInjector();
        $this->assertSame(1, $di->call(function(Foo $f) {
            return $f->getBar();
        }));
    }

    public function testClosureInjectionRegistration() {
        $di = new DependencyInjector();
        $di->registerObject(new Foo(8));
        $this->assertSame(8, $di->call(function(Foo $f) {
            return $f->getBar();
        }));
    }

    public function testFunctionInjection() {
        $di = new DependencyInjector();
        $this->assertInstanceOf(Foo::class, $di->call('\NoConflict_5835f1710cc04\makeFoo'));
    }

    public function testPosArg() {
        $di = new DependencyInjector();
        $foo = new Foo(9);
        $this->assertSame($foo, $di->call('\NoConflict_5835f1710cc04\makeFoo', [$foo]));
        $this->assertNotSame($foo, $di->call('\NoConflict_5835f1710cc04\makeFoo', []));
    }

    public function testKwArg() {
        $di = new DependencyInjector();
        $foo = new Foo(10);
        $this->assertSame($foo, $di->call('\NoConflict_5835f1710cc04\makeFoo', null, ['foo'=>$foo]));
    }

    public function testGlobalPrecedence() {
        $di = new DependencyInjector();
        $globalFoo = new Foo(11);
        $posFoo = new Foo(12);
        $kwFoo = new Foo(12);
        $di->registerGlobal('foo',$globalFoo);
        $this->assertSame($globalFoo, $di->call('\NoConflict_5835f1710cc04\makeFoo'));
        $this->assertSame($posFoo, $di->call('\NoConflict_5835f1710cc04\makeFoo', [$posFoo]));
        $this->assertSame($posFoo, $di->call('\NoConflict_5835f1710cc04\makeFoo', [$posFoo], ['foo'=>$kwFoo]));
        $this->assertSame($kwFoo, $di->call('\NoConflict_5835f1710cc04\makeFoo', [], ['foo'=>$kwFoo]));
    }

    public function testDbHex() {
        $di = new DependencyInjector();
        $di->registerGlobals([
            'doc_uuid' => '0d7589716087962a47c927c4de707d1b7cf3fbb8',
            'user_id' => '2006',
            'app_id' => 'io',
            'size' => 4068,
        ]);
        $this->expectException(\InvalidArgumentException::class);
        $di->call('\NoConflict_5835f1710cc04\StaticController::notify');
    }

    public function testDbHex2() {
        $di = new DependencyInjector([
            'coerceGlobals' => true,
        ]);
        $uuid = '0d7589716087962a47c927c4de707d1b7cf3fbb8';
        $di->registerGlobals([
            'doc_uuid' => $uuid,
            'user_id' => '2006',
            'app_id' => 'io',
            'size' => 4068,
        ]);
        $res = $di->call('\NoConflict_5835f1710cc04\StaticController::notify');
        $this->assertSame($uuid, $res['uuid']);
    }

    public function testDbHex3() {
        $di = new DependencyInjector([
            'coerceGlobals' => false,
        ]);
        $uuid = '0d7589716087962a47c927c4de707d1b7cf3fbb8';
        $di->registerGlobals([
            'doc_uuid' => $uuid,
            'user_id' => '2006',
            'app_id' => 'io',
            'size' => 4068,
        ]);
        $di->registerCallback(DbHex::class, function($hex) {
            return new DbHex($hex);
        });
        $res = $di->call('\NoConflict_5835f1710cc04\StaticController::notify');
        $this->assertSame($uuid, $res['uuid']);
    }

    public function testInterfaceRecursion() {
        $di = new DependencyInjector();
        $this->expectException(\InvalidArgumentException::class);
        $di->registerInterface(DbHex::class, DbHex::class);
    }

    public function testConstructAndInvoke() {
        $di = new DependencyInjector();
        $this->assertSame('abc1',$di->call('\NoConflict_5835f1710cc04\NonStaticController::someMethod', 'abc'));
    }

    public function testPropagateKwArgs() {
        $di = new DependencyInjector(['propagateKwArgs' => true]);
        $foo = $di->call('\NoConflict_5835f1710cc04\makeFoo',null,['bar'=>78]);
        $this->assertSame(78, $foo->bar);


        $di = new DependencyInjector(['propagateKwArgs' => false]);
        $foo = $di->call('\NoConflict_5835f1710cc04\makeFoo',null,['bar'=>78]);
        $this->assertSame(1, $foo->bar);
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

abstract class StaticController {

    public static function notify(DbHex $doc_uuid, Foo $foo) {
        return ['uuid' => $doc_uuid->hex];
    }
}

class DbHex {
    public $hex;
    
    public function __construct($hex) {
        $this->hex = $hex;
    }

}

class Invokable {
    function __invoke() {
        return 99;
    }
}

class NonStaticController {
    public $foo;
    
    public function __construct(Foo $foo) {
        $this->foo = $foo;
    }


    public function someMethod(DbHex $uuid) {
        return $uuid->hex . $this->foo->bar;
    }
}