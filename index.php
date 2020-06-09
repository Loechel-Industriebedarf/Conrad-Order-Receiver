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
			  ->setPaginate(false)
			  ->setStartDate($last_execution);
		  $result = $api->getOrders($request);
		  
		  //Download result
		  downloadAPIResult($result, $api_url, $api_key);

	} catch (\Exception $e) {
		// An exception is thrown if the requested object is not found or if an error occurs
		echo "Exception:<br><br>";
		var_dump($e);
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
			'ID_OFFER',
			'ID_ORDER_UNIT',
			'Bestellzeitpunkt',
			'Updatezeitpunkt',
			'Abholzeitpunkt',
			'Anzahl bestellt'
		));
		
		//Cycle throught items
		foreach($orderItems as $o){
			$orderData = $o->getData();
			$orderAdditonalFields = $orderData["order_additional_fields"];
			$orderCustomer = $orderData["customer"]->getData()["billing_address"]->getData();
			$orderShipping = $orderData["customer"]->getData()["shipping_address"]->getData();
			$orderLines = $orderData["order_lines"];
			$orderState = $orderLines->getItems()[0]->getData()["status"]->getData()["state"];
			
			$orderId = $orderData["id"];
			
			/*
			echo "<pre>";
				var_dump( $orderData );
			echo "</pre>";	
			*/			
			
			//Accept order if it wasn't yet
			//TODO: Accept multiple orders
			if($orderState == "WAITING_ACCEPTANCE"){				
				 $api = new ShopApiClient($api_url, $api_key, null);
				 $request = new AcceptOrderRequest($orderId, [
				     new AcceptOrderLine(['id' => $orderLines->getItems()[0]->getData()["id"], 'accepted' => true])
				 ]);
				 $api->acceptOrder($request);
			}
			//Only write order, if it was accepted
			else if($orderState == "SHIPPING"){
				//Cycle through orderlines
				foreach($orderLines->getItems() as $ol){
					$orderOffer = $ol->getData()["offer"];
					$orderHistory = $ol->getData()["history"];
					
					//Add items of orders to array
					array_push($order, array(
						$orderAdditonalFields->getItems()[0]->getData()["value"],
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
						$ol->getData()["shipping_price"],
						$ol->getData()["commission"]->getData()["fee"],
						$orderData["has_customer_message"],
						'',
						'',
						$orderHistory->getData()["created_date"]->format('Y-m-d\TH:i:s'),
						$orderHistory->getData()["last_updated_date"]->format('Y-m-d\TH:i:s'),
						gmdate("Y-m-d\TH:i:s", time()+3600),
						$ol->getData()["quantity"]						
					));
				}

				echo "New orders got parsed.";

				//Write order to csv
				writeToCsv($order);
			
				//Write current date to txt
				writeLast();
			}
			
		}	
				
	}
}

/*
* Write the last execution date to txt.
*/
function writeLast(){
	$time = date("Y-m-d\TH:i:s", time());
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