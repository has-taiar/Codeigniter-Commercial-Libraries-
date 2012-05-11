<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
* This library to process the payment with Paypal Express Checkout. 
*
* This library makes use of the PHP Functions provided by Paypal Express page.
*
*
* @author Jez  & Has AlTaiar
*/

class PaypalGateway_lib {
	
	public  $systemName = 'PayPal Payflow Gateway';
	public  $displayName = 'Credit Card';
	public  $library = 'paypalgateway_lib';
	public  $creditcard;
	
    public  $processType= 'local'; // 'remote', 'display';
	public  $processUrl = 'getcc';
	private $_error_message;
	
	public function __construct(){
		
		// config 
		$this->CI = & get_instance();
		// loading the settings of the library for this site
		if (!file_exists('config/payflowgateway.php')) {
			$this->_set_error_message('Sorry, could not find config file of the payflow payment library');
			return FALSE;
		}
		
		
		$this->CI->config->load('config/payflowgateway');
		
		$this->testing = $this->CI->config->item('pfTesting');
		$this->pfVendor = $this->CI->config->item('pfVendor');
		$this->pfPartner = $this->CI->config->item('pfPartner');
		$this->pfUser = $this->CI->config->item('pfUser');
		$this->pfPassword= $this->CI->config->item('pfPassword');
		
	}
	
	public function setCreditCard($num, $expMonth, $expYear, $name, $type, $cvv2 = ''){
		$this->creditcard = new CreditCard($num, $expMonth.$expYear, $name, $type, $cvv2);	
		if(is_object($this->creditcard)){
			$_SESSION['cornchips'] = serialize($this->creditcard);
			return true;
		}else{
			return false;
		}
	}
	
	function getCreditCard(){		
		$this->creditcard = unserialize($_SESSION['cornchips']);		
		if(is_object($this->creditcard)){
			return true;			
		}else{
			return false;
		}
	}
	
	function validateCreditCard(){
            if(is_object($this->creditcard)){
                return array('result' => $this->creditcard->validateNumber() , 'error' => 'not valid credit card details' );
            }else{
                return array('result' =>false, 'error' => 'no credit card defined');
            }
	}
	
	
        
        /**
         * do sale and prcess payment via credit card
         * 
         * @param  Order $order
         * @return Array
         * @author Anonymous & Has Taiar 
         */
        public function doSale(Order $order )
        {		
            if(!$this->getCreditCard()){
                return array('processed'=> FALSE, 'result' => 'error', 'message' =>'No credit card defined');
            }

            $payflow = new payflow($this->pfVendor, $this->pfUser, $this->pfPartner, $this->pfPassword);

            $bAddr = $order->get_billing_address();
            $extraData = array(
                    'comment1' => 'Order ID: '.$order->id,
                    'firstname' => trim( strstr($bAddr->name, ' ', true) ),
                    'lastname' => trim( strstr($bAddr->name, ' ') ) ,
                    'street' => $bAddr->address1 . ' ' . $bAddr->address2,
                    'city' => $bAddr->suburb_city,
                    'state' => $bAddr->state_region,
                    'zip' => $bAddr->postcode,
                    'country' => $this->getCountryCode($bAddr->country),
                    'clientip' => $_SERVER['REMOTE_ADDR']
            );
            
            // make sure that amount to charge has two decimal places.
            $totalPay = number_format($order->total_cost , 2); 
            // cc details are stored in the memory from the previous request.
            $result = $payflow->sale_transaction($this->creditcard, $totalPay , 'AUD', $extraData);
            // adding the payment record
            $payment = $order->add_payment($this->paymentMethodObject, $order->total_cost , PAYMENT_DETAILS_PENDING ,'' ,"Setting the Credit Card Details.");
            $payDetails =  $payment->add_details($this->creditcard->name , $this->creditcard->maskedNum , $this->creditcard->exp , PAYMENT_DETAILS_PENDING, '' , "Paying by {$this->creditcard->type}, setting Credit Card Details.", serialize($result));
            
            $result = $this->processResult($result);
            if (isset($result['result'])  && $result['result'] == 'success') 
            {
                $payDetails->update_status(PAYMENT_DETAILS_SUCCESS, '', $result['message'] , '');
                return array('processed'=>TRUE, 'result' => 'success', 'message' => $result['message'] );
            } 
            else 
            {
                $payDetails->update_status(PAYMENT_DETAILS_FAIL, '', $result['message'] , '');
                return array('processed'=>FALSE, 'result' => 'error', 'message' => $result['message'] );
            }
	}
        
        
        
	
	function getCountryCode($country){
		$country = new Country($country);
		return $country->country_code;
	}
	
	function doRecurringPayment($tehmonies, $frequency = 'MNTH'){
		
	}
	
	function doRecurringSomethingOrTheOther($tehmonies, $frequency = 'MNTH'){
		doRecurringPayment($tehmonies, $frequency);	
	}
	
	public function processResult($resultSet)
        {
            $result = $resultSet['RESULT'];
            $pfMsg = $resultSet['RESPMSG'];
            $finalResult = 'error';
            switch($result){
			case '0' : { // successful
				$message = sprintf('Transaction successful.');
				$finalResult = 'success';
				break;
			}
			// DO WE NEED TO HANDLE 08 HONOR WITH SIGNATURE HERE OR IS IT PART OF 0?
			
			case '12' : { // transaction declined.
				$message = sprintf('Transaction declined: %s', $pfMsg);
				break;
			}
			
			case '23' : { // credit card number invalid.
				$message = sprintf('Transaction failed: %s', $pfMsg);
				break;
			}
			
			case '24' : { // credit card expiry date invalid.
				$message = sprintf('Transaction failed: %s', $pfMsg);
				break;
			}			
	
			case '50' : { // insufficient funds in account - paypal accounts only?
				$message = sprintf('Transaction failed: %s', $pfMsg);
				break;
			}			
				
			case '51' : { // transaction amount exceeds per transaction limit.
				$message = sprintf(': %s', $pfMsg);
				break;
			}			
			
			case '125' : { // fraud profection services declined the transaction.
				$message = sprintf(': %s', $pfMsg);
				break;
			}			
			
			case '126' : { // fraud profection services declined the transaction.
				$message = sprintf(': %s', $pfMsg);
				break;
			}			

			case '150': case '151' : { // issuing bank timed out or is unavailable.
				$message = sprintf(': %s', $pfMsg);
				break;
			}				
			
			case '115' : { // system busy, retry later.
				$message = sprintf(': %s', $pfMsg);
				break;
			}			
							
			default : { // unspecificly handled error. Log message for Administrator perusal.
				$message = sprintf('System error: [%s] %s', $result, $pfMsg);								
				break;
			}			
		}
		$results = array('processed'=>true, 'message'=>$message, 'result'=>$finalResult);

		
		return $results;
		
	}
	
	
	private function _set_error_message($error)
	{
		$this->_error_message = $error;
	}
	
	public function get_error_message()
	{
		return $this->_error_message;
	}

}




class CreditCard {

	var $number;
	var $exp;

	var $name;
	var $type;

	var $cvv2;

	function CreditCard($num, $exp, $name, $type = 'visa', $cvv2 = ''){
		$this->number = $num;
		$this->exp = $exp;
		$this->name = $name;
		$this->type = $type;
		$this->cvv2 = $cvv2;
		$this->maskedNum = sprintf('%sXXX XXXX XXXX %s', substr($this->number, 0, 1), substr($this->number, -4));
	}


    function validateExpire() {
      if (!is_numeric($mmyy) || strlen($mmyy) != 4) {
        return false;
      }
      $mm = substr($mmyy, 0, 2);
      $yy = substr($mmyy, 2, 2);
      if ($mm < 1 || $mm > 12) {
        return false;
      }
      $year = date('Y');
      $yy = substr($year, 0, 2) . $yy; // eg 2007
      if (is_numeric($yy) && $yy >= $year && $yy <= ($year + 10)) {
      } else {
        return false;
      }
      if ($yy == $year && $mm < date('n')) {
        return false;
      }
      return true;
    }

    // luhn algorithm
    function validateNumber() {
      $card_number = preg_replace('[^0-9]', '', $this->number);
      if ($card_number < 9) return false;
      $card_number = strrev($card_number);
      $total = 0;
      for ($i = 0; $i < strlen($card_number); $i++) {
        $current_number = substr($card_number, $i, 1);
        if ($i % 2 == 1) {
          $current_number *= 2;
        }
        if ($current_number > 9) {
          $first_number = $current_number % 10;
          $second_number = ($current_number - $first_number) / 10;
          $current_number = $first_number + $second_number;
        }
        $total += $current_number;
      }
      return ($total % 10 == 0);
	}
}




  /**
  * The script implements the HTTPS protocol, via the PHP cURL extension. 
  *
  * The nice thing about this protocol is that if you *don't* get a
  * $response, you can simply re-submit the transaction *using the same
  * REQUEST_ID* until you *do* get a response -- every time PayPal gets
  * a transaction with the same REQUEST_ID, it will not process a new
  * transactions, but simply return the same results, with a DUPLICATE=1
  * parameter appended.
  *
  * API rebuild by Radu Manole, 
  * radu@u-zine.com, March 2007
  *
  * Many thanks to Sieber Todd, tsieber@paypal.com
  */

  class payflow {
    
    var $submiturl;
    var $vendor;
    var $user;
    var $partner;
    var $password;
    var $errors = '';
    var $ClientCertificationId = '13fda2433fc2123d8b191d2d011b7fdc'; // deprecated - use a random id
    var $currencies_allowed = array('USD', 'EUR', 'GBP', 'CAD', 'JPY', 'AUD');
	var $defaultCurrency = 'AUD';
    var $test_mode = 1; // 1 = true, 0 = production
    
    function payflow($vendor, $user, $partner, $password) {
      
      $this->vendor = $vendor;
      $this->user = $user;
      $this->partner = $partner;
      $this->password = $password;
      
      if (strlen($this->vendor) == 0) {
        $this->set_errors('Vendor not found');
        return;
      }
      if (strlen($this->user) == 0) {
        $this->set_errors('User not found');
        return false;
      }
      if (strlen($this->partner) == 0) {
        $this->set_errors('Partner not found');
        return false;
      }
      if (strlen($this->password) == 0) {
        $this->set_errors('Password not found');
        return false;
      }
      
      if ($this->test_mode == 1) {
        $this->submiturl = 'https://pilot-payflowpro.paypal.com/';   
      } else {
        $this->submiturl = 'https://payflowpro.paypal.com/';
      }
      
      // check for CURL
      if (!function_exists('curl_init')) {
        $this->set_errors('Curl function not found.');
        return;
      }           
    }

    function sale_transaction($card, $amount, $currency = '', $data_array = array() ) {
		/*if($currency=''){
			$currency = $this->defaultCurrency;
		}*/
		
      if (!is_numeric($amount) || $amount <= 0) {
        $this->set_errors('Amount is not valid');
        return;           
      }
      if (!in_array($currency, $this->currencies_allowed)) {
        $this->set_errors('Currency not allowed');
        return;                   
      } 

      // build hash
      $tempstr = $card->number . $amount . date('YmdGis') . "1";
      $request_id = md5($tempstr);

      // body
      $plist = 'USER=' . $this->user . '&';
      $plist .= 'VENDOR=' . $this->vendor . '&';
      $plist .= 'PARTNER=' . $this->partner . '&';
      $plist .= 'PWD=' . $this->password . '&';           
      $plist .= 'TENDER=' . 'C' . '&'; // C = credit card, P = PayPal
      $plist .= 'TRXTYPE=' . 'S' . '&'; //  S = Sale transaction, A = Authorisation, C = Credit, D = Delayed Capture, V = Void                        
      $plist .= 'ACCT=' . $card->number. '&'; 
      $plist .= 'EXPDATE=' . $card->exp . '&';
      $plist .= 'NAME=' . $card->name . '&';
      $plist .= 'AMT=' . $amount . '&';
      // extra data
      $plist .= 'CURRENCY=' . $currency . '&';
      $plist .= 'COMMENT1=' . $data_array['comment1'] . '&'; 
      $plist .= 'FIRSTNAME=' . $data_array['firstname'] . '&';
      $plist .= 'LASTNAME=' . $data_array['lastname'] . '&';
      $plist .= 'STREET=' . $data_array['street'] . '&';
      $plist .= 'CITY=' . $data_array['city'] . '&';     
      $plist .= 'STATE=' . $data_array['state'] . '&';     
      $plist .= 'ZIP=' . $data_array['zip'] .  '&';      
      $plist .= 'COUNTRY=US' . $data_array['country'] . '&';
      if ($card->cvv2!='') {
        $plist .= 'CVV2=' . $card->cvv2 . '&';
      }
      $plist .= 'CLIENTIP=' . $data_array['clientip'] . '&';
      // verbosity
      $plist .= 'VERBOSITY=MEDIUM';

      $headers = $this->get_curl_headers();
      $headers[] = "X-VPS-Request-ID: " . $request_id;

      $user_agent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)"; // play as Mozilla
      $ch = curl_init(); 
      curl_setopt($ch, CURLOPT_URL, $this->submiturl);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
      curl_setopt($ch, CURLOPT_HEADER, 1); // tells curl to include headers in response
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
      curl_setopt($ch, CURLOPT_TIMEOUT, 45); // times out after 45 secs
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // this line makes it work under https
      curl_setopt($ch, CURLOPT_POSTFIELDS, $plist); //adding POST data
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2); //verifies ssl certificate
      curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE); //forces closure of connection when done 
      curl_setopt($ch, CURLOPT_POST, 1); //data sent as POST 

      $result = curl_exec($ch);
      $headers = curl_getinfo($ch);
      curl_close($ch);

      $pfpro = $this->get_curl_result($result); //result arrray


      return $pfpro;
    }

    // Authorization
    function authorization($card, $amount, $currency ='') {
	
      if (!is_numeric($amount) || $amount <= 0) {
        $this->set_errors('Amount is not valid');
        return;           
      }
      if (!in_array($currency, $this->currencies_allowed)) {
        $this->set_errors('Currency not allowed');
        return;                   
      } 
     
      // build hash
      $tempstr = $card->number . $amount . date('YmdGis') . "1";
      $request_id = md5($tempstr);
      
      // body of the POST
      $plist = 'USER=' . $this->user . '&';
      $plist .= 'VENDOR=' . $this->vendor . '&';
      $plist .= 'PARTNER=' . $this->partner . '&';
      $plist .= 'PWD=' . $this->password . '&';           
      $plist .= 'TENDER=' . 'C' . '&'; // C = credit card, P = PayPal
      $plist .= 'TRXTYPE=' . 'A' . '&'; //  S = Sale transaction, A = Authorisation, C = Credit, D = Delayed Capture, V = Void                        
      $plist .= 'ACCT=' . $card->number . '&';
      $plist .= 'EXPDATE=' . $card->exp . '&'; 
      $plist .= 'NAME=' . $card->name . '&';
      $plist .= 'AMT=' . $amount . '&';  // amount
      $plist .= 'CURRENCY=' . $currency . '&';
      $plist .= 'VERBOSITY=MEDIUM';
    
      $headers = $this->get_curl_headers();
      $headers[] = "X-VPS-Request-ID: " . $request_id;

      $user_agent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)"; // play as Mozilla
      $ch = curl_init(); 
      curl_setopt($ch, CURLOPT_URL, $this->submiturl);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
      curl_setopt($ch, CURLOPT_HEADER, 1); // tells curl to include headers in response
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
      curl_setopt($ch, CURLOPT_TIMEOUT, 45); // times out after 45 secs
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // this line makes it work under https
      curl_setopt($ch, CURLOPT_POSTFIELDS, $plist); //adding POST data
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2); //verifies ssl certificate
      curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE); //forces closure of connection when done 
      curl_setopt($ch, CURLOPT_POST, 1); //data sent as POST 
    
      // $rawHeader = curl_exec($ch); // run the whole process
      // $info = curl_getinfo($ch); //grabbing details of curl connection
      $result = curl_exec($ch);
      $headers = curl_getinfo($ch);
      curl_close($ch);

      $pfpro = $this->get_curl_result($result); //result arrray
    

      return $pfpro;
    }

    // Delayed Capture
    function delayed_capture($origid, $card, $amount = '') {
      
      if (strlen($origid) < 3) {
        $this->set_errors('OrigID not valid');
        return; 
      }

      // build hash
      $tempstr = $card->number . $amount . date('YmdGis') . "2";
      $request_id = md5($tempstr);

      // body
      $plist = 'USER=' . $this->user . '&';
      $plist .= 'VENDOR=' . $this->vendor . '&';
      $plist .= 'PARTNER=' . $this->partner . '&';
      $plist .= 'PWD=' . $this->password . '&';           
      $plist .= 'TENDER=' . 'C' . '&'; // C = credit card, P = PayPal
      $plist .= 'TRXTYPE=' . 'D' . '&'; //  S = Sale transaction, A = Authorisation, C = Credit, D = Delayed Capture, V = Void                        
      $plist .= "ORIGID=" . $origid . "&"; // ORIGID to the PNREF value returned from the original transaction
      $plist .= 'VERBOSITY=MEDIUM';

      $headers = $this->get_curl_headers();
      $headers[] = "X-VPS-Request-ID: " . $request_id;
  
      $user_agent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)";
      $ch = curl_init(); 
      curl_setopt($ch, CURLOPT_URL, $this->submiturl);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
      curl_setopt($ch, CURLOPT_HEADER, 1); // tells curl to include headers in response
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
      curl_setopt($ch, CURLOPT_TIMEOUT, 45); // times out after 45 secs
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // this line makes it work under https
      curl_setopt($ch, CURLOPT_POSTFIELDS, $plist); //adding POST data
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2); //verifies ssl certificate
      curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE); //forces closure of connection when done 
      curl_setopt($ch, CURLOPT_POST, 1); //data sent as POST 
      
      $result = curl_exec($ch);
      $headers = curl_getinfo($ch);
      curl_close($ch);
            
      $pfpro = $this->get_curl_result($result); //result arrray
      
        return $pfpro;
     }

    // Authorization followed by Delayed Capture
    function authorization_delayed_capture($card, $amount, $currency = '') {
		if($currency=''){
			$currency = $this->defaultCurrency;
		}
		
		// 1. authorization
      $result = $this->authorization($card, $amount, $currency);
      if (!$this->get_errors() && isset($result['PNREF'])) {
        // 2. delayed
        $result_capture = $this->delayed_capture($result['PNREF']);
        if (!$this->get_errors()) {
          return $result_capture;
        }       
      }
      return false;
    }

    // Credit Transaction
    function credit_transaction($origid) {

      if (strlen($origid) < 3) {
        $this->set_errors('OrigID not valid');
        return; 
      }

      // body
      $plist = 'USER=' . $this->user . '&';
      $plist .= 'VENDOR=' . $this->vendor . '&';
      $plist .= 'PARTNER=' . $this->partner . '&';
      $plist .= 'PWD=' . $this->password . '&';           
      $plist .= 'TENDER=' . 'C' . '&'; // C = credit card, P = PayPal
      $plist .= 'TRXTYPE=' . 'C' . '&'; //  S = Sale transaction, A = Authorisation, C = Credit, D = Delayed Capture, V = Void
      $plist .= "ORIGID=" . $origid . "&"; // ORIGID to the PNREF value returned from the original transaction
      $plist .= 'VERBOSITY=MEDIUM';

      $headers = $this->get_curl_headers();
      $headers[] = "X-VPS-Request-ID: " . $request_id;
  
      $user_agent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)";
      $ch = curl_init(); 
      curl_setopt($ch, CURLOPT_URL, $this->submiturl);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
      curl_setopt($ch, CURLOPT_HEADER, 1); // tells curl to include headers in response
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
      curl_setopt($ch, CURLOPT_TIMEOUT, 45); // times out after 45 secs
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // this line makes it work under https
      curl_setopt($ch, CURLOPT_POSTFIELDS, $plist); //adding POST data
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2); //verifies ssl certificate
      curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE); //forces closure of connection when done 
      curl_setopt($ch, CURLOPT_POST, 1); //data sent as POST 
      
      $result = curl_exec($ch);
      $headers = curl_getinfo($ch);
      curl_close($ch);
            
      $pfpro = $this->get_curl_result($result); //result arrray
      
        return $pfpro;
       }
    
    // Void Transaction
    function void_transaction($origid) {

      if (strlen($origid) < 3) {
        $this->set_errors('OrigID not valid');
        return; 
      }

      // body
      $plist = 'USER=' . $this->user . '&';
      $plist .= 'VENDOR=' . $this->vendor . '&';
      $plist .= 'PARTNER=' . $this->partner . '&';
      $plist .= 'PWD=' . $this->password . '&';           
      $plist .= 'TENDER=' . 'C' . '&'; // C = credit card, P = PayPal
      $plist .= 'TRXTYPE=' . 'V' . '&'; //  S = Sale transaction, A = Authorisation, C = Credit, D = Delayed Capture, V = Void                        
      $plist .= "ORIGID=" . $origid . "&"; // ORIGID to the PNREF value returned from the original transaction
      $plist .= 'VERBOSITY=MEDIUM';

      $headers = $this->get_curl_headers();
      $headers[] = "X-VPS-Request-ID: " . $request_id;
  
      $user_agent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)";
      $ch = curl_init(); 
      curl_setopt($ch, CURLOPT_URL, $this->submiturl);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
      curl_setopt($ch, CURLOPT_HEADER, 1); // tells curl to include headers in response
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
      curl_setopt($ch, CURLOPT_TIMEOUT, 45); // times out after 45 secs
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // this line makes it work under https
      curl_setopt($ch, CURLOPT_POSTFIELDS, $plist); //adding POST data
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2); //verifies ssl certificate
      curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE); //forces closure of connection when done 
      curl_setopt($ch, CURLOPT_POST, 1); //data sent as POST 
      
      $result = curl_exec($ch);
      $headers = curl_getinfo($ch);
      curl_close($ch);
            
      $pfpro = $this->get_curl_result($result); //result arrray
      
      if (isset($pfpro['RESULT']) && $pfpro['RESULT'] == 0) {
        return $pfpro;
      } else {
        $this->set_errors($pfpro['RESPMSG'] . ' ['. $pfpro['RESULT'] . ']');
        return false;     
      } 
    }

    // Curl custom headers; adjust appropriately for your setup:
    function get_curl_headers() {
      $headers = array();
      
      $headers[] = "Content-Type: text/namevalue"; //or maybe text/xml
      $headers[] = "X-VPS-Timeout: 30";
      $headers[] = "X-VPS-VIT-OS-Name: Linux";  // Name of your OS
      $headers[] = "X-VPS-VIT-OS-Version: Ubuntu 10.04";  // OS Version
      $headers[] = "X-VPS-VIT-Client-Type: PHP/cURL";  // What you are using
      $headers[] = "X-VPS-VIT-Client-Version: 1.0";  // For your info
      $headers[] = "X-VPS-VIT-Client-Architecture: x86";  // For your info
      $headers[] = "X-VPS-VIT-Client-Certification-Id: " . $this->ClientCertificationId . ""; // get this from payflowintegrator@paypal.com
      $headers[] = "X-VPS-VIT-Integration-Product: SSB Payflow Gateway";  // For your info, would populate with application name
      $headers[] = "X-VPS-VIT-Integration-Version: 1.0"; // Application version    
      
      return $headers;  
    }

    // parse result and return an array
    function get_curl_result($result) {
      if (empty($result)) return;

      $pfpro = array();
      $result = strstr($result, 'RESULT');    
      $valArray = explode('&', $result);
      foreach($valArray as $val) {
        $valArray2 = explode('=', $val);
        $pfpro[$valArray2[0]] = $valArray2[1];
      }
      return $pfpro;    
    }

	function get_errors() {
      if ($this->errors != '') {
        return $this->errors;
      }
      return false;
    }
  
    function set_errors($string) {
      $this->errors = $string;
    }

    function get_version() {
      return '4.03';
    }    
  } 
?>