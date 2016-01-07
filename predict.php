<?php

date_default_timezone_set('UTC');

$historical_prices = file_get_contents('/Users/bblazer/stocks/historical_stocks.txt');
if (!$historical_prices) {$historical_prices = array();} else {$historical_prices = json_decode($historical_prices, true);}

$stock_events      = file_get_contents('/Users/bblazer/stocks/stock_events.txt');
if (!$stock_events) {$stock_events = array();} else {$stock_events = json_decode($stock_events, true);}

// First loop through the prices and find the most profitable points in the past day
foreach ($historical_prices as $symbol => $historical_price) {
	if (!array_key_exists('past_two_days', $historical_price)) {continue;}
	$prices     = array_slice($historical_price['past_two_days'], -390, 390);

	if (!array_key_exists($symbol, $stock_events)) {
		$stock_events[$symbol] = array();
	}

	// Take the current price and see what happens if we were to either sell and buy and ride that wave
	$buys          = array();
	$sells         = array();
	if (!array_key_exists('buys', $stock_events[$symbol])) {
		$stock_events[$symbol]['buys'] = array();
	}

	if (!array_key_exists('sells', $stock_events[$symbol])) {
		$stock_events[$symbol]['sells'] = array();
	}

	foreach ($prices as $price_count => $price) {
		if (!$price) {continue;}

		// Get the min and the max of today
		$post_max   = -99999;
		$post_maxct = $price_count;
		$price_cuts = array_slice($prices, $price_count);
		foreach ($price_cuts as $price_cutct => $price_cut) {if ($price_cut > $post_max) {$post_max = $price_cut;$post_maxct = $price_cutct;}}

		$pre_max    = -99999;
		$pre_min    = 99999;
		$pre_maxct  = $price_count;
		$pre_minct  = $price_count;
		$price_cuts = array_slice($prices, 0, $price_count);
		foreach ($price_cuts as $price_cutct => $price_cut) {if ($price_cut > $pre_max) {$pre_max = $price_cut;$pre_maxct = $price_cutct;}if ($price_cut < $pre_min) {$pre_min = $price_cut;$pre_minct = $price_cutct;}}

		$sell_profit = $pre_max  - $price;
		$buy_profit  = $post_max - $price;

		$buy_profit_percent  = ($buy_profit /$price)*100;
		$sell_profit_percent = ($sell_profit/$price)*100;

		// Trim out anything that's less than a 7% return please thanks
		if ($buy_profit_percent >= 7) {
			$slope_diff  = $pre_max - $price;
			$slope       = $slope_diff/$pre_max;
			$slope       = round($slope, 2);
			$slope       *= 100;

			if ($slope) {
				$found_key = false;
				foreach ($stock_events[$symbol]['buys'] as $key => $current_buy) {
					if ($current_buy['slope'] == $slope) {$found_key = $key;}
				}

				if ($found_key) {
					if ($price != $stock_events[$symbol]['buys'][$found_key]['og_price']) {
						$stock_events[$symbol]['buys'][$found_key]['times']++;
					}
				}
				else {
					$stock_events[$symbol]['buys'][] = array('times' => 1, 'slope' => $slope, 'og_price' => $price, 'roi_percent' => round($buy_profit_percent, 2));
				}
			}
		}

		if ($sell_profit_percent >= 7) {
			$slope_diff  = $price - $pre_min;
			$slope       = $slope_diff/$pre_min;
			$slope       = round($slope, 2);
			$slope       *= 100;

			if (!$slope) {continue;}

			$found_key = false;
			foreach ($stock_events[$symbol]['sells'] as $key => $current_sell) {
				if ($current_sell['slope'] == $slope) {$found_key = $key;}
			}

			if ($found_key) {
				if ($price != $stock_events[$symbol]['sells'][$found_key]['og_price']) {
					$stock_events[$symbol]['sells'][$found_key]['times']++;
				}
			}
			else {
				$stock_events[$symbol]['sells'][] = array('times' => 1, 'slope' => $slope, 'og_price' => $price, 'roi_percent' => round($sell_profit_percent, 2));
			}
		}
	}
}

// Output data to file
file_put_contents('/Users/bblazer/stocks/stock_events.txt', json_encode($stock_events));