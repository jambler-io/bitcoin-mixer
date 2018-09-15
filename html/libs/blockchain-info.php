<?php
$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_CUSTOMREQUEST => 'GET',
	CURLOPT_URL => 'https://blockchain.info/rawaddr/'.$_GET['address'],
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => '',
	CURLOPT_TIMEOUT => 30,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_HTTPHEADER => array(
		'Cache-Control: no-cache',
		'Content-Type: application/json'
	)
));
$res = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);
if ($err) {
	echo '{"err":"'.$err.'"}';
}
else {
	json_decode($res);
	if (json_last_error() == JSON_ERROR_NONE) {
		echo $res;
	}
	else {
		echo '{"err":"'.$res.'"}';
	}
}
?>
