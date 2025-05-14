<?php
/**
 * databaze.php
 *
 * připojení k databázi + ověření
 *
 * @author Mayer (mayersta)
 *
 */
require ("dbconfig.php");

$link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWD);

if (!$link) {
echo "Nepodařilo se spojit s DB.<br>";
echo mysqli_connect_error();
exit();
}

$success = mysqli_select_db($link, DB_NAME);
if (!$success) {
echo "Nepodařilo se přepnout na správnou databázi";
exit();
}

$link->query("SET NAMES utf8");



?>