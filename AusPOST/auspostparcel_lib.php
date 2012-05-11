<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class AusPostParcel_lib{

	public $name = 'Australia Post Parcel';
	public $library = 'auspostparcel_lib';
	public $postage = 0;
	public $cost;
	public $timeframe;
	
	public $description;
	private $service = 'STANDARD';	

	public function __construct(){			
	}	
	
	function calculate($address, $weight, $source = array('postcode'=>'3000')){
		$postage = $this->ausPost($source['postcode'], $address['postcode'], $this->getCountryCode($address['country']), $this->service, $weight);

		if($postage['msg'] !='OK'){
			return false;
		}else{
			$this->cost = $postage['charge'];
			$this->timeframe = $postage['days'];
			return true;
		}

	}
	
	function ausPost($frompcode, $topcode, $country, $service, $sweight, $sheight = '50', $swidth = '50', $slength = '50', $sboxes = '1') {
		// below is archaic code from WebAdmin CMS. AusPost calculator API hasn't changed since the year 2000...
		if($sweight <=0){
			$sweight = 1000;
		}else if($sweight > 20000){  // auspost dies with weight above 20kgs
			$sweight = 20000;
		}
		
		$url = "http://drc.edeliver.com.au/ratecalc.asp?Pickup_Postcode={$frompcode}&Destination_Postcode={$topcode}&Country=". strtoupper($country) ."&Weight={$sweight}&Service_Type={$service}&Height={$sheight}&Width={$swidth}&Length={$slength}&Quantity={$sboxes}";

		$myfile = file($url);
		
		foreach($myfile as $vals) {
			$bits =  explode("=", $vals);			
			$$bits[0] = $bits[1]; // Not a typo! Think of it being a variable named $bits[0] = $bits[1];
		}
                return array("charge"=>$charge, "days"=>$days, "msg"=>trim($err_msg));
	}		
	
	function getCountryCode($country){
		$country = new Country($country);
		return $country->country_code;
	}
	
	
	
}	