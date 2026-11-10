<?php

use SteadFast\SteadFastCourierLaravelPackage\Enums\DeliveryStatus;

it('covers every status the API documents', function () {
    expect(array_map(fn (DeliveryStatus $s) => $s->value, DeliveryStatus::cases()))
        ->toBe([
            'pending',
            'delivered_approval_pending',
            'partial_delivered_approval_pending',
            'cancelled_approval_pending',
            'unknown_approval_pending',
            'delivered',
            'partial_delivered',
            'cancelled',
            'hold',
            'in_review',
            'unknown',
        ]);
});

it('reads a status out of a status-check response', function () {
    expect(DeliveryStatus::fromResponse(['status' => 200, 'delivery_status' => 'partial_delivered']))
        ->toBe(DeliveryStatus::PartialDelivered);
});

it('returns null when a response carries no recognisable status', function (array $response) {
    expect(DeliveryStatus::fromResponse($response))->toBeNull();
})->with([
    'empty' => [[]],
    'unknown string' => [['delivery_status' => 'teleported']],
    'numeric status only' => [['status' => 200]],
]);

it('classifies settled statuses as final', function (DeliveryStatus $status, bool $final) {
    expect($status->isFinal())->toBe($final);
})->with([
    [DeliveryStatus::Delivered, true],
    [DeliveryStatus::PartialDelivered, true],
    [DeliveryStatus::Cancelled, true],
    [DeliveryStatus::Pending, false],
    [DeliveryStatus::InReview, false],
    [DeliveryStatus::Hold, false],
    [DeliveryStatus::DeliveredApprovalPending, false],
]);

it('classifies approval-pending statuses', function () {
    expect(DeliveryStatus::DeliveredApprovalPending->isAwaitingApproval())->toBeTrue()
        ->and(DeliveryStatus::CancelledApprovalPending->isAwaitingApproval())->toBeTrue()
        ->and(DeliveryStatus::Delivered->isAwaitingApproval())->toBeFalse();
});

it('treats every status as exactly one of final, awaiting approval or in progress', function () {
    foreach (DeliveryStatus::cases() as $status) {
        $flags = array_filter([
            $status->isFinal(),
            $status->isAwaitingApproval(),
            $status->isInProgress(),
        ]);

        expect($flags)->toHaveCount(1, "{$status->value} is in more or fewer than one bucket");
    }
});

it('flags the statuses that need support', function () {
    expect(DeliveryStatus::Unknown->needsSupport())->toBeTrue()
        ->and(DeliveryStatus::UnknownApprovalPending->needsSupport())->toBeTrue()
        ->and(DeliveryStatus::Delivered->needsSupport())->toBeFalse();
});

it('gives every status a human label', function () {
    foreach (DeliveryStatus::cases() as $status) {
        expect($status->label())->toBeString()->not->toBeEmpty();
    }
});

// placeOrder() nests the same status vocabulary under "consignment", so reading
// the status straight off that response should work without digging.
it('reads the status out of a placeOrder response', function () {
    expect(DeliveryStatus::fromResponse([
        'status' => 200,
        'message' => 'Consignment has been created successfully.',
        'consignment' => ['consignment_id' => 131822154, 'status' => 'in_review'],
    ]))->toBe(DeliveryStatus::InReview);
});

it('reads the status out of a webhook payload', function () {
    expect(DeliveryStatus::fromResponse([
        'consignment_id' => 131822154,
        'status' => 'delivered',
        'updated_at' => '2026-03-21T07:05:31.000000Z',
    ]))->toBe(DeliveryStatus::Delivered);
});

// A bulk entry's "status" says whether booking succeeded, not how delivery went.
it('does not mistake a bulk success flag for a delivery status', function (string $flag) {
    expect(DeliveryStatus::fromResponse(['status' => $flag]))->toBeNull();
})->with(['success', 'error']);

it('prefers delivery_status over any other candidate', function () {
    expect(DeliveryStatus::fromResponse([
        'delivery_status' => 'cancelled',
        'consignment' => ['status' => 'in_review'],
        'status' => 'delivered',
    ]))->toBe(DeliveryStatus::Cancelled);
});

it('falls through to the next candidate when one is unrecognised', function () {
    expect(DeliveryStatus::fromResponse([
        'delivery_status' => 'teleported',
        'consignment' => ['status' => 'hold'],
    ]))->toBe(DeliveryStatus::Hold);
});
