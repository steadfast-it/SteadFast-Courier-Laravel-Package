<?php

namespace SteadFast\SteadFastCourierLaravelPackage\Exceptions;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RuntimeException;

class SteadfastCourierException extends RuntimeException
{
    protected ?Response $response = null;

    /**
     * The API answered with something that is not JSON at all: an HTML gateway
     * error, a proxy notice, an empty body. This used to be handed back as a
     * silent null, which callers could not tell apart from an empty result.
     */
    public static function unexpectedResponse(string $method, string $url, Response $response): self
    {
        $body = trim((string) $response->body());

        $exception = new self(sprintf(
            'Steadfast returned a non-JSON response for %s %s (HTTP %d): %s',
            $method,
            $url,
            $response->status(),
            $body === '' ? '<empty body>' : Str::limit($body, 200)
        ));

        $exception->response = $response;

        return $exception;
    }

    /**
     * The response that could not be decoded, for logging and debugging.
     */
    public function response(): ?Response
    {
        return $this->response;
    }

    public function status(): ?int
    {
        return $this->response?->status();
    }
}
