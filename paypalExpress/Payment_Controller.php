<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Payment_Controller to process payment across all different modules and controllers
 *
 * This class holds all the payment related Control methods
 * to facilitate accessing the payment_libries from anywhere in the system
 * 
 * @name Payment_Controller
 * @copyright O&C, 2012.
 * @author Has Taiar
 */

class Payment_Controller extends MY_Controller
{
    public      $site_id = 1;

    //method names for successful payment and failuer
    const SUCCESS = 'complete';
    const REVIEW    = 'review';
    const CC_VALIDATION_OFF = true;
    
    function __construct()
    {
        parent::__construct();
        $this->load->library('payment_lib');
        // set site_id if set in session
        if (isset($_SESSION['site_id'])) {
            $this->site_id = $_SESSION['site_id'];
        }

    }


    public final function pay_order($orderId = 0, $payment_method_id = 0  ) 
    {
        // checking if we did not get any order_id || payment_method_id, then they are in the session (as in the case of CC)
        $orderId    = empty($orderId)             ?   $this->session->userdata('order_id')            :   $orderId;
        $payMethodId = empty($payment_method_id)    ?   $this->session->userdata('payment_method_id')   :   $payment_method_id;
        
        $this->session->set_userdata('payment_method_id', $payMethodId);
        $this->session->set_userdata('order_id', $orderId);
        
        $order = new Order($orderId);
        $method = new Payment_method($payMethodId);
        $paymentLib = null;
        $next = self::REVIEW; // next step
        if ($method->exists())
        {
            $paymentLib = $this->payment_lib->loadById($method->id);
        }

        // if the order does not exist, or could not load library >> cancel payment process
        if (!$order->exists()  ||  !is_object($paymentLib)) {
            $this->session->set_userdata('error', 'Error: could not identify order/paymentLib');
            $next = self::REVIEW;
            $this->$next($order->id);
            return;
        }
        // process the payment then
        $this->_doSale($order, $paymentLib);
        return;
    }


    private function  _doSale(Order $order, $paymentLib) {
        $next = self::REVIEW;
        // This calls one of the many Payment_methods that we have based on the payment methods we are passing.
		$result = $paymentLib->doSale($order);
        switch($result['result'])
        {
            case 'success' :
            {
                $order->setStatus(STATUS_PAYMENT_COMPLETE, "Payment successful via {$paymentLib->displayName}");
                $this->session->set_userdata('message', "<li>Payment was successful via {$paymentLib->displayName}</li> <li>Message: {$result['message']}</li>");
                $next = self::SUCCESS;
                break;
            }
            case 'invoice_required' :
            {
                $order->setStatus(STATUS_REQUIRES_INVOICE, "Invoice required to be sent to buyer: {$paymentLib->displayName}");
                $this->session->set_userdata('message', "<li>Invoice required to be sent to buyer: {$paymentLib->displayName}</li>");
                $next = self::SUCCESS;
                break;
            }
            case 'payment_required' :
            {
                $order->setStatus(STATUS_AWAITING_PAYMENT, "Awaiting payment: {$paymentLib->displayName}");
                $this->session->set_userdata('message', 'Awaiting payment : '.$paymentLib->displayName);
                break;
            }
            default:
            {
                $this->session->set_userdata('error', '<li>There was an error processing the transaction. Message: '.$result['message'].'. You can try placing the order again.</li>');
            }
       }
       // after finishing the processing >> go to the next step
       $this->$next($order->id);
       return;
    }



    /**************************************************
     * #############################################
     * These two methods are for the paypalExpress library
     * #############################################
     **************************************************
     */

    
    
    /**
     * processing the order after the user finishes the payment on paypal and comes back to our site
     * 
     * based on the response (SUCCESS or FAILED) the user will get redirected based on the results.
     * 
     * @param  String $option
     * @return void 
     * 
     * @author Has Taiar
     */
    public  function success($option ='') 
    {
        // getting the token and other infos
        $qry =  ($_SERVER['REQUEST_URI']);
        parse_str( substr($qry, strpos($qry, '?')+1 ));

        //loading the library to call the rest of the methods to confirmt the payment
        $this->load->library('payment_lib');
        $paymentLib = $this->payment_lib->loadById($this->session->userdata('payment_method_id'));

        if (isset($token)  && $token != "")
        {
            //getting the ShippingDetails in case the user has changed the info on Paypal payment screen
            $resArray = $paymentLib->GetShippingDetails( $token );
            $ack = strtoupper($resArray["ACK"]);
            if( $ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING")
            {
                $paymentLib->checkAddressAfterPayment($resArray);
                
                // This method must be implemented in all Payment library and should be called after finalising the payment
                $order = $paymentLib->completeOrder($resArray['INVNUM'] );
                if (is_object($order)   &&    $order->exists())
                {
                    $next = self::SUCCESS;
                    $this->$next($order->id);
                    return;
                }
                else
                {
                    $this->session->set_userdata('error', 'Sorry, there was an error in completing the payment of your order.<br />Please try placing the order again.');
                    $next = self::REVIEW;
                    $this->$next();
                    return;
                }
            }
            else
            {
                $this->session->set_userdata('error', '<li>There was an error processing the transaction. Message: '.urldecode($resArray["L_SHORTMESSAGE0"]).'.<br />Please try placing the order again.</li>');
                $next = self::REVIEW;
                $this->$next();
                return;
            }
        }
    }

    
    
    
    
    /**
     * cancelling the payment after the user is sent to paypal checkout.
     * 
     * @param  String $option
     * @return void
     * 
     * @author Has Taiar 
     */
    
    // to do: update this method to reflect the changes on order-payment relationship.
    
    public  function cancel($option = '') 
    {
        // if the payment failed then redirect the user back to the Order-Review page with an error message
        $this->session->set_userdata('error', 'Sorry, there was an error in processing your payment option <br />Please try again');
        $qry =  ($_SERVER['REQUEST_URI']);
        parse_str( substr($qry, strpos($qry, '?')+1 ));
        
        // update the PaymentDetails status
        $payment = new PaymentDetail();
        $payment->update_status_by_token($token, PAYMENT_DETAILS_FAIL , 'The Paypal payment returned fail.', $qry);
        $next = self::REVIEW;
        
        $payment->exists()  ?    $this->$next($payment->order_id)   :   $this->$next();
        return;
    }


    /**************************************************
     * #############################################
     * These two methods are for the paypalgateway library
     * #############################################
     **************************************************
     */
    // passing the order id would be useful for the Stock Module but not for the shop
    public function getcc()
    {
        $this->_data['order_id'] = $this->session->userdata('order_id');
        $this->_data['payment_method_id']= $this->session->userdata('payment_method_id');
        //checking where to load the view from
        $this->module  = (empty($this->module)) 	?   $this->router->fetch_module()	:    $this->module;
        !empty($this->module)  ?   $view = "{$this->module}/checkout_creditcard"  :   $view = 'checkout_creditcard';
        $this->_display($view);
    }

    public function storecc()
    {
        // getting the paymentMethodID from the session (ShoppingCart Order) or from the post Method (Backend Order).
        $paymentMethodId = $this->session->userdata('payment_method_id')    ?     $this->session->userdata('payment_method_id')      :     $this->input->post('payment_method_id');
        $orderId         = $this->session->userdata('order_id')             ?     $this->session->userdata('order_id')               :     $this->input->post('order_id');
        $this->load->library('payment_lib');
        $paymentLib = $this->payment_lib->loadById($paymentMethodId);
        $local_method = $paymentLib->processUrl;
        
        // doing some form validation before processing further
        $this->load->library('form_validation');
        $this->form_validation->set_rules('cornchips', 'Credit Card Number', 'required');
        $this->form_validation->set_rules('ccname', 'Name on Credit Card', 'required');
        $this->form_validation->set_rules('cctype', 'Type', 'required');
        $this->form_validation->set_rules('expmonth', 'Expiry Month', 'required');
        $this->form_validation->set_rules('expyear', 'Expiry Year', 'required');
        $this->form_validation->set_rules('cvv2', 'Security code', 'required');

        if ($this->form_validation->run() == FALSE) {
            $this->session->set_userdata('error', '<li>' . validation_errors() .'</li>');
            $this->$local_method($orderId);
            return;
        }

        if($paymentLib->setCreditCard($this->input->post('cornchips'), $this->input->post('expmonth'), $this->input->post('expyear'), $this->input->post('ccname'), $this->input->post('cctype'), $this->input->post('cvv2') ))
        {
            $return = $paymentLib->validateCreditCard();
            if(self::CC_VALIDATION_OFF  ||  ($return['result'] !== FALSE) )
            {
                // after verifying the credit card details >> CC Details are stored in memory the creditcard object >> move to payment
                $next = self::REVIEW;
                $this->session->set_userdata('message', '<li>Credit Card verification was successful, please just place the order now</li>');
                
                $this->$next($orderId);
                return;
            }
            else
            {
                $this->session->set_userdata('error', "<li>Credit Card Validation Error: {$return['error']}</li>");
            }
        }
        else
        {
            $this->session->set_userdata('error', '<li>There was an issue with your credit card details. Please re-enter them and try again.</li>');
        }
        
        $this->$local_method($orderId);
    }

		
}
