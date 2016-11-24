#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

class MyController {
    private $req;

    public function __construct(Request $req) {
        dump(__METHOD__, $req);
        $this->req = $req;
    }

    public function action(Response $res) {
        dump(__METHOD__, $res);
    }
}

class Request {
    private $uri;

    public function __construct(Uri $uri) {
        dump(__METHOD__, $uri);
        $this->uri = $uri;
    }
}

class Response {
    
}

class Uri {
    private $uriParts;

    public function __construct($uriString = null) {
        dump(__METHOD__, $uriString);
        if(!strlen($uriString)) {
            $this->uriParts = parse_url('http://username:password@hostname:9090/path?arg=value#anchor'); // TODO: get from $_REQUEST
        } else {
            $this->uriParts = parse_url($uriString);
        }
    }
}

$di = new \mpen\DI\DependencyInjector();
// $di->call('\MyController::action');

// $di->registerGlobal('uriString','http://example.com:3000');

$di->registerCallback(Uri::class, function() {
    return new Uri('https://bing.com');
});

$di->call('\MyController::action');