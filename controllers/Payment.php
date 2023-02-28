<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payment extends CI_Controller {
    
    function __construct() {
		parent::__construct();
	}
	
	public function index(){
		$this->load->view('payment/pay');
	}
	public function stripe(){
		
		$price =  2;

		$stripe = new \Stripe\StripeClient($this->config->item('stripe_api_key')
		);

		/*Create stripe payment Intents*/

		$paymentIntents = $stripe->paymentIntents->create([
			'amount' => $price * 100,
			'currency' => 'USD',
			'description' => 'Test Order',
			'shipping' => [
			'name' => 'John Doe',
				'address' => [
					'line1' => 'John Doe',
					'postal_code' => '98140',
					'city' => 'San Francisco',
					'state' => 'CA',
					'country' => 'US'
				],
			],
			['metadata' => ['order_id' =>'gul_123456']],
			'payment_method_types' => ['card']
		]);
		/*Create stripe payment customer*/
		$customer = $stripe->customers->create(
			[
			  'name' => 'John Doe',
			  'address' => [
				'line1' => 'John Doe',
				'postal_code' => '98140',
				'city' => 'San Francisco',
				'state' => 'CA',
				'country' => 'US'
				]
			]
		);

		/*Create stripe payment session*/
		$checkout_session = $stripe->checkout->sessions->create([
			'customer' => $customer->id,
			'line_items' => [
				[
				  'price_data' => [
					'currency' => 'usd',
					'product_data' => ['name' => 'Test Order',],
					'unit_amount' => $price * 100,
				  ],
				  'quantity' => 1,
				],
			],
			'mode' => 'payment',
			'success_url' => base_url('success'),
			'cancel_url' => base_url('cancel'),
		]);
		$this->session->set_userdata('checkout_intents_id',$paymentIntents->id);
		header("HTTP/1.1 303 See Other");
		header("Location: " . $checkout_session->url);
		exit();
	}
	public function cancelPayment(){
		$checkout_intents_id = $this->session->userdata('checkout_intents_id');
		$stripe = new \Stripe\StripeClient(
			$this->config->item('stripe_api_key')
		  );
		
		$cancelData = $stripe->paymentIntents->retrieve(
			$checkout_intents_id,
			[]
		);
		print_r($cancelData);

	}
	public function successPayment(){
		$checkout_intents_id = $this->session->userdata('checkout_intents_id');

		if(empty($checkout_intents_id)){
			redirect(base_url());
		}
		$stripe = new \Stripe\StripeClient(
			$this->config->item('stripe_api_key')
		  );
		
		$successData = $stripe->paymentIntents->retrieve(
			$checkout_intents_id,
			[]
		);
		$order_id = $successData->metadata->order_id;
		//update order and save intendsid for check success message later 
		/*
			Your code here to update orders sections
		
		*/

		if($successData->status =='requires_payment_method'){
			$confirmData =  $stripe->paymentIntents->confirm(
				$checkout_intents_id,
				['payment_method' => 'pm_card_visa',
				"return_url"=>base_url('success')]
			);  
			header("HTTP/1.1 303 See Other");
			header("Location: " . $confirmData->next_action->use_stripe_sdk->stripe_js);
			die();
		}else if($successData->status =='requires_action'){
			header("HTTP/1.1 303 See Other");
			header("Location: " . $successData->next_action->redirect_to_url->url);
			die();
		}else if($successData->status =='succeeded'){
			//update order and save intendsid for check success message later 
			/*
				Your code here to update orders sections
			
			*/
			$this->session->unset_userdata('checkout_intents_id');

			$this->load->view('payment/success');
		}else{
			redirect(base_url());
		}
	}
}
