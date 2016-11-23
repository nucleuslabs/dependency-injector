<?php namespace mpen\DI;

class NotImplementedException extends \BadMethodCallException {
}

class DependencyInjector {
    /**
     * @var bool Cache constructed options?
     */
    private $cacheObjects = true;
    /**
     * @var bool Memoize functions and static methods?
     */
    private $memoizeFunctions = true;
    /**
     * @var bool Memoize non-static methods?
     */
    private $memoizeMethods = false;
    /**
     * @var bool Bubble mismatched argument types to constructors?
     */
    private $bubbleArgs = true;
    
    private $objectCache = [];
    private $namedRegistry = [];
    private $unnamedRegistry = [];
    private $globals = [];

    public function __construct($options = []) {
        if(isset($options['cacheObjects'])) {
            $this->cacheObjects = (bool)$options['cacheObjects'];
        }
        if(isset($options['globals'])) {
            $this->globals = self::toArray($options['globals'], true);
        }
        if(isset($options['memoizeFunctions'])) {
            $this->memoizeFunctions = (bool)$options['memoizeFunctions'];
        }
        if(isset($options['memoizeMethods'])) {
            $this->memoizeMethods = (bool)$options['memoizeMethods'];
        }
        if(isset($options['bubbleArgs'])) {
            $this->bubbleArgs = (bool)$options['bubbleArgs'];
        }
    }

    /**
     * When an instance of $className is requested, invoke $callback to construct it. If $namePatt is provided,
     * the argument name must match that regex.
     *
     * @param string $className     Class name
     * @param callable $callback    Function used to contruct an instance of `$class`
     * @param string|null $namePatt Argument name regex
     */
    public function registerCallback($className, $callback, $namePatt = null) {
        if(strlen($namePatt)) {
            $this->namedRegistry[$className][$namePatt] = $callback;
        } else {
            $this->unnamedRegistry[$className] = $callback;
        }
    }

    /**
     * When an instance of the same type of object as $object is requested, use that specific instance instead
     * of attempting to construct a new one. If $namePatt is provided, the argument name must match that regex.
     *
     * @param object $object        Class instance
     * @param string|null $namePatt Argument name regex
     */
    public function registerObject($object, $namePatt = null) {
        $className = get_class($object);
        $callback = function () use ($object) {
            return $object;
        };
        $this->registerCallback($className, $callback, $namePatt);
    }

    /**
     * When an object of type $interfaceName is requested, return an instance of $className instead. May be used
     * to return concrete implementations of abstract classes, or subclasses as well. If $namePatt is provided, the
     * argument name must match that regex.
     *
     * @param string $interfaceName Interface/parent class name
     * @param string $className     Name of class to construct
     * @param string|null $namePatt Argument name regex
     */
    public function registerInterface($interfaceName, $className, $namePatt = null) {
        $callback = function () use ($className) {
            return $this->construct($className);
        };
        $this->registerCallback($interfaceName, $callback, $namePatt);
    }

    /**
     * Gets an object from the registry if it exists.
     *
     * @param string $className Class name
     * @param null|string $name Argument name
     * @param mixed $default Value to return if no match is found
     * @return mixed
     * @throws ClassNotFoundException Thrown if object was not found *and* default was not provided
     */
    public function get($className, $name = null, $default = null) {
        if(strlen($name)) {
            if(isset($this->namedRegistry[$className])) {
                foreach($this->namedRegistry[$className] as $namePatt => $ctor) {
                    if(preg_match($namePatt, $name)) {
                        return $this->call($ctor);
                    }
                }
            }
        }
        
        if(array_key_exists($className, $this->unnamedRegistry)) {
            return $this->call($this->unnamedRegistry[$className]);
        }
        
        if(func_num_args() >= 3) {
            return $default;
        }
        
        throw new ClassNotFoundException($className);
    }

    public function call($callable, array $posArgs = [], array $kwArgs = []) {
        return call_user_func_array($callable, $posArgs);
        // TODO: injection!!
    }



    /**
     * @param mixed $var
     * @return string
     */
    private static function hash($var) {
        if(is_object($var)) {
            if($var instanceof \Serializable) {
                return serialize($var);
            }
            if($var instanceof \JsonSerializable) {
                return json_encode($var, JSON_UNESCAPED_SLASHES);
            }
            return ltrim(spl_object_hash($var), '0');
        }
        return json_encode($var, JSON_UNESCAPED_SLASHES);
    }
    
    private function cacheKey($className, $args=[]) {
        return $className . '(' . implode(',', array_map('self::hash', $args)) . ')';
    }
    
    private static function getType($obj) {
        if(is_object($obj)) {
            return get_class($obj);
        }
        return gettype($obj);
    }

    /**
     * @param string|\ReflectionClass $class Class name
     * @param mixed[] $posArgs               Positional arguments
     * @param mixed[] $kwArgs                Keyword arguments
     * @return object Class instance
     * @throws \Exception
     */
    public function construct($class, $posArgs = [], $kwArgs = []) {
        if(is_string($class)) {
            $class = new \ReflectionClass($class);
        } elseif($class instanceof \ReflectionClass) {
            // good
        } else {
            throw new \InvalidArgumentException('Expected string or '.\ReflectionClass::class.' for argument $class, got '.self::getType($class));
        }
        $posArgs = self::toArray($posArgs, false);
        $kwArgs = self::toArray($posArgs, true);
        
        $cacheKey = $this->cacheKey($class->getName(), $posArgs);
        if(isset($this->objectCache[$cacheKey])) {
            return $this->objectCache[$cacheKey];
        }
        
        $instance = $sentinel = new \stdClass;
        
        if(!$posArgs && !$kwArgs) {
            $instance = $this->get($class->getName(), null, $sentinel);
        }
        
        if($instance === $sentinel) {
            $ctorParams = $class->getConstructor()->getParameters();
            $instanceArgs = [];

            foreach($posArgs as $arg) {
                $param = reset($ctorParams);
                if($param && !$param->isVariadic()) {
                    array_shift($ctorParams);
                }
                $instanceArgs[] = $this->coerce($param, $arg, $kwArgs);
            }

            $kwParams = array_merge($this->globals, $kwArgs);

            foreach($ctorParams as $param) {
                $paramName = $param->getName();
                if(array_key_exists($paramName, $kwParams)) {
                    $instanceArgs[] = $this->coerce($param, $kwParams[$paramName], $kwArgs);
                }
                if($param->isVariadic()) {
                    continue; // don't auto-inject variadic params
                }
                $paramClass = $param->getClass();
                if($paramClass !== null) {
                    if($param->isDefaultValueAvailable()) {
                        try {
                            $instanceArgs[] = $this->construct($paramClass, [], $kwArgs);
                        } catch(\Exception $ex) {
                            $instanceArgs[] = $param->getDefaultValue();
                        }
                    } else {
                        $instanceArgs[] = $this->construct($paramClass, [], $kwArgs);
                    }
                } elseif($param->isDefaultValueAvailable()) {
                    $instanceArgs[] = $param->getDefaultValue();
                } else {
                    // technically, we could inject 0 for ints, [] for arrays, "" for strings and so forth, but if they wanted that,
                    // they could just use parameter defaults!
                    throw new \Exception("Cannot auto inject non-optional, non-class-type-hinted parameter without default parameter: $paramName");
                }
                // TODO: what about *optional* params? is it better to omit the args altogether (instead of sending the default) if they aren't supplied, and aren't injectable?
                // the difference is that it affects func_get_args()
            }
            $instance = $class->newInstanceArgs($instanceArgs);
        }
        
        if($this->cacheObjects) {
            $this->objectCache[$cacheKey] = $instance;
        }
        return $instance;
    }
    
    private static function toArray($arg, $use_keys) {
        if(is_array($arg)) {
            if(!$use_keys) {
                return array_values($arg);
            }
            return $arg;
        }
        if($arg instanceof \Traversable) {
            return iterator_to_array($arg, $use_keys);
        }
        return (array)$arg;
    }
    
    private function coerce(\ReflectionParameter $param=null, $arg, $kwArgs) {
        if(!$param) {
            return $arg;
        }
        if($arg === null && $param->allowsNull()) {
            return null;
        }
        $paramClass = $param->getClass();
        if(!$paramClass) {
            if($param->isArray()) {
                return self::toArray($arg, true);
            }
            return $arg;
        }
        if(is_object($arg) && $paramClass->isInstance($arg)) {
            return $arg;
        } elseif($this->bubbleArgs) {
            return $this->construct($paramClass, [$arg], $kwArgs);
        } else {
            throw new \InvalidArgumentException("Could not coerce argument of type ".self::getType($arg)." to ".$paramClass->getName());
        }
    }
}