<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload

use Vsmoraes\Aegir\Aegir as Server;

(new Server('localhost', 10000, true))->run();
