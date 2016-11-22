#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';


class Foo {
    public function __construct() {
        dump(__METHOD__);
    }

}

class Bar {
    public function __construct(Foo $foo) {
        dump(__METHOD__);
    }
}

class Baz {
    public function __construct(Bar $bar, Foo $foo) {
        dump(__METHOD__);
    }
}

class Quux {
    private $x;
    
    public function __construct($x) {
        dump(__METHOD__);
        $this->x = $x;
        // throw new \Exception('quux fail');
    }
}

class Corge {
    private $q;
    
    public function __construct(Quux $q=null) {
        dump(__METHOD__);
        $this->q = $q;
    }

}

$di = new \mpen\DI\DependencyInjector(['cache'=>true]);


$obj = $di->construct(Corge::class, [3]);
dump($obj);
// $quux = new Quux(7);

// $obj1 = $di->construct(Corge::class, 7);
// $obj2 = $di->construct(Corge::class, 7);
// dump($obj1 === $obj2);
dump($di);