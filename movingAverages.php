<?php
require "config.php";
require_once ('jpgraph/jpgraph.php');
require_once ('jpgraph/jpgraph_line.php');
require_once ('jpgraph/jpgraph_bar.php');

// Connection
$db = new mysqli($host, $username, $password, "stockmarket");
if ($db->connect_error) {
	die("Connection failed: " . $db->connect_error);
}

ini_set('memory_limit','4000M');
ini_set('max_execution_time', 6000);
$starttime = microtime(true);

echo "1. Calculate Averages, time: ".(microtime(true) - $starttime)." <br><br>";
echo "2. Running db query, time: ".(microtime(true) - $starttime)." <br><br>";
flush();
ob_flush();
$q_1 = "SELECT INSTRUMENT_ID, TRADE_DATE, OPEN_PRICE FROM STOCK_HISTORY order by TRADE_DATE";
$r_1 = $db->query($q_1);

$stockArray = array();
echo "3. Processing query results into array, time: ".(microtime(true) - $starttime)." <br><br>";
flush();
ob_flush();

// Using a 2D array we store INSTRUMENT_ID as key, and the corresponding OPEN_PRICE as values in an array ordered by TRADE_DATE
if ($r_1->num_rows > 0) {
	while($row = $r_1->fetch_assoc()) {
		if(array_key_exists($row["INSTRUMENT_ID"], $stockArray)){
			array_push($stockArray[$row["INSTRUMENT_ID"]], $row["OPEN_PRICE"]);
		}else{
			$stockArray[$row["INSTRUMENT_ID"]] = array($row["OPEN_PRICE"]);
		}
	}
} else {
	echo "0 results";
}

$cashStart = 10000.0;
$cash = $cashStart;

echo "4. Starting budget: $".$cash." time: ".(microtime(true) - $starttime)." <br><br>";
echo "5. Calculating 50 and 200 day averages, time: ".(microtime(true) - $starttime)." <br><br>";
flush();
ob_flush();
$avgArray = array();

// Used to graph for instrument id = 0
$day50Array = array();
$day200Array = array();

for($i = 0; $i < count($stockArray); $i++){
	$priceArray = $stockArray[$i];
	$avgInstArray = array();

	// Check for opportunities to buy if the shorter day moving average is above the longer day moving average
	for($day = 200; $day < count($priceArray); $day++){
		$day50 = array_sum(array_slice ( $priceArray, $day-50, 50 ))/50;
		$day200 = array_sum(array_slice ( $priceArray, $day-200, 200 ))/200;

                if ($i == 0) {
                    array_push($day50Array, $day50);
                    array_push($day200Array, $day200);
                }

		// Store the boolean value and the associated price for the day
		$avgInstArray[$day] = array(($day50 > $day200 ? 1:0), $priceArray[$day]);
	}
	$avgArray[$i] = $avgInstArray;
}

// Show moving crossing averages for instrument id = 0
$graph = new Graph(600, 200);
$graph->SetScale('intint');
$graph->title->Set('Moving averages for INSTRUMENT_ID = 0');
$graph->xaxis->title->Set('(Day)');
$graph->yaxis->title->Set('(Average open price in $)');
 
$lineploti=new LinePlot($day50Array);
$lineplot1->SetFillColor('orange@0.5');
$lineploti=new LinePlot($day200Array);
$lineplot1->SetFillColor('blue@0.5');

$graph->Add($lineplot1);
$graph->Add($lineplot2);
$graph->Stroke();

echo "6. Adding stocks to buy/sell list for each day, time: ".(microtime(true) - $starttime)." <br><br>";
flush();
ob_flush();

$buy = array();
$sell = array();
for($day = 200; $day < count($avgArray[0]); $day++){
	$buyDay = array();
	$sellDay = array();

	foreach($avgArray as $i => $arr){
		// Buy if shorter is above the longer day moving average
		if($avgArray[$i][$day][0] == 1){
			$buyDay[$i]=$avgArray[$i][$day][1];
		}else{
			$sellDay[$i]=$avgArray[$i][$day][1];
		}
	}

	$buy[$day] =  $buyDay;
	$sell[$day] =  $sellDay;
}

$holdings = array();
echo "7. Running algorithm:<br> for each day sell all stocks that match criteria and then buy randomly from the pool of stocks that meet buy criteria, time: ".(microtime(true) - $starttime)." <br><br>";
flush();
ob_flush();

for($day = 200; $day < count($avgArray[0]); $day++){
	// Sell our holdings if we own
	foreach($sell[$day] as $i => $price){
		if(array_key_exists($i, $holdings)){
			$cash += $price * $holdings[$i];
			unset($holdings[$i]);
		}
	}

	// Randomly buy a stock
	while($cash > 250 and count($buy[$day]) > 0 ){
		$i = array_rand($buy[$day]);
		$cash -= $buy[$day][$i];
		if(array_key_exists($i, $holdings)){
			$holdings[$i] += 1;
		}else{
			$holdings[$i] = 1;
		}
	}
}

echo "8. Calculating profits, time: ".(microtime(true) - $starttime)." <br><br>";
flush();
ob_flush();
foreach($holdings as $i => $qty){
	$cash += end($stockArray[$i]) * $qty;
}

$profit = $cash - $cashStart;
echo "9. Profit: $" . $profit . " time: ".(microtime(true) - $starttime)." <br><br>";
?>
