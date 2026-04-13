<?php
//  OpenEMR
//  MySQL Config

global $disable_utf8_flag;
$disable_utf8_flag = false;


$host	= 'localhost';
$port	= '3306';
$login	= 'root';
$pass	= '';
$dbase	= 'openemr_db';
$db_encoding	= 'utf8mb4';

$sqlconf = array();
global $sqlconf;
$sqlconf["host"] = $host;
$sqlconf["port"] = $port;
$sqlconf["login"] = $login;
$sqlconf["pass"] = $pass;
$sqlconf["dbase"] = $dbase;
$sqlconf["db_encoding"] = $db_encoding;



// $host	= '';
// $port	= '';
// $login	= '';
// $pass	= '';
// $dbase	= '';
// $db_encoding	= 'utf8mb4';

// $sqlconf = array();
// global $sqlconf;
// $sqlconf["host"]= $host;
// $sqlconf["port"] = $port;
// $sqlconf["login"] = $login;
// $sqlconf["pass"] = $pass;
// $sqlconf["dbase"] = $dbase;
// $sqlconf["db_encoding"] = $db_encoding;

//////////////////////////
//////////////////////////
//////////////////////////
//////DO NOT TOUCH THIS///
$config = 1; /////////////
//////////////////////////
//////////////////////////
//////////////////////////
?>
