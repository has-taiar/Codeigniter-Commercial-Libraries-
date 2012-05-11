<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/*
| -------------------------------------------------------------------
|  config options of Xero accounting package integration
| -------------------------------------------------------------------
|   @author Hasn AlTaiar
|
*/


// INVOICE STATUS IN XERO 
define('XERO_INVOICE_STATUS_SUBMITTED'    , 'SUBMITTED' );
define('XERO_INVOICE_STATUS_AUTHORISED'   , 'AUTHORISED' );
define('XERO_INVOICE_STATUS_VOIDED'	  , 'VOIDED');
define('XERO_INVOICE_STATUS_PAID'	  , 'PAID');
define('XERO_INVOICE_STATUS_DRAFT'	  , 'DRAFT');

// TYPE OF INVOICES 
define('XERO_INVOICE_TYPE_ORDER'	  , 'ACCREC');
define('XERO_INVOICE_TYPE_STOCK_ORDER'    , 'ACCPAY');

// ACCOUNT CODE FOR STOCK SALES AND PURCHASES 
define('XERO_SALES_ACCOUNT_CODE'  	  , 200 );
define('XERO_PURCHASES_ACCOUNT_CODE'      , 300 );


// FORMAT OF ENTITY IDs 
define('XERO_SUPPLIER_ID_FORMAT'          , "SUP-%05d");
define('XERO_CUSTOMER_ID_FORMAT'          , "CUS-%05d");
define('XERO_ORDER_ID_FORMAT'             , "REC-%05d");
define('XERO_STOCKORDER_ID_FORMAT'        , "PAY-%05d");


/*
* Public and Secret Keys are generated from Xero when you
* register your account as a private App.
* Generally, You would want to have an invitation from your clients account
* on Xero then you login to Xero and register their app and get your keys
* to put it for your config
*/
$config['xero_key']      	= "PUT_YOUR_XERO_KEY_HERE";   
$config['xero_secret']		= "PUT_YOUR_XERO_SECRET_KEY_HERE";
$config['public_key_path']      = "config/publickey.cer";
$config['private_key_path']     = "config/privatekey.pem";
$config['request_format']       = "xml";

/* End of file xero.php */
