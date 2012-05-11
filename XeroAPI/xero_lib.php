<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');


//include the class file
include_once "xero.php";

/**
 * Description of xero_lib
 * This Class is part of SS&B Class Libraries 
 * This is the implementation of our Xero_library for integrating
 * our Codeigniter App to Xero.
 * This makes use of Codeigniter, XeroAPI, and DataMapper.
 *
 * @author Has Taiar
 */

class xero_lib implements iAcc_lib {
    
    protected     $xero_key       =   '';
    protected     $xero_secret    =   '';
    protected     $public_key_path=   '';
    protected     $private_key_path=  '';
    protected     $format         =   '';
    private       $_CI;
    public        $xeroAPI        = null;
    public        $erMessage      =   '';
    

    function __construct() 
    {
        // must check first for the xero-config file if it is exists
        $this->_CI = & get_instance();
        $this->_CI->config->load('config/xero_config');
        
        $this->xero_key         =   $this->_CI->config->item('xero_key');
        $this->xero_secret      =   $this->_CI->config->item('xero_secret');
        $this->public_key_path  =   $this->_CI->config->item('public_key_path');
        $this->private_key_path =   $this->_CI->config->item('private_key_path');
        $this->format           =   $this->_CI->config->item('xero_request_format');
        
        if (is_null($this->xeroAPI)) {
            $this->xeroAPI = new Xero( $this->xero_key, $this->xero_secret, $this->public_key_path, $this->private_key_path, $this->format );
        }
        
    }
    
    /**
     * get an instance of the xeroAPI to interact with it
     * 
     * Singlton implementation for the xeroAPI instances. 
     * 
     * @return  xero
     * @author  Has Taiar 
     * 
     */
    public   function    get_xeroAPI() 
    {
        if (is_null($this->xeroAPI)) {
            $this->xeroAPI = new Xero( $this->xero_key, $this->xero_secret, $this->public_key_path, $this->private_key_path, $this->format );
        }
        
        return $this->xeroAPI;
    }
    
    
    /**
     * adding a new order
     *
     * @param   Order $order
     * @return  bool 
     * @author  Has Taiar
     */
    public function add_order( $order = array() )
    
    {
        $order = $this->_check_instance($order, 'order');
        if (empty($order)) 
        {
            $this->set_erMessage(' no order provided ');
            return FALSE;
        }
        
        // getting the order in the right format for xero 
        $newOrder = $this->_get_xero_formatted_order($order);
        if (is_array($newOrder)     &&      !empty($newOrder)) 
        {
            $ret = $this->xeroAPI->Invoices( $newOrder );
            return $this->_check_returned_value( 'Invoice' , $ret);
        }
        
        $this->set_erMessage(" unknown error occurred when attempting to insert a new Order to Xero. ");
        return false;
    }
    
    public function update_order($order = array())
    {
        // add_order checks for the order and updates it if exists, so just call it from here
        return $this->add_order($order);
    }
    
    
    /**
     * add a new customer to the accounting package
     * 
     * @param   type $customer 
     * @author  Has Taiar
     * 
     */ 
    public function add_customer($customer = array())
    {
        // testing whether we passing an array (calling from outside the library) or an object (insdie call)
        $customer = $this->_check_instance($customer, 'user');
        if ($customer == FALSE) {
            return FALSE;
        }

        // getting the xero_formatted_array
        $newContact = $this->_get_xero_formatted_contact($customer);
        
        // if the customer exists in Xero, then it will be updated
        $ret = $this->xeroAPI->Contacts($newContact);
        
        return $this->_check_returned_value('Contact', $ret);
    }
    
    
    
    /**
     * checking the instance passed. 
     * This tests whether we are getting an Array, an instance of (order, cutstomer, etc), or NULL.
     * This is used to make sure we are getting the right data to work with. And the type of data input
     * might be different wether it is called from the outside using the __call() method in its parent class
     * or just internally by passing an instance of (Order, Customer, etc).
     * 
     * @param  User|array   $obj
     * @param  String       $type
     * @return User|bool
     *  
     * $author Has Taiar
     */
    private function _check_instance($obj , $type) 
    {
        $name = ucfirst($type);
        if (  is_array($obj)   &&   !empty($obj[0])   &&   ($obj[0] instanceof $name) )
        {
            return $obj[0];
        }
        elseif ($obj instanceof $name  &&   $obj->exists() ) 
        {
            return $obj;
        }
        else
        {
            $this->set_erMessage(" no {$name} provided ");
            return false;
        }
    }
    
    
    /**
     * get a customerId by his/her email 
     *
     * @param type $email
     * @return type 
     * @author Has Taiar
     */
    public function get_customerId_by_email($email = '' ) 
    {
        if (!empty($email)) 
        {
            $contact  = $this->xeroAPI->Contacts( false, false, array("EmailAddress" => $email) );
            if ( is_array($contact)     &&      isset($contact['Status'])    &&    $contact['Status']=="OK"    &&     !empty($contact["Contacts"]["Contact"])  )
            {
                return $contact["Contacts"]["Contact"]['ContactID'];
            }
        }
        $this->set_erMessage(" No Contact was found in Xero with this email ");
        return false;
    }
    
    
    
    /**
     * convert an order to a xero formatted array
     * 
     * @param  Order    $order
     * @return boolean|array 
     * @author Has Taiar
     */
    private function _get_xero_formatted_order($order = array ())
    {
        list ($contactId, $status)  =  array( '' , XERO_INVOICE_STATUS_AUTHORISED);
        
        if ( !$order->exists() ) 
        {
            $this->set_erMessage(" Order does not exists ");
            return FALSE;
        }
        
        // getting the contactId from Xero by the email 
        $contactId  = $this->get_customerId_by_email(  $order->customer_email  );
        
        if ($contactId == false)
        {
            // add the new customer (contact) first
            $user = $order->customer->get();
            $contactId = $this->add_customer($user);
        }
        
        if (!empty($contactId)) {
            $contact = array("ContactID" => $contactId);
        }
        else 
        {
            // if there is no account there (ex retail sale), just put the customer name and email
            $contact = array('Name' => "{$order->customer_name} ({$order->customer_email})" , 'EmailAddress' => $order->customer_email);
        }
        
        $ordDate = !empty($order->created)    ?   date('Y-m-d', strtotime($order->created) )    :    date('Y-m-d');
        
        // constructing the array of lineItems
        foreach($order->products->include_join_fields()->get() as $prod) 
        {
            $lineItem[] = array ("Description" => $prod->name , "Quantity" => $prod->join_quantity , "UnitAmount" => $prod->join_price , "AccountCode" => XERO_SALES_ACCOUNT_CODE );
        }
        
        // adding the shopping cost as an individual item
        $lineItem[] = array ("Description" => "Shipping Cost" , "Quantity" => 1 , "UnitAmount" => $order->shipping_cost , "AccountCode" => XERO_SALES_ACCOUNT_CODE );
        $lineItems  = array ("LineItem" => $lineItem );
        
        // switch statement for updating the status of the order
        $stat = $order->get_current_status();
        if (is_object($stat)    &&   $stat->exists())
        {
            switch ($stat->id)
            {
                case STATUS_CANCELLED : 
                {
                    $status = XERO_INVOICE_STATUS_VOIDED;
                    break;
                }
                case STATUS_QUOTE :
                {
                    $status = XERO_INVOICE_STATUS_DRAFT;
                    break;
                }
                default :
                {
                    $status = XERO_INVOICE_STATUS_AUTHORISED;
                }
            }
        }
        
        // after this, we check for the contactID >> if not, then just insert the order without a customer 
        $newInvoice = array(  array(
                                        "Type"              => XERO_INVOICE_TYPE_ORDER,
                                        "Contact"           => $contact,
                                        "Date"              => $ordDate,
                                        "DueDate"           => $ordDate,
                                        "Status"            => $status,
                                        "LineAmountTypes"   => "Inclusive",
                                        "InvoiceNumber"     => sprintf( XERO_ORDER_ID_FORMAT , $order->id) ,
                                        "Reference"         => $order->id,
                                        "LineItems"         => $lineItems,
                                        "Total"             => $order->total_cost
                                    )
                            );
        return $newInvoice;
    }
    
    
    
    /**
     * get a xero_formatted contact 
     * 
     * @param  User $user
     * @return boolean|array 
     * @author Has Taiar
     */
    private function _get_xero_formatted_contact($user = null)
    {
        if (is_null($user)    ||  !$user->exists()) 
        {
            $this->set_erMessage(" User/Customer does not exists ");
            return FALSE;
        }
        // get addresses
        $user->addresses->get();
        list($adds , $phones)  = array( array() , array() );
        
        // getting all the addresses
        foreach($user->addresses as $address) 
        {
            $adds[] = array( "AddressType" => "STREET",  "AddressLine1" => $address->address1, "AddressLine2" => $address->address2, 
                                "City" => $address->suburb_city , "Region" => $address->state_region , "PostalCode" => $address->postcode);
        }
        
        $phones     = array( array("PhoneType" => "DEFAULT", "PhoneNumber" => $user->user_meta->phone), array("PhoneType" => "MOBILE", "PhoneNumber" => $user->user_meta->mobile));
        $newContact = array(
                            array(
                                    "Name"          => "{$user->user_meta->first_name} {$user->user_meta->last_name} ({$user->email})",
                                    "FirstName"     => $user->user_meta->first_name,
                                    "LastName"      => $user->user_meta->last_name,
                                    "EmailAddress"  => $user->email, 
                                    "ContactNumber" => sprintf(XERO_CUSTOMER_ID_FORMAT, $user->id ), 
                                    "Addresses"     => array( "Address" => $adds ),                                     
                                    "Phones"        => array( "Phone"   => $phones)
                                 )
                              );
        return $newContact;
    }
    
    /**
	* @param Order|StockOrder $order (orders are for sales, StockOrders are for purchases in our Codeigniter App)
	* @param Payment $payment
	* 
	* if Payment is not specified, then all sucessful payments that are related to this order
	* will be formatted and returned. 
	* @author Has Taiar
	*/
    private function _get_xero_formatted_payment($order = null, $payment = null)
    {
        $newPay = false;
        
        if (!is_object($order)    ||  !$order->exists()) 
        {
            $this->set_erMessage(" Order does not exists ");
            return FALSE;
        }
                
        // checking order type >> purchase/sales
        switch(get_class($order))
        {
            case 'Order' :      case 'order' :  
            {
                // get the InvoiceId
                $invNo = sprintf( XERO_ORDER_ID_FORMAT, $order->id);
                $ret = $this->xeroAPI->Invoices(false, false,  array( "InvoiceNumber" => $invNo) );
                $invoiceId = $this->_check_returned_value('Invoice', $ret);
                
                if ($invoiceId == false   ||   empty($invoiceId)) {
                    $this->set_erMessage(" Could not find a Recivable Invoice in Xero with InvoiceNumber = {$invNo}");
                    return FALSE;
                }
                
                // if we want to push ONE payment, then just push it, otherwise, push all successful payments
                if (!is_null($payment)   &&   $payment->exists())
                {
                    $newPay  = array(
                                        "Invoice"   => array( "InvoiceID"   => $invoiceId   ),
                                        "Account"   => array( "Code"        => XERO_SALES_ACCOUNT_CODE  ),
                                        "Date"      => date_format(date_create($payment->created), 'Y-m-d') ,
                                        "Amount"    => $payment->amount, 
                                        "Reference" => $payment->id
                                     );
                    return $newPay;
                }
                
                $order->load_payments();
                foreach($order->payments as $pay) 
                {
                    $newPay[] = array(
                                                "Invoice"   => array( "InvoiceID"   => $invoiceId   ),
                                                "Account"   => array( "Code"        => XERO_SALES_ACCOUNT_CODE  ),
                                                "Date"      => date_format(date_create($pay->created), 'Y-m-d'),
                                                "Amount"    => $pay->amount, 
                                                "Reference" => $pay->id
                                    );
                }
                return  $newPay;
            }
            case 'Stockorder' :     case 'stockorder' :     
            {
                // get the InvoiceId
                $invNo = sprintf( XERO_STOCKORDER_ID_FORMAT , $order->id);
                $ret = $this->xeroAPI->Invoices(false, false,  array( "InvoiceNumber" => $invNo) );
                $invoiceId = $this->_check_returned_value('Invoice', $ret);
                
                if ($invoiceId == false   ||   empty($invoiceId)) {
                    $this->set_erMessage(" Could not find a Payable Invoice in Xero with InvoiceNumber = {$invNo}");
                    return FALSE;
                }
                
                $newPay = array(
                                    array(
                                            "Invoice"   => array( "InvoiceID"   => $invoiceId   ),
                                            "Account"   => array( "Code"        => XERO_PURCHASES_ACCOUNT_CODE  ),
                                            "Date"      => date('Y-m-d'),
                                            "Amount"    => $order->total_cost, 
                                            "Reference" => $order->payment_method
                                        )
                                );
                return $newPay;
            }
        }
        
        return $newPay;
    }
    
    
    
    
    /**
     * get a xero_formatted contact 
     * 
     * @param  User $user
     * @return boolean|array 
     * @author Has Taiar
     */
    private function _get_xero_formatted_supplier($supplier = null)
    {
        if (!is_object($supplier)    ||  !$supplier->exists()) 
        {
            $this->set_erMessage(" Supplier does not exists ");
            return FALSE;
        }
        // get addresses
        $supplier->addresses->get();
        $adds = array();
        
        // getting all the addresses
        foreach($supplier->addresses as $address) 
        {
            $adds[] = array( "AddressType" => "STREET",  "AddressLine1" => $address->address1, "AddressLine2" => $address->address2, 
                                "City" => $address->suburb_city , "Region" => $address->state_region , "PostalCode" => $address->postcode);
        }
        
        $newContact = array(
                            array(
                                    "Name"          => "{$supplier->name} ({$supplier->email})",
                                    "FirstName"     => $supplier->name,
                                    "EmailAddress"  => $supplier->email, 
                                    "ContactNumber" => sprintf(XERO_SUPPLIER_ID_FORMAT, $supplier->id), 
                                    "Addresses"     => array( "Address" => $adds )
                                 )
                              );
        return $newContact;
    }
    
    
    /**
     *
     * setting the error message
     * 
     * @param string $error 
     * @author Has Taiar
     */
    public function set_erMessage($error = '')
    {
        $this->erMessage = $error;
    }
    
    
    /**
     * get error message
     * 
     * @return string error
     * @author Has Taiar 
     */
    public  function    get_erMessage()
    {
        return $this->erMessage;
    }
    
    
    
    private function _check_returned_value($name, $returned = array())
    {
        $class   = ucfirst($name);
        if ( is_array($returned)     &&      isset($returned['Status'])    &&    $returned['Status']=="OK"    &&     !empty($returned["{$class}s"][$class])  )
        {
            return $returned["{$class}s"][$class]["{$class}ID"];
        }
        return false;
    }
    
    
    /**
     * update Customer 
     * 
     * @param   User|Array  $customer
     * @return  String|bool
     * @author  Has Taiar 
     */
    public function update_customer($customer = array())
    {
        // add customer function will update the contact if it does exists
        return $this->add_customer($customer);
    }
    
    
    
    
    public function pay_order($payment = array() )
    {
        return $this->add_payment($payment, 'order');
    }
    
    
    public function pay_stockorder($payment = array() )
    {
        return $this->add_payment($payment, 'Stockorder');
    }
    
    
    /**
     * Add Payment/Payments to order or a stock orders in Xero
     * This method checks the type of the order based on orderType
     * and also checks if Payment is passed, the it will only add one payment.
     * Otherwise, it will push all of the successful payment. 
     * 
     * @param  Order/Array/StockOrder   $order
     * @param  String                   $orderType
     * @param  Payment                  $payment
     * @return boolean 
     * 
     * @author  Has Taiar
     */
    public function add_payment($order = array(), $orderType = 'order', $payment = null)
    {
        // testing whether we passing an array (calling from outside the library) or an object (insdie call)
        $order = $this->_check_instance($order, $orderType);
        if ($order == FALSE) {
            return FALSE;
        }

        // getting the xero_formatted_array
        $payment = $this->_get_xero_formatted_payment($order, $payment);
        
        $ret = $this->xeroAPI->Payments( $payment ); 
        // in Xero Suppliers r contacts 
        return $this->_check_returned_value('Payment', $ret);
    }
    
    public function update_payment($payment = array())
    {
        throw  new Exception(" Sorry you can not update a payment once it is placed.");
    }
    
    public function add_supplier($supplier = array())
    {
        // testing whether we passing an array (calling from outside the library) or an object (insdie call)
        $supplier = $this->_check_instance($supplier, 'supplier');
        if ($supplier == FALSE) {
            return FALSE;
        }

        // getting the xero_formatted_array
        $supplier = $this->_get_xero_formatted_supplier($supplier);
        
        // if the customer exists in Xero, then it will be updated  >> 
        $ret = $this->xeroAPI->Contacts($supplier);
        // in Xero Suppliers r contacts 
        return $this->_check_returned_value('Contact', $ret);
        
    }
    
    public function update_supplier($supplier = array())
    {
        return $this->add_supplier($supplier);
    }
    
    
    
    
    
    
    public function add_stockorder($order = array())
    {
        $order = $this->_check_instance($order, 'Stockorder');
        if (empty($order)) 
        {
            $this->set_erMessage(' no Stock order provided ');
            return FALSE;
        }
        
        // getting the order in the right format for xero 
        $newOrder = $this->_get_xero_formatted_stockorder($order);
        if (is_array($newOrder)     &&      !empty($newOrder)) 
        {
            $ret = $this->xeroAPI->Invoices( $newOrder );
            return $this->_check_returned_value( 'Invoice' , $ret);
        }
        
        $this->set_erMessage(" unknown error occured when attempting to insert a new Stockorder to Xero. ");
        return false;
    }
    
    
    
    
    public function update_stockorder($order = array())
    {
        return $this->add_stockorder($order);
    }
    
    
    
    
    
    /**
     * convert a Stockorder to a xero formatted array
     * 
     * @param  Order    $order
     * @return boolean|array 
     * @author Has Taiar
     */
    private function _get_xero_formatted_stockorder($order = array ())
    {
        list ($contactId, $status)  =  array( '' , XERO_INVOICE_STATUS_AUTHORISED);
        
        if ( !$order->exists() ) 
        {
            $this->set_erMessage(" Stockorder does not exists ");
            return FALSE;
        }
        
        // getting the contactId from Xero 
        $contactId  = $this->xeroAPI->Contacts(false, false, array('ContactNumber' => sprintf( XERO_SUPPLIER_ID_FORMAT , $order->id)));
        $contactId  = $this->_check_returned_value("Contact", $contactId);
        
        if ($contactId == false)
        {
            $supp = $order->supplier->get();
            $contactId = $this->add_supplier($supp);
            if (empty($contactId)) 
            {
                $this->set_erMessage("Stock Order could not be inserted to xero, could not find/insert the Supplier.");
                return false;
            }
        }
        
        $ordDate = !empty($order->created)    ?   date('Y-m-d', strtotime($order->created) )    :    date('Y-m-d');
        
        // constructing the array of lineItems
        foreach($order->items->include_join_fields()->get() as $item) 
        {
            $lineItem[] = array ("Description" => $item->name , "Quantity" => $item->join_quantity , "UnitAmount" => $item->join_unit_cost , "AccountCode" => XERO_PURCHASES_ACCOUNT_CODE );
        }
        
        $lineItems  = array ("LineItem" => $lineItem );
        
        // after this, we check for the contactID >> if not, then just insert the order without a customer 
        $newInvoice = array(  array(
                                        "Type"              => XERO_INVOICE_TYPE_STOCK_ORDER,
                                        "Contact"           => array("ContactID" =>  $contactId),
                                        "Date"              => $ordDate,
                                        "DueDate"           => $ordDate,
                                        "Status"            => $status,
                                        "LineAmountTypes"   => "Inclusive",
                                        "InvoiceNumber"     => sprintf( XERO_STOCKORDER_ID_FORMAT , $order->id) ,
                                        "Reference"         => $order->id,
                                        "LineItems"         => $lineItems,
                                        "Total"             => $order->total_cost
                                    )
                            );
        return $newInvoice;
    }
    
    
    
    
    public function add_product($product = array())
    {
        throw Exception(" Unimplemented method, It is only accounting package, Stock inventory tasks are handled at SSB..");
    }
    
    
    public function update_product($product = array())
    {
        throw Exception(" Unimplemented method, It is only accounting package, Stock inventory tasks are handled at SSB..");
    }
    
    
    
}


