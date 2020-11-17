<?php
//TODO: Better error/success messages

require 'vendor/autoload.php';
require_once 'settings.php';

use Mirakl\MMP\Shop\Client\ShopApiClient;
use Mirakl\MMP\Shop\Request\Order\Get\GetOrdersRequest;
use Mirakl\MMP\Shop\Request\Order\Accept\AcceptOrderRequest;
use Mirakl\MMP\Common\Domain\Order\Accept\AcceptOrderLine;

if(!file_exists($csvPath)){
	try {
		 //New API request
		 $api = new ShopApiClient($api_url, $api_key, null);
		 
		 $request = new GetOrdersRequest();
		 // Set parameters
		 $request->setMax(25)
			  ->setStartUpdateDate($last_execution)
			  ->setPaginate(false);
		  $result = $api->getOrders($request);
		  
		  //Download result
		  downloadAPIResult($result, $api_url, $api_key);

	} catch (\Exception $e) {
		// An exception is thrown if the requested object is not found or if an error occurs
		echo "Exception:<br><br> ";
		//var_dump($e);
	}
}
else{
	echo "CSV file was not processed yet!";
}


/*
* If there are new orders, download them as csv file.
*/
function downloadAPIResult($result, $api_url, $api_key){	
	$ordercount = $result->getTotalCount();
		
	//Check, if there are new orders
	if($ordercount == 0){
		echo "No new orders.";
	}
	else{
		$order = array();
		$orderItems = $result->getItems();
		
		//Generate Headline
		$order = generateHeadline($order);
		
		//Cycle throught items
		foreach($orderItems as $o){
			$orderData = $o->getData();
			$orderLines = $orderData["order_lines"];
			$orderState = $orderLines->getItems()[0]->getData()["status"]->getData()["state"];
			
			$orderId = $orderData["id"];

			/*echo "<pre>";
			echo var_dump( $o );
			echo "</pre>";
			echo "<br><br><br>";*/
			
			//Accept order if it wasn't yet
			if($orderState == "WAITING_ACCEPTANCE"){	
				$acceptOrderArray = array();
			
				foreach($orderLines->getItems() as $ol){
					array_push($acceptOrderArray, new AcceptOrderLine(['id' => $ol->getData()["id"], 'accepted' => true]));
				}	
				
				//echo "<pre>".var_dump($acceptOrderArray)."</pre>";
				
				$api = new ShopApiClient($api_url, $api_key, null);
				$request = new AcceptOrderRequest($orderId, $acceptOrderArray);
				$api->acceptOrder($request);
				
				echo $orderId." was accepted successfully.";
			}	
			//Only write order, if it was accepted
			else if($orderState == "SHIPPING"){
				$diff_minutes = (time()+3600 - $orderData["acceptance_decision_date"]->getTimestamp()) / 60;
				//echo $diff_minutes."<br><br>";
				
				//Only import orders younger than 60 minutes
				if($diff_minutes < 60){
					//Get shipping price
					//We need the shipping price in every line of the csv, or our erp system throws an error
					$shippingPrice = 0;
					foreach($orderLines->getItems() as $ol){
						$sp = $ol->getData()["shipping_price"];
						if($sp > 0){ $shippingPrice = $sp; }
					}
					
					//Cycle through orderlines
					foreach($orderLines->getItems() as $ol){	
						/* echo "<pre>";
						echo var_dump( $ol );
						echo "</pre>"; */
						
						$orderAdditonalFields = $orderData["order_additional_fields"];
						$orderCustomer = $orderData["customer"]->getData()["billing_address"]->getData();
						$orderShipping = $orderData["customer"]->getData()["shipping_address"]->getData();		
						
						$orderOffer = $ol->getData()["offer"];
						$orderHistory = $ol->getData()["history"];
						
						//Add items of orders to array
						array_push($order, array(
							$orderAdditonalFields->getItems()[count($orderAdditonalFields->getItems())-1]->getData()["value"],
							$orderId,
							$orderCustomer["company"],
							$orderCustomer["firstname"] . " " . $orderCustomer["lastname"],
							$orderCustomer["street_1"] . " " . $orderCustomer["street_2"],
							$orderCustomer["zip_code"],
							$orderCustomer["city"],
							$orderCustomer["country"],
							'',
							$orderCustomer["company"],
							$orderShipping["firstname"] . " " . $orderShipping["lastname"],
							$orderShipping["street_1"] . " " . $orderShipping["street_2"],
							$orderShipping["zip_code"],
							$orderShipping["city"],
							$orderShipping["country"],
							'',
							$ol->getData()["offer"]->getData()["sku"],
							$orderOffer->getData()["price"],
							$shippingPrice,
							$ol->getData()["commission"]->getData()["fee"] / $ol->getData()["quantity"],
							$orderData["has_customer_message"],
							$orderData["paymentType"],
							$ol->getData()["quantity"],
							$orderHistory->getData()["created_date"]->format('Y-m-d\TH:i:s'),
							$orderHistory->getData()["last_updated_date"]->format('Y-m-d\TH:i:s'),
							gmdate("Y-m-d\TH:i:s", time()+3600),
							$ol->getData()["offer"]->getData()["product"]->getData()["title"]						
						));
					}

					echo $orderId." got parsed. ";

					//Write order to csv
					writeToCsv($order);
				
					//Write current date to txt
					writeLast();
				}
				
			}
			
		}	
	
	}
}

/*
* Generates the headline for the csv file
*/
function generateHeadline($order){
	array_push($order, array(
		'Mail',
		'Bestellungs-ID',
		'Rechnungsfirma 1',
		'Rechnungsfirma 2',
		'Rechnungsstrasse',
		'RechnungsPLZ',
		'Rechnungsort',
		'Rechnungsland',
		'Rechnungstelefon',
		'Versandfirma 1',
		'Versandfirma 2',
		'Versandstrasse',
		'VersandPLZ',
		'Versandort',
		'Versandland',
		'Versandtelefon',
		'Artikelnummer',
		'Preis',
		'Versandkosten',
		'Nebenkosten',
		'Notiz',
		'Bezahlart',
		'Anzahl bestellt',
		'Bestellzeitpunkt',
		'Updatezeitpunkt',
		'Abholzeitpunkt'
	));
	return $order;
}

/*
* Write the last execution date to txt.
*/
function writeLast(){
	$time = date("Y-m-d\TH:i:s", strtotime('-1 minute'));
	$fp = fopen('last.txt', 'w+');
	fwrite($fp, $time);
	fclose($fp);
}

/*
* Write the order array to csv
*/
function writeToCsv($order){
	$fp = fopen('../conradOrder.csv', 'w');
	for ($i = 0; $i < count($order); $i++) {
		fputcsv($fp, $order[$i], ';');
	}
	fclose($fp);
} 