<?php 

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload

use Vsmoraes\Aegir\Core\Server AS VsmServer;

$server = new VsmServer('localhost', 10000, true);
//$server->run();