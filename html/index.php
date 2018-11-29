<?php
///////////////////////////////////////////////////

$YOUR_JAMBLER_API_KEY = '__YOUR_JAMBLER_API_KEY__';

$titles = array(
	'index' => '{mixer_name} — bitcoin mixer 2.0',
	'mix' => 'Second page — {mixer_name}',
	'result' => 'First page — {mixer_name}'
);

$descriptions = array(
	'index' => 'Main page description',
	'mix' => 'Second page description',
	'result' => 'First page description'
);

$tor_address = '';

///////////////////////////////////////////////////

$apiUrl = 'https://api.jambler.io';
$clearCDN = '';
$darkCDN = '';
$coin_ids = array(
	'btc'
);
$version = 13;


if (isset($_GET['guarantee'])) {
	header('Content-type: text/plain');
	header('Content-Disposition: attachment; filename="LetterGuarantee.txt"');
	echo @$_REQUEST['text'];
	exit();
}

$dir = dirname($_SERVER['PHP_SELF']);
if (substr($dir, -1) === '/') {
	$dir = substr($dir, 0, -1);
}
$query_string = !empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '';

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_CUSTOMREQUEST => 'GET',
	CURLOPT_URL => $apiUrl.'/partners/info/'.$coin_ids[0],
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => '',
	CURLOPT_TIMEOUT => 10,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_HTTPHEADER => array(
		'Cache-Control: no-cache',
		'Content-Type: application/json',
		'xkey: '.$YOUR_JAMBLER_API_KEY
	)
));
$info = json_decode(curl_exec($curl), true);
$err = curl_error($curl);
curl_close($curl);
if ($err) {
	echo $err;
	exit();
}
elseif (!empty($info['error_message'])) {
	echo $info['error_message'];
	exit();
}
if (!isset($info['mixer_fee_pct'])) $info['mixer_fee_pct'] = 2;
if (!isset($info['mixer_name'])) $info['mixer_name'] = 'Partner bitcoin mixer';
$info['order_lifetime'] = floor($info['order_lifetime'] / 24);
$info['mixer_fix_fee'] = round($info['mixer_fix_fee'] / 100000000, 5);
$info['min_amount'] = round($info['min_amount'] / 100000000, 5);
$info['max_amount'] = round($info['max_amount'] / 100000000, 5);

$cdn = strrchr($_SERVER['HTTP_HOST'], '.') == '.onion' ? $darkCDN : $clearCDN;

function newSecurityCode() {
	$exit = '<?php exit("Restricted area");?>';
	$ut = date('U');
	$code = md5(password_hash($ut, PASSWORD_DEFAULT));
	file_put_contents('codes.php', $exit."\t".$ut."\t".$code."\n", FILE_APPEND | LOCK_EX);
	return $code;
}

function securityCodeValidator() {
	if (empty($_REQUEST['code'])) {
		return false;
	}
	$file = @file('codes.php');
	if (empty($file)) {
		return true;
	}
	$row = current(preg_grep('/'.@$_REQUEST['code'].'/', $file));
	if (empty($row)) {
		return false;
	}
	$row_num = array_search($row, $file);
	list($_, $ut) = explode("\t", $row);
	unset($file[$row_num]);
	file_put_contents('codes.php', implode('', $file), LOCK_EX);
	if ((date('U') - $ut) < 3) {
		return false;
	}
	return true;
}

function addressValidator($address) {
	global $YOUR_JAMBLER_API_KEY;
	$exit = '<?php exit("Restricted area");?>';
	$file = @file('addresses.php');
	if (empty($file)) {
		$file = array();
	}
	$hash = md5($YOUR_JAMBLER_API_KEY.substr($address, 6, -6));
	$row = current(preg_grep('/'.$hash.'/', $file));
	if (empty($row)) {
		$file[] = $exit."\t".date('U')."\t".$hash."\t1\n";
		file_put_contents('addresses.php', implode('', $file), LOCK_EX);
	}
	else {
		$row_num = array_search($row, $file);
		list($_, $ut, $hash, $count) = explode("\t", $row);
		if ($count >= 5) {
			if ((date('U') - $ut) >= 60 * 60 * 24) {
				unset($file[$row_num]);
				file_put_contents('addresses.php', implode('', $file), LOCK_EX);
			}
			else {
				return array(null, 'Blocked address');
			}
		}
		else {
			if ((date('U') - $ut) >= 20) {
				unset($file[$row_num]);
			}
			else {
				$file[$row_num] = $exit."\t".$ut."\t".$hash."\t".((int)$count + 1)."\n";
			}
			file_put_contents('addresses.php', implode('', $file), LOCK_EX);
		}
	}
	$proto = !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : (!empty($_SERVER['HTTPS']) ? 'https': 'http');
	$host = strrchr($_SERVER['HTTP_HOST'], '.') == '.onion' ? 'http://localhost:8080' : $proto.'://'.$_SERVER['HTTP_HOST'];
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => $host.'/libs/bitcoin-address-validator.php?address='.$address,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_TIMEOUT => 10,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'GET'
	));
	$res = curl_exec($curl);
	$err = curl_error($curl);
	if ($err) {
		return array(null, $err);
	}
	else {
		return array($res, null);
	}
}

$p = 'index';
if (isset($_GET['mix'])) {
	$p = 'mix';
	if (isset($_REQUEST['submit_mix'])) {
		$ERR = array();
		if (empty($_REQUEST['forward_addr'])) {
			$ERR['forward_addr'] = 'This field is required';
		}
		elseif (!securityCodeValidator()) {
			$ERR['forward_addr'] = 'Wait 2 seconds before submit';
		}
		else {
			list($res, $err) = addressValidator($_REQUEST['forward_addr']);
			if ($err) {
				$ERR['forward_addr'] = $err;
			}
			elseif ($res != '1') {
				$ERR['forward_addr'] = 'Wrong format of BTC Address';
			}
			if (!empty($_REQUEST['forward_addr2'])) {
				list($res, $err) = addressValidator($_REQUEST['forward_addr2']);
				if ($err) {
					$ERR['forward_addr2'] = $err;
				}
				elseif ($res != '1') {
					$ERR['forward_addr2'] = 'Wrong format of BTC Address';
				}
			}
		}
		if (empty($ERR)) {
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_URL => $apiUrl.'/partners/orders/'.$coin_ids[0],
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_TIMEOUT => 10,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_HTTPHEADER => array(
					'Cache-Control: no-cache',
					'Content-Type: application/json',
					'xkey: '.$YOUR_JAMBLER_API_KEY
				),
				CURLOPT_POSTFIELDS => json_encode(array(
					'forward_addr' => $_REQUEST['forward_addr'],
					'forward_addr2' => $_REQUEST['forward_addr2']
				))
			));
			$res = json_decode(curl_exec($curl), true);
			$err = curl_error($curl);
			curl_close($curl);
			if ($err) {
				$ERR['curl'] = $err;
			}
			elseif (!empty($res['error_message'])) {
				$ERR['curl'] = $res['error_message'];
			}
			else {
				header('Location: ?result&address='.$res['address'].'&guarantee_text='.bin2hex(gzcompress($res['guarantee'], 9)));
				exit();
			}
		}
	}
}

if (isset($_GET['result'])) {
	$p = 'result';
	if (!empty($_GET['guarantee_text'])) {
		$guarantee_text = gzuncompress(hex2bin($_GET['guarantee_text']));
	}
	$you_send = !empty($_REQUEST['you_send']) ? $_REQUEST['you_send'] : 1;
	$you_receive = $you_send - $you_send * $info['mixer_fee_pct'] / 100 - $info['mixer_fix_fee'];
}

foreach ($titles as $_p => $v) {
	$titles[$_p] = str_replace('{mixer_name}', $info['mixer_name'], $v);
}
$title = $titles[$p];
foreach ($descriptions as $_p => $v) {
	$descriptions[$_p] = str_replace('{mixer_name}', $info['mixer_name'], $v);
}
$description = $descriptions[$p];
?>

<!doctype html>
<html lang="en">
<head>
	<title><?php echo @$title?></title>
	<meta name="description" content="<?php echo @$description?>" />
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<!--[if IE]><link rel="shortcut icon" href="<?php echo $cdn?>/j-images/favicon.ico"><![endif]-->
	<link rel="apple-touch-icon-precomposed" href="<?php echo $cdn?>/j-images/apple-touch-icon-precomposed.png">
	<link rel="icon" href="<?php echo $cdn?>/j-images/favicon.png">
	<link rel="stylesheet" href="<?php echo $cdn?>/bootstrap/bootstrap.min.css?v=<?php echo $version?>">
	<link rel="stylesheet" href="<?php echo $cdn?>/j-fonts/style.css?v=<?php echo $version?>">
	<link rel="stylesheet" href="<?php echo $cdn?>/style.tpl.css?v=<?php echo $version?>">
	<link rel="stylesheet" href="<?php echo $cdn?>/libs/toastr.min.css?v=<?php echo $version?>">
	<script src="<?php echo $cdn?>/libs/jquery-3.3.1.min.js"></script>
	<script src="<?php echo $cdn?>/libs/toastr.min.js"></script>
	<script src="<?php echo $cdn?>/libs/helpers.js"></script>
	<script>
	$(document).ready(function() {
		$('#calculate_btn').hide();
		$('input[name=you_send]').on('keyup mouseup', e => {
			var mixer_fee_pct = <?php echo $info['mixer_fee_pct']?>;
			var mixer_fix_fee = <?php echo $info['mixer_fix_fee']?>;
			var you_send = $(e.target).val();
			$('#you_receive').text(Math.round((you_send - you_send * mixer_fee_pct /100 - mixer_fix_fee) * 100000) / 100000);
		});
	});
	</script>
</head>

<body>
	<svg aria-hidden="true" style="position: absolute; width: 0; height: 0; overflow: hidden;" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
	<defs>
	<symbol id="icon-bitcoin" viewBox="0 0 32 32">
	<path d="M26.651 16.788c-0.743-0.961-1.865-1.622-3.365-1.981 1.91-0.974 2.75-2.628 2.519-4.962-0.077-0.846-0.311-1.58-0.702-2.201s-0.92-1.122-1.587-1.5c-0.667-0.379-1.392-0.667-2.173-0.866s-1.68-0.343-2.692-0.433v-4.846h-2.961v4.712c-0.513 0-1.295 0.013-2.346 0.038v-4.75h-2.962v4.846c-0.423 0.013-1.045 0.019-1.865 0.019l-4.077-0.019v3.154h2.135c0.974 0 1.545 0.436 1.712 1.308v5.519c0.128 0 0.231 0.007 0.308 0.019h-0.308v7.731c-0.102 0.654-0.474 0.981-1.115 0.981h-2.134l-0.596 3.519h3.846c0.244 0 0.596 0.003 1.058 0.009s0.807 0.010 1.038 0.010v4.904h2.962v-4.846c0.538 0.013 1.32 0.019 2.346 0.019v4.827h2.961v-4.904c1.052-0.051 1.984-0.147 2.798-0.288s1.593-0.362 2.337-0.664c0.743-0.301 1.365-0.676 1.865-1.125s0.913-1.019 1.241-1.712c0.326-0.692 0.535-1.494 0.624-2.404 0.167-1.782-0.121-3.154-0.865-4.115zM13.4 8.211c0.090 0 0.343-0.003 0.76-0.010s0.763-0.013 1.039-0.019c0.276-0.006 0.648 0.010 1.116 0.048s0.862 0.090 1.183 0.154 0.673 0.17 1.058 0.317c0.385 0.147 0.692 0.327 0.923 0.538s0.426 0.481 0.586 0.808c0.16 0.327 0.241 0.702 0.241 1.125 0 0.359-0.058 0.686-0.173 0.981s-0.289 0.542-0.519 0.74c-0.231 0.199-0.468 0.372-0.712 0.519s-0.555 0.266-0.933 0.356c-0.378 0.090-0.708 0.16-0.99 0.211s-0.635 0.087-1.058 0.106c-0.423 0.019-0.744 0.032-0.962 0.039s-0.516 0.006-0.894 0c-0.378-0.006-0.599-0.010-0.664-0.010v-5.904h-0zM21.487 21.307c-0.122 0.295-0.279 0.552-0.471 0.77s-0.446 0.41-0.76 0.577c-0.314 0.167-0.619 0.301-0.913 0.404s-0.651 0.192-1.067 0.269c-0.417 0.077-0.782 0.131-1.097 0.163s-0.689 0.058-1.125 0.077c-0.436 0.019-0.776 0.029-1.019 0.029s-0.551-0.003-0.923-0.010c-0.372-0.006-0.609-0.009-0.712-0.009v-6.5c0.102 0 0.407-0.006 0.913-0.019s0.92-0.019 1.24-0.019c0.32 0 0.763 0.019 1.327 0.058s1.038 0.096 1.423 0.173c0.384 0.077 0.804 0.195 1.26 0.356s0.824 0.356 1.106 0.586c0.282 0.231 0.519 0.526 0.711 0.885s0.289 0.769 0.289 1.231c-0 0.359-0.061 0.686-0.183 0.981z"></path>
	</symbol>
	<symbol id="icon-reddit" viewBox="0 0 32 32">
	<path d="M10.952 17.989v0.002h0.996z"></path>
	<path d="M20.909 17.989v0.002h0.996z"></path>
	<path d="M32 15.004c0-2.196-1.786-3.983-3.983-3.983-0.878 0-1.712 0.287-2.394 0.806-2.222-1.543-5.134-2.463-8.23-2.696l1.671-3.919 4.861 1.135c0.155 1.503 1.414 2.682 2.957 2.682 1.647 0 2.987-1.34 2.987-2.987s-1.34-2.987-2.987-2.987c-1.043 0-1.961 0.54-2.495 1.354l-5.681-1.328c-0.482-0.11-0.954 0.135-1.145 0.579l-2.31 5.416c-3.343 0.116-6.529 1.057-8.935 2.698-0.655-0.486-1.468-0.755-2.334-0.755-2.196 0-3.983 1.786-3.983 3.983 0 1.456 0.795 2.772 2.013 3.447-0.016 0.175-0.022 0.354-0.022 0.536 0 5.49 6.253 9.956 13.939 9.956 7.684 0 13.939-4.466 13.939-9.956 0-0.155-0.006-0.309-0.016-0.462 1.298-0.661 2.147-2.009 2.147-3.521zM26.882 5.048c0.548 0 0.996 0.446 0.996 0.996s-0.448 0.996-0.996 0.996-0.996-0.446-0.996-0.996c0-0.55 0.448-0.996 0.996-0.996zM8.961 17.991c0-1.097 0.894-1.991 1.991-1.991s1.991 0.894 1.991 1.991c0 1.099-0.894 1.991-1.991 1.991s-1.991-0.892-1.991-1.991zM20.528 24.596c-1.374 0.994-2.987 1.491-4.598 1.491s-3.224-0.498-4.598-1.491c-0.446-0.323-0.546-0.946-0.223-1.39s0.946-0.544 1.39-0.223c2.053 1.483 4.809 1.488 6.862 0 0.444-0.321 1.065-0.225 1.39 0.223 0.323 0.446 0.221 1.067-0.223 1.39zM20.909 19.983c-1.099 0-1.991-0.892-1.991-1.991 0-1.097 0.892-1.991 1.991-1.991s1.991 0.894 1.991 1.991c0 1.099-0.892 1.991-1.991 1.991z"></path>
	</symbol>
	<symbol id="icon-telegram" viewBox="0 0 32 32">
	<path d="M0.565 15.429l7.373 2.752 2.854 9.178c0.183 0.588 0.901 0.805 1.379 0.415l4.11-3.351c0.431-0.351 1.044-0.369 1.495-0.042l7.413 5.382c0.51 0.371 1.233 0.091 1.361-0.525l5.43-26.122c0.14-0.674-0.522-1.236-1.164-0.988l-30.261 11.674c-0.747 0.288-0.74 1.345 0.009 1.626zM10.333 16.716l14.41-8.875c0.259-0.159 0.525 0.191 0.303 0.397l-11.893 11.055c-0.418 0.389-0.688 0.91-0.764 1.475l-0.405 3.002c-0.054 0.401-0.617 0.441-0.727 0.053l-1.558-5.475c-0.178-0.624 0.082-1.291 0.634-1.632z"></path>
	</symbol>
	<symbol id="icon-email" viewBox="0 0 32 32">
	<path d="M2.531 6.124l11.526 9.13c0.523 0.414 1.243 0.597 1.942 0.563 0.698 0.034 1.419-0.148 1.941-0.563l11.526-9.13c0.924-0.727 0.715-1.323-0.457-1.323h-26.019c-1.173 0-1.382 0.596-0.461 1.323z"></path>
	<path d="M30.3 8.533l-12.596 9.563c-0.471 0.354-1.088 0.526-1.702 0.518-0.616 0.008-1.233-0.165-1.704-0.518l-12.598-9.563c-0.935-0.709-1.7-0.329-1.7 0.844v15.69c0 1.173 0.96 2.133 2.133 2.133h27.734c1.173 0 2.133-0.96 2.133-2.133v-15.69c0-1.173-0.765-1.553-1.7-0.844z"></path>
	</symbol>
	<symbol id="icon-top" viewBox="0 0 32 32">
	<path d="M16 0c-8.821 0-15.999 7.178-15.999 16s7.177 16 15.999 16c8.822 0 15.999-7.178 15.999-16s-7.177-16-15.999-16zM25.765 21.296c-0.229 0.228-0.528 0.343-0.827 0.343s-0.599-0.115-0.828-0.343l-8.11-8.11-8.109 8.11c-0.457 0.457-1.199 0.457-1.656 0s-0.457-1.198 0-1.655l8.938-8.937c0.458-0.457 1.197-0.457 1.654 0l8.938 8.937c0.458 0.457 0.458 1.198 0.001 1.655z"></path>
	</symbol>
	<symbol id="icon-copy" viewBox="0 0 32 32">
	<path d="M20.594 5.597h-14.876c-1.396 0-2.53 1.134-2.53 2.53v21.344c0 1.396 1.134 2.53 2.53 2.53h14.876c1.396 0 2.53-1.134 2.53-2.53v-21.344c-0.007-1.396-1.14-2.53-2.53-2.53zM21.348 29.464c0 0.419-0.341 0.76-0.76 0.76h-14.876c-0.419 0-0.76-0.341-0.76-0.76v-21.338c0-0.419 0.341-0.76 0.76-0.76h14.876c0.419 0 0.76 0.341 0.76 0.76v21.338z"></path>
	<path d="M26.282 0h-14.876c-1.396 0-2.53 1.134-2.53 2.53 0 0.491 0.393 0.885 0.885 0.885s0.885-0.393 0.885-0.885c0-0.419 0.341-0.76 0.76-0.76h14.876c0.419 0 0.76 0.341 0.76 0.76v21.344c0 0.419-0.341 0.76-0.76 0.76-0.491 0-0.885 0.393-0.885 0.885s0.393 0.885 0.885 0.885c1.396 0 2.53-1.134 2.53-2.53v-21.344c0-1.396-1.134-2.53-2.53-2.53z"></path>
	</symbol>
	<symbol id="icon-key" viewBox="0 0 32 32">
	<path d="M28.984 3.016c-4.021-4.021-10.563-4.021-14.584 0-2.747 2.747-3.701 6.794-2.511 10.466l-11.614 11.614c-0.176 0.176-0.275 0.414-0.275 0.663v5.304c0 0.518 0.419 0.937 0.938 0.937h5.304c0.249 0 0.487-0.099 0.663-0.275l1.326-1.327c0.202-0.202 0.301-0.486 0.268-0.771l-0.165-1.425 1.974-0.186c0.449-0.042 0.803-0.396 0.845-0.845l0.186-1.974 1.425 0.166c0.265 0.036 0.531-0.053 0.732-0.231s0.314-0.433 0.314-0.7v-1.746h1.714c0.249 0 0.487-0.099 0.663-0.275l2.404-2.372c3.671 1.19 7.649 0.308 10.395-2.44 4.021-4.021 4.021-10.563 0-14.584zM26.332 9.645c-1.097 1.097-2.88 1.097-3.977 0s-1.097-2.88 0-3.977 2.88-1.097 3.977 0 1.097 2.88 0 3.977z"></path>
	</symbol>
	</defs>
	</svg>
	<header class="<?php echo $p?>">
		<div class="container">
			<nav class="text-center">
				<a href="<?php echo $dir?>/" class="logo" title="To main page"><?php echo $info['mixer_name']?></a>
				<?php if (!empty($tor_address)) { ?>
					<br>TOR: <a href="http://<?php echo $tor_address?>" target="_blank"><?php echo $tor_address?></a>
				<?php } ?>
				<div class="menu border-top-light nowrap pt-4 d-flex justify-content-between d-sm-block">
					<a class="m-0 mr-sm-2 mr-xl-4" href="<?php echo $dir?>/#how">How Does It Work?</a>
					<a class="m-0 ml-sm-2 mr-sm-2 ml-xl-4 mr-xl-4" href="<?php echo $dir?>/#benefits">Benefits</a>
					<a class="m-0 ml-sm-2 mr-sm-2 ml-xl-4 mr-xl-4" href="<?php echo $dir?>/#faq">FAQ</a>
					<a class="m-0 ml-sm-2 ml-xl-4" href="<?php echo $dir?>/#contacts">Contacts</a>
				</div>
			</nav>
			<?php
			if ($p == 'index') {
				?>
				<div class="text-center">
					<h1>Bitcoin Mixer&nbsp;2.0</h1>
					<p class="big">Get cleanest coins from European, Asian and North American cryptocurrency stock exchanges</p>
					<div class="btn-jam-wrapper">
						<p>
							<a href="<?php echo $dir?>/?mix" class="btn btn-jam btn-jam-xl mb-3">Start Bitcoin Anonymization</a>
						</p>
						<p>
							<a href="<?php echo $dir?>/?mix=free" class="btn btn-jam btn-jam-xl btn-jam-white">Mix Coins For Free*</a>
						</p>
					</div>
					<div style="display: inline-block; padding: 5px 15px; background: rgba(255, 255,255,.8);">* transaction amount for a free trial is 0,001 BTC</div>
				</div>
				<?php
			}
			?>
		</div>
	</header>

	<main>
		<?php
		if ($p == 'index') {
			?>
			<section>
				<div class="container">
					<a name="how"></a>
					<h2 class="h1 text-center">How Does It Work?</h2>
					<div class="row">
						<div class="col-12 text-center">
							<img class="mt-4 mb-4 scheme" src="<?php echo $cdn?>/j-images/scheme.tpl.png">
							<p class="text-20">Bitcoin is pseudo anonymous, all transactions are written in blockchain. Any person can obtain an access to the history of money transfers from one address to another one. Our service provides you with an opportunity to protect your anonymity.</p>
							<p class="text-20">We apply an innovative algorithm, Bitcoin Mixer 2.0, to uplevel anonymity and money mixing in comparison with classic mixers. The main advantage of our service is that all the funds returned to you after a mixing procedure are verified coins from cryptocurrency stock exchanges having an undoubtedly positive history.</p>
							<p class="text-20">An additional point is that you receive all your money in various parts at random time intervals and to the different addresses. It is probably the best anonymization algorithm as of today.</p>
						</div>
					</div>
				</div>
			</section>

			<section class="bg-grey">
				<div class="container">
					<a name="benefits"></a>
					<h2 class="h1 text-center">Benefits of Using Mixer</h2>
					<div class="row">
						<div class="col-12 col-sm-6 mt-4">
							<h3 class="border-bottom-dark pb-3 mb-2">The algorithm: Bitcoin Mixer&nbsp;2.0</h3>
							<p>We took the best of existing Bitcoin mixers, developed a new cryptocurrency anonymization algorithm, added an ability to receive a verified cryptocurrency from North American, European and Asian stock exchanges.</p>
						</div>
						<div class="col-12 col-sm-6 mt-4">
							<h3 class="border-bottom-dark pb-3 mb-2">No registration, no logs</h3>
							<p>We do not store logs, all necessary information for transactions processing is deleted right after work completion and transaction confirmation or beyond the expiration of address lifetime for unexecuted requests.</p>
						</div>
					</div>
					<div class="row">
						<div class="col-12 col-sm-6 mt-4">
							<h3 class="border-bottom-dark pb-3 mb-2">We are available 24x7x365</h3>
							<p>Our algorithms are fully automated and our service operates around the clock. We provide our clients with ongoing support. In case of issues we are seeking to promptly address them.</p>
						</div>
						<div class="col-12 col-sm-6 mt-4">
							<h3 class="border-bottom-dark pb-3 mb-2">Letters of guarantee</h3>
							<p>We provide digitally signed letters of guarantee which evidence a 100% obligation of our service to you. Please retain letters of guarantee. This gives an additional assurance to deal with any disputes that may arise.</p>
						</div>
					</div>
				</div>
			</section>

			<section>
				<div class="container">
					<a name="faq"></a>
					<h2 class="h1 text-center mb-5">FAQs</h2>

					<div class="mb-4">
						<h5>1. What is Bitcoin Mixer&nbsp;2.0?</h5>
						<p>Bitcoin Mixer 2.0 applies an innovative algorithm, which is totally different from the approach of classic mixers. We replace your crypto coins with verified coins from European, Asian and North American Bitcoin stock exchanges. It significantly increases anonymization level and reduces a risk of getting cryptocoins of a doubtful character as in classic mixers.</p>
					</div>

					<div class="mb-4">
						<h5>2. Can I trust you with my money?</h5>
						<p>We offer a protection against accidental errors or deliberate actions to all our clients – all incoming orders are coupled with letters of guarantee signed with PGP keys.</p>
					</div>

					<div class="mb-4">
						<h5>3. How long does it take to cleanse Bitcoins?</h5>
						<p>The anonymization process takes up to 8 hours after receipt of the first confirmation on the incoming customer transaction. To prevent advanced time-based analysis of your blockchain transactions, we set a random time for Bitcoin return. As a result, a mixing time may vary from 1 up to 8 hours in order to increase anonymization level. Moreover, our clients receive cleansed money in various parts.</p>
					</div>

					<div class="mb-4">
						<h5>4. How many confirmations are required to accept a transfer?</h5>
						<p>We consider a transfer accepted upon receiving 1 confirmation.</p>
					</div>

					<div class="mb-4">
						<h5>5. What is a minimum amount of funds for cleansing?</h5>
						<p><?php echo $info['min_amount']?> BTC</p>
					</div>

					<div class="mb-4">
						<h5>6. What is a maximum amount of funds for cleansing?</h5>
						<p><?php echo $info['max_amount']?> BTC per one request. This limitation is forced, since a large volume per one transaction is easier to deanonymize using blockchain volume analysis method. The mixer allows to create several requests for cleansing.</p>
					</div>

					<div class="mb-4">
						<h5>7. What happens if I sent less money than required?</h5>
						<p>Smaller transactions will be regarded as a donation since it makes no business sense to conduct them and a commission of the Bitcoin network may be higher than that money. But if you have sent such sum by mistake, contact our help desk and we will suggest what might be done in your particular case.</p>
					</div>

					<div class="mb-4">
						<h5>8. How much does it cost?</h5>
						<p>A commission fee is dynamic and comes up to <?php echo $info['mixer_fee_pct']?>% + <?php echo $info['mixer_fix_fee']?> BTC which is a good offer for such anonymization level.</p>
					</div>

					<div class="mb-4">
						<h5>9. What kind of logs is stored in the system?</h5>
						<p>We do not store logs, all necessary information for transactions processing is deleted right after work completion and transaction confirmation or beyond the expiration of address lifetime for unexecuted requests. The only proof of our work is our letter of guarantee which you can keep.</p>
					</div>

					<div class="mb-4">
						<h5>10. What is a letter of guarantee?</h5>
						<p>When we provide you with a Bitcoin address, to which you may send your coins to be mixed, we provide a digitally signed confirmation that this address has truly been generated by our server. For your peace of mind we always provide you with such letter and sign it with a PGP signature. You may verify our digital sign using our <a class="underline" href="<?php echo $cdn?>/pgp-key.txt" download>public key</a>. The Letter of Guarantee is the only proof of our obligations. Please always save the Letter of Guarantee before you send your coins to us.</p>
					</div>

					<div class="mb-4">
						<h5>11. How can I check a letter of guarantee?</h5>
						<p>To check a letter of guarantee, install a PGP client (for example, <a class="underline" href="https://www.gpg4win.org/download.html" target="_blank">PGP4Win</a>), import a <a class="underline" href="<?php echo $cdn?>/pgp-key.txt" download>public key</a> from the website to the installed client and verify a letter of guarantee.</p>
					</div>

					<div class="mb-4">
						<h5>12. How long do addresses for coins transfer remain valid?</h5>
						<p>Addresses for coins transfer remain valid within 7 days since their creation. Such period was not chosen by accident. If any transactional issues of the network or errors on the sender’s side arise, such period is enough to address all emerging issues and delays, thus guaranteeing safeguard of assets of our end customers.</p>
					</div>

					<div class="mb-4">
						<h5>13. My browser had closed before I could get a confirmation for my transaction</h5>
						<p>No worries, everything is all right, the system operates in automatic mode, you will definitely receive your money. To monitor confirmations, you can use any Bitcoin block explorer, for example, blockchain.info. Enter there the information on addresses from a letter of guarantee and monitor transactions on your own.</p>
					</div>

				</div>
			</section>

			<section class="bg-grey">
				<div class="container text-center text-sm-left">
					<a name="contacts"></a>
					<h2 class="h1 text-center mb-50">Contacts</h2>
					<p>
						We are always open to communication, you can find our official topics under the following links:
					</p>

					<div class="d-flex flex-wrap align-items-center mt-4 justify-content-center justify-content-sm-start">
						<a href="https://bitcointalk.org/index.php?topic=4667343" target="_blank" class="rounded-with-icon d-flex flex-wrap mb-1">
							<div class="inner-icon d-flex justify-content-center align-items-center">
								<svg class="icon"><use xlink:href="#icon-bitcoin"></use></svg>
							</div>
							<div class="d-flex align-items-center ml-3">BitcoinTalk</div>
						</a>
						<a class="ml-3 d-none d-sm-inline" href="https://bitcointalk.org/index.php?topic=4667343" target="_blank">bitcointalk.org/index.php?topic=4667343</a>
					</div>

					<div class="d-flex flex-wrap align-items-center mt-4 justify-content-center justify-content-sm-start">
						<a href="https://www.reddit.com/user/Jambler_io/" target="_blank" class="rounded-with-icon d-flex flex-wrap mb-1">
							<div class="inner-icon d-flex justify-content-center align-items-center">
								<svg class="icon"><use xlink:href="#icon-reddit"></use></svg>
							</div>
							<div class="d-flex align-items-center ml-3">Reddit</div>
						</a>
						<a class="ml-3 d-none d-sm-inline" href="https://www.reddit.com/user/Jambler_io/" target="_blank">reddit.com/user/Jambler_io</a>
					</div>

					<p class="mt-4">
						We are always ready to answer all your questions. Feel free to contact us anytime at:
					</p>

					<div class="d-flex flex-wrap align-items-center mt-4 justify-content-center justify-content-sm-start">
						<a href="http://t.me/jambler" target="_blank" class="rounded-with-icon d-flex flex-wrap mb-1">
							<div class="inner-icon d-flex justify-content-center align-items-center">
								<svg class="icon"><use xlink:href="#icon-telegram"></use></svg>
							</div>
							<div class="d-flex align-items-center ml-3">Telegram</div>
						</a>
						<a class="ml-3 d-none d-sm-inline" href="http://t.me/jambler" target="_blank">t.me/jambler</a>
					</div>

					<div class="d-flex flex-wrap align-items-center mt-4 justify-content-center justify-content-sm-start">
						<a href="mailto:support@jambler.io" class="rounded-with-icon d-flex flex-wrap mb-1">
							<div class="inner-icon d-flex justify-content-center align-items-center">
								<svg class="icon"><use xlink:href="#icon-email"></use></svg>
							</div>
							<div class="d-flex align-items-center ml-3">E-mail</div>
						</a>
						<a class="ml-3 d-none d-sm-inline" href="mailto:support@jambler.io">support@jambler.io</a>
					</div>

					<div class="d-flex flex-wrap align-items-center mt-4 justify-content-center justify-content-sm-start">
						<a href="https://jambler.io/pgp-key.txt" download class="rounded-with-icon d-flex flex-wrap mb-1">
							<div class="inner-icon d-flex justify-content-center align-items-center">
								<svg class="icon"><use xlink:href="#icon-key"></use></svg>
							</div>
							<div class="d-flex align-items-center ml-3">PGP Open Key</div>
						</a>
						<a class="ml-3 d-none d-sm-inline" href=" https://jambler.io/pgp-key.txt" download>B8A5 CFCA F63F F2D8 384A 6B12 D3B2 8095 6F0E 7CAF</a>
					</div>

				</div>
			</section>
			<?php
		}
		elseif ($p == 'mix') {
			?>
			<section>
				<div class="container">
					<div class="text-center">
						<h1>Bitcoin Mixer&nbsp;2.0</h1>
						<p class="big mt-4 d-none d-sm-block">Get cleanest coins from<br>European, Asian and North American<br>cryptocurrency stock exchanges</p>
						<?php if ($_GET['mix'] == 'free') {?>
							<p> Test the quality of our service for free! Send 0,001 BTC for cleansing and pay no commission.</p>
						<?php } ?>
						<p class="mt-5">Enter your Bitcoin forward to address below:</p>
					</div>
					<div class="row mt-3">
						<div class="m-auto col-12 col-sm-9 col-md-7 col-lg-5 col-xl-4 d-inline-block text-center">
							<div class="color-red text-left">
								<?php echo @$ERR['curl']?>
							</div>
							<form method="POST">
								<input type="hidden" name="code" value="<?php echo newSecurityCode()?>">
								<input type="text" class="form-control" name="forward_addr" value="<?php echo @$_REQUEST['forward_addr']?>" placeholder="Enter first address (mandatory)" required>
								<div class="color-red text-left">
									<?php echo @$ERR['forward_addr']?>
								</div>
								<?php if ($_GET['mix'] != 'free') {?>
									<input type="text" class="form-control mt-2" name="forward_addr2" value="<?php echo @$_REQUEST['forward_addr2']?>" placeholder="Enter second address (optional)">
									<div class="color-red text-left">
										<?php echo @$ERR['forward_addr2']?>
									</div>
								<?php } ?>
								<div class="row mt-4">
									<div class="col-12">
										<button type="submit" name="submit_mix" class="btn btn-jam">Mix My Coins</button>
									</div>
								</div>
							</form>
						</div>
					</div>
					<div class="text-center mt-5">
						<p>Your money will be returned in different parts to addresses specified above</p>
						<p>
							Time of return will be <?php echo $info['withdraw_max_timeout']?> hours
							<span class="ml-2 mr-2">|</span>
							Service fee: <?php echo $info['mixer_fee_pct']?>% + <?php echo $info['mixer_fix_fee']?> BTC
							<span class="ml-2 mr-2">|</span>
							A generated address is valid for <?php echo $info['order_lifetime']?> days (See <a href="<?php echo $dir?>/#faq">FAQ</a>)
						</p>
					</div>
				</div>
			</section>
			<?php
		}
		elseif ($p == 'result') {
			?>
			<section>
				<div class="container">
					<div class="text-center">
						<h1>Bitcoin Mixer&nbsp;2.0</h1>
						<?php if (!empty($guarantee_text)) { ?>
							<form class="mt-4" action="<?php echo $dir?>/?guarantee" method="POST">
								<input type="hidden" name="text" value="<?php echo $guarantee_text?>">
								<button type="submit" class="d-inline a underline btn btn-link text-30">Download Letter of Guarantee</button>
							</form>
						<?php } ?>
						<p class="mt-4">Please send your bitcoins (min <?php echo $info['min_amount']?>, max <?php echo $info['max_amount']?> BTC) to</p>
						<div class="btn-jam pl-5 pr-5" style="position: relative; height: auto">
							<svg class="icon copy-to-clipboard d-none pointer" style="position: absolute; right: 5px; top: 5px;" data-text="<?php echo $_GET['address']?>" title="Copy to clipboard"><use xlink:href="#icon-copy"></use></svg>
							<span class="break-word">
							<?php echo $_GET['address']?>
							</span>
						</div>
						<div class="mt-3 text-center">
							<img src="<?php echo $cdn?>/libs/qrcode.php?text=bitcoin:<?php echo $_GET['address']?>">
							<div id="loader" class="d-none">
								<div id="block-1" class="loader-block"></div>
								<div id="block-2" class="loader-block"></div>
								<div id="block-3" class="loader-block"></div>
								<div id="block-4" class="loader-block"></div>
							</div>

							<p id="blockchain_error" class="d-none color-red"></p>
							<p id="blockchain_result" class="d-none">A transaction is detected, the sum is <b><span></span> BTC</b>, awaiting confirmation</p>

							<script>
								$(document).ready(function() {
									$('#loader').removeClass('d-none');
									var req = function() {
										$.get('<?php echo $cdn?>/libs/blockchain-info.php?address=<?php echo $_GET['address']?>').done(resp => {
											resp = JSON.parse(resp);
											if (resp.err) {
												$('#blockchain_error').text(resp.err);
												$('#blockchain_error').removeClass('d-none');
												$('#loader').addClass('d-none');
											}
											else if (+resp.total_received === 0) {
												setTimeout(req, 20000);
											}
											else {
												$('#loader').addClass('d-none');
												$('#blockchain_result span').text(
													Math.round((resp.total_received / 100000000) * 100000) / 100000
												);
												$('#blockchain_result').removeClass('d-none');
											}
										});
									}
									req();
								});
							</script>
						</div>
						<div class="mt-4 text-center">
							<a href="<?php echo $dir?>/?mix<?php echo (!empty($_GET['mix']) ? '=' : '').$_GET['mix'];?>" class="btn btn-jam btn-jam-white">Mix More Coins</a>
						</div>
					</div>

					<div class="mt-5">
						<a name="calculator"></a>
						<h2 class="text-center">Exact Value & Fee Calculator</h2>

						<form class="mt-4" action="<?php echo $dir.'/'.$query_string?>#calculator" method="POST">
							<div class="form-group row">
								<label class="col-2 col-form-label text-right">You send:</label>
								<div class="col-3">
									<div class="input-group">
										<input type="number" class="form-control text-right" name="you_send" value="<?php echo $you_send?>" step=".001" required>
										<div class="input-group-append">
											<span class="input-group-text">BTC</span>
										</div>
									</div>
								</div>
								<p class="col-7 text-14">There is a field for entering a sum which a user wants to transfer, the same as above the QR code. It is also a link to the blockchain.info service</p>
							</div>

							<div class="form-group row">
								<label class="col-2 col-form-label text-right">You receive:</label>
								<div class="col-3 col-form-label text-right">
									<b id="you_receive"><?php echo $you_receive?></b> BTC
								</div>
								<p class="col-7 text-14">Your commission is not more than <?php echo $info['mixer_fee_pct']?>% and <?php echo $info['mixer_fix_fee']?> BTC – the BTC value is calculated, the commission value is taken from a letter of guarantee</p>
							</div>


							<div id="calculate_btn" class="col-12 text-center">
								<button type="submit" name="submit_calculator" class="btn btn-jam btn-jam-white">Calculate</button>
							</div>

						</form>

					</div>
			</section>
			<?php
		}
		?>
	</main>


<footer>
	<div class="container">
		<div class="row d-flex flex-wrap">
			<div class="order-0 col-12 text-center text-md-left border-bottom-light mb-3">
				<a href="<?php echo $dir?>/" class="logo" title="To main page"><?php echo $info['mixer_name']?></a>
			</div>
			<div class="order-2 order-md-1 col-12 col-md-6 col-xl-4 d-flex flex-column justify-content-between">
				<?php if (!empty($tor_address)) { ?>
					<div class="mt-4 mt-md-0 text-center text-md-left">
						TOR: <a href="http://<?php echo $tor_address?>" target="_blank"><?php echo $tor_address?></a>
					</div>
				<?php } ?>
				<div class="d-none d-md-block">
					Powered by <a href="http://jambler.io" target="_blank">Jambler.io</a>
				</div>
				<div class="icons mt-5 text-center text-md-left">
					<a href="mailto:support@jambler.io" class="mr-3">
						<svg class="icon"><use xlink:href="#icon-email"></use></svg>
					</a>
					<a href="http://t.me/jambler" target="_blank" class="mr-3">
						<svg class="icon"><use xlink:href="#icon-telegram"></use></svg>
					</a>
					<a href="https://bitcointalk.org/index.php?topic=4667343" target="_blank" class="mr-3">
						<svg class="icon"><use xlink:href="#icon-bitcoin"></use></svg>
					</a>
					<a href="https://www.reddit.com/user/Jambler_io/" target="_blank">
						<svg class="icon"><use xlink:href="#icon-reddit"></use></svg>
					</a>
				</div>
			</div>
			<div class="order-1 order-md-2 col-12 col-md-6 col-xl-3 d-flex flex-row flex-md-column justify-content-between menu">
				<a href="<?php echo $dir?>/#how">How Does It Work?</a>
				<a href="<?php echo $dir?>/#benefits">Benefits</a>
				<a href="<?php echo $dir?>/#faq">FAQ</a>
				<a href="<?php echo $dir?>/#contacts">Contacts</a>
			</div>
			<div class="order-3 col-12 col-xl-5 mt-5 mt-xl-0 text-center text-md-left">
				<p class="text-14">Jambler.io PGP fingerprint:</p>
				<p>
					<a class="fingerprint" href=" https://jambler.io/pgp-key.txt" download>B8A5 CFCA F63F F2D8 384A 6B12 D3B2 8095 6F0E 7CAF</a>
				</p>
				<p class="mb-0 text-14">Follow the link to download a public key to verify the letters of guarantee provided by the platform.<br>* For more information on how it works, see <a href="<?php echo $dir?>/#faq"><b>FAQ</b></a>
				</p>
			</div>
		</div>
				<div class="d-block d-md-none mt-5 text-center">
					Powered by <a href="http://jambler.io" target="_blank">Jambler.io</a>
				</div>
	</div>
</footer>
<a name="top"></a>
<a href="#top">
	<svg class="icon"><use xlink:href="#icon-top"></use></svg>
</a>
</body>

</html>
