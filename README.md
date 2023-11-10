# Laravel PayPal

- [Introduction](#introduction)
- [Installation](#installation)
- [Setup](#setup)
- [Configuration](#configuration)
- [Express Checkout](#express-checkout)
- [Override PayPal API Configuration](#override-api-configuration)
- [Set Currency](#set-currency)
- [Refund Transaction](#refund-transaction)
- [Recurring Payments Profile](#recurring-payment-profile)
- [Adaptive Payments](#adaptive-payments)
- [Handling PayPal IPN](#paypalipn)
- [Creating Subscriptions](#create-subscriptions)

<a name="introduction"></a>
## Introduction

By using this plugin you can process or refund payments and handle IPN (Instant Payment Notification) from PayPal in your Laravel application.

**Currently only PayPal Express Checkout API Is Supported.**

<a name="installation"></a>
## Installation

* Use following command to install:

  ```bash
  composer require proxylyx/paypal
  ```
<a name="setup"></a>
## Setup

> If you are using Laravel 5.5+, you do not need to register the service provider or the alias for the facade. Laravel's [Package Discovery](https://laravel.com/docs/5.5/packages#package-discovery) will handle this for you.

* Add the service provider to your `$providers` array in `config/app.php` file like: 

  ```php
  // Laravel 5.1 or up
  Proxylyx\PayPal\Providers\PayPalServiceProvider::class 
  ```
  ```php
  // Laravel 5 or below
  'Proxylyx\PayPal\Providers\PayPalServiceProvider'
  ```

* Add the alias to your `$aliases` array in `config/app.php` file like: 

  ```php
  // Laravel 5.1 or up
  'PayPal' => Proxylyx\PayPal\Facades\PayPal::class
  ```
  
  ```php
  // Laravel 5 or below
  'PayPal' => 'Proxylyx\PayPal\Facades\PayPal'
  ```



<a name="configuration"></a>
## Configuration
* Run the following command to publish configuration:

  ```bash
  php artisan vendor:publish --provider "Proxylyx\PayPal\Providers\PayPalServiceProvider"
  ```

* Set you PayPal credentials and environment in **.env** with following variables. For additional configuration you can have a look at **config/paypal.php**, which you should update accordingly.

  ```env
  PAYPAL_ENV=sandbox
  PAYPAL_API_USERNAME=
  PAYPAL_API_PASSWORD=
  PAYPAL_API_SECRET=
  PAYPAL_API_CERTIFICATE=
  ```

<a name="express-checkout"></a> 
## Express Checkout

* **Import Class**
  ```php
  use PayPal;
  ```
* **Create Provider**

  ```php
  protected $PayPalProvider;

  public function __construct()
  {
        $this->PayPalProvider = PayPal::setProvider('express_checkout');
  }
  ```
* **Generate PayPal Data**
  ```php
  protected function paypalData()
  {
          $order = Order::find(session('orderId'));
          $data = [];
          try {
              $orderItems = $order->items()->get();
          } catch (\Exception $e) {
              throw new \Exception("Order items empty", 1);
          }
          $data['items'] = [];
          foreach ($orderItems as $item) {
              array_push($data['items'], [
                  'name' => $item->item_name,
                  'price' => $item->price,
                  'qty' => $item->quantity
              ]);
          }
          $data['invoice_id'] = $order->id;
          $data['invoice_description'] = "Order #{$data['invoice_id']} Invoice";
          $data['return_url'] = route('paymentCallback');
          $data['cancel_url'] = route('paymentCancel');

          $total = 0;
          foreach($data['items'] as $item) {
              $total += $item['price']*$item['qty'];
          }
          $data['total'] = $total;

          return $data;
  }
  ```
* **Additional PayPal API Parameters**

    By default only a specific set of parameters are used for PayPal API calls. However, if you wish specify any other additional parameters you may call the `addOptions` method before calling any respective API methods:
  ```php
  $options = [
      'BRANDNAME' => env('APP_NAME'),
      'LOGOIMG' => url('img/logo.png'),
      'CHANNELTYPE' => 'Merchant'
  ];
  ```
* **Redirect to PayPal**
  ```php
  $data = $this->paypalData();
  $response = $this->PayPalProvider->addOptions($options)->setExpressCheckout($data);
  return redirect($response['paypal_link']);
  ```
* **Callabck and Process Payment**
  ```php
  protected function paypalCallback()
  { 
      $token = isset($_GET['token']) ? $_GET['token'] : null;
      $payerID = isset($_GET['PayerID']) ? $_GET['PayerID'] : null;

      if ($token == null || $payerID == null) {
          return collect([
              'status' => 'invalid',
              'message' => "Token or Payer ID not found"
          ]);
      }

      $data = $this->paypalData();

      $response = $this->PayPalProvider->doExpressCheckoutPayment($data, $token, $payerID);

      $ack = strtolower($response['ACK']);

      if ($ack === 'success' || $ack === 'successwithwarning') {
          return collect([
              'status' => 'success',
              'transactionId' => $response['PAYMENTINFO_0_TRANSACTIONID'],
              'message' => "Payment successfully processed."
          ]);
      } else {
          return collect([
              'status' => 'failed',
              'message' => $response['L_SHORTMESSAGE0']
          ]);
      }

  }
  ```

<a name="override-api-configuration"></a>
## Override PayPal API Configuration

You can override PayPal API configuration by calling `setApiCredentials` method:

```php
$provider->setApiCredentials($config);
```

<a name="set-currency"></a>
## Set Currency

By default the currency used is `USD`. If you wish to change it, you may call `setCurrency` method to set a different currency before calling any respective API methods:

```php
$provider->setCurrency('EUR')->setExpressCheckout($data);
```


<a name="refund-transaction"></a>
## Refund Transaction

```php
$response = $provider->refundTransaction($transactionid);
```

To issue partial refund, you must provide the amount as well for refund:

```php
$amount = 10;
$response = $provider->refundTransaction($transactionid, $amount);
```
<a name="usage-ec-createbillingagreement"></a>    
**Create Billing Agreement**

```php
// The $token is the value returned from SetExpressCheckout API call
$response = $provider->createBillingAgreement($token);
```    

<a name="recurring-payment"></a>
## Recurring Payment

* **Creating Profile**
  ```php
  // The $token is the value returned from SetExpressCheckout API call
  $startdate = Carbon::now()->toAtomString();
  $profile_desc = !empty($data['subscription_desc']) ?
  $data['subscription_desc'] : $data['invoice_description'];
  $data = [
  'PROFILESTARTDATE' => $startdate,
  'DESC' => $profile_desc,
  'BILLINGPERIOD' => 'Month', // Can be 'Day', 'Week', 'SemiMonth', 'Month', 'Year'
  'BILLINGFREQUENCY' => 1, // 
  'AMT' => 10, // Billing amount for each billing cycle
  'CURRENCYCODE' => 'USD', // Currency code 
  'TRIALBILLINGPERIOD' => 'Day',  // (Optional) Can be 'Day', 'Week', 'SemiMonth', 'Month', 'Year'
  'TRIALBILLINGFREQUENCY' => 10, // (Optional) set 12 for monthly, 52 for yearly 
  'TRIALTOTALBILLINGCYCLES' => 1, // (Optional) Change it accordingly
  'TRIALAMT' => 0, // (Optional) Change it accordingly
  ];
  $response = $provider->createRecurringPaymentsProfile($data, $token);
  ```    

<a name="usage-ec-getrecurringprofiledetails"></a>
* **GetRecurringPaymentsProfileDetails**

    ```php
    $response = $provider->getRecurringPaymentsProfileDetails($profileid);
    ```    

<a name="usage-ec-updaterecurringprofile"></a>
* **UpdateRecurringPaymentsProfile**

    ```php
    $response = $provider->updateRecurringPaymentsProfile($data, $profileid);
    ```    

<a name="usage-ec-managerecurringprofile"></a>
* **ManageRecurringPaymentsProfileStatus**

    ```php
    // Cancel recurring payment profile
    $response = $provider->cancelRecurringPaymentsProfile($profileid);
    
    // Suspend recurring payment profile
    $response = $provider->suspendRecurringPaymentsProfile($profileid);
    
    // Reactivate recurring payment profile
    $response = $provider->reactivateRecurringPaymentsProfile($profileid);    
    ```    

<a name="adaptive-payments"></a>
## Adaptive Payments

To use adaptive payments, you must set the provider to use Adaptive Payments:

```php
PayPal::setProvider('adaptive_payments');
```

<a name="usage-adaptive-pay"></a>
* **Pay**

  ```php

  // Change the values accordingly for your application
  $data = [
      'receivers'  => [
          [
              'email' => 'johndoe@example.com',
              'amount' => 10,
              'primary' => true,
          ],
          [
              'email' => 'janedoe@example.com',
              'amount' => 5,
              'primary' => false
          ]
      ],
      'payer' => 'EACHRECEIVER', // (Optional) Describes who pays PayPal fees. Allowed values are: 'SENDER', 'PRIMARYRECEIVER', 'EACHRECEIVER' (Default), 'SECONDARYONLY'
      'return_url' => url('payment/success'), 
      'cancel_url' => url('payment/cancel'),
  ];

  $response = $provider->createPayRequest($data);

  // The above API call will return the following values if successful:
  // 'responseEnvelope.ack', 'payKey', 'paymentExecStatus'

  ```

Next, you need to redirect the user to PayPal to authorize the payment

  ```php
  $redirect_url = $provider->getRedirectUrl('approved', $response['payKey']);

  return redirect($redirect_url);
  ```

<a name="paypalipn"></a>
## Handling PayPal IPN
You can also handle Instant Payment Notifications from PayPal.
Suppose you have set IPN URL to **http://example.com/ipn/notify/** in PayPal. To handle IPN you should do the following:

* First add the `ipn/notify` tp your routes file:

    ```php
    Route::post('ipn/notify','PayPalController@postNotify'); // Change it accordingly in your application
    ```
          
* Open `App\Http\Middleware\VerifyCsrfToken.php` and add your IPN route to `$excluded` routes variable.

    ```php
    'ipn/notify'
    ```
    
* Write the following code in the function where you will parse IPN response:    
    
    ```php
    /**
     * Retrieve IPN Response From PayPal
     *
     * @param \Illuminate\Http\Request $request
     */
    public function postNotify(Request $request)
    {
        // Import the namespace Proxylyx\PayPal\Services\ExpressCheckout first in your controller.
        $provider = new ExpressCheckout;
        $response = (string) $provider->parsePayPalIPN($request);
        
        if ($response === 'VERIFIED') {                      
            // Your code goes here ...
        }                            
    }        
    ```

<a name="create-subscriptions"></a>
## Create Subscriptions

* For example, you want to create a recurring subscriptions on paypal, first pass data to `SetExpressCheckout` API call in following format:

  ```php
  // Always update the code below accordingly to your own requirements.
  $data = [];

  $data['items'] = [
      [
          'name'  => "Monthly Subscription",
          'price' => 0,
          'qty'   => 1,
      ],
  ];

  $data['subscription_desc'] = "Monthly Subscription #1";
  $data['invoice_id'] = 1;
  $data['invoice_description'] = "Monthly Subscription #1";
  $data['return_url'] = url('/paypal/ec-checkout-success?mode=recurring');
  $data['cancel_url'] = url('/');

  $total = 0;
  foreach ($data['items'] as $item) {
      $total += $item['price'] * $item['qty'];
  }

  $data['total'] = $total;
  ```            

* Next perform the remaining steps listed in [`SetExpressCheckout`](#usage-ec-setexpresscheckout).
* Next perform the exact steps listed in [`GetExpressCheckoutDetails`](#usage-ec-getexpresscheckoutdetails).
* Finally do the following for [`CreateRecurringPaymentsProfile`](#usage-ec-createrecurringprofile)

  ```php
  $amount = 9.99;
  $description = "Monthly Subscription #1";
  $response = $provider->createMonthlySubscription($token, $amount, $description);

  // To create recurring yearly subscription on PayPal
  $response = $provider->createYearlySubscription($token, $amount, $description);
  ```
            