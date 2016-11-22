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

    public function invoke($callable, ...$params) {

    }

    /**
     * @param mixed $var
     * @return string
     */
    private static function hash($var) {
        if(is_object($var)) {
            if($var instanceof \Serializable) {
                return $var->serialize();
            }
            return ltrim(spl_object_hash($var),'0');
        }
        return json_encode($var,JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param string|\ReflectionClass $class Class name
     * @param mixed[] ...$posArgs            Positional arguments
     * @return object Class instance
     * @throws \Exception
     */
    public function construct($class, ...$posArgs) {
        if(is_string($class)) {
            $refClass = new \ReflectionClass($class);
        } elseif($class instanceof \ReflectionClass) {
            $refClass = $class;
        } else {
            throw new \InvalidArgumentException();
        }

        $cacheKey = $refClass->getName().'('.implode(',',array_map('self::hash',$posArgs)).')';
        if(isset($this->instances[$cacheKey])) {
            return $this->instances[$cacheKey];
        }
        $params = $refClass->getConstructor()->getParameters();
        $args = [];
        foreach($params as $i => $param) {
            $paramClass = $param->getClass();
            if($i < count($posArgs)) {
                if($paramClass === null || (is_object($posArgs[$i]) && $paramClass->isInstance($posArgs[$i]))) {
                    $args[] = $posArgs[$i];
                } else {
                    $args[] = $this->construct($paramClass, $posArgs[$i]);
                }
            } else {
                if($paramClass !== null) {
                    $args[] = $this->construct($paramClass);
                } else {
                    throw new NotImplementedException();
                }
            }
        }
        $instance = $refClass->newInstanceArgs($args);
        if($this->cache) {
            $this->instances[$cacheKey] = $instance;
        }
        return $instance;
    }
}