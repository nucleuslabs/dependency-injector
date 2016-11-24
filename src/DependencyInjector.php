<?php namespace NucleusLabs\DI;

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
     * @var bool Bubble mismatched positional arguments to constructor?
     */
    private $coercePosArgs = true;
    /**
     * @var bool Bubble mismatched keyword arguments to constructor?
     */
    private $coerceKwArgs = true;
    /**
     * @var bool Bubble global arguments to constructor?
     */
    private $coerceGlobals = false;
    /**
     * @var null|callable Default function to use to construct a new instance of an object from an arg when coercion is enabled
     */
    private $coerceCallback = null;
    /**
     * @var bool Use KwArgs in recursive constructors?
     */
    private $propagateKwArgs = false;

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
        if(isset($options['coercePosArgs'])) {
            $this->coercePosArgs = (bool)$options['coercePosArgs'];
        }
        if(isset($options['coerceKwArgs'])) {
            $this->coerceKwArgs = (bool)$options['coerceKwArgs'];
        }
        if(isset($options['coerceGlobals'])) {
            $this->coerceGlobals = (bool)$options['coerceGlobals'];
        }
        if(isset($options['propagateKwArgs'])) {
            $this->propagateKwArgs = (bool)$options['propagateKwArgs'];
        }
        if(isset($options['coerceCallback'])) {
            $this->coerceCallback = $options['coerceCallback'];
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
            $this->namedRegistry[$className][] = [$namePatt, $callback];
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
        if($interfaceName === $className) {
            throw new \InvalidArgumentException("Interface name and class name cannot be the same ($interfaceName) -- this would create a infinite recursion!");
        }
        $callback = function () use ($className) {
            return $this->construct($className);
        };
        $this->registerCallback($interfaceName, $callback, $namePatt);
    }

    public function registerGlobal($name, $value) {
        $this->globals[$name] = $value;
    }

    public function registerGlobals($globals) {
        $this->globals = $globals + $this->globals;
    }

    /**
     * Gets an object from the registry if it exists.
     *
     * @param string $className Class name
     * @param null|string $name Argument name
     * @param array $posArgs    Positional arguments
     * @param array $kwArgs     Keyword arguments
     * @param mixed $default    Value to return if no match is found
     * @return mixed
     * @throws ClassNotFoundException Thrown if object was not found *and* default was not provided
     */
    public function get($className, $name = null, $posArgs = [], $kwArgs = [], $default = null) {
        if(strlen($name)) {
            if(isset($this->namedRegistry[$className])) {
                foreach($this->namedRegistry[$className] as list($namePatt, $ctor)) {
                    if(preg_match($namePatt, $name)) {
                        return $this->call($ctor, $posArgs, $kwArgs);
                    }
                }
            }
        }

        if(array_key_exists($className, $this->unnamedRegistry)) {
            return $this->call($this->unnamedRegistry[$className], $posArgs, $kwArgs);
        }

        if(func_num_args() >= 5) {
            return $default;
        }

        throw new ClassNotFoundException($className);
    }

    /**
     * Call an arbitrary function, injecting any missing parameters automatically.
     * 
     * @param string|array|\ReflectionFunctionAbstract|\Closure $callable
     * @param array $posArgs Positional arguments
     * @param array $kwArgs Keyword arguments
     * @return mixed
     */
    public function call($callable, $posArgs = [], $kwArgs = []) {
        $posArgs = self::toArray($posArgs, false);
        $kwArgs = self::toArray($kwArgs, true);
        return $this->invoke($callable, $posArgs, $kwArgs);
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
            // if($var instanceof \JsonSerializable) {
            //     return json_encode($var, JSON_UNESCAPED_SLASHES);
            // }
            return ltrim(spl_object_hash($var), '0');
        }
        return json_encode($var, JSON_UNESCAPED_SLASHES);
    }

    private static function getType($obj) {
        if(is_object($obj)) {
            return get_class($obj);
        }
        return gettype($obj);
    }

    /**
     * @param \ReflectionParameter[] $funcParams
     * @param array $posArgs
     * @param array $kwArgs
     * @return array
     * @throws \Exception
     */
    private function fillParams($funcParams, $posArgs, $kwArgs) {
        $funcArgs = [];

        foreach($posArgs as $arg) {
            if($funcParams) {
                $param = $funcParams[0];
                if(!$param->isVariadic()) {
                    array_shift($funcParams);
                }
            } else {
                $param = null;
            }
            $funcArgs[] = $this->coerce($param, $arg, $kwArgs, $this->coercePosArgs);
        }

        foreach($funcParams as $param) {
            $paramName = $param->getName();
            if(array_key_exists($paramName, $kwArgs)) {
                $funcArgs[] = $this->coerce($param, $kwArgs[$paramName], $this->propagateKwArgs ? $kwArgs : [], $this->coerceKwArgs);
                continue;
            }
            if(array_key_exists($paramName, $this->globals)) {
                $funcArgs[] = $this->coerce($param, $this->globals[$paramName], $this->propagateKwArgs ? $kwArgs : [], $this->coerceGlobals);
                continue;
            }
            if($param->isVariadic()) {
                continue; // don't auto-inject variadic params
            }
            $paramClass = $param->getClass();
            if($paramClass !== null) {
                if($param->isDefaultValueAvailable()) {
                    try {
                        $funcArgs[] = $this->construct($param, [], $this->propagateKwArgs ? $kwArgs : []);
                    } catch(\Exception $ex) {
                        $funcArgs[] = $param->getDefaultValue();
                    }
                } else {
                    $funcArgs[] = $this->construct($param, [], $this->propagateKwArgs ? $kwArgs : []);
                }
            } elseif($param->isDefaultValueAvailable()) {
                $funcArgs[] = $param->getDefaultValue();
            } elseif($param->isOptional()) {
                // This can happen with built-in functions: some parameters are optional, but the default value is not provided
                // If we can't inject it, we have to abort!
                break;
            } else {
                // technically, we could inject 0 for ints, [] for arrays, "" for strings and so forth, but if they wanted that,
                // they could just use parameter defaults!
                throw new \Exception("Cannot auto-inject non-optional, non-object parameter without default parameter: $paramName");
            }
            // TODO: what about *optional* params? is it better to omit the args altogether (instead of sending the default) if they aren't supplied, and aren't injectable?
            // the difference is that it affects func_get_args()
        }
        
        return $funcArgs;
    }

    /**
     * Gets a name for the given callable. Useful for debugging.
     * 
     * @param \ReflectionFunctionAbstract|\ReflectionClass|\Closure|string $callable
     * @return string
     */
    public function getFunctionName($callable) {
        if(is_string($callable)) {
            $parts = preg_split('/::|->|@/', $callable, 2); // Laravel uses @ for some reason; we might as well support it
            if(count($parts) === 2) {
                $callable = new \ReflectionMethod(...$parts);
            } else {
                $callable = new \ReflectionFunction($callable);
            }
        }

        if(is_array($callable)) {
            if(count($callable) !== 2) {
                throw new \InvalidArgumentException("Array-style callables must have exactly 2 elements (class name or instance, and method name)");
            }
            $callable = new \ReflectionMethod(...$callable);
        }

        if($callable instanceof \Closure) {
            return \Closure::class; // TODO: include parameter names or something so it's a little more distinguishable?
        }

        if(is_object($callable) && method_exists($callable, '__invoke')) {
            $callable = new \ReflectionMethod($callable, '__invoke');
        }

        if($callable instanceof \ReflectionClass) {
            $ctor = $callable->getConstructor();
            if($ctor) {
                $callable = $ctor;
            } else {
                return 'new '.$callable->getName();
            }
        }

        if($callable instanceof \ReflectionMethod) {
            $ret = $callable->getDeclaringClass()->getName();
            $ret .= $callable->isStatic() ? '::' : '->';
            $ret .= $callable->getName();
            return $ret;
        }
        
        if($callable instanceof \ReflectionFunction) {
            return $callable->getName();
        }
        
        throw new \InvalidArgumentException('Expected a callable for $func, got '.self::getType($callable));
    }

    /**
     * @param \ReflectionFunctionAbstract|\ReflectionClass|\Closure|string $callable
     * @param array $posArgs
     * @param array $kwArgs
     * @return mixed
     * @throws \Exception
     */
    private function invoke($callable, array $posArgs, array $kwArgs) {
        if($callable instanceof \ReflectionFunctionAbstract) {
            $funcParams = $callable->getParameters();
        } elseif($callable instanceof \Closure) {
            $ref = new \ReflectionFunction($callable);
            $funcParams = $ref->getParameters();
        } elseif(is_string($callable)) {
            $parts = preg_split('/::|->|@/', $callable, 2); // Laravel uses @ for some reason; we might as well support it
            if(count($parts) === 2) {
                $callable = new \ReflectionMethod(...$parts);
            } else {
                $callable = new \ReflectionFunction($callable);
            }
            $funcParams = $callable->getParameters();
        } elseif(is_array($callable)) {
            if(count($callable) !== 2) {
                throw new \InvalidArgumentException("Array-style callables must have exactly 2 elements (class name or instance, and method name)");
            }
            $ref = new \ReflectionMethod(...$callable);
            $funcParams = $ref->getParameters();
        } elseif(is_object($callable) && method_exists($callable, '__invoke')) {
            $ref = new \ReflectionMethod($callable, '__invoke');
            $funcParams = $ref->getParameters();
        } elseif($callable instanceof \ReflectionClass) {
            $ctor = $callable->getConstructor();
            if($ctor) {
                $funcParams = $ctor->getParameters();
            } else {
                // class does not have a constructor, so we cannot pass any constructor arguments
                return $callable->newInstance();
            }
        } else {
            throw new \InvalidArgumentException('Expected a callable for $callable, got '.self::getType($callable));
        }
        
        $funcArgs = $this->fillParams($funcParams, $posArgs, $kwArgs);

        if($callable instanceof \ReflectionClass) {
            return $callable->newInstanceArgs($funcArgs);
        }
        
        if($callable instanceof \ReflectionMethod) {
            if($callable->isConstructor()) {
                return $callable->getDeclaringClass()->newInstanceArgs($funcArgs);
            }

            if($callable->isStatic()) {
                return $callable->invokeArgs(null, $funcArgs);
            }
            
            $obj = $this->construct($callable->getDeclaringClass());
            return $callable->invokeArgs($obj, $funcArgs);
        } 
        
        if($callable instanceof \ReflectionFunction) {
            return $callable->invokeArgs($funcArgs);
        }
        
        return call_user_func_array($callable, $funcArgs);
    }

    /**
     * @param string|\ReflectionClass|\ReflectionParameter $class Class name
     * @param mixed[] $posArgs               Positional arguments
     * @param mixed[] $kwArgs                Keyword arguments
     * @return object Class instance
     * @throws \Exception
     */
    public function construct($class, $posArgs = [], $kwArgs = []) {
        $paramName = null;
        if(is_string($class)) {
            $class = new \ReflectionClass($class);
        } elseif($class instanceof \ReflectionClass) {
            // good
        } elseif($class instanceof \ReflectionParameter) {
            $paramName = $class->getName();
            $class = $class->getClass();
        } else {
            throw new \InvalidArgumentException('Expected string or ' . \ReflectionClass::class . ' for argument $class, got ' . self::getType($class));
        }
        $posArgs = self::toArray($posArgs, false);
        $kwArgs = self::toArray($kwArgs, true);

        $cacheKey = null;

        if($this->cacheObjects) {
            $cacheKey = $class->getName();
            if(strlen($paramName)) {
                $cacheKey .= '$' . $paramName;
            }
            $cacheKey .= '(' . implode(',', array_map('self::hash', $posArgs)) . ')';

            if(isset($this->objectCache[$cacheKey])) {
                return $this->objectCache[$cacheKey];
            }
        }
        
        $sentinel = new \stdClass;
        $instance = $this->get($class->getName(), $paramName, $posArgs, $kwArgs, $sentinel);

        if($instance === $sentinel) {
            $instance = $this->invoke($class, $posArgs, $kwArgs);
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

    /**
     * Force arg into the type of $param
     *
     * @param \ReflectionParameter|null $param If not provided, return $arg as-is
     * @param mixed $arg                       Argument to coerce
     * @param array $kwArgs                    Keyword args for bubbling
     * @param bool $bubble                     If arg is not of the correct type, should it be passed to the constructor?
     * @return mixed
     */
    private function coerce(\ReflectionParameter $param = null, $arg, $kwArgs, $bubble) {
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
        }

        $sentinel = new \stdClass;
        $instance = $this->get($paramClass->getName(), $param->getName(), [$arg], $kwArgs, $sentinel);
        
        if($instance !== $sentinel) {
            return $instance;
        }
        
        if($bubble) {
            if($this->coerceCallback) {
                return $this->call($this->coerceCallback, [$arg], $kwArgs + $this->globals);    
            } else {
                return $this->construct($paramClass, [$arg], $kwArgs);
            }
        } 
        
        throw new \InvalidArgumentException("Could not coerce argument of type " . self::getType($arg) . " to " . $paramClass->getName());
    }
}