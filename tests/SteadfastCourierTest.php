<?php

use SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;

it('can place an order', function () {
    $data = [
        'invoice' => '123456',
        'recipient_name' => 'John Doe',
        'recipient_phone' => '01234567890',
        'recipient_address' => '123 Main St',
        'cod_amount' => 1000,
        'note' => 'Handle with care'
    ];

    $response = SteadfastCourier::placeOrder($data);

    expect($response['status'])->toBe(200);
    expect($response['message'])->toBe('Consignment has been created successfully.');
    expect($response)->toHaveKey('consignment');
});

it('can bulk create orders', function () {
    $data = [
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

    $response = SteadfastCourier::bulkCreateOrders($data);

    expect($response)->toBeArray();
    expect($response)->toHaveCount(2);

    foreach ($response as $order) {
        expect($order['status'])->toBe('success');
    }
});
