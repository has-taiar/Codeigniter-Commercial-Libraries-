<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

 /*
 * This library to process the payment with Paypal Express Checkout. 
 *
 * This library makes use of the PHP Functions provided by Paypal Express page.
 *
 *
 * @author  Hasn AlTaiar
 */

class Paypalexpress_lib {
	
    public  $systemName  = 'PayPal Express Checkout';
    public  $displayName = 'PayPal Express Checkout';
    public  $library     = 'paypalexpress_lib';
    public  $processType= 'remote'; 
    public  $processUrl = '';
    public  $CI;

    public  $currencyCodeType = "";
    public  $paymentType = "";
    public  $returnURL = "";
    public  $cancelURL = "";
    public  $PROXY_HOST = '';
    public  $PROXY_PORT = '';
    public  $SandboxFlag = true;
    public  $API_UserName='';
    public  $API_Password='';
    public  $API_Signature='';
    public  $sBNCode = '';
    public  $API_Endpoint = '';
    public  $PAYPAL_URL = '';
    public  $USE_PROXY = false;
    public  $version="";
    private $module='';



    public    function __construct() 
    {
        $this->CI = & get_instance();
        //specifying the module to know where to go back after the processing
		// in my case, I have few modules (stock/shop/site, etc).
        $this->module     = $this->CI->router->fetch_module();
        $this->controller = $this->CI->router->fetch_controller();

        $server = $_SERVER['SERVER_NAME'];
        $this->returnURL = "http://{$server}/{$this->module}/{$this->controller}/success/";
        $this->cancelURL = "http://{$server}/{$this->module}/{$this->controller}/cancel/";


        // loading the settings of the library for this site
        if (!file_exists('config/paypalexpress.php')) {
            $this->_set_error_message('Sorry, could not find config file of the paypalexpress payment library');
            return;
        }
        $this->CI->config->load('config/paypalexpress');

        $this->API_UserName     = $this->CI->config->item('API_Username');
        $this->API_Password     = $this->CI->config->item('API_Password');
        $this->API_Signature    = $this->CI->config->item('API_Signature');
        $this->SandboxFlag      = $this->CI->config->item('SandboxFlag');
        $this->currencyCodeType = $this->CI->config->item('currencyCodeType');
        $this->paymentType      = $this->CI->config->item('paymentType');
        $this->PROXY_HOST       = $this->CI->config->item('PROXY_HOST');
        $this->PROXY_PORT       = $this->CI->config->item('PROXY_PORT');
        $this->sBNCode          = $this->CI->config->item('sBNCode');
        $this->USE_PROXY        = $this->CI->config->item('USE_PROXY');
        $this->version          = $this->CI->config->item('version');

        if ($this->SandboxFlag == true){
            $this->API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
            $this->PAYPAL_URL = "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=";
        }  else  {
            $this->API_Endpoint = "https://api-3t.paypal.com/nvp";
            $this->PAYPAL_URL = "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
        }
    }



    // TO DO: CHECK THE RESPONSE OF THIS METHOD WHEN RETURNED.
    public function doSale(Order $order)
    {
        $shipAddr = $order->get_shipping_address();    
        
        $shipAddr->country->get();
        $shipToName     = $shipAddr->name;
        $shipToStreet   = $shipAddr->address1;
        $shipToStreet2  = $shipAddr->address2;
        $shipToCity     = $shipAddr->suburb_city;
        $shipToState    = $shipAddr->state_region;
        $shipToCountryCode = $shipAddr->country->country_code;
        $shipToZip      = $shipAddr->postcode;
        $phoneNum       = $shipAddr->phone_number;

        $products = $order->load_products(TRUE);
        $lineItems = array();
        if(is_array($products)  && !empty($products))
        {
            foreach($products as $product)
            {
                // array entries MUST match payflow field names or you'll break things.
                $lineItems[] = array('NAME'=>$product['name'], 'AMT'=>$product['price'], 'QTY'=> $product['quantity']);
            }
        }
        
        // creating a payment record for it.
        $payment = $order->add_payment($this->paymentMethodObject, $order->total_cost, PAYMENT_DETAILS_PENDING);

        if(!is_object($payment)  ||  !$payment->exists())
        {
            $error = is_object($payment)    ?     $payment->get_error_message()     :    "Unknown error occurred while processing the payment. Please try again.";
            return array('processed'=> FALSE , 'result' => 'error', 'message' => $error);
        }
        
        
        $resArray = $this->CallMarkExpressCheckout ($order->total_cost, $this->currencyCodeType, $this->paymentType, $this->returnURL, $this->cancelURL,
                                                $shipToName, $shipToStreet, $shipToCity, $shipToState,  $shipToCountryCode, $shipToZip, $shipToStreet2, $phoneNum, $order->id, $order->shipping_cost, $lineItems);

        $ack = strtoupper($resArray["ACK"]);
        if($ack=="SUCCESS" || $ack=="SUCCESSWITHWARNING") 
        {
            $token = urldecode($resArray["TOKEN"]);
            $_SESSION['reshash']=$token;
            
            // storing the payment details
            $payDetails = $payment->add_details($order->customer_name, '' , '' , STATUS_PAYMENT_PENDING, $token, "Sending the user to Paypal express checkout", serialize($resArray));
            $this->RedirectToPayPal ( $token );
        }
        else
        {
            // storing the payment details
            $notes = "An error encountered when trying to redirect the user to paypal checkout: ". urldecode($resArray['L_SHORTMESSAGE0']);
            $payDetails = $payment->add_details($order->customer_name, '' , '' , STATUS_PAYMENT_FAILED, $token, $notes, serialize($resArray));
            
            return array('processed'=>FALSE, 'result' => 'error', 'message' => urldecode($resArray['L_SHORTMESSAGE0']) );
        }

    }

    /*
	' This method is provided by the Paypal Express Example.
	'-------------------------------------------------------------------------------------------------------------------------------------------
	' Purpose: 	Prepares the parameters for the SetExpressCheckout API Call.
	' Inputs:
	'		paymentAmount:  	Total value of the shopping cart
	'		currencyCodeType: 	Currency code value the PayPal API
	'		paymentType: 		paymentType has to be one of the following values: Sale or Order or Authorization
	'		returnURL:			the page where buyers return to after they are done with the payment review on PayPal
	'		cancelURL:			the page where buyers return to when they cancel the payment review on PayPal
	'		shipToName:		the Ship to name entered on the merchant's site
	'		shipToStreet:		the Ship to Street entered on the merchant's site
	'		shipToCity:			the Ship to City entered on the merchant's site
	'		shipToState:		the Ship to State entered on the merchant's site
	'		shipToCountryCode:	the Code for Ship to Country entered on the merchant's site
	'		shipToZip:			the Ship to ZipCode entered on the merchant's site
	'		shipToStreet2:		the Ship to Street2 entered on the merchant's site
	'		phoneNum:			the phoneNum  entered on the merchant's site
	'--------------------------------------------------------------------------------------------------------------------------------------------
	*/
	function CallMarkExpressCheckout( $paymentAmount, $currencyCodeType, $paymentType, $returnURL, $cancelURL, $shipToName, 
                                        $shipToStreet, $shipToCity, $shipToState,  $shipToCountryCode, $shipToZip, $shipToStreet2, $phoneNum, $invoiceNo, $shippingCost = FALSE, $lineItems = FALSE)
	{
		//------------------------------------------------------------------------------------------------------------------------------------
		// Construct the parameter string that describes the SetExpressCheckout API call in the shortcut implementation

		$nvpstr="&PAYMENTREQUEST_0_AMT=". $paymentAmount;
		$nvpstr = $nvpstr . "&PAYMENTREQUEST_0_PAYMENTACTION=" . $paymentType;
		$nvpstr = $nvpstr . "&RETURNURL=" . $returnURL;
		$nvpstr = $nvpstr . "&CANCELURL=" . $cancelURL;
		$nvpstr = $nvpstr . "&PAYMENTREQUEST_0_CURRENCYCODE=" . $currencyCodeType;
                $nvpstr = $nvpstr . "&ADDROVERRIDE=1";
                if($shippingCost){
                    $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPPINGAMT=" . $shippingCost;
                    $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_ITEMAMT=" . ($paymentAmount - $shippingCost);                    
                }
		$nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTONAME=" . $shipToName;
		$nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOSTREET=" . $shipToStreet;
		$nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOSTREET2=" . $shipToStreet2;
		$nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOCITY=" . $shipToCity;
		$nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOSTATE=" . $shipToState;
		$nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE=" . $shipToCountryCode;
		$nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOZIP=" . $shipToZip;
		$nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOPHONENUM=" . $phoneNum;
                // if we've got an array of $lineitems, populate it.
                if(is_array($lineItems)){                
                    $count = 0;
                    foreach($lineItems as $item){
                        foreach($item as $key=>$value){
                            $nvpstr .= sprintf("&L_PAYMENTREQUEST_0_%s%s=%s", $key, $count, $value);
                        }                       
                        $count++;
                    }
                }
                
                $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_INVNUM=" . $invoiceNo;

		$_SESSION["currencyCodeType"] = $currencyCodeType;
		$_SESSION["PaymentType"] = $paymentType;

		//'---------------------------------------------------------------------------------------------------------------
		//' Make the API call to PayPal
		//' If the API call succeded, then redirect the buyer to PayPal to begin to authorize payment.
		//' If an error occured, show the resulting errors
		//'---------------------------------------------------------------------------------------------------------------
                $resArray = $this->hash_call("SetExpressCheckout", $nvpstr);
                $resArray['WHAT_WE_SENT'] = $nvpstr;
		$ack = strtoupper($resArray["ACK"]);
		if($ack=="SUCCESS" || $ack=="SUCCESSWITHWARNING")
		{
			$token = urldecode($resArray["TOKEN"]);
			$_SESSION['TOKEN']=$token;
		}

	    return $resArray;
	}

	/*
	' This method is provided by the Paypal Express Example.
	'-------------------------------------------------------------------------------------------
	' Purpose: 	Prepares the parameters for the GetExpressCheckoutDetails API Call.
	'
	' Inputs:
	'		None
	' Returns:
	'		The NVP Collection object of the GetExpressCheckoutDetails Call Response.
	'-------------------------------------------------------------------------------------------
	*/
	function GetShippingDetails( $token )
	{
	    $nvpstr="&TOKEN=" . $token;

            $resArray=$this->hash_call("GetExpressCheckoutDetails",$nvpstr);
	    $ack = strtoupper($resArray["ACK"]);
		if($ack == "SUCCESS" || $ack=="SUCCESSWITHWARNING")
		{
			$_SESSION['payer_id'] =	$resArray['PAYERID'];
		}
		return $resArray;
	}

	// this method is not used
    public    function ConfirmPayment( $FinalPaymentAmt )
	{
		//Format the other parameters that were stored in the session from the previous calls
		$token 			= urlencode($_SESSION['TOKEN']);
		$paymentType 		= urlencode($_SESSION['PaymentType']);
		$currencyCodeType 	= urlencode($_SESSION['currencyCodeType']);
		$payerID 		= urlencode($_SESSION['payer_id']);

		$serverName 		= urlencode($_SERVER['SERVER_NAME']);

		$nvpstr  = '&TOKEN=' . $token . '&PAYERID=' . $payerID . '&PAYMENTREQUEST_0_PAYMENTACTION=' . $paymentType . '&PAYMENTREQUEST_0_AMT=' . $FinalPaymentAmt;
		$nvpstr .= '&PAYMENTREQUEST_0_CURRENCYCODE=' . $currencyCodeType . '&IPADDRESS=' . $serverName;
		$resArray=$this->hash_call("DoExpressCheckoutPayment",$nvpstr);
		
		return $resArray;
	}

	

        
     
    /**
     * redirect the user to Paypal Express Checkout with the appropriate
     * token value. 
     * 
     * @param  String $token 
     * @return void
     * @author Has Taiar
     */
     
    public function RedirectToPayPal ( $token )
    {
        // Redirect to paypal.com here
        $payPalURL = $this->PAYPAL_URL . $token;
        header("Location: $payPalURL");
        exit();
    }


	/*'
  	  * This method is provided by the Paypal Express Example.
	  *----------------------------------------------------------------------------------
	  * This function will take NVPString and convert it to an Associative Array and it will decode the response.
	  * It is usefull to search for a particular key and displaying arrays.
	  * @nvpstr is NVPString.
	  * @nvpArray is Associative Array.
	   ----------------------------------------------------------------------------------
	  */
	function deformatNVP($nvpstr)
	{
		$intial=0;
	 	$nvpArray = array();

		while(strlen($nvpstr))
		{
			//postion of Key
			$keypos= strpos($nvpstr,'=');
			//position of value
			$valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&'): strlen($nvpstr);

			/*getting the Key and Value values and storing in a Associative Array*/
			$keyval=substr($nvpstr,$intial,$keypos);
			$valval=substr($nvpstr,$keypos+1,$valuepos-$keypos-1);
			//decoding the respose
			$nvpArray[urldecode($keyval)] =urldecode( $valval);
			$nvpstr=substr($nvpstr,$valuepos+1,strlen($nvpstr));
	     }
		return $nvpArray;
	}

        /*
         * Check the address of the shipping order against the one sent by Paypal in case the user changed it when doing the payment
         * @input array of options returned from invoking the paypal api
         */
         function checkAddressAfterPayment($resArray) {

            $email 		= $resArray["EMAIL"]; // ' Email address of payer.
            isset($resArray["FIRSTNAME"])   ?    $firstName = $resArray["FIRSTNAME"] : '';
            isset($resArray["LASTNAME"])    ?    $lastName  = $resArray["LASTNAME"]  : '';
            isset($resArray["SHIPTOSTREET2"])    ?  $shipToStreet2    = $resArray["SHIPTOSTREET2"]  :   '';
            isset($resArray["INVNUM"])      ?    $invoiceNumber      = $resArray["INVNUM"]: "";
            isset($resArray["PHONENUM"])    ?    $phoneNumber       = $resArray["PHONENUM"] : '';

            $shipToName		= $resArray["SHIPTONAME"]; // ' Person's name associated with this address.
            $shipToStreet    	= $resArray["SHIPTOSTREET"]; // ' First street address.
            $shipToCity		= $resArray["SHIPTOCITY"]; // ' Name of city.
            $shipToState        = $resArray["SHIPTOSTATE"]; // ' State or province
            $shipToCntryCode    = $resArray["SHIPTOCOUNTRYCODE"]; // ' Country code.
            $shipToZip  	= $resArray["SHIPTOZIP"]; // ' U.S. Zip code or other country-specific postal code.
            
            // and check them against the orders info
            $order = new Order($invoiceNumber);
            if ($order->exists()) {
                $shipAddress = $order->shipping_address->get();
                $shipAddress->country->get();
                // do the checking
                !empty ($email)          ?   $order->customer_email = $email         : $email = '';
                !empty ($shipToName)     ?   $shipAddress->name = $shipToName        : $shipToName = '';
                !empty ($shipToStreet)   ?   $shipAddress->address1 = $shipToStreet  : $shipToStreet = '';
                !empty ($shipToStreet2)  ?   $shipAddress->address2 = $shipToStreet2 : $shipToStreet2 = '';
                !empty ($shipToCity)     ?   $shipAddress->suburb_city = $shipToCity : $shipToCity = '';
                !empty ($shipToState)    ?   $shipAddress->state_region=$shipToState : $shipToState = '';
                !empty ($shipToZip)      ?   $shipAddress->postcode    = $shipToZip  : $shipToZip = '';

                if (!empty ($shipToCntryCode)   &&  $shipAddress->country->country_code != $shipToCntryCode) {
                    $country = new Country();
                    $country->where('country_code', $shipToCntryCode)->get();
                    if ($country->exists()) {
                        $old_country = $shipAddress->country->get();
                        $shipAddress->delete($old_country);
                        $shipAddress->save($country);
                    }
                }
                //saving the shipping address after checking for any modifications
                $order->save(array('shipping_address'=> $shipAddress) ) ;
                log_message('info', "Order no {$order->id} was address-checked after COMPLETING PAYMENT on " . date('Y/m/d H-i-s') );
            } else {
                 log_message('error', "Order no {$order->id} could not be address-verified AFTER COMPLETING PAYMENT ON " . date('Y/m/d H-i-s') );
            }
         }



    /**
     * Compelete Order is a method that is implemented by all Payment libraries to
     * Finalise the order and update the stock level and update the order status to payment complete
     * 
     * @param  int $id
     * @return boolean|Order 
     * 
     * @author Has Taiar
     */
    public function completeOrder($id) 
    {
        $order = new Order($id);
        
        if ($order->exists()) 
        {
            $order->generate_barcode();
            $result = $this->ConfirmPayment($order->total_cost);
            $ack   = strtoupper($result["ACK"]);
            $token = urldecode( $result["TOKEN"] );
            $payDetails = new PaymentDetail();
            
            if($ack == "SUCCESS" || $ack=="SUCCESSWITHWARNING")
            {
                 $order->setStatus(STATUS_PAYMENT_COMPLETE, 'Status updated in Paypal Express Checkout Library');
                 $payDetails->update_status_by_token( trim($token) , PAYMENT_DETAILS_SUCCESS, "Payment Completed.", serialize($result));
            }
            else 
            {
                $payDetails->update_status_by_token( trim($token) , PAYMENT_DETAILS_FAIL, "Payment FAILED.", serialize($result));
                log_message('error', "Order no {$order->id} FAILED TO BE UPDATED AFTER COMPLETING PAYMENT ON " . date('Y/m/d H-i-s') );
                return FALSE;
            }
        } 
        else 
        {
             log_message('error', "Order no {$order->id} FAILED TO BE UPDATED AFTER COMPLETING PAYMENT ON " . date('Y/m/d H-i-s') );
        }
        return $order;
    }




      /**
	  *	 This method is provided by the Paypal Express Example.
	  *-------------------------------------------------------------------------------------------------------------------------------------------
	  * hash_call: Function to perform the API call to PayPal using API signature
	  * @methodName is name of API  method.
	  * @nvpStr is nvp string.
	  * returns an associtive array containing the response from the server.
	  *-------------------------------------------------------------------------------------------------------------------------------------------
	*/
	function hash_call($methodName,$nvpStr)
	{
		//setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$this->API_Endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		//turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POST, 1);

	       if($this->USE_PROXY)
			curl_setopt ($ch, CURLOPT_PROXY, $this->PROXY_HOST. ":" . $this->PROXY_PORT);

		//NVPRequest for submitting to server
		$nvpreq="METHOD=" . urlencode($methodName) . "&VERSION=" . urlencode($this->version) . "&PWD=" . urlencode($this->API_Password) . "&USER=" . urlencode($this->API_UserName) . "&SIGNATURE=" . urlencode($this->API_Signature) . $nvpStr . "&BUTTONSOURCE=" . urlencode($this->sBNCode);

		//setting the nvpreq as POST FIELD to curl
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

		//getting response from server
		$response = curl_exec($ch);

		//convrting NVPResponse to an Associative Array
		$nvpResArray=$this->deformatNVP($response);
		$nvpReqArray=$this->deformatNVP($nvpreq);
		$_SESSION['nvpReqArray']=$nvpReqArray;

		if (curl_errno($ch))
		{
                     // moving to display page to display curl errors
                      $_SESSION['curl_error_no']=curl_errno($ch) ;
                      $_SESSION['curl_error_msg']=curl_error($ch);
                      //Execute the Error handling module to display errors.
		}
		else
		{
                     //closing the curl
                    curl_close($ch);
		}
		return $nvpResArray;
	}


}






?>