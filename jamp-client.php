<?php

namespace baratine;

require_once('jamp.php');
require_once('transport-http.php');

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
                                 array $args = null,
                                 callable $callback,
                                 array $headerMap = null);

  public abstract function close();
  public abstract function reconnect();
}

class JampClientImpl extends JampClient
{
  private $transport;
  
  private $requestMap;
  private $listenerMap;
  
  private $queryCount;
  
  function __construct(/* string */ $url)
  {
    $this->requestMap = array();
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
      $this->transport = new HttpTransport($url, $this);
    }
    else {
      throw new \Exception('invalid url: ' . $url);
    }
  }
  
  public function onMessage(Message $msg)
  {
    if ($msg instanceof ReplyMessage) {
      $queryId = $msg->queryId;
      $request = $this->removeRequest($queryId);
      
      if ($request !== null) {
        $request->completed($this, $msg->result);
      }
      else {
        error_log('cannot find request for query id: ' . queryId);
      }
    }
    else if ($msg instanceof ErrorMessage) {
      $queryId = $msg->queryId;
      $request = $this->removeRequest($queryId);
      
      if ($request !== null) {
        $request->error($this, $msg->result);
      }
      else {
        error_Log('cannot find request for query id: ' . queryId);
      }
    }
    else if ($msg instanceof SendMessage) {
      $listener = $this->getListener($msg->address);
      
      $method = $msg->method;
      
      $listener->$method($msg->parameters);
    }
    else {
      throw new Exception('unexpected jamp message type: ' . msg);
    }
  }
  
  public function onMessageArray(array $array)
  {
    foreach ($array as $json) {
      $msg = Jamp::unserializeArray($json);
      
      $this->onMessage($msg);
    }
  }
  
  private function expireRequests()
  {
    $expiredRequests = array();
    
    foreach ($this->requestMap as $queryId => $request) {
      $expiredRequests[] = $request;
    }
    
    foreach ($expiredRequests as $request) {
      $this->removeRequrst($request->queryId);
      
      $request->error($this, 'request expired');
    }
  }
  
  public function removeRequest(/* int */ $queryId)
  {
    $request = $this->requestMap[$queryId];
    
    unset($this->requestMap[$queryId]);
    
    return $request;
  }
  
  public function close()
  {
    $this->transport->close();
  }
  
  public function reconnect()
  {
    $this->transport->reconnect();
  }
  
  public function submitRequest(Request $request)
  {
    $this->transport->submitRequest($request);
  }
  
  public function onMessageJson(/* string */ $json, JampClient $client)
  {
    $msg = Jamp::unserialize($json);
    
    $client->onMessage($msg);
  }
  
  private function getListener(/* string */ $listenerAddress)
  {
    return $this->listenerMap[$listenerAddress];
  }
  
  public function send(/* string */ $service,
                      /* string */ $method,
                      array $args = null,
                      array $headerMap = null)
  {
    $queryId = $this->queryCount++;
    
    $msg = new SendMessage($headerMap, $service, $method, $args);
    
    $request = $this->createSendRequest($queryId, $msg);
    
    $this->submitRequest($request);
  }
  
  public function query(/* string */ $service,
                        /* string */ $method,
                        array $args = null,
                        callable $callback = null,
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
    
    $request = $this->createQueryRequest($queryId, $msg, $callback);
    
    $this->submitRequest($request);
    
    return $request->getResult();
  }
  
  public function onFail($error)
  {
    error_log('error: ' . json_encode($error));
  }
  
  private function createQueryRequest(/* int */ $queryId, Message $msg, callable $callback = null)
  {
    $request = new QueryRequest($queryId, $msg, $callback);
    
    $this->requestMap[$queryId] = $request;
    
    return $request;
  }
  
  private function createSendRequest(/* int */ $queryId, Message $msg)
  {
    $request = new SendRequest($queryId, $msg);
    
    $this->requestMap[$queryId] = $request;
    
    return $request;
  }
}

class Request
{
  public $queryId;
  public $msg;
  
  private $expirationTime;
  
  function __construct(/* int */ $queryId, Message $msg, /* int */ $timeout = null)
  {
    $this->queryId = $queryId;
    $this->msg = $msg;
    
    $this->expirationTime = $timeout;
    
    if ($timeout == null) {
      $this->expirationTime = time() + 60 * 5;
    }
  }
  
  public function isExpired(/* int */ $now)
  {
    if ($now === null) {
      $now = time();
    }
    
    return ($now - $this->expirationTime) > 0;
  }
  
  public function sent(JampClient $client)
  {
  }
  
  public function completed(JampClient $client, $value)
  {
    $client->remove($this->queryId);
  }
  
  public function error(JampClient $client, $value)
  {
    $client->remove($queryId);
    
    error_log(value);
  }
}

class SendRequest extends Request
{
  function __construct(/* int */ $queryId, Message $msg, /* int */ $timeout = null)
  {
    parent::__construct($queryId, $msg, $timeout);
  }
  
  public function sent(JampClient $client)
  {
    $client->removeRequest($this->queryId);
  }
}

class QueryRequest extends Request
{
  private $callback;
  private $result;
  
  function __construct(/* int */ $queryId, Message $msg, callable $callback = null, int $timeout = null)
  {
    parent::__construct($queryId, $msg, $timeout);
    
    $this->callback = $callback;
  }
  
  public function completed(JampClient $client, $value)
  {
    $this->result = $value;
    
    $client->removeRequest($this->queryId);
        
    $callback = $this->callback;
    
    if ($callback !== null) {
      $callback($value);
    }
  }
  
  public function error(JampClient $client, $value)
  {
    $client->removeRequest($this->queryId);
    
    if ($this->callback !== null && $this->callback->onFail !== null) {
      $this->callback->onFail($value);
    }
    else {
      error_log($value);
    }
  }
  
  public function getResult()
  {
    return $this->result;
  }
}

