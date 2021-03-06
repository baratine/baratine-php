<?php

namespace Baratine;

require_once('HttpTransport.php');
require_once('HttpPushPullTransport.php');

abstract class Transport
{
  protected $url;
  
  protected function __construct(/* string */ $url)
  {
    $this->url = $url;
  }

  public abstract /* void */ function send(Message $msg);
  public abstract /* void */ function query(QueryMessage $msg);
  public abstract /* value */ function querySync(QueryMessage $msg);
  
  public abstract /* array */ function poll();
  
  public function /* string */ __toString()
  {
    return __CLASS__ . '[' . $this->url . ']';
  }
}

