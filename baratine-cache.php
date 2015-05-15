<?php

namespace baratine\cache;

require_once('jamp-client.php');

abstract class Service
{
  private $jampClient;
  private $serviceName;
  
  function __construct(/* string */ $url, /* string */ $serviceName)
  {
    $this->jampClient = \baratine\JampClient::create($url);
    
    $this->serviceName = $serviceName;
  }

  public function query($id, $method, $args = null)
  {
    $serviceName = $this->serviceName;
    
    if ($id !== null) {
      $serviceName .= '/' . $id;
    }
    
    return $this->jampClient->querySync($serviceName, $method, $args);
  }
}

class MapManagerService extends Service
{
  function __construct(/* string */ $url, /* string */ $serviceName = '/map')
  {
    parent::__construct($url, $serviceName);
  }
  
  public function lookup(/* string */ $id)
  {
    return new MapService($this, $id);
  }
}

class MapService
{
  private $manager;
  private $id;
  
  function __construct(MapManagerService $manager, /* string */ $id)
  {
    $this->manager = $manager;
    $this->id = $id;
  }
  
  public function get(/* string */ $key)
  {
    return $this->manager->query($this->id, 'get', array($key));
  }
  
  public function getAll()
  {
    $result = $this->manager->query($this->id, 'getAll');
    
    return $this->toArray($result);
  }
  
  public function put(/* string */ $key, $value)
  {
    return $this->manager->query($this->id, 'put', array($key, $value));
  }
  
  public function putMap(array $map)
  {
    return $this->manager->query($this->id, 'putMap', array($map));
  }
  
  public function containsKey(/* string */ $key)
  {
    return $this->manager->query($this->id, 'containsKey', array($key));  
  }
  
  public function remove(/* string */ $key)
  {
    return $this->manager->query($this->id, 'remove', array($key));  
  }
  
  public function size()
  {
    return $this->manager->query($this->id, 'size');
  }
  
  public function clear()
  {
    return $this->manager->query($this->id, 'clear');
  }
  
  private function toArray($obj)
  {
    if ($obj == null) {
      return $obj;
    }
    else {
      return (array) $obj;
    }
  }
}

class ListManagerService extends Service
{
  function __construct(/* string */ $url, /* string */ $serviceName = '/list')
  {
    parent::__construct($url, $serviceName);
  }
  
  public function lookup(/* string */ $id)
  {
    return new ListService($this, $id);
  }
}

class ListService
{
  private $manager;
  private $id;
  
  function __construct(ListManagerService $manager, /* string */ $id)
  {
    $this->manager = $manager;
    $this->id = $id;
  }
  
  public function get(/* string */ $index)
  {
    return $this->manager->query($this->id, 'get', array($index));
  }
  
  public function getAll()
  {
    return $this->manager->query($this->id, 'getAll');
  }
  
  public function getRange(/* int */ $start, /* int */ $end)
  {
    return $this->manager->query($this->id, 'getRange', array($start, $end));
  }
  
  public function pushHead($value)
  {
    return $this->manager->query($this->id, 'pushHead', array($value));
  }
  
  public function pushTail($value)
  {
    return $this->manager->query($this->id, 'pushTail', array($value));  
  }
  
  public function popHead()
  {
    return $this->manager->query($this->id, 'popHead');
  }
  
  public function popTail()
  {
    return $this->manager->query($this->id, 'popTail');
  }
  
  public function trim(/* int */ $start, /* int */ $end)
  {
    return $this->manager->query($this->id, 'trim', array($start, $end));  
  }
  
  public function size()
  {
    return $this->manager->query($this->id, 'size');
  }
  
  public function clear()
  {
    return $this->manager->query($this->id, 'clear');
  }
}

class ScoreManagerService extends Service
{
  function __construct(/* string */ $url, /* string */ $serviceName = '/tree')
  {
    parent::__construct($url, $serviceName);
  }
  
  public function lookup(/* string */ $id)
  {
    return new ScoreService($this, $id);
  }
}

class ScoreService
{
  private $manager;
  private $id;
  
  function __construct(ScoreManagerService $manager, /* string */ $id)
  {
    $this->manager = $manager;
    $this->id = $id;
  }
  
  public function get(/* string */ $key)
  {
    return $this->manager->query($this->id, 'get', array($key));
  }
  
  public function put(/* string */ $key, /* int/string */ $score)
  {
    return $this->manager->query($this->id, 'put', array($key, $score));
  }
  
  public function remove(/* string */ $key)
  {
    return $this->manager->query($this->id, 'remove', array($key));
  }
  
  public function getRange(/* int */ $start, /* int */ $end)
  {
    return $this->manager->query($this->id, 'getRange', array($start, $end));
  }
  
  public function getRangeDescending(/* int */ $start, /* int */ $end)
  {
    return $this->manager->query($this->id, 'getRangeDescending', array($start, $end));
  }
  
  public function getRangeKeys(/* int */ $start, /* int */ $end)
  {
    return $this->manager->query($this->id, 'getRangeKeys', array($start, $end));
  }
  
  public function getRangeDescendingKeys(/* int */ $start, /* int */ $end)
  {
    return $this->manager->query($this->id, 'getRangeDescendingKeys', array($start, $end));
  }
  
  public function size()
  {
    return $this->manager->query($this->id, 'size');
  }
  
  public function clear()
  {
    return $this->manager->query($this->id, 'clear');
  }
}

class CounterManagerService extends Service
{
  function __construct(/* string */ $url, /* string */ $serviceName = '/counter')
  {
    parent::__construct($url, $serviceName);
  }
  
  public function lookup(/* string */ $id)
  {
    return new CounterService($this, $id);
  }
}

class CounterService
{
  private $manager;
  private $id;
  
  function __construct(CounterManagerService $manager, /* string */ $id)
  {
    $this->manager = $manager;
    $this->id = $id;
  }
  
  public function get()
  {
    return $this->manager->query($this->id, 'get');
  }
  
  public function set(/* int */ $value)
  {
    return $this->manager->query($this->id, 'set', array($value));
  }
  
  public function increment(/* int */ $value)
  {
    return $this->manager->query($this->id, 'increment', array($value));
  }
  
  public function delete()
  {
    return $this->manager->query($this->id, 'delete');  
  }
}

function test()
{
  var_dump("test0");

  $mapManager = new MapManagerService('http://localhost:8085/s/pod');
  $map = $mapManager->lookup('5');

  var_dump("test1");
  

  

  echo "result:\n";
  
  var_dump($map->clear());
  
  var_dump($map->size());
  var_dump($map->getAll());
  
  var_dump("test2");

  var_dump($map->put('foo', 'bar'));
  
  
  //var_dump($map->putMap(array('foo0' => 'bar0')));
  //die();
  
  
  var_dump($map->get('foo'));
  var_dump($map->getAll());
  var_dump($map->size());

  
  echo "\ncounter:\n";
  
  $counterManager = new CounterManagerService('http://localhost:8085/s/pod');
  $counter = $counterManager->lookup('5');
  
  var_dump($counter->increment(1));
  
  var_dump("test4");
  
  echo "\nlist:\n";
  
  $listManager = new ListManagerService('http://localhost:8085/s/pod');
  $list = $listManager->lookup('5');
  
  var_dump($list->pushHead('abc'));
  var_dump($list->getAll());
}

//echo "<pre>";
//test();

//die();

