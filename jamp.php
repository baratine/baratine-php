<?php

namespace baratine;

class Jamp
{
  public static function unserialize($json)
  {
    $array = \json_decode($json);
    
    $msg = Jamp::unserializeArray($array);
    
    return $msg;
  }
  
  public static function unserializeArray($array)
  {
    $type = $array[0];
    
    switch ($type) {
      case 'reply': {
        if (count($array) < 5) {
          throw new \Exception('incomplete message for JAMP type: ' . type);
        }
        
        $headers = $array[1];
        $fromAddress = $array[2];
        $queryId = $array[3];
        $result = $array[4];
        
        $msg = new ReplyMessage($headers, $fromAddress, $queryId, $result);
        
        return $msg;
      }
      
      case 'error': {
        if (count($array) < 5) {
          throw new \Exception('incomplete message for JAMP type: ' . type);
        }
        
        $headers = $array[1];
        $toAddress = $array[2];
        $queryId = $array[3];
        $result = $array[4];
        
        if (count($array) > 5) {
          $resultArray = array();
          
          for ($i = 4; $i < count($array); $i++) {
            $resultArray[] = $array[$i];
          }
          
          $result = $resultArray;
        }
        
        $msg = new ErrorMessage($headers, $toAddress, $queryId, $result);
        
        return $msg;
      }
      
      case 'query': {
        if (count($array) < 6) {
          throw new \Exception('incomplete message for JAMP type: ' . type);
        }
        
        $headers = $array[1];
        $fromAddress = $array[2];
        $queryId = $array[3];
        $toAddress = $array[4];
        $methodName = $array[5];
                
        $args = null;
        
        if (count($array) > 6) {
          $args = array();
          
          for ($i = 6; $i < count($array); $i++) {
            $args[] = $array[$i];
          }
        }
        
        $msg = new QueryMessage($headers,
                                $fromAddress,
                                $queryId,
                                $toAddress,
                                $methodName,
                                $args);
        
        return $msg;
      }
      
      case 'send': {
        if (count($array) < 4) {
          throw new \Exception('incomplete message for JAMP type: ' . type);
        }
        
        $headers = $array[1];
        $toAddress = $array[2];
        $methodName = $array[3];
        
        $parameters = null;
        
        if (count($array) > 4) {
          $parameters = array();
          
          for ($i = 4; $i < count($array); $i++) {
            $parameters[] = $array[$i];
          }
        }
        
        $msg = new SendMessage($headers, $toAddress, $methodName, $parameters);
        
        return $msg;
      }
      
      default: {
        throw new \Exception('unknown JAMP type: ' . $type);
      }
      
    } // end switch
  }
}

abstract class Message
{
  protected $headers;
  
  function __construct($headers)
  {
    if ($headers != null) {
      $this->headers = $headers;
    }
  }
  
  public function serialize()
  {
    $array = $this->serializeImpl();
    
    $json = \json_encode($array);
    
    return $json;
  }
  
  protected abstract function serializeImpl();
}

class SendMessage extends Message
{
  private $address;
  private $method;
  private $args;

  function __construct($headers, $address, $method, $args)
  {
    parent::__construct($headers);
    
    $this->address = $address;
    $this->method = $method;
        
    $this->args = $args;
  }
  
  protected function serializeImpl()
  {
    $array = array();
    
    $array[] = 'send';
    $array[] = $this->headers;
    $array[] = $this->address;
    $array[] = $this->method;
    
    if ($this->args !== null) {
      foreach ($this->args as $arg) {
        $array[] = $arg;
      }
    }
        
    return $array;
  }
  
  public function toUrl(/* string */ $baseUrl)
  {
    $url = $baseUrl . $this->address . '?m=' . $this->method;
    
    for ($i = 0; $i < count($this->args); $i++) {
      $arg = $this->args[$i];
      
      $url .= '&p' . $i . '=' . $arg;
    }
    
    return $url;
  }
}

class QueryMessage extends Message
{
  private $fromAddress;
  private $queryId;

  private $address;
  private $method;
  private $args;
  
  private $listeners;

  function __construct($headers, $fromAddress, $queryId, $address, $method, $args)
  {
    parent::__construct($headers);
        
    $this->fromAddress = $fromAddress;
    $this->queryId = $queryId;
    
    $this->address = $address;
    $this->method = $method;
    
    if ($args !== null) {
      $this->args = array();
      
      foreach ($args as $arg) {
        if ($arg instanceof Listener
            || is_object($arg) && property_exists($arg, '___isListener')) {
          $listener = $this->addListener($arg, $queryId);
        
          $this->args[] = $listener;
        }
        else {
          $this->args[] = $arg;
        }
      }
    }
    
    if ($fromAddress == null) {
      $this->fromAddress = 'me';
    }
  }
  
  protected function serializeImpl()
  {
    $array = array();
    
    $array[] = 'query';
    $array[] = $this->headers;
    $array[] = $this->fromAddress;
    $array[] = $this->queryId;
    $array[] = $this->address;
    $array[] = $this->method;
    
    if ($this->args !== null) {
      foreach ($this->args as $arg) {
        $array[] = $arg;
      }
    }
    
    return $array;
  }
  
  private function addListener($listener, $queryId)
  {
    if ($this->listeners === null) {
      $this->listeners = array();
    }

    $callbackAddress = '/callback-' . $queryId;
    $this->listeners[$callbackAddress] = $listener;
    
    return $callbackAddress;
  }
  
  public function getListeners()
  {
    return $this->listeners;
  }
  
  public function toUrl(/* string */ $baseUrl)
  {
    $url = $baseUrl . $this->address . '?m=' . $this->method;
    
    for ($i = 0; $i < count($this->args); $i++) {
      $arg = $this->args[$i];
      
      $url .= '&p' . $i . '=' . $arg;
    }
    
    return $url;
  }
}

class ReplyMessage extends Message
{
  private $fromAddress;
  private $queryId;
  private $result;
  
  function __construct($headers, $fromAddress, $queryId, $result)
  {
    parent::__construct($headers);
    
    $this->fromAddress = $fromAddress;
    $this->queryId = $queryId;
    
    $this->result = $result;
  }
  
  protected function serializeImpl()
  {
    $array = array();
    
    $array[] = 'reply';
    $array[] = $this->headers;
    $array[] = $this->fromAddress;
    $array[] = $this->queryId;
    $array[] = $this->result;
    
    return $array;
  }
}

class ErrorMessage extends Message
{
  private $address;
  private $queryId;
  private $result;
  
  function __construct($headers, $toAddress, $queryId, $result)
  {
    parent::__construct($headers);
    
    $this->address = $toAddress;
    $this->queryId = $queryId;
    
    $this->result = $result;
  }
  
  protected function serializeImpl()
  {
    $array = array();
    
    $array[] = 'error';
    $array[] = $this->headers;
    $array[] = $this->address;
    $array[] = $this->queryId;
    $array[] = $this->result;
    
    return $array;
  }
}

abstract class Listener
{
  public abstract function __call($name, $arguments);
}

