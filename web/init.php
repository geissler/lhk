<?php

$mysqli = new mysqli($_SERVER["DB1_HOST"], $_SERVER["DB1_USER"], $_SERVER["DB1_PASS"], $_SERVER["DB1_NAME"], $_SERVER["DB1_PORT"]);
$mysqli->query(file_get_contents(__DIR__ . '/init.sql'));
$mysqli->close();
unlink(__DIR__ . '/init.sql');
unlink(__DIR__ . '/init.php');