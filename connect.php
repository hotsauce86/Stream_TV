<?php

$servername = "localhost";
$username = “your_username”;
$password = “your_password”;
$dbname = “your_dbname”;


$db = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
// set the PDO error mode to exception
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

?>