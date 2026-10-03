# Steadfast Courier Laravel Package

Laravel/PHP client for the Steadfast Courier API documented in the Merchant Dashboard.

[![Tests](https://github.com/steadfast-it/SteadFast-Courier-Laravel-Package/actions/workflows/tests.yml/badge.svg)](https://github.com/steadfast-it/SteadFast-Courier-Laravel-Package/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/steadfast-courier/steadfast-courier-laravel-package.svg)](https://packagist.org/packages/steadfast-courier/steadfast-courier-laravel-package)
[![Total Downloads](https://img.shields.io/packagist/dt/steadfast-courier/steadfast-courier-laravel-package.svg)](https://packagist.org/packages/steadfast-courier/steadfast-courier-laravel-package)

## Requirements

- PHP 7.2 or newer
- Laravel 6 through 12 (via the supported `illuminate/contracts` range)
- A Steadfast API key and secret key

PHP 8.3 is used by the repository CI and is the recommended version for local package development.

## Installation

```bash
composer require steadfast-courier/steadfast-courier-laravel-package
php artisan vendor:publish --tag="steadfast-courier-config"
```

Configure the credentials issued from the Steadfast API page:

```env
STEADFAST_BASE_URL=https://portal.packzy.com/api/v1
STEADFAST_API_KEY=your-api-key
STEADFAST_SECRET_KEY=your-secret-key
STEADFAST_TOKEN=your-webhook-secret
```

The API client sends `Api-Key`, `Secret-Key`, and `Content-Type: application/json` on authenticated requests. The `/ping` endpoint is intentionally sent without API credentials.

## Creating orders

```php
use SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;

$response = SteadfastCourier::placeOrder([
    'invoice' => 'ORD-10231',
    'recipient_name' => 'Jahid Hasan',
    'recipient_phone' => '01712345678',
    'recipient_address' => 'House 17/1, Road 3/A, Dhanmondi, Dhaka',
    'cod_amount' => 1060,
    'note' => 'Call before 3 PM',
]);
```

The API response is returned unchanged, including `consignment_id`, `tracking_code`, `tracking_link`, recipient fields, `total_lot`, status, and timestamps.

### Bulk orders

Both bulk methods send the guide-compatible request shape:

```php
$orders = [
    [
        'invoice' => 'ORD-10231',
        'recipient_name' => 'Jahid Hasan',
        'recipient_phone' => '01712345678',
        'recipient_address' => 'Dhanmondi, Dhaka',
        'cod_amount' => 1060,
    ],
];

$response = SteadfastCourier::bulkCreateOrders($orders);
$extendedResponse = SteadfastCourier::bulkCreateOrdersExtended($orders);
```

The extended endpoint is recommended for new integrations because its validation errors are human-readable:

```json
{
    "status": 200,
    "message": "Processed bulk order entries.",
    "data": [
        {
            "invoice": "ORD-10231",
            "status": "error",
            "error": ["The recipient phone format is invalid."],
            "consignment_id": null,
            "tracking_code": null
        }
    ]
}
```

`bulkCreateOrdersExtended` accepts the standard order fields plus `recipient_email`, `alternative_phone`, `item_description`, and `total_lot`.

## Status and tracking

```php
SteadfastCourier::checkDeliveryStatusByConsignmentId(12345);
SteadfastCourier::checkDeliveryStatusByInvoiceId('ORD-10231');
SteadfastCourier::checkDeliveryStatusByTrackingCode('15BAEB8A');
SteadfastCourier::checkDeliveryStatusWithReturnByConsignmentId(12345);
SteadfastCourier::getTrackingsByInvoice('ORD-10231');
SteadfastCourier::getCurrentBalance();
```

`checkDeliveryStatusWithReturnByConsignmentId` also reports the return journey. `getTrackingsByInvoice` returns the full tracking history.

## Pickup and returns

```php
SteadfastCourier::createPickupRequest([
    'address_id' => 42,
    'police_station_id' => 17,
    'address' => 'House 17/1, Road 3/A, Dhanmondi, Dhaka',
    'contact_number' => '01712345678',
    'note' => 'Call on arrival',
    'estim_qty' => 25,
]);

SteadfastCourier::createReturnRequest([
    'invoice' => 'ORD-10231',
    'reason' => 'Customer changed their mind',
]);

SteadfastCourier::getReturnRequests(2);
SteadfastCourier::getReturnRequest(1);
```

`createPickupRequest` accepts `address_id`, `police_station_id`, `address`, `contact_number`, optional `note`, and optional `estim_qty`. A successful pickup request returns HTTP 201 from the service. Return requests may identify a parcel by `consignment_id`, `invoice`, or `tracking_code`.

## Payments, lookup, and fraud score

```php
SteadfastCourier::getPayments(2);
SteadfastCourier::getPayment('SFC-88213');
SteadfastCourier::getPoliceStations();
SteadfastCourier::getFraudScore('01712345678');
SteadfastCourier::ping();
```

`getFraudScore` returns delivery/cancellation ratios, volume information, reports, and the documented `score`, `level`, `reasons`, and `scoring_disabled` fields. A null score is meaningful and must not be treated as a clean record.

## Webhooks

The dashboard webhook sends a raw JSON body and an `X-Signature` header. The signature is the lowercase hexadecimal HMAC-SHA256 of the exact raw body using `STEADFAST_TOKEN`.

```php
public function handle(Request $request)
{
    $body = $request->getContent();

    abort_unless(
        SteadfastCourier::verifyWebhookSignature(
            $body,
            (string) $request->header('X-Signature')
        ),
        401
    );

    $event = SteadfastCourier::parseWebhookPayload($body);

    // Use notification_type to handle delivery_status,
    // tracking_update, or consignment_update.
    return response()->json(['status' => 'success']);
}
```

The package does not register a route or controller. The application must preserve the raw request body until signature verification is complete.

Do not log or commit API keys, secret keys, webhook secrets, or raw webhook payloads containing customer information.

Example event types:

```json
{
    "notification_type": "delivery_status",
    "consignment_id": 12345,
    "invoice": "INV-67890",
    "cod_amount": 1500,
    "status": "delivered",
    "delivery_charge": 100,
    "tracking_message": "Your package has been delivered successfully.",
    "updated_at": "2025-03-02 12:45:30"
}
```

`tracking_update` and `consignment_update` events do not necessarily contain `status` or `cod_amount`; applications must branch on `notification_type` instead of applying one fixed field list.

## Custom configuration

For a one-off or multi-tenant integration:

```php
$response = SteadfastCourier::withConfig(
    'your-api-key',
    'your-secret-key',
    'https://portal.packzy.com/api/v1'
)->getCurrentBalance();
```

## Supported API paths

`/ping`, `/create_order`, `/create_order/bulk-order`, `/create_order/bulk-order/extended`, `/status_by_cid/{consignment_id}`, `/status_with_return_status_by_cid/{consignment_id}`, `/status_by_invoice/{invoice}`, `/status_by_trackingcode/{tracking_code}`, `/trackings_by_invoice/{invoice}`, `/create_pickup_request`, `/create_return_request`, `/get_return_requests`, `/get_return_request/{id}`, `/get_balance`, `/payments`, `/payments/{payment_id}`, `/police_stations`, and `/fraud_check/score/{phone}`.

## Local development

Install the development dependencies and run the same checks used by CI:

```bash
composer install
vendor/bin/pint --test
vendor/bin/pest
```

To format changed PHP files:

```bash
vendor/bin/pint
```

The tests use Laravel's HTTP fake and never call the live Steadfast API. No API credentials are required to run them.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
