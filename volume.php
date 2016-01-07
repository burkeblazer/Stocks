<?php

date_default_timezone_set('UTC');
error_reporting(E_ERROR | E_PARSE);

$historical_prices = file_get_contents('/Users/bblazer/stocks/historical_stocks.txt');

if (!$historical_prices) {$historical_prices = array();} else {$historical_prices = json_decode($historical_prices, true);}

$symbol_ct = count($historical_prices);
$breaks    = $symbol_ct / 100;

$current_ct = 0;
$num_breaks = 0;
foreach ($historical_prices as $symbol => $historical_price) {
	$info         = file_get_contents('http://www.google.com/finance?q=NASDAQ:'.$symbol.'&output=json');

	$length       = strpos($info, '"eps"') - strpos($info, '"lo"');

	$good_data    = substr($info, strpos($info, '"lo"'), $length);
	$good_data    = '{'.$good_data;
	$good_data    = substr($good_data, 0, -2);
	$good_data    .= '}';
	$good_data    = json_decode($good_data, true);

	$volume       = $good_data['vo'];
	$beta         = $good_data['beta'];
	$hi_fifty     = $good_data['hi52'];
	$lo_fifty     = $good_data['lo52'];

	if (strpos($volume, 'M')) {
		$volume   = str_replace('M', '', $volume);
		$volume   *= 1000000;
	}

	$volume = str_replace(",", "", $volume);
	$volume *= 1;

	$call_put_ratio = getWeeklyPutCalls($symbol);

	$historical_prices[$symbol]['cp_ratio'] = $call_put_ratio;
	$historical_prices[$symbol]['volume']   = $volume;
	$historical_prices[$symbol]['beta']     = $beta;
	$historical_prices[$symbol]['hi_fifty'] = $hi_fifty;
	$historical_prices[$symbol]['lo_fifty'] = $lo_fifty;

	$current_ct++;

	if ($current_ct % $breaks === 0) {
		$num_breaks++;
		if ($num_breaks % 5 === 0) {
			print $num_breaks;
		}
		else {
			print '.';
		}
	}
}

function getWeeklyPutCalls($symbol) {
	$put_calls    = file_get_contents('http://www.google.com/finance/option_chain?q='.$symbol.'&output=json');
	$put_calls    = preg_replace("/([{,])([a-zA-Z][^: ]+):/", "$1\"$2\":", $put_calls);
	$put_calls    = str_replace('y:', '"y":', $put_calls);
	$put_calls    = str_replace('m:', '"m":', $put_calls);
	$put_calls    = str_replace('d:', '"d":', $put_calls);
	$put_calls    = str_replace('s:', '"s":', $put_calls);
	$put_calls    = str_replace('e:', '"e":', $put_calls);
	$put_calls    = str_replace('p:', '"p":', $put_calls);
	$put_calls    = str_replace('c:', '"c":', $put_calls);
	$put_calls    = str_replace('b:', '"b":', $put_calls);
	$put_calls    = str_replace('a:', '"a":', $put_calls);
	$put_calls    = json_decode($put_calls, true);

	if (!$put_calls || !array_key_exists('expirations', $put_calls)) {return 0;}

	$expirations  = $put_calls['expirations'];

	$today        = strtotime('now');
	$twowks       = strtotime('+2 weeks');
	$good_all_exp = array();

	foreach ($expirations as $expiration) {
		$exp_date = strtotime($expiration['y'].'/'.$expiration['m'].'/'.$expiration['d']);
		if ($exp_date <= $twowks && $exp_date >= $today) {
			$good_all_exp[] = $expiration;
		}
	}

	$puts  = array();
	$calls = array();
	foreach ($good_all_exp as $good_exp) {
		$put_calls    = '';
		$put_calls    = file_get_contents('http://www.google.com/finance/option_chain?q='.$symbol.'&output=json&expd='.$good_exp['d'].'&expm='.$good_exp['m'].'&expy='.$good_exp['y']);
		$put_calls    = preg_replace("/([{,])([a-zA-Z][^: ]+):/", "$1\"$2\":", $put_calls);
		$put_calls    = str_replace('y:', '"y":', $put_calls);
		$put_calls    = str_replace('m:', '"m":', $put_calls);
		$put_calls    = str_replace('d:', '"d":', $put_calls);
		$put_calls    = str_replace('s:', '"s":', $put_calls);
		$put_calls    = str_replace('e:', '"e":', $put_calls);
		$put_calls    = str_replace('p:', '"p":', $put_calls);
		$put_calls    = str_replace('c:', '"c":', $put_calls);
		$put_calls    = str_replace('b:', '"b":', $put_calls);
		$put_calls    = str_replace('a:', '"a":', $put_calls);
		$put_calls    = json_decode($put_calls, true);

		$puts         = array_merge($puts, $put_calls['puts']);
		$calls        = array_merge($puts, $put_calls['calls']);
	}

	$putct  = count($puts);
	$callct = count($calls);

	if ($callct == 0 || $putct == 0) {return 0;}

	$ratio  = round($putct/$callct, 2);

	return $ratio;
}

// Output data to file
file_put_contents('/Users/bblazer/stocks/historical_stocks.txt', json_encode($historical_prices));