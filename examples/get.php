<?php
require_once('../lib/Prism.php');

$client = new Prism($url = 'http://192.168.51.50:8080/api', $key = 'pufy2a7d', $secret = 'skqovukpk2nmdrljphgj');


echo $client->get('/test/test');

// 返回
// {"httpMethod":"GET","responseTime":"10ms"}