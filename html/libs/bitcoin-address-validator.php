<?php
if (!$_GET['address']) {	
	exit;
}

include('PackageLoader.php');
$loader = new PackageLoader\PackageLoader();
$loader->load('bitcoin-address-validator');
use Kielabokkie\Bitcoin\AddressValidator;

echo AddressValidator::isValid($_GET['address']) ? 1 : 0;