<?php
if (!$_GET['address']) {
	echo 0;
	exit;
}

include('PackageLoader.php');
$loader = new PackageLoader\PackageLoader();
$loader->load('bitcoin-address-validator');
use LinusU\Bitcoin\AddressValidator;

echo AddressValidator::isValid($_GET['address']) ? 1 : 0;
