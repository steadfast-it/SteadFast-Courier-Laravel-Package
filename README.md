<p align="center">
  <img src="https://raw.githubusercontent.com/steadfast-it/SteadFast-Laravel-Package/main/logo.png" width="30%" alt="SteadFast">
</p>

<h1 align="center">SteadFast Courier for Laravel</h1>

<p align="center">
  A complete Laravel client for the <a href="https://www.steadfast.com.bd/">SteadFast Courier Limited</a> API.
</p>

<p align="center">
  <a href="https://packagist.org/packages/steadfast-courier/steadfast-courier-laravel-package"><img src="https://img.shields.io/packagist/v/steadfast-courier/steadfast-courier-laravel-package.svg?style=flat-square" alt="Latest version"></a>
  <a href="https://packagist.org/packages/steadfast-courier/steadfast-courier-laravel-package"><img src="https://img.shields.io/packagist/dt/steadfast-courier/steadfast-courier-laravel-package.svg?style=flat-square" alt="Downloads"></a>
  <a href="https://github.com/steadfast-it/SteadFast-Laravel-Package/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/steadfast-it/SteadFast-Laravel-Package/run-tests.yml?branch=main&label=tests&style=flat-square" alt="Tests"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/packagist/l/steadfast-courier/steadfast-courier-laravel-package.svg?style=flat-square" alt="License"></a>
</p>

Book parcels, track deliveries, handle returns and pickups, and reconcile payments from your
Laravel application. Every endpoint in the
[official SteadFast API documentation](https://documenter.getpostman.com/view/26211192/2sAYJ1mhpk)
is covered, and every method returns the API's JSON decoded into a plain PHP array.

Works the same in an API-only backend, a queue worker, or a Blade application.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick start](#quick-start)
- [Endpoint coverage](#endpoint-coverage)
- [Orders](#orders)
- [Delivery status and tracking](#delivery-status-and-tracking)
- [Returns and pickups](#returns-and-pickups)
- [Account and reference data](#account-and-reference-data)
- [Multiple Steadfast accounts](#multiple-steadfast-accounts)
- [Error handling](#error-handling)
- [Webhooks](#webhooks)
- [Upgrading from 1.x](#upgrading-from-1x)
- [Contributing](#contributing)
- [Credits and license](#credits-and-license)

## Requirements

| | Supported |
| --- | --- |
| PHP | 8.2 – 8.5 |
| Laravel | 12.x, 13.x |

Laravel 11 and earlier are not supported. Laravel 11 reached end of security support on
12 March 2026, and Composer's `block-insecure` audit default refuses to install any 11.x
release. Projects still on Laravel 11 or older should stay on `^1.1` of this package.

## Installation

```bash
composer require steadfast-courier/steadfast-courier-laravel-package
```

Publish the config file:

```bash
php artisan vendor:publish --tag="steadfast-courier-config"
```

## Configuration

Add your credentials to `.env`. You can find them in your Steadfast merchant dashboard, or
request them from Steadfast support.

```env
STEADFAST_API_KEY="your-api-key"
STEADFAST_SECRET_KEY="your-secret-key"
```

Everything else is optional. The defaults are shown here, so you only need to add the lines
you actually want to change:

```env
STEADFAST_BASE_URL="https://portal.packzy.com/api/v1"

# Webhooks, see the Webhooks section below
STEADFAST_BEARER_TOKEN=

# Network behaviour
STEADFAST_TIMEOUT=30
STEADFAST_CONNECT_TIMEOUT=10
STEADFAST_RETRY_TIMES=2
STEADFAST_RETRY_SLEEP=200
```

The published `config/steadfast-courier.php` documents each option inline.

## Quick start

```php
use SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;

// Book a parcel
$response = SteadfastCourier::placeOrder([
    'invoice' => 'INV-1001',
    'recipient_name' => 'Badrul Hasan Sajib',
    'recipient_phone' => '01309055966',
    'recipient_address' => 'Dhanmondi, Zigatola, Dhaka',
    'cod_amount' => 3010,
]);

$consignmentId = $response['consignment']['consignment_id'];

// Check on it later
$status = SteadfastCourier::checkDeliveryStatusByConsignmentId($consignmentId);

echo $status['delivery_status']; // "in_review"
```

If you prefer the registered alias, `\SteadFastCourier::placeOrder(...)` works identically.

## Endpoint coverage

| Steadfast endpoint | Method |
| --- | --- |
| `POST /create_order` | [`placeOrder()`](#place-an-order) |
| `POST /create_order/bulk-order` | [`bulkCreateOrders()`](#create-orders-in-bulk) |
| `GET /status_by_cid/{id}` | [`checkDeliveryStatusByConsignmentId()`](#check-a-delivery-status) |
| `GET /status_by_invoice/{invoice}` <sup>1</sup> | [`checkDeliveryStatusByInvoiceId()`](#check-a-delivery-status) |
| `GET /status_by_trackingcode/{code}` <sup>1</sup> | [`checkDeliveryStatusByTrackingCode()`](#check-a-delivery-status) |
| `GET /trackings_by_invoice/{invoice}` | [`getTrackingsByInvoice()`](#read-the-full-tracking-history) |
| `POST /create_return_request` | [`createReturnRequest()`](#returns-and-pickups) |
| `GET /get_return_requests` | [`getReturnRequests()`](#returns-and-pickups) |
| `GET /get_return_request/{id}` | [`getReturnRequest()`](#returns-and-pickups) |
| `POST /create_pickup_request` | [`createPickupRequest()`](#request-a-pickup) |
| `GET /get_balance` | [`getCurrentBalance()`](#account-and-reference-data) |
| `GET /payments` | [`getPayments()`](#payments) |
| `GET /payments/{payment_id}` | [`getPayment()`](#payments) |
| `GET /police_stations` | [`getPoliceStations()`](#police-stations) |
| `GET /fraud_check/{phone}` | [`fraudCheck()`](#fraud-check) |

<sup>1</sup> Not listed in the current Postman collection, but still served by the API and
supported here since 1.x.

Each example below assumes this import:

```php
use SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;
```

## Orders

### Place an order

```php
$response = SteadfastCourier::placeOrder([
    // Required
    'invoice' => 'INV-1001',                         // your own reference, must be unique
    'recipient_name' => 'Badrul Hasan Sajib',
    'recipient_phone' => '01309055966',
    'recipient_address' => 'Dhanmondi, Zigatola, Dhaka',
    'cod_amount' => 3010,                            // 0 when nothing is collected

    // Optional
    'recipient_email' => 'badrul.sajib@gmail.com',
    'alternative_phone' => '01722743076',
    'item_description' => 'iPhone 16 Pro Max 512GB',
    'note' => 'Call before delivery.',
]);
```

Unrecognised keys are passed through untouched, so a field Steadfast adds later works without
waiting for a package release.

```json
{
    "status": 200,
    "message": "Consignment has been created successfully.",
    "consignment": {
        "consignment_id": 131822154,
        "invoice": "INV-1001",
        "tracking_code": "7DB724A65D4",
        "recipient_name": "Badrul Hasan Sajib",
        "recipient_phone": "01309055966",
        "recipient_address": "Dhanmondi, Zigatola, Dhaka",
        "cod_amount": 3010,
        "status": "in_review",
        "note": "Call before delivery.",
        "created_at": "2026-03-21T07:05:31.000000Z",
        "updated_at": "2026-03-21T07:05:31.000000Z"
    }
}
```

### Create orders in bulk

Up to **500 orders** per call.

```php
$response = SteadfastCourier::bulkCreateOrders([
    [
        'invoice' => 'INV-1001',
        'recipient_name' => 'Badrul Hasan Sajib',
        'recipient_phone' => '01309055966',
        'recipient_address' => 'Dhanmondi, Zigatola, Dhaka',
        'cod_amount' => 3010,
    ],
    [
        'invoice' => 'INV-1002',
        'recipient_name' => 'Jane Smith',
        'recipient_phone' => '01711111111',
        'recipient_address' => 'House 44, Road 2/A, Dhanmondi, Dhaka 1209',
        'cod_amount' => 1500,
    ],
]);

foreach ($response['data'] as $consignment) {
    if ($consignment['status'] === 'error') {
        // This one order was rejected; the rest may have succeeded.
        continue;
    }

    // $consignment['consignment_id'], $consignment['tracking_code']
}
```

```json
{
    "status": 200,
    "message": "We have a response for you.",
    "data": [
        {
            "invoice": "INV-1001",
            "recipient_name": "Badrul Hasan Sajib",
            "recipient_phone": "01309055966",
            "recipient_address": "Dhanmondi, Zigatola, Dhaka",
            "cod_amount": "3010",
            "consignment_id": 11543968,
            "tracking_code": "B025A038",
            "status": "success"
        }
    ]
}
```

A bulk call succeeds as a whole even when individual orders fail, so check each entry's
`status` rather than assuming the batch went through.

These mistakes are caught before any request is sent, and raise
[`InvalidRequestException`](#error-handling):

| Call | Problem |
| --- | --- |
| `bulkCreateOrders([])` | nothing to send |
| `bulkCreateOrders(['invoice' => '...'])` | a single order, where a list of orders is expected |
| more than 500 orders | over the API limit, send them in chunks |

Chunking is straightforward:

```php
use SteadFast\SteadFastCourierLaravelPackage\SteadfastCourier as Client;

foreach (array_chunk($orders, Client::MAX_BULK_ORDERS) as $chunk) {
    SteadfastCourier::bulkCreateOrders($chunk);
}
```

## Delivery status and tracking

### Check a delivery status

Three interchangeable lookups, all returning the same shape:

```php
SteadfastCourier::checkDeliveryStatusByConsignmentId(131822154);
SteadfastCourier::checkDeliveryStatusByInvoiceId('INV-1001');
SteadfastCourier::checkDeliveryStatusByTrackingCode('7DB724A65D4');
```

```json
{
    "status": 200,
    "delivery_status": "partial_delivered"
}
```

### Work with statuses using the enum

Comparing raw strings is easy to get wrong: a typo becomes a branch that silently never runs.
Use the `DeliveryStatus` enum instead.

```php
use SteadFast\SteadFastCourierLaravelPackage\Enums\DeliveryStatus;

$status = DeliveryStatus::fromResponse(
    SteadfastCourier::checkDeliveryStatusByConsignmentId($consignmentId)
);

if ($status?->isFinal()) {
    // Settled and the balance is updated. Stop polling.
}

if ($status?->isAwaitingApproval()) {
    // Outcome recorded, waiting on a Steadfast admin. The balance has not moved.
}

if ($status?->needsSupport()) {
    // Steadfast cannot classify this parcel. A human needs to look at it.
}

echo $status?->label(); // "Partially delivered and balance updated"
```

`fromResponse()` returns `null` when the payload carries no status this package recognises, so
a status Steadfast adds in future degrades gracefully instead of throwing in production.

| API value | Enum case | Meaning | Bucket |
| --- | --- | --- | --- |
| `in_review` | `InReview` | Order placed, under review | in progress |
| `pending` | `Pending` | Not yet delivered or cancelled | in progress |
| `hold` | `Hold` | On hold | in progress |
| `delivered_approval_pending` | `DeliveredApprovalPending` | Delivered, awaiting admin approval | awaiting approval |
| `partial_delivered_approval_pending` | `PartialDeliveredApprovalPending` | Partially delivered, awaiting approval | awaiting approval |
| `cancelled_approval_pending` | `CancelledApprovalPending` | Cancelled, awaiting approval | awaiting approval |
| `unknown_approval_pending` | `UnknownApprovalPending` | Unknown state, needs support | awaiting approval |
| `delivered` | `Delivered` | Delivered and balance updated | final |
| `partial_delivered` | `PartialDelivered` | Partially delivered and balance updated | final |
| `cancelled` | `Cancelled` | Cancelled and balance updated | final |
| `unknown` | `Unknown` | Unknown status, contact support | in progress |

Every status falls into exactly one bucket: `isFinal()`, `isAwaitingApproval()` or
`isInProgress()`.

### Read the full tracking history

Where a status check returns the current state, this returns the whole event trail:

```php
$response = SteadfastCourier::getTrackingsByInvoice('INV-1001');

foreach ($response['tracking'] as $event) {
    echo $event['text']; // "Consignment created by Sender(API)."
}
```

## Returns and pickups

### Create a return request

Identify the consignment by **one** of `consignment_id`, `invoice` or `tracking_code`.
Omitting all three raises `InvalidRequestException` before any request is sent.

```php
$response = SteadfastCourier::createReturnRequest([
    'consignment_id' => 160757328,
    'reason' => 'Received a damaged or broken item',
]);
```

### List and read return requests

```php
$all = SteadfastCourier::getReturnRequests();
$one = SteadfastCourier::getReturnRequest(1);
```

### Request a pickup

Ask Steadfast to collect parcels from one of your saved addresses. Use
[`getPoliceStations()`](#police-stations) to look up `police_station_id`.

```php
$response = SteadfastCourier::createPickupRequest([
    'address_id' => 5,
    'police_station_id' => 92,
    'address' => 'Dhanmondi, Zigatola 2/a',
    'contact_number' => '01722743076',
    'note' => 'Ten small parcels, ready after 3pm.',
    'estim_qty' => 12,
]);
```

## Account and reference data

### Current balance

```php
$response = SteadfastCourier::getCurrentBalance();
```

```json
{
    "status": 200,
    "current_balance": 4820
}
```

### Payments

```php
$all = SteadfastCourier::getPayments();
$one = SteadfastCourier::getPayment('SFC-23618228');
```

```json
{
    "status": 1,
    "alertClass": "success",
    "message": "Fetched successfully!",
    "payment": {
        "payment_id": "SFC-23618228",
        "amount": 1,
        "method": "Cash",
        "due_bills": 0,
        "paid_bills": 0,
        "charges": 0,
        "total": 1,
        "status_label": "ready",
        "created_at": "2026-02-27 16:08:24",
        "ready_at": "2026-02-27 16:27:20",
        "paid_at": null
    }
}
```

### Police stations

Districts with their police stations, needed for `police_station_id` on a pickup request:

```php
$response = SteadfastCourier::getPoliceStations();

foreach ($response['data'] as $district) {
    echo $district['name']; // "Bagerhat"

    foreach ($district['policestations'] as $station) {
        echo "{$station['id']}: {$station['name']}";
    }
}
```

### Fraud check

A recipient's delivery and cancellation history across Steadfast. Worth calling before
accepting a high-value COD order:

```php
$response = SteadfastCourier::fraudCheck('01309055966');
```

```json
{
    "total_parcels": 24,
    "total_delivered": 9,
    "total_cancelled": 15,
    "total_fraud_reports": []
}
```

## Multiple Steadfast accounts

If you are not using `.env`, or you run a SaaS where each tenant has their own Steadfast
account, pass credentials per call with `withConfig()`:

```php
$response = SteadfastCourier::withConfig(
    $tenant->steadfast_api_key,
    $tenant->steadfast_secret_key,
    'https://portal.packzy.com/api/v1', // optional, falls back to config
)->getCurrentBalance();
```

`withConfig()` returns a **new** client and never modifies the shared one, so credentials
cannot leak between tenants later in the same request, queue worker or Octane worker:

```php
$tenantA = SteadfastCourier::withConfig($a->api_key, $a->secret_key);
$tenantB = SteadfastCourier::withConfig($b->api_key, $b->secret_key);

$tenantA->placeOrder($order); // always tenant A
$tenantB->placeOrder($order); // always tenant B
SteadfastCourier::getCurrentBalance(); // still the credentials from config
```

## Error handling

Steadfast reports its own validation failures as JSON, and that body carries the message you
need, so it is returned to you unchanged rather than raised:

```php
$response = SteadfastCourier::placeOrder($order);

if ($response['status'] !== 200) {
    Log::warning('Steadfast rejected the order', ['message' => $response['message']]);
}
```

Three kinds of problem throw instead:

| Exception | When |
| --- | --- |
| `InvalidRequestException` | Your input cannot succeed. Raised locally, before any network call. |
| `SteadfastCourierException` | Steadfast answered, but not with JSON: an HTML gateway error, an empty body. |
| `Illuminate\Http\Client\ConnectionException` | Steadfast could not be reached within the timeout. |

`InvalidRequestException` extends `SteadfastCourierException`, so one `catch` covers both.

```php
use Illuminate\Http\Client\ConnectionException;
use SteadFast\SteadFastCourierLaravelPackage\Exceptions\InvalidRequestException;
use SteadFast\SteadFastCourierLaravelPackage\Exceptions\SteadfastCourierException;

try {
    $response = SteadfastCourier::getCurrentBalance();
} catch (InvalidRequestException $e) {
    // A bug in the calling code: bad identifier, oversized batch, missing reference.
    report($e);
} catch (SteadfastCourierException $e) {
    Log::error('Unreadable response from Steadfast', [
        'status' => $e->status(),
        'body' => $e->response()?->body(),
    ]);
} catch (ConnectionException $e) {
    Log::error('Steadfast unreachable', ['message' => $e->getMessage()]);
}
```

Earlier versions returned `null` for a non-JSON reply, which callers could not tell apart from
an empty result. That case now throws, with the status code and a snippet of the body in the
message.

### Timeouts and retries

Requests give up after `STEADFAST_TIMEOUT` seconds (default 30) and stop waiting to connect
after `STEADFAST_CONNECT_TIMEOUT` seconds (default 10). Without these, an unresponsive
endpoint would block your web request or queue worker indefinitely.

Status lookups, tracking, balance, payments and reference data are retried up to
`STEADFAST_RETRY_TIMES` times (default 2) when the connection fails.

**Writes are never retried.** A `create_order` call that timed out may still have been
accepted, and the API has no idempotency key that would make a retry safe, so retrying could
book the same parcel twice. If `placeOrder()`, `bulkCreateOrders()`, `createReturnRequest()`
or `createPickupRequest()` throws a `ConnectionException`, confirm the state with a status
lookup before sending again:

```php
try {
    SteadfastCourier::placeOrder($order);
} catch (ConnectionException $e) {
    // Do not simply retry. Find out whether it was created first.
    $existing = SteadfastCourier::checkDeliveryStatusByInvoiceId($order['invoice']);
}
```

## Webhooks

Steadfast pushes parcel status changes to a callback URL you register with them, along with a
bearer token of your choosing for authentication.

Set the same token in your `.env`:

```env
STEADFAST_BEARER_TOKEN="your-generated-bearer-token"
```

Register a route. Webhooks come from Steadfast's servers, not a browser, so keep it out of any
CSRF-protected or session-authenticated group:

```php
Route::post('/steadfast-webhook', [SteadFastWebhookController::class, 'handle']);
```

```php
use Illuminate\Http\Request;

class SteadFastWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $expected = config('steadfast-courier.webhook_bearer_token');

        // Never authenticate against an unset token. If $expected were empty, the
        // comparison below would collapse to 'Bearer ', which any caller can send.
        if (blank($expected)) {
            return response()->json(['error' => 'Webhook token is not configured'], 500);
        }

        $token = $request->header('Authorization');

        // hash_equals compares in constant time, so the token cannot be recovered
        // by measuring how long the comparison takes.
        if (! is_string($token) || ! hash_equals('Bearer '.$expected, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->validate([
            'consignment_id' => ['required'],
            'invoice' => ['required'],
            'status' => ['required', 'string'],
            'cod_amount' => ['required'],
            'updated_at' => ['required'],
        ]);

        // Hand off to your own job so the HTTP response is not blocked.
        ProcessSteadfastWebhook::dispatch($payload);

        // Answer immediately so Steadfast does not time out and resend.
        return response()->json(['status' => 'success']);
    }
}
```

The `status` field carries the same values as a status check, so the
[`DeliveryStatus`](#work-with-statuses-using-the-enum) enum applies here too:

```php
$status = DeliveryStatus::tryFrom($payload['status']);

if ($status?->isFinal()) {
    $order->markSettled($status);
}
```

Steadfast may deliver the same event more than once, so make your handler idempotent: key it
on `consignment_id` and ignore an update whose `updated_at` is not newer than what you stored.

## Upgrading from 1.x

Version 2.0 drops end-of-life framework versions and corrects two behaviours that could lose
data or leak credentials. Expect a short checklist rather than a rewrite.

**Requirements.** PHP 8.2+ and Laravel 12+. See [Requirements](#requirements).

**`bulkCreateOrders()` posts a different body.** It now sends the order list as the request
body, matching the current API documentation; it previously sent
`{"data": "<json string>"}`. The response is wrapped, so read `$response['data']` instead of
iterating the response directly:

```php
// 1.x
foreach (SteadfastCourier::bulkCreateOrders($orders) as $consignment) { }

// 2.0
foreach (SteadfastCourier::bulkCreateOrders($orders)['data'] as $consignment) { }
```

**Non-JSON replies throw.** A reply that is not JSON used to come back as `null`. It now
raises `SteadfastCourierException`. See [Error handling](#error-handling).

**Invalid input throws early.** An empty identifier, a batch over 500 orders, a single order
passed where a list belongs, or a return request with no consignment reference now raise
`InvalidRequestException` instead of producing a malformed request.

**`withConfig()` no longer mutates the shared client.** Chained calls such as
`SteadfastCourier::withConfig(...)->placeOrder(...)` are unaffected. Previously the credentials
you passed leaked into every later call in the same process, which in a multi-tenant
application meant booking parcels on the wrong account.

**`webhook_bearer_token` now exists in the config file.** If you copied the webhook controller
from an older README, re-read [Webhooks](#webhooks): the previous example authenticated any
request sending a bare `Bearer ` header whenever the token was unset. Re-publish the config,
or add the key by hand:

```bash
php artisan vendor:publish --tag="steadfast-courier-config" --force
```

## Contributing

Contributions are welcome. Working on the package itself needs PHP 8.3 or newer, since Pest
requires it.

```bash
composer install
composer test      # Pest
composer analyse   # PHPStan
composer format    # Pint
```

The test suite never touches the network: it fakes the HTTP client and fails on any
unexpected request. Please add a test with your change, and make sure it fails without it.

Bug reports and feature requests belong in
[GitHub issues](https://github.com/steadfast-it/SteadFast-Laravel-Package/issues).

## Credits and license

Built and maintained with thanks to [AmadulHaque](https://github.com/AmadulHaque) and
[all contributors](https://github.com/steadfast-it/SteadFast-Laravel-Package/graphs/contributors).

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
