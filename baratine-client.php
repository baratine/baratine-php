<?php

namespace baratine;

require_once('jamp-client.php');

abstract class BaratineClient
{
  public static function create(/* string */ $url)
  {
    return new BaratineClientImpl($url);
  }
  
  public abstract function _lookup(/* string */ $url);
  
  public abstract function close();
}

class BaratineClientImpl extends BaratineClient
{
  private $jampClient;
  
  public function __construct(/* string */ $url)
  {
    $this->jampClient = JampClient::create($url);
  }

  public function _lookup(/* string */ $url)
  {
    return new Proxy($this->jampClient, $url);
  }

  public function close()
  {
    $this->jampClient->close();
  }
}

class Proxy
{
  private $jampClient;
  private $serviceName;
  
  public function __construct(JampClient $jampClient, $serviceName)
  {
    $this->jampClient = $jampClient;
    
    $this->serviceName = $serviceName;
  }

  public function __call($name, $arguments)
  {
    return $this->jampClient->querySync($this->serviceName, $name, $arguments);
  }
  
  public function _as(/* string */ $clsName)
  {
    return new ClassProxy($this, $clsName);
  }
  
  public function _lookup(/* string */ $url)
  {
    return new Proxy($this->jampClient, $this->serviceName . $url);
  }
}

class ClassProxy
{
  private $proxy;
  private $clsName;
  
  private $cls;

  public function __construct(Proxy $proxy,
                              /* string */ $clsName)
  {
    $this->proxy = $proxy;
    $this->clsName = $clsName;
    
    $this->cls = new \ReflectionClass($clsName);
  }
  
  public function _lookup(/* string */ $url)
  {
    $proxy = $this->proxy->_lookup($url);
    
    return new ClassProxy($proxy, $this->clsName);
  }
  
  public function _as(/* string */ $clsName)
  {
    return new ClassProxy($this->proxy, $clsName);
  }
  
  public function __call($name, $arguments)
  {
    $method = $this->cls->getMethod($name);
    
    $this->_checkArguments($method, $arguments);
    
    return $this->proxy->__call($name, $arguments);
  }

  private function _checkArguments($method, &$arguments)
  {
    $requiredCount = $method->getNumberOfRequiredParameters();
    $requiredAndOptionalCount = $method->getNumberOfParameters();
    
    $argCount = count($arguments);
    
    /*
    $requiredCount = 0;
    
    $args = $method->getParameters();
    
    foreach ($args as $arg) {
      if ($arg->isOptional()) {
        break;
      }

      $requiredCount++;
      
      if (method_exists($arg, 'isVariadic') && $arg->isVariadic()) {
        break;
      }
    }
    */
    
    if ($argCount < $requiredCount) {
      throw new \Exception("required $requiredCount parameters but saw $argCount");
    }
    else if ($argCount > $requiredAndOptionalCount) {
      throw new \Exception("needed only $requiredAndOptionalCount parameters but saw $argCount");
    }
    
    $this->_checkTypes($method, $arguments);
  }
  
  private function _checkTypes($method, &$arguments)
  {
    $parameters = $method->getParameters();
    
    $i = 0;
    
    for ($i = 0; $i < count($arguments); $i++) {
      $arg = $arguments[$i];
      $param = $parameters[$i];
      
      $cls = $param->getClass();
      
      if ($cls != null) {
        if (is_object($arg) && is_a($arg, $cls->getName())) {
        }
        else if ($arg === null
                 && $param->isDefaultValueAvailable()
                 && $param->getDefaultValue() === null) {
        }
        else {
          $type;
          
          if (is_object($arg)) {
            $type = get_class($arg);
          }
          else {
            $type = gettype($arg);
          }
          
          throw new \Exception('expected ' . $cls->getName() . ' but saw ' . $type);
        }
      }
    }
    
    for (; $i < count($parameters); $i++) {
      $param = $parameters[$i];
      
      if ($param->isDefaultValueAvailable()) {
        for ($j = $i; $j < count($arguments); $j++) {
          $arguments[] = null;
        }
        
        $arguments[] = $param->getDefaultValue();
      }
    }
    
    for ($i = count($arguments); $i < count($parameters); $i++) {
      $arguments[] = null;
    }
  }
}

