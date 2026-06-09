<?php
$host = "localhost";
$user = "u481409735_eysi";
$pass = "Eysiparkinglot27";
$dbname = "u481409735_eysiparkinglot";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
  
  $conn->query("SET time_zone = '+08:00'");

}


?>
