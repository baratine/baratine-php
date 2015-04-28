<?php

namespace baratine;

//$file = fopen('http://192.168.1.147:8085/s/pod/map/5?m=getAll');

var_dump(file_get_contents('http://192.168.1.147:8085/s/pod/map/5?m=getAll'));


require_once('jamp-client.php');

$msg = Jamp::unserialize('["query",{},"me",11,"/foo","getUserCount"]');
echo ($msg->serialize());

echo "\n";

$msg = Jamp::unserialize('["reply",{},"me",0,123]');
echo ($msg->serialize());

echo "\n";

$msg = Jamp::unserialize('["error",{},"me",4,"UNKNOWN","\'/foo2\' is an unknown service","io.baratine.core.ServiceNotFoundException"]');
echo ($msg->serialize());

echo "\n";

$msg = Jamp::unserialize('["send",{},"/foo","getUserCount", 1, 2]');
echo ($msg->serialize());

echo "\n";
echo "\n";

$jampClient = JampClient::create('http://192.168.1.147:8085/s/pod');

$jampClient->query(
  '/map/5',
  'getAll',
  null,
  function($value) {
    echo "hello world\n";
    var_dump($value);
  }
);


//var_dump($jampClient);

