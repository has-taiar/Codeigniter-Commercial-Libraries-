<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/*
| -------------------------------------------------------------------
|  config options of the accounting package integration
| -------------------------------------------------------------------
|   @author Has Taiar
|
|   This tells our Codeigniter App whether it has an integration with an accounting package or not
|   And if it has, then what is the library that implements this integration. 
|
*/

$config['acc_package_enable'] 	= TRUE;   
$config['acc_package_lib_name']	= "xero_lib";
$config['acc_package_config']   = "xero_config";

/* End of file accounting_pkg.php */
