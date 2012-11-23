<?php

$mysqli = new mysqli(tunnel.pagodabox.com,  $_SERVER["DB_USER"], $_SERVER["DB1_PASSWORD"], $_SERVER["DB_NAME"], 3306);
$mysqli->query(file_get_contents(__DIR__ . '/init.sql'));
$mysqli->close();
unlink(__DIR__ . '/init.sql');
unlink(__DIR__ . '/init.php');