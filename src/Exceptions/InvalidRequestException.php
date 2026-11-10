<?php

namespace SteadFast\SteadFastCourierLaravelPackage\Exceptions;

/**
 * The request was rejected locally, before any network call, because it cannot
 * succeed as written. Only cases that are certainly invalid are raised here:
 * Steadfast's own field validation is left to the API, which reports it as a
 * JSON body that the client returns to the caller untouched.
 */
class InvalidRequestException extends SteadfastCourierException
{
    public static function missingBaseUrl(): self
    {
        return new self(
            'No Steadfast base URL is configured. Set STEADFAST_BASE_URL in your .env file, '
            .'or publish the package config with: '
            .'php artisan vendor:publish --tag="steadfast-config"'
        );
    }

    public static function emptyIdentifier(string $name): self
    {
        return new self(sprintf(
            'A %s is required: an empty value would be sent as a URL with a missing path segment.',
            $name
        ));
    }

    public static function emptyOrderList(): self
    {
        return new self('bulkCreateOrders() needs at least one order.');
    }

    public static function notAListOfOrders(): self
    {
        return new self(
            'bulkCreateOrders() expects a list of orders, each one an array of fields. '
            .'Pass [[...], [...]] rather than a single [...] order.'
        );
    }

    public static function tooManyOrders(int $count, int $max): self
    {
        return new self(sprintf(
            'Steadfast accepts at most %d orders per bulk request, %d given. Send them in chunks.',
            $max,
            $count
        ));
    }

    public static function missingConsignmentReference(): self
    {
        return new self(
            'A return request needs one of consignment_id, invoice or tracking_code.'
        );
    }
}
