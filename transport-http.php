<?php

namespace baratine;

class HttpTransport //extends Transport
{ 
  private $curl;
  
  function __construct(/* string */ $url)
  {
    parent::__construct($url);
    
    $this->curl = curl_init();
  }
  
  public function send(Message $msg)
  {
    if ($this->curl === null) {
      throw new \Exception('connection already closed');
    }
  }
  
  public function query(QueryMessage $msg)
  {
    if ($this->curl === null) {
      throw new \Exception('connection already closed');
    }
    
    $url = $msg->toUrl($this->url);
    
    $curl = $this->curl;
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    /*
    $msg = $request->msg;
    
    $json = $msg->serialize();
    $json = '[' . $json . ']';
    
    $curl = curl_init($this->url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: x-application/jamp-push'));
    curl_setopt($curl, CURLOPT_POSTFIELDS, array($json));
    */
    
    $json = curl_exec($curl);
    
    if ($json === false) {
      throw new \Exception('error submitting message: ' . curl_error($curl));
    }
    
    $response = \json_decode($json);
    
    if (! isset($response->status)) {
      throw new \Exception('status not found in response: ' . $json);
    }
    else if ($response->status != 'ok') {
      $error = $json;
      
      //if (isset($response->error)) {
      //  $error = $response->error;
      //}
      
      throw new \Exception('error response: ' . $error);
    }
    
    $value = @$response->value;
    
    return $value;
  }
  
  public function poll()
  {
    if ($this->curl === null) {
      return;
    }
    
    $curl = $this->curl;
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_GET, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: x-application/jamp-pull'));
    //curl_setopt($curl, CURLOPT_POSTFIELDS, array('[]'));
    
    $json = curl_exec($curl);
        
    if ($json !== false) {
      $list = json_decode($json);
    
      $this->client->onMessageArray($list);
    }
    else {
      error_log('error polling: url=' . curl_error($curl));
    }
  }
    
  public function close()
  {
    curl_close($this->curl);
    $this->curl = null;
  }
}

