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
        $serviceName = $array[2];
        $queryId = $array[3];
        $result = $array[4];
        
        if (count($array) > 5) {
          $resultArray = array();
          
          for ($i = 4; $i < count($array); $i++) {
            $resultArray[] = $array[$i];
          }
          
          $result = $resultArray;
        }
        
        $msg = new ErrorMessage($headers, $serviceName, $queryId, $result);
        
        return $msg;
      }
      
      case 'query': {
        if (count($array) < 6) {
          throw new \Exception('incomplete message for JAMP type: ' . type);
        }
        
        $headers = $array[1];
        $fromAddress = $array[2];
        $queryId = $array[3];
        $serviceName = $array[4];
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
                                $serviceName,
                                $methodName,
                                $args);
        
        return $msg;
      }
      
      case 'send': {
        if (count($array) < 4) {
          throw new \Exception('incomplete message for JAMP type: ' . type);
        }
        
        $headers = $array[1];
        $serviceName = $array[2];
        $methodName = $array[3];
        
        $args = null;
        
        if (count($array) > 4) {
          $args = array();
          
          for ($i = 4; $i < count($array); $i++) {
            $args[] = $array[$i];
          }
        }
        
        $msg = new SendMessage($headers, $serviceName, $methodName, $args);
        
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
  
  public abstract function toUrl(/* string */ $baseUrl);
  
  protected function buildUrl(/* string */ $baseUrl,
                              /* string */ $serviceName,
                              /* string */ $methodName,
                              array $args = null)
  {
    $url = $baseUrl . $serviceName . '?m=' . $methodName;
    
    for ($i = 0; $i < count($args); $i++) {
      $arg = $args[$i];
      
      $url .= '&p' . $i . '=' . $arg;
    }
    
    return $url;
  }
}

class SendMessage extends Message
{
  private $serviceName;
  private $methodName;
  private $args;

  function __construct($headers, $serviceName, $methodName, $args)
  {
    parent::__construct($headers);
    
    $this->serviceName = $serviceName;
    $this->methodName = $methodName;
        
    $this->args = $args;
  }
  
  protected function serializeImpl()
  {
    $array = array();
    
    $array[] = 'send';
    $array[] = $this->headers;
    $array[] = $this->serviceName;
    $array[] = $this->methodName;
    
    if ($this->args !== null) {
      foreach ($this->args as $arg) {
        $array[] = $arg;
      }
    }
        
    return $array;
  }
  
  public function toUrl(/* string */ $baseUrl)
  {
    return $this->buildUrl($baseUrl, $this->serviceName, $this->methodName, $this->args);
  }
}

class QueryMessage extends Message
{
  private $fromAddress;
  private $queryId;

  private $serviceName;
  private $methodName;
  private $args;
  
  private $listeners;

  function __construct($headers, $fromAddress, $queryId, $serviceName, $methodName, $args)
  {
    parent::__construct($headers);
        
    $this->fromAddress = $fromAddress;
    $this->queryId = $queryId;
    
    $this->serviceName = $serviceName;
    $this->methodName = $methodName;
    
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
    $array[] = $this->serviceName;
    $array[] = $this->methodName;
    
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
    return $this->buildUrl($baseUrl, $this->serviceName, $this->methodName, $this->args);
  }
  
  public function getQueryId()
  {
    return $this->queryId;
  }
}

class ReplyMessage extends Message
{
  private $fromAddress;
  private $queryId;
  private $value;
  
  function __construct($headers, $fromAddress, $queryId, $value)
  {
    parent::__construct($headers);
    
    $this->fromAddress = $fromAddress;
    $this->queryId = $queryId;
    
    $this->value = $value;
  }
  
  protected function serializeImpl()
  {
    $array = array();
    
    $array[] = 'reply';
    $array[] = $this->headers;
    $array[] = $this->fromAddress;
    $array[] = $this->queryId;
    $array[] = $this->value;
    
    return $array;
  }
  
  public function toUrl(/* string */ $baseUrl)
  {
    throw new Exception('unsupported operation');
  }
  
  public function getQueryId()
  {
    return $this->queryId;
  }
  
  public function getValue()
  {
    return $this->value;
  }
}

class ErrorMessage extends Message
{
  private $address;
  private $queryId;
  private $error;
  
  function __construct($headers, $toAddress, $queryId, $error)
  {
    parent::__construct($headers);
    
    $this->address = $toAddress;
    $this->queryId = $queryId;
    
    $this->error = $error;
  }
  
  protected function serializeImpl()
  {
    $array = array();
    
    $array[] = 'error';
    $array[] = $this->headers;
    $array[] = $this->address;
    $array[] = $this->queryId;
    $array[] = $this->error;
    
    return $array;
  }
  
  public function toUrl(/* string */ $baseUrl)
  {
    throw new Exception('unsupported operation');
  }
  
  public function getQueryId()
  {
    return $this->queryId;
  }
  
  public function getError()
  {
    return $this->error;
  }
}

abstract class Response
{
  private $status;
  private $value;
  private $error;
  private $isError;
  
  private $rawResponse;
  
  public function getStatus() { return $this->status; }
  public function setStatus(/* string */ $status) { $this->status = $status; }
  
  public function getError() { return $this->error; }
  public function setError(object $error) { $this->error = $error; }
  
  public function getValue() { return $this->value; }
  public function setValue($value) { $this->value = $value; }
  
  public function getRawResponse() { return $this->rawResponse; }
  public function setRawResponse(/* string */ $str) { return $this->rawResponse = $str; }
  
  public function isError() { return $this->isError; }
  public function setIsError(/* bool */ $isError) { $this->isError = $isError; }
}

class RawResponse extends Response
{
  private $str;

  protected function __construct(/* string */ $rawResponse)
  {
    $this->setRawResponse($str);
  }
}

class ErrorResponse extends RawResponse
{
  function __construct(/* string */ $rawResponse, /* string */ $status, object $error)
  {
    parent::__construct($rawResponse);
    
    $this->setStatus($status);
    $this->setError($error);
    $this->setIsError(true);
  }
}


abstract class Listener
{
  public abstract function __call($name, $arguments);
}

