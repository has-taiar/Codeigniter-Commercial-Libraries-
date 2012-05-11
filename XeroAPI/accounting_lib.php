<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * Description of accounting_lib
 * This Class is part of SS&B Class Libraries 
 * 
 * It works as an abstraction layer between my Codeigniter app and the chosen accounting package.
 * This is useful to allow our Codeigniter app to use different Accounting package based on our configuration
 * in the accounting_pkg.php config file. 
 * 
 * @author Has Taiar
 */



/**
 * This determines the contract of operations between SSB app
 * and the acc_lib for the chosen accounting package
 * 
 * all acc_libs MUST implement this interface
 * 
 * @author Has Taiar
 * 
 */

interface  iAcc_lib 
{
    
    public function add_order($order = array());
    
    public function update_order($order = array());
    
    public function add_customer($customer = array());
    
    public function update_customer($customer = array());
    
    public function pay_order($payment = array());
    
    public function pay_stockorder($payment = array());
    
    public function add_supplier($supplier = array());
    
    public function update_supplier($supplier = array());
    
    public function add_stockorder($order = array());
    
    public function update_stockorder($order = array());
    
    public function add_product($product = array());
    
    public function update_product($product = array());
    
    public function get_erMessage();
    
    public function set_erMessage($error = '');
    
    
}




class accounting_lib {
    
    protected     $acc_package_enable       =   '';
    protected     $acc_package_lib_name     =   '';
    protected     $acc_package_config       =   '';
    protected     $acc_package_lib          =   null;
    public        $erMessage                =   '';
    
    /**
     * constructor of accounting lib
     * it loads the lib and its config file 
     * for the chosen accounting_package
     * 
     * @author Has Taiar
     */
    
    function __construct() 
    {
        // must check first for the accounting_pkg config file if it is exists
        $this->CI = & get_instance();
        if (file_exists('config/accounting_pkg.php')) 
        {
            $this->CI->config->load('config/accounting_pkg');
            $this->acc_package_enable         =   $this->CI->config->item('acc_package_enable');
            $this->acc_package_lib_name       =   $this->CI->config->item('acc_package_lib_name');
            $this->acc_package_config         =   $this->CI->config->item('acc_package_config');
             
            // loading the lib and cofig file
            if ($this->acc_package_enable       &&      file_exists(APPPATH . "libraries/acc_libs/{$this->acc_package_lib_name}.php")  ) 
            {
                $this->CI->load->library("acc_libs/{$this->acc_package_lib_name}");
                // getting a copy of the library to use it later
                $this->acc_package_lib = $this->CI->{$this->acc_package_lib_name};
            }
            
            // loading the lib and cofig file
            if ($this->acc_package_enable       &&      file_exists("config/{$this->acc_package_config}.php")  ) 
            {
                $this->CI->config->load($this->acc_package_config);
            }
            
        }
        else 
        {
            // Logging an error for not finding the configuratin 
            $this->set_erMessage("config/accounting_pkg.php Configuration file was not found and so no account integration package implemented.");
            log_message('error', "config/accounting_pkg.php Configuration file was not found and so no account integration package implemented."); 
        }
        
    }
    
    
    /**
     * This method is the broker between all classes, models, controllers and the chosen-accounting-package
     * It would look for the desired method and pass the args to it if it exists, otherwise, will fail and 
     * shows a meaningful message 
     * 
     * @param type $method
     * @param type $args 
     * @author Has Taiar
     * 
     */
    public function __call($method, $args )
    {
        // checking first if the method exists here
        if (method_exists($this, $method ))
        {
            return $this->$method($args);
        }
        elseif ($this->acc_package_enable   &&      !is_null($this->acc_package_lib))
        {
            if (method_exists($this->acc_package_lib , $method))
            {
                $ret = $this->acc_package_lib->$method($args);
                $this->set_erMessage($this->acc_package_lib->get_erMessage());
                return $ret;
            }
            else 
            {
                $this->set_erMessage("method $method does not exist in {$this->acc_package_lib_name}. Please check the library again..");
                log_message("error" , "method $method does not exist in {$this->acc_package_lib_name}. Please check the library again..");
                return false;
            }
        }
        elseif ($this->acc_package_enable   &&     is_null($this->acc_package_lib))
        {
            // if the acc_package_enable flag is true, but the library does not exist, then throw an error
            $this->set_erMessage("{$this->acc_package_lib_name} library does NOT exist. Please check the library again..");
            log_message("error" , "Oops! the {$this->acc_package_lib_name} library does NOT exist. Please check the library again..");
            return false;
        }
        else 
        {
            $this->set_erMessage("No Accounting Package integration was configured.");
            return false;
        }
    }
    
    
    /**
     * setting the error message
     * @return void 
     * @author Has Taiar
     */
    public function set_erMessage($er = '') 
    {
        $this->erMessage = $er;
    }
    
    /**
     * getting the error message
     * @return string 
     * @author Has Taiar
     */
    public function get_erMessage()
    {
        return $this->erMessage;
    }
    
    
}



