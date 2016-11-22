<?php namespace mpen\DI;

class NotImplementedException extends \BadMethodCallException {
}

class DependencyInjector {
    private $cache;
    private $instances;
    private $globals;

    public function __construct($options = []) {
        $this->cache = !empty($options['cache']);
        $this->globals = !empty($options['globals']) ? (array)$options['globals'] : [];
        $this->instances = [];
    }


    /**
     * @param string|object $class  Instance or class name
     * @param string|null $namePatt Argument name pattern
     * @param callable $ctor        Function used to contruct an instance of `$class`
     */
    public function register($class, $namePatt = null, $ctor = null) {

    }

    public function call($callable, array $posArgs = [], array $kwArgs = []) {

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

    /**
     * @param string|\ReflectionClass $class Class name
     * @param mixed[] $posArgs               Positional arguments
     * @param mixed[] $kwArgs                Keyword arguments
     * @return object Class instance
     */
    public function construct($class, $posArgs = [], $kwArgs = []) {
        if(is_string($class)) {
            $class = new \ReflectionClass($class);
        } elseif($class instanceof \ReflectionClass) {
            // good
        } else {
            throw new \InvalidArgumentException('Expected string or '.\ReflectionClass::class.', got '.get_class($class));
        }
        $posArgs = self::toArray($posArgs, false);
        $kwArgs = self::toArray($posArgs, true);

        if($this->cache) {
            $cacheKey = $class->getName() . '(' . implode(',', array_map('self::hash', $posArgs)) . ')';
            if(isset($this->instances[$cacheKey])) {
                return $this->instances[$cacheKey];
            }
        }
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

        foreach($ctorParams as $i => $param) {
            $paramName = $param->getName();
            if(array_key_exists($paramName, $kwParams)) {
                $instanceArgs[] = $this->coerce($param, $kwParams[$paramName], $kwArgs);
            }
            if($param->isVariadic()) {
                continue; // don't auto-inject variadic params
            }
            $paramClass = $param->getClass();
            if($paramClass !== null) {
                $instanceArgs[] = $this->construct($paramClass, [], $kwArgs);
            } else {
                throw new NotImplementedException();
            }
        }
        $instance = $class->newInstanceArgs($instanceArgs);
        if(isset($cacheKey)) {
            $this->instances[$cacheKey] = $instance;
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
        } else {
            return $this->construct($paramClass, [$arg], $kwArgs);
        }
    }
}