<?php

namespace baratine;

require_once('jamp.php');
require_once('transport.php');

abstract class JampClient
{
  public static function create(/* string */ $url)
  {
    return new JampClientImpl($url);
  }
  
  public abstract function send(/* string */ $service,
                                /* string */ $method, 
                                array $args = null,
                                array $headerMap = null);
                                
  public abstract function query(/* string */ $service,
                                 /* string */ $method,
                                 Result $result,
                                 array $args = null,
                                 array $headerMap = null);

  public abstract function querySync(/* string */ $service,
                                     /* string */ $method,
                                     array $args = null,
                                     array $headerMap = null);

  public abstract function poll();

  public abstract function close();
}

class JampClientImpl extends JampClient
{
  private $transport;
  
  private $resultMap;
  private $listenerMap;
  
  private $queryCount;
  
  protected function __construct(/* string */ $url)
  {
    $this->resultMap = array();
    $this->listenerMap = array();
    $this->queryCount = 0;
    
    $url = trim($url);
    
    if (strpos($url, 'ws:') === 0) {
      $url = 'http:' . substr($url, 3);
    }
    else if (strpos($url, 'wss:') === 0) {
      $url = 'https:' . substr($url, 4);
    }
    
    if (strpos($url, 'http:') === 0
        || strpos($url, 'https:') === 0) {
      $this->transport = new HttpPushPullTransport($url);
    }
    else {
      throw new \Exception('invalid url: ' . $url);
    }
  }
  
  public function send(/* string */ $service,
                      /* string */ $method,
                      array $args = null,
                      array $headerMap = null)
  {
    $queryId = $this->queryCount++;
    
    $msg = new SendMessage($headerMap, $service, $method, $args);
    
    return $this->transport->send($msg);
  }
  
  public function query(/* string */ $service,
                        /* string */ $method,
                        Result $result,
                        array $args = null,
                        array $headerMap = null)
  {
    $msg = $this->initQuery($service, $method, $result, $args, $headerMap);
    
    $msgArray = $this->transport->query($msg);

    foreach ($msgArray as $msg) {
      $this->onMessage($msg);
    }
    
    return count($msgArray);
  }
  
  public function querySync(/* string */ $service,
                            /* string */ $method,
                            array $args = null,
                            array $headerMap = null)
  {
    $result = new Result();
    
    $msg = $this->initQuery($service, $method, $result, $args, $headerMap);
  
    $msgArray = $this->transport->querySync($msg);
    
    foreach ($msgArray as $msg) {
      $this->onMessage($msg);
    }
    
    if ($result->isFailed()) {
      throw new \Exception($result->getError());
    }
    else {
      return $result->getValue();
    }
    
    /*
    
    $this->query($service, $method, $result, $args, $headerMap);
    
    $count = 0;
    
    while (! $result->isCompleted() && ! $result->isFailed()) {
      if ($count++ > 5) {
        throw new \Exception('result not completed: ' . $service . '?m=' . $method);
      }
      
      $this->poll();
    }
    
    if ($result->isCompleted()) {
      return $result->getValue();
    }
    else {
      $error = $result->getError();
      
      if (is_array($error)) {
        $sb = '';
        
        $i = 0;
        foreach ($error as $str) {
          if ($i++ !== 0) {
            $sb .= ', ';
          }
          
          $sb .= $str;
        }
        
        $error = $sb;
      }
      
      throw new \Exception($error);
    }
    
    */
  }
  
  private function initQuery(/* string */ $service,
                             /* string */ $method,
                             Result $result = null,
                             array $args = null,
                             array $headerMap = null)
  {
    $queryId = $this->queryCount++;
    
    $msg = new QueryMessage($headerMap, '/client', $queryId, $service, $method, $args);
    
    $listeners = $msg->getListeners();
    
    if ($listeners !== null) {
      foreach ($listeners as $address => $listener) {        
        $this->listenerMap[$address] = $listener;
      }
    }
    
    if ($result !== null) {
      $this->resultMap[$queryId] = $result;
    }
    
    return $msg;
  }
  
  public function poll()
  {
    $msgArray = $this->transport->poll();

    foreach ($msgArray as $msg) {
      $this->onMessage($msg);
    }
    
    return count($msgArray);
  }
  
  private function onMessage(Message $msg)
  {
    if ($msg instanceof ReplyMessage) {
      $queryId = $msg->getQueryId();
      $result = $this->removeResult($queryId);
      
      if ($result !== null) {
        $result->complete($msg->getValue());
      }
      else {
        throw new \Exception('cannot find request for query id: ' . queryId);
      }
    }
    else if ($msg instanceof ErrorMessage) {
      $queryId = $msg->getQueryId();
      $result = $this->removeResult($queryId);
      
      if ($result !== null) {
        $result->fail($msg->getError());
      }
      else {
        throw new \Exception('cannot find request for query id: ' . queryId);
      }
    }
    else if ($msg instanceof SendMessage) {
      $listener = $this->getListener($msg->address);
      
      $method = $msg->method;
      
      $listener->$method($msg->args);
    }
    else {
      throw new \Exception('unexpected jamp message type: ' . msg);
    }
  }
  
  private function removeResult(/* int */ $queryId)
  {
    $result = $this->resultMap[$queryId];
    
    unset($this->resultMap[$queryId]);
    
    return $result;
  }
  
  private function getListener(/* string */ $listenerAddress)
  {
    return $this->listenerMap[$listenerAddress];
  }
  
  public function close()
  {
    $this->transport->close();
  }
}

class Result
{ 
  private $isCompleted;
  private $isFailed;
  
  private $value;
  private $error;
  
  public function __construct()
  {
  }
  
  public function complete($value)
  {
    $this->isCompleted = true;
    $this->value = $value;
  }
  
  public function fail($error)
  {
    $this->isFailed = true;
    $this->error = $error;
  }
  
  public function isCompleted()
  {
    return $this->isCompleted;
  }
  
  public function isFailed()
  {
    return $this->isFailed;
  }
  
  public function getValue()
  {
    return $this->value;
  }
  
  public function getError()
  {
    return $this->error;
  }
}

