<?php

namespace SteadFast\SteadFastCourierLaravelPackage\Enums;

/**
 * The delivery statuses the Steadfast API reports.
 *
 * Use this instead of comparing raw strings, so a typo is a compile-time
 * problem rather than a branch that silently never runs.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case DeliveredApprovalPending = 'delivered_approval_pending';
    case PartialDeliveredApprovalPending = 'partial_delivered_approval_pending';
    case CancelledApprovalPending = 'cancelled_approval_pending';
    case UnknownApprovalPending = 'unknown_approval_pending';
    case Delivered = 'delivered';
    case PartialDelivered = 'partial_delivered';
    case Cancelled = 'cancelled';
    case Hold = 'hold';
    case InReview = 'in_review';
    case Unknown = 'unknown';

    /**
     * Read the status out of a status-check response, or null when the response
     * carries no recognisable status.
     */
    public static function fromResponse(array $response): ?self
    {
        $status = $response['delivery_status'] ?? $response['status'] ?? null;

        return is_string($status) ? self::tryFrom($status) : null;
    }

    /**
     * Steadfast has settled this parcel and the merchant balance is updated.
     * No further status change is expected.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Delivered, self::PartialDelivered, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * An outcome is recorded but a Steadfast admin has not confirmed it yet, so
     * the merchant balance has not moved.
     */
    public function isAwaitingApproval(): bool
    {
        return match ($this) {
            self::DeliveredApprovalPending,
            self::PartialDeliveredApprovalPending,
            self::CancelledApprovalPending,
            self::UnknownApprovalPending => true,
            default => false,
        };
    }

    /**
     * The parcel is still moving: not delivered, not cancelled.
     */
    public function isInProgress(): bool
    {
        return ! $this->isFinal() && ! $this->isAwaitingApproval();
    }

    /**
     * Needs a human: Steadfast cannot classify the parcel.
     */
    public function needsSupport(): bool
    {
        return $this === self::Unknown || $this === self::UnknownApprovalPending;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not yet delivered or cancelled',
            self::DeliveredApprovalPending => 'Delivered, awaiting admin approval',
            self::PartialDeliveredApprovalPending => 'Partially delivered, awaiting approval',
            self::CancelledApprovalPending => 'Cancelled, awaiting approval',
            self::UnknownApprovalPending => 'Unknown state, needs support intervention',
            self::Delivered => 'Delivered and balance updated',
            self::PartialDelivered => 'Partially delivered and balance updated',
            self::Cancelled => 'Cancelled and balance updated',
            self::Hold => 'On hold',
            self::InReview => 'Order placed, under review',
            self::Unknown => 'Unknown status, contact support',
        };
    }
}
