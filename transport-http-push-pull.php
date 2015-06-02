<?php

namespace baratine;

class HttpPushPullTransport extends Transport
{ 
  private $curl;
  private $cookies;
  
  function __construct(/* string */ $url)
  {
    parent::__construct($url);
    
    $this->curl = curl_init($url);
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->curl, CURLOPT_COOKIEFILE, '');
    
    $this->cookies = array();
  }
  
  public function send(Message $msg)
  {
    if ($this->curl === null) {
      throw new Exception('connection already closed');
    }
  }
  
  public function query(QueryMessage $msg)
  {
    if ($this->curl === null) {
      throw new \Exception('connection already closed');
    }

    $json = $msg->serialize();
    $json = '[' . $json . ']';
    
    $curl = $this->curl;
    
    curl_setopt($curl, CURLOPT_POST, true);
    
    $headers = array('Content-Type: x-application/jamp-push');
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
    
    log('query sent: ' . $json);
    
    $data = curl_exec($curl);
    
    if ($data === false) {
      throw new \Exception('error submitting message: ' . curl_error($curl));
    }
    
    log('query response: ' . $data);
    
    $responses = array();
    
    if ($data !== '') {
      $list = json_decode($data);
      
      if ($list == null) {
        throw new \Exception($data);
      }
    
      foreach ($list as $array) {
        $msg = Jamp::unserializeArray($array);
        
        $responses[] = $msg;
      }
    }
    
    return $responses;
  }
  
  public function querySync(QueryMessage $msg)
  {
    if ($this->curl === null) {
      throw new \Exception('connection already closed');
    }

    $json = $msg->serialize();
    $json = '[' . $json . ']';
    
    $curl = $this->curl;
    
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    $headers = array('Content-Type: x-application/jamp-rpc');
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
    
    log('querySync sent: ' . $json);
    
    $data = curl_exec($curl);
    
    if ($data === false) {
      throw new \Exception('error submitting message: ' . curl_error($curl));
    }
    
    log('querySync response: ' . $data);
    
    $responses = array();
    
    if ($data !== '') {
      $list = json_decode($data);
      
      if ($list == null) {
        throw new \Exception($data);
      }
    
      foreach ($list as $array) {
        $msg = Jamp::unserializeArray($array);
        
        $responses[] = $msg;
      }
    }
    
    return $responses;
  }
  
  public function poll()
  {
    if ($this->curl === null) {
      return;
    }
    
    $curl = $this->curl;
    
    curl_setopt($curl, CURLOPT_HTTPGET, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    $headers = array('Content-Type: x-application/jamp-pull');
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    
    $data = curl_exec($curl);
    
    log('poll response: ' . $data);
    
    if ($data === false) {
      throw new Exception('error polling: ' . curl_error($curl));
    }
    
    $list = json_decode($data);
    
    if ($list === null) {
      throw new \Exception($data);
    }
    
    $responses = array();
    
    foreach ($list as $array) {
      $msg = Jamp::unserializeArray($array);
      
      $responses[] = $msg;
    }
    
    return $responses;
  }
    
  public function close()
  {
    curl_close($this->curl);
    $this->curl = null;
  }
  
}

function log($msg)
{
  //var_dump($msg);
}

