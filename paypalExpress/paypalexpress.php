<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/*
| -------------------------------------------------------------------
|  config options of Paypal Express Checkout
| -------------------------------------------------------------------
|   @author Hasn AlTaiar
| 
|	This is where you configure your Paypal Express Account.
|
|	For the sake of testing, you can just create an account on
|
|	paypal Express's Sandbox to play with it. Remeber that you would
|
|	need to use a Sandbox account for buying as well (for testing).
|
|
*/

$config['paymentType']      = "Sale";
$config['currencyCodeType'] = "AUD";
$config['PROXY_HOST']       = "127.0.0.1";
$config['PROXY_PORT']       = "808";
$config['SandboxFlag']      = TRUE;
$config['API_Username']     = 'YOUR_API_USERNAME_HERE';
$config['API_Password']     = 'YOUR_API_PASSWORD_HERE';
$config['API_Signature']    = 'YOUR_API_SIGNATURE_HERE';
$config['sBNCode']          = 'YOUR_API_SBNCODE_HERE';
$config['USE_PROXY']        = FALSE;
$config['version']          = "64";

/* End of file paypalexpress.php */
