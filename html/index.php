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

///////////////////////////////////////////////////

$apiUrl = 'https://api.jambler.io';
$clearCDN = '';
$darkCDN = '';
$coin_ids = array(
	'btc'
);
$version = 5;


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
				$p = 'result';
			}
		}
	}
	if (isset($_REQUEST['submit_calculator'])) {
		$p = 'result';
		$res = array(
			'guarantee' => $_REQUEST['guarantee'],
			'address' => $_REQUEST['address']
		);
	}

	if ($p == 'result') {
		$you_send = !empty($_REQUEST['you_send']) ? $_REQUEST['you_send'] : 1;
		$you_receive = $you_send - $you_send * $info['mixer_fee_pct'] / 100 - $info['mixer_fix_fee'];
	}

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
	<link rel="stylesheet" href="<?php echo $cdn?>/j-icons/fa.css?v=<?php echo $version?>">
	<link rel="stylesheet" href="<?php echo $cdn?>/j-icons/style.css?v=<?php echo $version?>">
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

	<header class="<?php echo $p?>">
		<div class="container">
			<nav class="text-center">
				<a href="<?php echo $dir?>/" class="logo" title="To main page"><?php echo $info['mixer_name']?></a>
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
					<a href="<?php echo $dir?>/?mix" class="btn btn-jam btn-jam-xl">Start Bitcoin Anonymization</a>
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
								<i class="fa fa-bitcoin"></i>
							</div>
							<div class="d-flex align-items-center ml-3">BitcoinTalk</div>
						</a>
						<a class="ml-3 d-none d-sm-inline" href="https://bitcointalk.org/index.php?topic=4667343" target="_blank">bitcointalk.org/index.php?topic=4667343</a>
					</div>

					<div class="d-flex flex-wrap align-items-center mt-4 justify-content-center justify-content-sm-start">
						<a href="https://www.reddit.com/user/Jambler_io/" target="_blank" class="rounded-with-icon d-flex flex-wrap mb-1">
							<div class="inner-icon d-flex justify-content-center align-items-center">
								<i class="fa fa-reddit"></i>
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
								<i class="fa fa-telegram"></i>
							</div>
							<div class="d-flex align-items-center ml-3">Telegram</div>
						</a>
						<a class="ml-3 d-none d-sm-inline" href="http://t.me/jambler" target="_blank">t.me/jambler</a>
					</div>

					<div class="d-flex flex-wrap align-items-center mt-4 justify-content-center justify-content-sm-start">
						<a href="mailto:support@jambler.io" class="rounded-with-icon d-flex flex-wrap mb-1">
							<div class="inner-icon d-flex justify-content-center align-items-center">
								<i class="fa fa-email"></i>
							</div>
							<div class="d-flex align-items-center ml-3">E-mail</div>
						</a>
						<a class="ml-3 d-none d-sm-inline" href="mailto:support@jambler.io">support@jambler.io</a>
					</div>

					<div class="d-flex flex-wrap align-items-center mt-4 justify-content-center justify-content-sm-start">
						<a href="pgp-key.txt" download class="rounded-with-icon d-flex flex-wrap mb-1">
							<div class="inner-icon d-flex justify-content-center align-items-center">
								<i class="fa fa-key"></i>
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
								<input type="text" class="form-control mt-2" name="forward_addr2" value="<?php echo @$_REQUEST['forward_addr2']?>" placeholder="Enter second address (optional)">
								<div class="color-red text-left">
									<?php echo @$ERR['forward_addr2']?>
								</div>
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
						<form class="mt-4" action="<?php echo $dir?>/?guarantee" method="POST">
							<input type="hidden" name="text" value="<?php echo $res['guarantee']?>">
							<button type="submit" class="d-inline a underline btn btn-link text-30">Download Letter of Guarantee</button>
						</form>
						<p class="mt-4">Please send your bitcoins (min <?php echo $info['min_amount']?>, max <?php echo $info['max_amount']?> BTC) to</p>
						<div class="btn-jam pl-5 pr-5" style="position: relative; height: auto">
							<i class="fa fa-copy d-none pointer" style="position: absolute; right: 5px; top: 5px;" data-text="<?php echo $res['address']?>" title="Copy to clipboard"></i>
							<span class="break-word">
							<?php echo $res['address']?>
							</span>
						</div>
						<div class="mt-3 text-center">
							<img src="<?php echo $cdn?>/libs/qrcode.php?text=bitcoin:<?php echo $res['address']?>">
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
										$.get('<?php echo $cdn?>/libs/blockchain-info.php?address=<?php echo $res['address']?>').done(resp => {
											resp = JSON.parse(resp);
											if (resp.err) {
												$('#blockchain_error').text(resp.err);
												$('#blockchain_error').removeClass('d-none');
												$('#loader').hide();
											}
											else if (+resp.total_received === 0) {
												setTimeout(req, 20000);
											}
											else {
												$('#loader').hide();
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
							<a href="<?php echo $dir?>/?mix" class="btn btn-jam btn-jam-white">Mix More Coins</a>
						</div>
					</div>

					<div class="mt-5">
						<a name="calculator"></a>
						<h2 class="text-center">Exact Value & Fee Calculator</h2>

						<form class="mt-4" action="<?php echo $dir?>/#calculator" method="POST">
							<input type="hidden" name="guarantee" value="<?php echo $res['guarantee']?>">
							<input type="hidden" name="address" value="<?php echo $res['address']?>">

							<div class="form-group row">
								<label class="col-2 col-form-label text-right">You send:</label>
								<div class="col-3">
									<div class="input-group">
										<input type="number" class="form-control text-right" name="you_send" value="<?php echo $you_send?>" min="<?php echo $info['min_amount']?>" max="<?php echo $info['max_amount']?>" step=".001" required>
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
				<div class="d-none d-md-block">
					Powered by <a href="http://jambler.io" target="_blank">Jambler.io</a>
				</div>
				<div class="icons mt-5 text-center text-md-left">
					<a href="mailto:support@jambler.io" class="mr-3">
						<i class="fa fa-email"></i>
					</a>
					<a href="http://t.me/jambler" target="_blank" class="mr-3">
						<i class="fa fa-telegram"></i>
					</a>
					<a href="https://bitcointalk.org/index.php?topic=4667343" target="_blank" class="mr-3">
						<i class="fa fa-bitcoin"></i>
					</a>
					<a href="https://www.reddit.com/user/Jambler_io/" target="_blank">
						<i class="fa fa-reddit"></i>
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
	<i class="fa fa-top"></i>
</a>
</body>

</html>
