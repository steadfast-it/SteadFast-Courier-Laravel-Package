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
