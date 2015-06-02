<?php

namespace baratine;

require_once('jamp-client.php');

abstract class BaratineClient
{
  public static function create(/* string */ $url)
  {
    return new BaratineClientImpl($url);
  }

  public abstract function lookup(/* string */ $url);
}

class BaratineClientImpl extends BaratineClient
{
  private $jampClient;
  
  public function __construct(/* string */ $url)
  {
    $this->jampClient = JampClient::create($url);
  }

  public function lookup(/* string */ $url)
  {
    return new Proxy($this->jampClient, $url);
  }
}

class Proxy
{
  private $jampClient;
  
  public function __construct(JampClient $jampClient, $serviceName)
  {
    $this->jampClient = $jampClient;
    
    $this->serviceName = $serviceName;
  }
  
  public function call($name, $arguments)
  {
    return __call($name, $arguments);
  }
  
  public function __call($name, $arguments)
  {
    return $this->jampClient->querySync($this->serviceName, $name, $arguments);
  }
  
  public function asClass(/* string */ $clsName)
  {
    return new ClassProxy($this, $clsName);
  }
}

class ClassProxy
{
  private $proxy;
  private $cls;

  public function __construct(Proxy $proxy,
                              /* string */ $clsName)
  {
    $this->proxy = $proxy;
    $this->clsName = $clsName;
    
    $this->cls = new \ReflectionClass($clsName);
  }
  
  public function __call($name, $arguments)
  {
    $method = $this->cls->getMethod($name);
    
    $this->checkArguments($method, $arguments);
    
    return $this->proxy->__call($name, $arguments);
  }
  
  private function checkArguments($method, $arguments)
  {
    $requiredCount = $method->getNumberOfRequiredParameters();
    
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
    
    if (count($arguments) < $requiredCount) {
      throw new \Exception("required $requireCount parameters but saw only {count($arguments)}");
    }
    
    $this->checkTypes($method, $arguments);
  }
  
  private function checkTypes($method, $arguments)
  {
    $parameters = $method->getParameters();
    
    for ($i = 0; $i < count($arguments); $i++) {
      $arg = $arguments[$i];
      $param = $parameters[$i];
      
      $cls = $param->getClass();
      
      if ($cls != null && ! $cls->isInstance($arg)) {
        throw new \Exception('expected ' . $cls->getName() . ' but saw ' . get_class($arg));
      }
    }
  }
}

