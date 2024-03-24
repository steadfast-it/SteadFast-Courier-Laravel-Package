<p align="center">
  <img src="https://raw.githubusercontent.com/steadfast-it/SteadFast-Courier-Laravel-Package/main/logo.png" style="width:30% !important;">
</p>

<h1 align="center">A complate Laravel package for SteadFast Courier Limited</h1>


<p align="center" >
<img src="https://img.shields.io/packagist/v/steadfast-it/SteadFast-Courier-Laravel-Package.svg?style=flat-square">
<img src="https://img.shields.io/packagist/dt/steadfast-it/SteadFast-Courier-Laravel-Package.svg?style=flat-square">
</p>

This is a Laravel/PHP package for [Steadfast](https://www.steadfast.com.bd/)  Courier System. This package can be used in laravel projects. You can use this package for headless/rest implementation as well as blade or regular mode development. We created this package while working for a project and thought to made it release for all so that it helps. This package is available as regular php [composer package](https://packagist.org/packages/steadfast-it/SteadFast-Courier-Laravel-Package).



## Features

1. [Placing an order](https://github.com/steadfast-it/SteadFast-Courier-Laravel-Package#2-placing-an-order)
2. [Bulk Order Create](https://github.com/steadfast-it/SteadFast-Courier-Laravel-Package#3-bulk-order-create)
3. [Checking Delivery Status](https://github.com/steadfast-it/SteadFast-Courier-Laravel-Package#4-checking-deliverystatus)
4. [Checking Current Balance](https://github.com/steadfast-it/SteadFast-Courier-Laravel-Package#5-checking-current-balance)

 
## Installation

You can install the package via composer:

```bash
composer require steadfast-courier/steadfast-courier-laravel-package
```


You can publish the config file with:

```bash
php artisan vendor:publish --tag="steadfast-courier-config"
```

After publish config file setup your credential. you can see this in your config directory steadfast-courier.php file

```
 "base_url" => env('STEADFAST_BASE_URL', 'https://portal.steadfast.com.bd/api/v1'),
 "api_key" => env('STEADFAST_API_KEY', 'your-api-key'),
 "secret_key" => env('STEADFAST_SECRET_KEY', 'your-secret-key'),
```

### Set .env configuration

```
STEADFAST_BASE_URL= "https://portal.steadfast.com.bd/api/v1"
STEADFAST_API_KEY = "your-api-key"
STEADFAST_SECRET_KEY ="your-secret-key"
```



### 1. Placing an order

use SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;

$orderData = 
    [

        'invoice' => '123456',

        'recipient_name' => 'John Doe',

        'recipient_phone' => '01234567890',

        'recipient_address' => 'Fla# A1,House# 17/1, Road# 3/A, Dhanmondi,Dhaka-1209',

        'cod_amount' => 1000,

        'note' => 'Handle with care'

    ];

$response = SteadfastCourier::placeOrder($orderData);


Response:

{

    "status": 200,

    "message": "Consignment has been created successfully.",

    "consignment": {

        "consignment_id": 1424107,

        "invoice": "Aa12-das4",

        "tracking_code": "15BAEB8A",

        "recipient_name": "John Doe",

        "recipient_phone": "01234567890",

        "recipient_address": "Fla# A1,House# 17/1, Road# 3/A, Dhanmondi,Dhaka-1209",

        "cod_amount": 1000,

        "status": "in_review",

        "note": "Deliver within 3PM",

        "created_at": "2021-03-21T07:05:31.000000Z",

        "updated_at": "2021-03-21T07:05:31.000000Z"

    }

}


### 2. Bulk Order Create

use  SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;


$orderData =

    [

        [

            'invoice' => '123456',

            'recipient_name' => 'John Doe',

            'recipient_phone' => '01234567890',

            'recipient_address' => '123 Main St',

            'cod_amount' => 1000,

            'note' => 'Handle with care'

        ],

        [

            'invoice' => '789012',

            'recipient_name' => 'Jane Smith',

            'recipient_phone' => '09876543210',

            'recipient_address' => '456 Elm St',

            'cod_amount' => 1500,

            'note' => 'Fragile'

        ]

    ];

$response = SteadfastCourier::bulkCreateOrders($ordersData);

Response:

    [

        {

            "invoice": "230822-1",

            "recipient_name": "John Doe",

            "recipient_address": "House 44, Road 2/A, Dhanmondi, Dhaka 1209",

            "recipient_phone": "0171111111",

            "cod_amount": "0.00",

            "note": null,

            "consignment_id": 11543968,

            "tracking_code": "B025A038",

            "status": "success"

        },

        {

            "invoice": "230822-1",

            "recipient_name": "John Doe",

            "recipient_address": "House 44, Road 2/A, Dhanmondi, Dhaka 1209",

            "recipient_phone": "0171111111",

            "cod_amount": "0.00",

            "note": null,

            "consignment_id": 11543969,

            "tracking_code": "B025A1DC",

            "status": "success"

        }
    ]

## If there is any error in data your will get response like 

Response:

    [

        {

        "invoice": "230822-1",

        "recipient_name": "John Doe",

        "recipient_address": "House 44, Road 2/A, Dhanmondi, Dhaka 1209",

        "recipient_phone": "0171111111",

        "cod_amount": "0.00",

        "note": null,

        "consignment_id": null,

        "tracking_code": null,

        "status": "error"

        },

    ]

### 3. Checking Delivery Status


use  SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;

$consignmentId = 123456;

$invoice = "230822-1";

$trackingCode = "B025A3FA";

$response1 = SteadfastCourier::checkDeliveryStatusByConsignmentId($consignmentId);

$response2 = SteadfastCourier::checkDeliveryStatusByTrackingCode($trackingCode);

$response3 = SteadfastCourier::checkDeliveryStatusByInvoiceId($invoice);

 
Response:
{

    "status": 200,

    "delivery_status": "in_review"

}

## Delivery Statuses

Here are the possible delivery statuses returned by the Steadfast Courier API along with their descriptions:

- **pending**: Consignment is not delivered or cancelled yet.
- **delivered_approval_pending**: Consignment is delivered but waiting for admin approval.
- **partial_delivered_approval_pending**: Consignment is delivered partially and waiting for admin approval.
- **cancelled_approval_pending**: Consignment is cancelled and waiting for admin approval.
- **unknown_approval_pending**: Unknown Pending status. Need contact with the support team.
- **delivered**: Consignment is delivered and balance added.
- **partial_delivered**: Consignment is partially delivered and balance added.
- **cancelled**: Consignment is cancelled and balance updated.
- **hold**: Consignment is held.
- **in_review**: Order is placed and waiting to be reviewed.
- **unknown**: Unknown status. Need contact with the support team.

You can use these statuses to track the progress of your consignments and take appropriate actions.



### 4. Checking Current Balance

use  SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;

$response = SteadfastCourier::getCurrentBalance();

Response:

 {

    "status": 200,

    "current_balance": 0

}


Support
For any issues or questions related to this package, please open an issue on GitHub.

## Credits

- [AmadulHaque](https://github.com/AmadulHaque)
- [Badrul Sajib](https://github.com/badrul-sajib)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
