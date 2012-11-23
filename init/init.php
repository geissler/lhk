<?php

$mysqli = new mysqli(tunnel.pagodabox.com,  $_SERVER["DB_USER"], $_SERVER["DB1_PASSWORD"], $_SERVER["DB_NAME"], 3306);
$mysqli->query(file_get_contents(__DIR__ . '/ini.sql'));
$mysqli->close();