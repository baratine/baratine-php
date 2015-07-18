baratine-php
============
baratine-php is a PHP client for `Baratine <http://baratine.io/>`_.


Installation
------------

::

  $ composer require baratine/baratine


Usage
---------
::

  <?php

  //require_once('baratine-php/src/Baratine/baratine-client.php');

  use Baratine\BaratineClient;

  function main()
  {
    $client = new BaratineClient('http://127.0.0.1:8085/s/pod');

    $counter = $client->_lookup('/counter/123')->_as('CounterService');

    $result = counter->addAndGet(1000);

    var_dump($result);

    $client->close();
  }

  abstract class CounterService
  {
    public abstract function addAndGet(/* int */ $value);
    public abstract function incrementAndGet();
    public abstract function get();
  }

  main();



