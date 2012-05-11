<?php


if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * This Orders controller handles all order-related processing
 * in the back-end. 
 * 
 * @name Order
 * @author Has Taiar
 * @copyright O&C, 2012.
 */

class Orders extends Payment_Controller 
{

    protected $my_roles = array( array('name' => 'change_order_status', 'description' => 'Change Order Status'),
                                 array('name' => 'approve_order', 'description' => 'Approve an Order')
                               );

    
    public function __construct() 
    {
        parent::__construct();
    }

    /**
     * List orders in system. Defaults to showing current orders.
     * @param mixed $status_id Status level ID, or a string of either 'current' or 'all'
     * @author Jez
     */
    function index($status_id = 'current') 
	{
      
	}
    
    public function review($id = 0, $stage = 'confirm') 
    {
        $orderId    = empty($id)    ?   $this->session->userdata('order_id')    :   $id;
        $order = new Order($orderId);
        if ($order->exists()) {
            $order->order_status->where('current', 1)->limit(1)->get();
            $order->shipping_address->get();
            $order->billing_address->get();
            $order->customer->get();
            switch ($stage) {
                case 'review' : {
                        $this->_data['payment_methods'] = Payment_method::get_available_methods( new User($order->customer->id) , new Site($this->site_id)) ;
                        $this->_data['order'] = $order;
                        $this->_display('stock/orders/payment_options');
                        return;
                        break;
                    }
                case 'confirm' : {
                        $paymentMethodId = $this->session->userdata('payment_method_id');
                        $paymentMethod = new Payment_method($paymentMethodId);
                        if ($paymentMethod->exists()) {
                            $this->_data['payment_method'] = $paymentMethod;
                            $this->_data['payments'] = $order->get_payments(FALSE);
                            $this->_data['order'] = $order;
                            $this->_display('stock/orders/payment_confirm');
                            return;
                        } else {
                            $this->session->set_userdata('error', 'could not identify this payment method');
                        }
                        break;
                    }
            }
        } else {
            $this->session->set_userdata('error', 'could not identify this order');
            header('location: /stock/orders/');
            return;
        }
        header("location: /stock/orders/review/$orderId/review/");
        return;
    }

    
    
    
    /**
     * This is an example of how you can use the Payment Methods to pay any order in the system.
     * pay an existing order.
     * 
     * @param  int  $orderId
     * @param  int  $paymentMethodId
     * @return void
     * 
     * @author Has Taiar 
     */
    public function pay($orderId = 0, $paymentMethodId = 0) 
    {
        $paymentMethodId = (empty($paymentMethodId))  ? $this->input->post('paymentMethod') :  $paymentMethodId;
        $this->load->library('form_validation');
        $this->form_validation->set_rules('paymentMethod', 'Payment Method', 'required');
        
        if ($this->form_validation->run() == FALSE) 
        {
            $this->session->set_userdata('error', '<li>' . validation_errors() . '</li>');
            header("location: /stock/orders/review/$orderId/review/");
            return;
        }

        $this->session->set_userdata('payment_method_id', $paymentMethodId);
        $this->session->set_userdata('order_id', $orderId);
        
        $order = new Order($orderId);
        if ($order->exists()) 
        {
            $this->load->library('payment_lib');
            $paymentLib = $this->payment_lib->loadById($paymentMethodId);
            
            if ($paymentLib->processType == 'local') 
            {
				// some payment methos will have a local url for processing (like Credit Cards), other are not so we check before we process.
                $next = $paymentLib->processUrl;
                $this->$next();
                return;
            } 
            else 
            {
                // calling the payment processing method that will take care of the rest
                $this->pay_order($order->id, $paymentMethodId);
                return;
            }
        }

        // if we could not identify the order, move the user back to orders
        $this->session->set_userdata('error', 'sorry, could not identify the order');
        header('location: /stock/orders/');
        return;
    }

     
    /**
     * viewing the details of an order
     * 
     * @param   int $id
     * @return  void
     * @author  Has Taiar 
     */
    public function view($id = '-1') 
    {
        $order = new Order($id);
        
        if ($order->exists()) 
        {
            $statuses = new Status();
            $order->load_order(TRUE, TRUE);
            
            $this->_data['statuses'] = $statuses->get_iterated();
            $this->_data['order']    = $order;
            $this->_data['payments'] = $order->get_payments(FALSE);
            $this->_data['comments'] = $order->get_comments();

            // get required component list.
            $this->_data['required_materials'] = $order->get_required_components();
        } 
        else 
        {
            $this->session->set_userdata('error', 'Could not identify this order.');
            header('Location: /stock/orders/');
            return;
        }
        
        $this->_data['page'] = array('title' => 'Stock Manager', 'location' => 'Orders Overview');

        $this->_display('stock/orders/view');
    }

    
    /**
	 * This is an example of how you can use the PDFGenerator library to generate an invoice by id.
     * generate invoice by id
     * @param int  $id
     * @param bool $noPDF
     * @param bool $quote 
     * 
     * @return void
     * @author Has Taiar
     */
    public function invoice($id = '-1', $noPDF = FALSE, $quote = FALSE) 
    {
        $order = new Order($id);
        if ($order->exists()) {
            $order->get_invoice($this->site_id, $noPDF, $quote);
        } else {
            header('Location: /stock/orders/');
        }
    }

    
    
    /**
     * validating the existance of an order.
     * 
     * @param   int $id
     * @return  Order 
     * 
     * @author  Has Taiar
     */
    private function _validate_order($id=0)
    {
        $order = new Order($id);
        if ($order->exists()) 
        {
            return $order;
        } 
        else 
        {
            $this->session->set_userdata('error', 'Could not identify this order.');
            header('Location: /stock/orders/');
            return;
        }
    }
        
      
    
    /**
     * An Ajax enabled method for calculating shipping for the backend orders
     * 
     * @param  int $ShippingMethodId
     * @param  int $ShippingAddressId
     * @return JSON object 
     * 
     * @author Has Taiar
     */
    public function calculateShipping($ShippingMethodId = 0, $ShippingAddressId = 0) 
    {
        $lineItems = $this->input->post('items')      ?        $this->input->post('items')      :       array();
        $products = array();
        $results = array();
        
        foreach ($lineItems as $productArray) 
        {
            $products[] = array('id'=> $productArray['id'], 'quantity'=>$productArray['qty'], 'price' => 0);    
        }
        
        $order = new Order();
        $shippingDetails = $order->calculate_shipping(new Shipping_method($ShippingMethodId) , new Address($ShippingAddressId) , $products, $this->site_id);
        if (is_array($shippingDetails)   &&   !empty($shippingDetails))
        {
            $results = array('cost' => $shippingDetails['shipping_cost'], 'timeframe' => $shippingDetails['timeframe'], 'costtax' => $shippingDetails['tax_amount'], 'check' => 'success');
        }
        else 
        {
            $results = array('check'=> 'fail', 'message'=> $order->get_error_message());
        }

        echo json_encode($results);
        return;
    }
    
    /**
	*   After an order is being placed, remove items from the Session, and view it.
	*
	* @param int orderId
	* @return void
	* @author Has Taiar
	*/
    public function complete($orderId = 0) 
    {
        if (isset($_SESSION['current_items'])) 
        {
            unset($_SESSION['current_items']);
        }
        
        // clear the order_id and the payment_method_id
        $this->session->unset_userdata('order_id');
        $this->session->unset_userdata('payment_method_id');
        
        $this->session->set_userdata('message', 'Order completed successfully');
        header('location: /stock/orders/view/' . $orderId);
    }
    
    
    
    
}
