# Dependency Injector

Unlike other dependency injectors, this is not meant as a DI-container. It calls methods for you, filling in any
missing parameters instead of forcing you to pull them out of the container manually.

## Overview

### Zero-configuration

```php
$di = new DependencyInjector();
$di->construct(\DateTime::class); // constructs a new DateTime object using default parameters (i.e. current time)
$di->call('time'); // calls time()
```

Not so exciting? The real magic happens when you use type-hinted parameters:

```php
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
$di->call('\MyController::action');
```

What this does is:

1. Tries to call `\MyController::action`
2. Notices `\MyController::action` is non-static, so it tries to construct a new `MyController`
3. `MyController` requires a `Request` so it tries to create one of those
4. `Request` needs a `Uri`
5. `Uri` *wants* a `$uriString` but we didn't supply one so it uses the default (`null`)
6. Now that we have a fully-instantiated `MyController`, we can invoke `->action()` on it, but it needs a `Response`
7. Constructs a `Response` and invokes the method

If that wasn't clear, here's what the output looks like:

```
Uri::__construct"
null

"Request::__construct"
Uri {#18
  -uriParts: array:8 [
    "scheme" => "http"
    "host" => "hostname"
    "port" => 9090
    "user" => "username"
    "pass" => "password"
    "path" => "/path"
    "query" => "arg=value"
    "fragment" => "anchor"
  ]
}

"MyController::__construct"
Request {#14
  -uri: Uri {#18
    -uriParts: array:8 [
      "scheme" => "http"
      "host" => "hostname"
      "port" => 9090
      "user" => "username"
      "pass" => "password"
      "path" => "/path"
      "query" => "arg=value"
      "fragment" => "anchor"
    ]
  }
}

"MyController::action"
Response {#7}
```

### Registering globals

In the above example, what if we did have a URI and wanted to use that instead of the default? We can register it as
a global variable early in the application life-cycle:

```php
$di->registerGlobal('uriString', 'http://example.com:3000');
```

Then whenever a parameter called `$uriString` is encountered and no other value is provided, it will check if one
exists in the globals and use that instead!

You can register your `$_GET` and/or `$_POST` variables as globals if you want to use them as defaults for your
controller actions, for example (if you're using MVC).

### Registering classes

Alternatively, you can register a class instead,

```php
$di->registerObject(new Uri('http://google.com'));
```

Now when the `DependencyInjector` encounters a `Uri`, it will use your registered instance instead of trying
to construct a new one on its own.

### Registering callbacks

If you don't want to instantiate an object until it's needed, and you want full control over how it's created,
you can register a callback instead:

```php
$di->registerCallback(Uri::class, function() {
    return new Uri('https://bing.com');
});
```

### More

See the unit tests for more examples.

## Options


Option             | Type     | Default   | Description
------------------ | -------- | --------- | ---------------------------------------------------------------------------------
`cacheObjects`     | bool     | `true`    | Cache constructed objects for future re-use
`globals`          | array    | `[]`      | Global keyword arguments (automatically injected if parameter name matches)
`memoizeFunctions` | bool     | `true`    | Not implemented
`memoizeMethods`   | bool     | `false`   | Not implemented
`coercePosArgs`    | bool     | `true`    | Implicitly convert positional arguments to the correct type if they do not match
`coerceKwArgs`     | bool     | `true`    | Implicitly convert keyword arguments to the correct type if they do not match
`coerceGlobals`    | bool     | `false`   | Implicitly convert global vars to the correct type if they do not match
`coerceCallback`   | callable | construct | Default function to use for the coercion if no callback is registered for the specific type. If not provided, will try using the constructor, passing in the one arg that was in the position of this type.
`propagateKwArgs`  | bool     | `false`   | Match keyword arguments against top-level call (`false`) or propagate keyword arguments to recursively construct dependencies (`true`)?