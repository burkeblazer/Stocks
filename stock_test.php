<?php

date_default_timezone_set('America/New_York');
if (date('Hi')*1 < 930) {return;}
date_default_timezone_set('UTC');

// Get the most common symbols
$symbols_text = file_get_contents('ftp://ftp.nasdaqtrader.com/SymbolDirectory/nasdaqlisted.txt');
$symbols_text = explode(PHP_EOL, $symbols_text);
$symbols      = array();
array_shift($symbols_text);
array_pop($symbols_text);
array_pop($symbols_text);

foreach ($symbols_text as $symbol) {
	$save_symbol = substr($symbol, 0, strpos($symbol, '|'));
	if (preg_match('/[^A-Za-z0-9]/', $save_symbol)) {continue;}
	$symbols[] = $save_symbol;
}

$historical_prices = file_get_contents('/Users/bblazer/stocks/historical_stocks.txt');

if (!$historical_prices) {$historical_prices = array();} else {$historical_prices = json_decode($historical_prices, true);}

$stock_events = file_get_contents('/Users/bblazer/stocks/stock_events.txt');

if (!$stock_events) {$stock_events = array();} else {$stock_events = json_decode($stock_events, true);}

$symbols        = array_unique(array_filter($symbols));
$max_symbols    = 99;
$base_url       = "http://finance.google.com/finance/info?client=ig&q=";
$current_length = strlen($base_url);
$results        = array();
$done           = false;
$count          = 0;
while(!$done && $count < 50) {
	$temp_symbols = array_filter($symbols);
	$ct = 0;
	$current_url = $base_url;
	$too_long = false;
	$cur_ct = 0;
	foreach ($temp_symbols as $symbol) {
		$symbol = "NASDAQ:$symbol";
		$potential_url = $current_url.$symbol;
		if ($cur_ct >= $max_symbols) {
			$current_url = substr($current_url, 0, -1);
			$too_long = true;
			break;
		}
		else {
			$current_url .= $symbol.',';
			unset($symbols[$ct]);
			$ct++;
		}
		$cur_ct++;
	}
	$symbols = array_filter($symbols);
	$symbols = array_values($symbols);

	if ($too_long) {
		$url = $current_url;
	}
	else {
		$done = true;
		$url = substr($current_url, 0, -1);
	}

	$google_results = file_get_contents($url);
	$google_results = json_decode(substr($google_results, 4), true);
	foreach ($google_results as $google_result) {
		$symbol       = $google_result['t'];
		$price        = $google_result['l_cur']*1;
		$last_updated = strtotime($google_result['lt_dts']);

		if (!$historical_prices[$symbol]) {
			$historical_prices[$symbol] = array();
		}

		if (!$historical_prices[$symbol]['past_two_days']) {$historical_prices[$symbol]['past_two_days'] = array();}

		$historical_prices[$symbol]['current_price']   = $price;
		$historical_prices[$symbol]['past_two_days'][] = $price;
		$historical_prices[$symbol]['last_updated']    = $last_updated;

		// Trim to 780
		$historical_prices[$symbol]['past_two_days']   = array_slice($historical_prices[$symbol]['past_two_days'], -780, 780);

		$max = -999999;
		$min = 999999;
		foreach ($historical_prices[$symbol]['past_two_days'] as $past_price) {
			if ($past_price*1 < $min) {$min = $past_price;}
			if ($past_price*1 > $max) {$max = $past_price;}
		}

		date_default_timezone_set('America/New_York');
		$num_prices    = abs((strtotime('now') - strtotime('today 9:30am'))/60);
		date_default_timezone_set('UTC');
		$todays_prices = array_slice($historical_prices[$symbol]['past_two_days'], ($num_prices*-1), $num_prices);

		$historical_prices[$symbol]['events'] = getEvent($stock_events, $symbol, $todays_prices, $price);

		$historical_prices[$symbol]['min'] = $min;
		$historical_prices[$symbol]['max'] = $max;
	}

	$count++;
}

function getEvent($stock_events, $symbol, $prices, $current_price) {
	$current_stock_events = $stock_events[$symbol];
	$buys                 = $current_stock_events['buys'];
	$sells                = $current_stock_events['sells'];

	$pre_max = -99999;
	$pre_min = 99999;
	foreach ($prices as $price_cut) {if ($price_cut > $pre_max) {$pre_max = $price_cut;}if ($price_cut < $pre_min) {$pre_min = $price_cut;}}

	if (!$pre_max || !$pre_min) {return;}

	$current_buy_slope_diff  = $pre_max - $current_price;
	$current_buy_slope       = $current_buy_slope_diff/$pre_max;
	$current_buy_slope       = round($current_buy_slope, 2);
	$current_buy_slope       *= 100;

	$current_sell_slope_diff  = $current_price - $pre_min;
	$current_sell_slope       = $current_sell_slope_diff/$pre_min;
	$current_sell_slope       = round($current_sell_slope, 2);
	$current_sell_slope       *= 100;

	// Buys
	$possible_buys = array();
	foreach ($buys as $buy) {
		$buy_slope = $buy['slope'];

		if ($buy_slope == $current_buy_slope) {
			$possible_buys[round($buy['roi_percent'])] = $buy;
		}
	}

	// Sells
	$possible_sells = array();
	foreach ($sells as $sell) {
		$sell_slope = $sell['slope'];

		if ($sell_slope == $current_sell_slope) {
			$possible_sells[round($sell['roi_percent'])] = $sell;
		}
	}

	krsort($possible_buys,  SORT_NUMERIC);
	krsort($possible_sells, SORT_NUMERIC);

	$possible_buys   = array_values($possible_buys);
	$possible_sells  = array_values($possible_sells);

	return array('sell_event' => $possible_sells[0], 'buy_event' => $possible_buys[0]);
}

// Output data to file
file_put_contents('/Users/bblazer/stocks/historical_stocks.txt', json_encode($historical_prices));