<?php
include('PackageLoader.php');

$loader = new PackageLoader\PackageLoader();
$loader->load('qrcode');

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

$options = new QROptions([
	'version' => 5,
	'outputType' => QRCode::OUTPUT_IMAGE_PNG,
	'eccLevel' => QRCode::ECC_L,
	'scale' => 5,
	'imageBase64' => false,
	'imageTransparent' => false,
]);

header('Content-type: image/png');
echo (new QRCode($options))->render(@$_GET['text']);
?>
