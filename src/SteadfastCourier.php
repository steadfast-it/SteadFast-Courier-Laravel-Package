<?php

namespace SteadFast\SteadFastCourierLaravelPackage;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SteadFast\SteadFastCourierLaravelPackage\Exceptions\InvalidRequestException;
use SteadFast\SteadFastCourierLaravelPackage\Exceptions\SteadfastCourierException;

class SteadfastCourier
{
    /**
     * Steadfast accepts at most this many orders in one bulk request.
     */
    public const MAX_BULK_ORDERS = 500;

    protected $baseUrl;

    protected $apiKey;

    protected $secretKey;

    protected $contentType;

    protected $timeout;

    protected $connectTimeout;

    protected $retryTimes;

    protected $retrySleep;

    public function __construct()
    {
        $this->baseUrl = config('steadfast.base_url');
        $this->apiKey = config('steadfast.api_key');
        $this->secretKey = config('steadfast.secret_key');
        $this->contentType = config('steadfast.content_type', 'application/json');
        $this->timeout = config('steadfast.timeout', 30);
        $this->connectTimeout = config('steadfast.connect_timeout', 10);
        $this->retryTimes = config('steadfast.retry.times', 2);
        $this->retrySleep = config('steadfast.retry.sleep', 200);
    }

    /**
     * Return a copy of this client that uses the given credentials.
     *
     * This intentionally does NOT mutate the current instance. The facade caches
     * the instance it resolves, so mutating $this would leak these credentials
     * into every later call in the same process (request, queue worker, Octane
     * worker) and bill the wrong merchant account.
     */
    public function withConfig(string $apiKey, string $secretKey, ?string $baseUrl = null): self
    {
        $clone = clone $this;

        $clone->apiKey = $apiKey;
        $clone->secretKey = $secretKey;
        $clone->baseUrl = $baseUrl ?? $this->baseUrl;

        return $clone;
    }

    // ---------------------------------------------------------------------
    // Orders
    // ---------------------------------------------------------------------

    /**
     * POST /create_order
     *
     * Recognised fields: invoice, recipient_name, recipient_phone,
     * recipient_address, recipient_email, alternative_phone, item_description,
     * cod_amount, note. Unknown fields are passed through untouched, so new API
     * fields work without a package release.
     */
    public function placeOrder($data)
    {
        return $this->post('/create_order', $data);
    }

    /**
     * POST /create_order/bulk-order
     *
     * The body is the list of orders itself. Up to MAX_BULK_ORDERS per call.
     */
    public function bulkCreateOrders($data)
    {
        $orders = $this->validateOrderList($data);

        return $this->post('/create_order/bulk-order', $orders);
    }

    // ---------------------------------------------------------------------
    // Delivery status and tracking
    // ---------------------------------------------------------------------

    /**
     * GET /status_by_cid/{id}
     */
    public function checkDeliveryStatusByConsignmentId($id)
    {
        return $this->get('/status_by_cid/'.$this->requiredSegment($id, 'consignment id'));
    }

    /**
     * GET /status_by_invoice/{invoice}
     *
     * Not listed in the current Postman collection but still served, and
     * documented by this package since 1.x.
     */
    public function checkDeliveryStatusByInvoiceId($id)
    {
        return $this->get('/status_by_invoice/'.$this->requiredSegment($id, 'invoice'));
    }

    /**
     * GET /status_by_trackingcode/{code}
     *
     * Not listed in the current Postman collection but still served, and
     * documented by this package since 1.x.
     */
    public function checkDeliveryStatusByTrackingCode($id)
    {
        return $this->get('/status_by_trackingcode/'.$this->requiredSegment($id, 'tracking code'));
    }

    /**
     * GET /trackings_by_invoice/{invoice}
     *
     * The full tracking history for a consignment, rather than just its current
     * delivery status.
     */
    public function getTrackingsByInvoice($invoice)
    {
        return $this->get('/trackings_by_invoice/'.$this->requiredSegment($invoice, 'invoice'));
    }

    // ---------------------------------------------------------------------
    // Return requests
    // ---------------------------------------------------------------------

    /**
     * POST /create_return_request
     *
     * Identify the consignment by one of consignment_id, invoice or
     * tracking_code, and give a reason.
     */
    public function createReturnRequest(array $data)
    {
        $hasReference = array_filter(
            ['consignment_id', 'invoice', 'tracking_code'],
            fn (string $key) => isset($data[$key]) && $data[$key] !== ''
        );

        if ($hasReference === []) {
            throw InvalidRequestException::missingConsignmentReference();
        }

        return $this->post('/create_return_request', $data);
    }

    /**
     * GET /get_return_requests
     */
    public function getReturnRequests()
    {
        return $this->get('/get_return_requests');
    }

    /**
     * GET /get_return_request/{id}
     */
    public function getReturnRequest($id)
    {
        return $this->get('/get_return_request/'.$this->requiredSegment($id, 'return request id'));
    }

    // ---------------------------------------------------------------------
    // Pickup requests
    // ---------------------------------------------------------------------

    /**
     * POST /create_pickup_request
     *
     * Recognised fields: address_id, police_station_id, address,
     * contact_number, note, estim_qty.
     */
    public function createPickupRequest(array $data)
    {
        return $this->post('/create_pickup_request', $data);
    }

    // ---------------------------------------------------------------------
    // Account
    // ---------------------------------------------------------------------

    /**
     * GET /get_balance
     */
    public function getCurrentBalance()
    {
        return $this->get('/get_balance');
    }

    /**
     * GET /payments
     */
    public function getPayments()
    {
        return $this->get('/payments');
    }

    /**
     * GET /payments/{payment_id}
     */
    public function getPayment($paymentId)
    {
        return $this->get('/payments/'.$this->requiredSegment($paymentId, 'payment id'));
    }

    // ---------------------------------------------------------------------
    // Reference data
    // ---------------------------------------------------------------------

    /**
     * GET /police_stations
     *
     * Districts with their police stations, for the police_station_id a pickup
     * request needs.
     */
    public function getPoliceStations()
    {
        return $this->get('/police_stations');
    }

    /**
     * GET /fraud_check/{phone}
     *
     * A recipient's delivery and cancellation history across Steadfast.
     */
    public function fraudCheck($phone)
    {
        return $this->get('/fraud_check/'.$this->requiredSegment($phone, 'phone number'));
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * Reject bulk input that cannot succeed, before spending a network call.
     *
     * @return array<int, mixed>
     */
    protected function validateOrderList($data): array
    {
        if (! is_array($data) || $data === []) {
            throw InvalidRequestException::emptyOrderList();
        }

        if (! array_is_list($data)) {
            throw InvalidRequestException::notAListOfOrders();
        }

        foreach ($data as $order) {
            if (! is_array($order)) {
                throw InvalidRequestException::notAListOfOrders();
            }
        }

        if (count($data) > self::MAX_BULK_ORDERS) {
            throw InvalidRequestException::tooManyOrders(count($data), self::MAX_BULK_ORDERS);
        }

        return $data;
    }

    /**
     * Percent-encode a required value that is interpolated into the URL path.
     *
     * Merchant invoice numbers and payment ids are caller-supplied. Unencoded,
     * an invoice such as "INV/2024/01" adds path segments and reaches a
     * different endpoint, and a "#" truncates the path into a URL fragment.
     */
    protected function requiredSegment($value, string $name): string
    {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            throw InvalidRequestException::emptyIdentifier($name);
        }

        return rawurlencode((string) $value);
    }

    protected function get(string $uri)
    {
        return $this->send('GET', $uri);
    }

    protected function post(string $uri, $data)
    {
        return $this->send('POST', $uri, ['json' => $data]);
    }

    protected function send(string $method, string $uri, array $options = [])
    {
        $url = $this->baseUrl.$uri;

        return $this->decode($method, $url, $this->client($method)->send($method, $url, $options));
    }

    protected function client(string $method): PendingRequest
    {
        $client = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => $this->contentType,
            'Accept' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);

        // Only idempotent reads are retried. Retrying a create_order call that
        // timed out could book the same parcel twice, and the Steadfast API
        // offers no idempotency key that would make that safe.
        if ($method === 'GET' && $this->retryTimes > 1) {
            $client = $client->retry($this->retryTimes, $this->retrySleep, throw: false);
        }

        return $client;
    }

    /**
     * Steadfast reports its own validation failures as a JSON body, so those are
     * returned to the caller untouched. A body that is not JSON at all is a
     * transport or gateway failure and is raised instead of returned as null.
     */
    protected function decode(string $method, string $url, Response $response)
    {
        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw SteadfastCourierException::unexpectedResponse($method, $url, $response);
        }

        return $decoded;
    }
}
