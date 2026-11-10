<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SteadFast\SteadFastCourierLaravelPackage\Exceptions\InvalidRequestException;
use SteadFast\SteadFastCourierLaravelPackage\Exceptions\SteadfastCourierException;
use SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;
use SteadFast\SteadFastCourierLaravelPackage\SteadfastCourier as SteadfastCourierClient;

const BASE = 'https://portal.packzy.com/api/v1';

beforeEach(function () {
    // Any request this suite does not explicitly fake is a bug, not a live call.
    Http::preventStrayRequests();
});

function fakeOk(array $body = ['status' => 200]): void
{
    Http::fake(['*' => Http::response($body)]);
}

function sentApiKeys(): array
{
    return Http::recorded()
        ->map(fn (array $pair) => $pair[0]->header('Api-Key')[0] ?? null)
        ->all();
}

function anOrder(array $overrides = []): array
{
    return array_merge([
        'invoice' => '13151523',
        'recipient_name' => 'Badrul Hasan Sajib',
        'recipient_phone' => '+8801309055966',
        'recipient_address' => 'Dhanmondi, Zigatola',
        'cod_amount' => '3010',
    ], $overrides);
}

// =====================================================================
// Orders
// =====================================================================

it('places an order', function () {
    Http::fake(['*' => Http::response([
        'status' => 200,
        'message' => 'Consignment has been created successfully.',
        'consignment' => ['consignment_id' => 131822154, 'tracking_code' => '7DB724A65D4'],
    ])]);

    $response = SteadfastCourier::placeOrder(anOrder());

    expect($response['status'])->toBe(200)
        ->and($response)->toHaveKey('consignment');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === BASE.'/create_order'
        && $request->hasHeader('Api-Key', 'test-api-key')
        && $request->hasHeader('Secret-Key', 'test-secret-key')
        && $request['invoice'] === '13151523');
});

it('passes every documented order field through untouched', function () {
    fakeOk();

    $order = anOrder([
        'recipient_email' => 'badrul.sajib@gmail.com',
        'alternative_phone' => '01309055966',
        'item_description' => 'iPhone 16pro Max 512GB',
        'note' => 'Handle with care',
        'some_future_field' => 'kept',
    ]);

    SteadfastCourier::placeOrder($order);

    Http::assertSent(fn (Request $request) => $request->data() === $order);
});

// The bulk endpoint takes the list of orders as the request body itself. It
// previously sent {"data": "<json string>"}, which the current API does not
// document.
it('posts bulk orders as a bare json list', function () {
    Http::fake(['*' => Http::response([
        'status' => 200,
        'message' => 'We have a response for you.',
        'data' => [anOrder(), anOrder(['invoice' => '789012'])],
    ])]);

    $orders = [anOrder(), anOrder(['invoice' => '789012'])];

    $response = SteadfastCourier::bulkCreateOrders($orders);

    expect($response['status'])->toBe(200)
        ->and($response['data'])->toHaveCount(2);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === BASE.'/create_order/bulk-order'
        && $request->data() === $orders
        && ! array_key_exists('data', $request->data()));
});

it('rejects bulk input that cannot succeed', function (callable $call, string $expected) {
    Http::fake();

    expect($call)->toThrow(InvalidRequestException::class, $expected);

    Http::assertNothingSent();
})->with([
    'empty' => [fn () => SteadfastCourier::bulkCreateOrders([]), 'at least one order'],
    'single order, not a list' => [fn () => SteadfastCourier::bulkCreateOrders(anOrder()), 'expects a list of orders'],
    'list of scalars' => [fn () => SteadfastCourier::bulkCreateOrders(['nope']), 'expects a list of orders'],
]);

it('rejects more than 500 bulk orders without calling the api', function () {
    Http::fake();

    $orders = array_fill(0, 501, anOrder());

    expect(fn () => SteadfastCourier::bulkCreateOrders($orders))
        ->toThrow(InvalidRequestException::class, 'at most 500 orders');

    Http::assertNothingSent();
});

it('accepts exactly 500 bulk orders', function () {
    fakeOk(['status' => 200, 'data' => []]);

    SteadfastCourier::bulkCreateOrders(array_fill(0, SteadfastCourierClient::MAX_BULK_ORDERS, anOrder()));

    Http::assertSent(fn (Request $request) => count($request->data()) === 500);
});

// =====================================================================
// Delivery status and tracking
// =====================================================================

it('reads every status and tracking endpoint', function (string $method, array $args, string $path) {
    fakeOk(['status' => 200, 'delivery_status' => 'partial_delivered']);

    SteadfastCourier::$method(...$args);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === BASE.$path);
})->with([
    'by consignment id' => ['checkDeliveryStatusByConsignmentId', [98339475], '/status_by_cid/98339475'],
    'by invoice' => ['checkDeliveryStatusByInvoiceId', ['230822-1'], '/status_by_invoice/230822-1'],
    'by tracking code' => ['checkDeliveryStatusByTrackingCode', ['B025A3FA'], '/status_by_trackingcode/B025A3FA'],
    'tracking history' => ['getTrackingsByInvoice', ['INV0EEBA2A'], '/trackings_by_invoice/INV0EEBA2A'],
]);

// =====================================================================
// Return requests, pickup requests, account, reference data
// =====================================================================

it('creates a return request', function () {
    Http::fake(['*' => Http::response(['id' => 1, 'consignment_id' => 160757328], 201)]);

    $payload = ['consignment_id' => '160757328', 'reason' => 'Received a damaged or broken item'];

    $response = SteadfastCourier::createReturnRequest($payload);

    expect($response['id'])->toBe(1);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === BASE.'/create_return_request'
        && $request->data() === $payload);
});

it('accepts any of the three consignment references for a return request', function (string $key) {
    Http::fake(['*' => Http::response(['id' => 1], 201)]);

    SteadfastCourier::createReturnRequest([$key => 'ref-1', 'reason' => 'damaged']);

    Http::assertSent(fn (Request $request) => $request->url() === BASE.'/create_return_request');
})->with(['consignment_id', 'invoice', 'tracking_code']);

it('refuses a return request with no consignment reference', function () {
    Http::fake();

    expect(fn () => SteadfastCourier::createReturnRequest(['reason' => 'damaged']))
        ->toThrow(InvalidRequestException::class, 'consignment_id, invoice or tracking_code');

    Http::assertNothingSent();
});

it('sends a pickup request', function () {
    Http::fake(['*' => Http::response(['message' => 'Pickup request created successfully.'], 201)]);

    $payload = [
        'address_id' => 5,
        'police_station_id' => 92,
        'address' => 'Dhanmondi, Zigatola 2/a',
        'contact_number' => '01722743076',
        'note' => 'Urgent case. Please investigate.',
        'estim_qty' => 12,
    ];

    SteadfastCourier::createPickupRequest($payload);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === BASE.'/create_pickup_request'
        && $request->data() === $payload);
});

it('reads every GET endpoint at its documented path', function (string $method, array $args, string $path) {
    fakeOk();

    SteadfastCourier::$method(...$args);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === BASE.$path
        && $request->hasHeader('Api-Key', 'test-api-key'));
})->with([
    'balance' => ['getCurrentBalance', [], '/get_balance'],
    'payments' => ['getPayments', [], '/payments'],
    'single payment' => ['getPayment', ['SFC-23618228'], '/payments/SFC-23618228'],
    'return requests' => ['getReturnRequests', [], '/get_return_requests'],
    'single return request' => ['getReturnRequest', [1], '/get_return_request/1'],
    'police stations' => ['getPoliceStations', [], '/police_stations'],
    'fraud check' => ['fraudCheck', ['01309055966'], '/fraud_check/01309055966'],
]);

it('returns the balance payload', function () {
    fakeOk(['status' => 200, 'current_balance' => -10]);

    expect(SteadfastCourier::getCurrentBalance())->toBe(['status' => 200, 'current_balance' => -10]);
});

it('returns the fraud check payload', function () {
    fakeOk([
        'total_parcels' => 24,
        'total_delivered' => 9,
        'total_cancelled' => 15,
        'total_fraud_reports' => [],
    ]);

    expect(SteadfastCourier::fraudCheck('01309055966'))
        ->toHaveKeys(['total_parcels', 'total_delivered', 'total_cancelled', 'total_fraud_reports']);
});

// =====================================================================
// Identifier handling
// =====================================================================

// Merchant invoice numbers are caller-supplied. Unencoded, "INV/2024/01" adds
// path segments and reaches a different endpoint, and "#" truncates the path.
it('percent-encodes values interpolated into the url path', function (string $invoice, string $expected) {
    fakeOk(['status' => 200, 'delivery_status' => 'pending']);

    SteadfastCourier::checkDeliveryStatusByInvoiceId($invoice);

    Http::assertSent(fn (Request $request) => $request->url() === BASE.'/status_by_invoice/'.$expected);
})->with([
    'plain' => ['230822-1', '230822-1'],
    'slashes' => ['INV/2024/01', 'INV%2F2024%2F01'],
    'space' => ['ORD 118', 'ORD%20118'],
    'fragment and ampersand' => ['a&b#7', 'a%26b%237'],
    'question mark' => ['INV?1', 'INV%3F1'],
]);

it('encodes tracking codes and payment ids too', function (string $method, string $value, string $path) {
    fakeOk();

    SteadfastCourier::$method($value);

    Http::assertSent(fn (Request $request) => $request->url() === BASE.$path);
})->with([
    'tracking code' => ['checkDeliveryStatusByTrackingCode', 'B025/A3FA', '/status_by_trackingcode/B025%2FA3FA'],
    'payment id' => ['getPayment', 'SFC/236', '/payments/SFC%2F236'],
    'invoice history' => ['getTrackingsByInvoice', 'INV#9', '/trackings_by_invoice/INV%239'],
]);

// An empty identifier would build a URL with a missing path segment, silently
// hitting a different endpoint.
it('refuses an empty identifier instead of building a broken url', function (string $method, $value) {
    Http::fake();

    expect(fn () => SteadfastCourier::$method($value))->toThrow(InvalidRequestException::class);

    Http::assertNothingSent();
})->with([
    'blank consignment id' => ['checkDeliveryStatusByConsignmentId', ''],
    'blank invoice' => ['checkDeliveryStatusByInvoiceId', ''],
    'whitespace tracking code' => ['checkDeliveryStatusByTrackingCode', '   '],
    'blank payment id' => ['getPayment', ''],
    'blank phone' => ['fraudCheck', ''],
    'blank return request id' => ['getReturnRequest', ''],
    'blank tracking invoice' => ['getTrackingsByInvoice', ''],
]);

// =====================================================================
// Headers, errors, timeouts and retries
// =====================================================================

it('asks for JSON explicitly', function () {
    fakeOk();

    SteadfastCourier::getCurrentBalance();

    Http::assertSent(fn (Request $request) => $request->hasHeader('Accept', 'application/json')
        && $request->hasHeader('Content-Type', 'application/json'));
});

it('throws when Steadfast returns a non-JSON body', function () {
    Http::fake(['*' => Http::response('<html><body>502 Bad Gateway</body></html>', 502, [
        'Content-Type' => 'text/html',
    ])]);

    expect(fn () => SteadfastCourier::getCurrentBalance())
        ->toThrow(SteadfastCourierException::class);
});

it('throws on an empty body rather than returning null', function () {
    Http::fake(['*' => Http::response('', 204)]);

    expect(fn () => SteadfastCourier::getCurrentBalance())
        ->toThrow(SteadfastCourierException::class, '<empty body>');
});

it('exposes the failing response on the exception', function () {
    Http::fake(['*' => Http::response('<html><body>502 Bad Gateway</body></html>', 502, [
        'Content-Type' => 'text/html',
    ])]);

    try {
        SteadfastCourier::getCurrentBalance();

        $this->fail('Expected a SteadfastCourierException.');
    } catch (SteadfastCourierException $e) {
        expect($e->status())->toBe(502)
            ->and($e->getMessage())->toContain('HTTP 502')
            ->and($e->getMessage())->toContain('get_balance')
            ->and($e->response()->body())->toContain('Bad Gateway');
    }
});

it('reports a local validation failure as a SteadfastCourierException too', function () {
    expect(new InvalidRequestException('x'))->toBeInstanceOf(SteadfastCourierException::class);
});

// Steadfast reports its own validation failures as JSON, and those carry the
// message the caller needs, so they must not be turned into exceptions.
it('returns Steadfast validation errors as-is', function () {
    Http::fake(['*' => Http::response([
        'status' => 400,
        'message' => 'The recipient phone must be 11 digits.',
    ], 400)]);

    expect(SteadfastCourier::placeOrder(anOrder()))
        ->toBe(['status' => 400, 'message' => 'The recipient phone must be 11 digits.']);
});

it('retries idempotent reads when the connection fails', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('Connection timed out');
    });

    expect(fn () => SteadfastCourier::getCurrentBalance())->toThrow(ConnectionException::class);

    expect($attempts)->toBe(2);
});

// Retrying a timed-out create could book the same parcel twice, and the API has
// no idempotency key that would make a retry safe.
it('never retries a write', function (callable $call) {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('Connection timed out');
    });

    expect($call)->toThrow(ConnectionException::class);

    expect($attempts)->toBe(1);
})->with([
    'placeOrder' => [fn () => SteadfastCourier::placeOrder(anOrder())],
    'bulkCreateOrders' => [fn () => SteadfastCourier::bulkCreateOrders([anOrder()])],
    'createReturnRequest' => [fn () => SteadfastCourier::createReturnRequest(['invoice' => 'x', 'reason' => 'y'])],
    'createPickupRequest' => [fn () => SteadfastCourier::createPickupRequest(['address_id' => 5])],
]);

// =====================================================================
// Multi-tenant credentials
// =====================================================================

it('sends the credentials supplied to withConfig', function () {
    fakeOk();

    SteadfastCourier::withConfig('tenant-key', 'tenant-secret')->getCurrentBalance();

    Http::assertSent(fn (Request $request) => $request->hasHeader('Api-Key', 'tenant-key')
        && $request->hasHeader('Secret-Key', 'tenant-secret'));
});

it('honours a custom base url passed to withConfig', function () {
    fakeOk();

    SteadfastCourier::withConfig('k', 's', 'https://staging.example.test/api/v1')->getCurrentBalance();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://staging.example.test/api/v1/get_balance');
});

it('falls back to the configured base url when withConfig omits one', function () {
    fakeOk();

    SteadfastCourier::withConfig('k', 's')->getCurrentBalance();

    Http::assertSent(fn (Request $request) => $request->url() === BASE.'/get_balance');
});

// Regression test: withConfig() used to mutate the facade's cached instance, so
// one tenant's credentials leaked into every later call in the same process.
it('does not leak withConfig credentials into later calls', function () {
    fakeOk();

    SteadfastCourier::withConfig('tenant-key', 'tenant-secret')->getCurrentBalance();
    SteadfastCourier::getCurrentBalance();

    expect(sentApiKeys())->toBe(['tenant-key', 'test-api-key']);
});

it('keeps separate withConfig clients isolated from each other', function () {
    fakeOk();

    $tenantA = SteadfastCourier::withConfig('tenant-a-key', 'tenant-a-secret');
    $tenantB = SteadfastCourier::withConfig('tenant-b-key', 'tenant-b-secret');

    $tenantA->getCurrentBalance();
    $tenantB->getCurrentBalance();
    $tenantA->getCurrentBalance();

    expect(sentApiKeys())->toBe(['tenant-a-key', 'tenant-b-key', 'tenant-a-key']);
});

// =====================================================================
// Package wiring
// =====================================================================

it('ships every documented config key', function () {
    expect(config('steadfast-courier'))->toHaveKeys([
        'base_url', 'api_key', 'secret_key', 'webhook_bearer_token',
        'content_type', 'timeout', 'connect_timeout', 'retry',
    ]);
});

it('registers a facade alias and providers that point at real classes', function () {
    $composer = json_decode(file_get_contents(__DIR__.'/../composer.json'), true, 512, JSON_THROW_ON_ERROR);

    $aliases = $composer['extra']['laravel']['aliases'];

    expect($aliases)->toHaveKey('SteadFastCourier');

    foreach ($aliases as $alias => $class) {
        expect(class_exists($class))->toBeTrue("Alias [{$alias}] points at missing class [{$class}]");
    }

    foreach ($composer['extra']['laravel']['providers'] as $provider) {
        expect(class_exists($provider))->toBeTrue("Provider [{$provider}] does not exist");
    }
});

// Guards against a public method being added without a facade @method tag, which
// would silently lose IDE completion and static analysis for it.
it('documents every public method on the facade', function () {
    $client = file_get_contents(__DIR__.'/../src/SteadfastCourier.php');
    $facade = file_get_contents(__DIR__.'/../src/Facades/SteadfastCourier.php');

    preg_match_all('/public function (\w+)\(/', $client, $m);
    $methods = array_diff($m[1], ['__construct']);

    preg_match_all('/@method static \S+ (\w+)\(/', $facade, $f);

    expect(array_values(array_diff($methods, $f[1])))->toBe([]);
});
