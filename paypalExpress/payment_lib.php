<?php  

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * This library is useful for handling the different payment methods and load the proper Payment Library. 
 * 
 * This assumes that we have a table called Payment_methods in the database  
 * @name payment_lib
 * @author Has Taiar
 */

class Payment_lib implements IteratorAggregate{

    private $availableOptions = array("paypalgateway_lib", "payonaccount_lib", "paypalexpress_lib");

    private $paymentOptions = array();

     
    public function getIterator() {
        return new ArrayIterator($this->paymentOptions);
    }
	
    function loadAll(){
        $CI =& get_instance();
        foreach($this->availableOptions as $opt){
                $CI->load->library($opt);
                $this->paymentOptions[] = $CI->$opt;
        }
        return true;
    }
	
    function loadById($id = 0){
        $method = new Payment_Method($id);
        if($method->exists()){
                $this->loadLib($method);
                return end($this->paymentOptions);
        }else{
                return false;
        }
    }
				
    function loadAvailable($site_id = 1, $user_id = 0){
	$user = new User($user_id);
        $methods = new Payment_Method();
        //getting all the payment_methods that are attached to this user type, or just the default GLOBAL methods
        $methods->get_all_avaiable_methods($user);

        foreach($methods as $opt){
                $this->loadLib($opt);
        }
        return true;
    }	
		
    function loadLib($opt){
        $CI =& get_instance();
        $libName = strtolower($opt->type);
        $objName = $opt->id;
        $CI->load->library($libName, NULL, $objName);
        $CI->$objName->id = $opt->id;
        $CI->$objName->name = $opt->name;
        $CI->$objName->displayName = $opt->name;
        $CI->$objName->paymentMethodObject = $opt;
        $this->paymentOptions[] = $CI->$objName;
    }
				
}
