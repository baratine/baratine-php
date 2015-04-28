<?php

namespace baratine;

require_once('jamp.php');
require_once('jamp-client.php');

class HttpTransport
{
  private $url;
  private $client;
  
  private $isClosed;
  
  private $currentPullRequest;
  
  function __construct(/* string */ $url, JampClient $client)
  {
    $this->url = $url;
    $this->client = $client;
    
    $this->isClosed = false;
  }
  
  public function submitRequest(Request $request)
  {
    if ($this->isClosed) {
      throw new Exception('connection already closed');
    }
    
    $url = $request->msg->toUrl($this->url);
    
    $curl = curl_init($url);
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
    
    if ($json !== false) {
      //$request->sent($this->client);
      
      $value = \json_decode($json);
      
      if (is_array($value)) {
        $value = @$value[0];
      }
      
      $request->completed($this->client, $value);
      
      //$this->client->onMessage($value);
    }
    else {
      error_log('error submitting request: ' . curl_error($curl));
    }
    
    curl_close($curl);
  }
  
  private function poll()
  {
    if ($this->isClosed) {
      return;
    }
    
    $curl = curl_init($this->url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: x-application/jamp-pull'));
    curl_setopt($curl, CURLOPT_POSTFIELDS, array('[]'));
    
    $json = curl_exec($curl);
    
    var_dump($json);
        
    if ($json !== false) {
      $list = json_decode($json);
    
      $this->client->onMessageArray($list);
    }
    else {
      error_log('error polling: url=' . curl_error($curl));
    }
    
    curl_close($curl);
  }
    
  public function close()
  {
    $this->isClosed = true;
    
    if ($this->pullRequest !== null) {
      try {
        $this->pullRequest->abort();
      }
      catch (Exception $e) {
      }
    }
  }
  
}

