<?php

use Illuminate\Support\Facades\Http;
use SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;

beforeEach(function () {
    config()->set('steadfast-courier.api_key', 'api-key');
    config()->set('steadfast-courier.secret_key', 'secret-key');
    config()->set('steadfast-courier.base_url', 'https://portal.packzy.com/api/v1');
    config()->set('steadfast-courier.webhook_secret', 'webhook-secret');
});

it('places an order using the documented request', function () {
    Http::fake([
        '*' => Http::response([
            'status' => 200,
            'message' => 'Consignment has been created successfully.',
        ]),
    ]);

    $response = SteadfastCourier::placeOrder([
        'invoice' => 'ORD-10231',
        'recipient_name' => 'Jahid Hasan',
        'recipient_phone' => '01712345678',
        'recipient_address' => 'Dhanmondi, Dhaka',
        'cod_amount' => 1060,
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://portal.packzy.com/api/v1/create_order'
            && $request->header('Api-Key')[0] === 'api-key'
            && $request->header('Secret-Key')[0] === 'secret-key'
            && $request['invoice'] === 'ORD-10231';
    });

    expect($response['status'])->toBe(200);
});

it('sends bulk orders as an array and supports the extended endpoint', function () {
    Http::fake([
        '*' => Http::response(['status' => 200, 'data' => []]),
    ]);

    $orders = [['invoice' => 'ORD-10231', 'recipient_name' => 'Jahid Hasan']];

    SteadfastCourier::bulkCreateOrders($orders);
    SteadfastCourier::bulkCreateOrdersExtended($orders);

    Http::assertSentCount(2);
    Http::assertSent(function ($request) {
        return $request->url() === 'https://portal.packzy.com/api/v1/create_order/bulk-order'
            && is_array($request['data']);
    });
    Http::assertSent(function ($request) {
        return $request->url() === 'https://portal.packzy.com/api/v1/create_order/bulk-order/extended'
            && is_array($request['data']);
    });
});

it('supports the documented status, tracking, return, payment, lookup, and fraud endpoints', function () {
    Http::fake(['*' => Http::response(['status' => 200])]);

    SteadfastCourier::ping();
    SteadfastCourier::checkDeliveryStatusByConsignmentId(12345);
    SteadfastCourier::checkDeliveryStatusWithReturnByConsignmentId(12345);
    SteadfastCourier::getTrackingsByInvoice('ORD-10231');
    SteadfastCourier::createPickupRequest(['address_id' => 42]);
    SteadfastCourier::createReturnRequest(['invoice' => 'ORD-10231']);
    SteadfastCourier::getReturnRequests(2);
    SteadfastCourier::getReturnRequest(1);
    SteadfastCourier::getCurrentBalance();
    SteadfastCourier::getPayments(2);
    SteadfastCourier::getPayment('SFC-88213');
    SteadfastCourier::getPoliceStations();
    SteadfastCourier::getFraudScore('01712345678');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://portal.packzy.com/api/v1/ping'
            && ! $request->hasHeader('Api-Key');
    });
    Http::assertSent(function ($request) {
        return $request->url() === 'https://portal.packzy.com/api/v1/get_return_requests?page=2';
    });
    Http::assertSent(function ($request) {
        return $request->url() === 'https://portal.packzy.com/api/v1/payments/SFC-88213';
    });
});

it('verifies raw webhook signatures and parses event payloads', function () {
    $payload = '{"notification_type":"tracking_update","consignment_id":12345}';
    $signature = hash_hmac('sha256', $payload, 'webhook-secret');

    expect(SteadfastCourier::verifyWebhookSignature($payload, $signature))->toBeTrue()
        ->and(SteadfastCourier::verifyWebhookSignature($payload, 'invalid'))->toBeFalse()
        ->and(SteadfastCourier::parseWebhookPayload($payload))->toMatchArray([
            'notification_type' => 'tracking_update',
            'consignment_id' => 12345,
        ]);
});
