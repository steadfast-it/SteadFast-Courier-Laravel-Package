<?php

namespace SteadFast\SteadFastCourierLaravelPackage;

use Illuminate\Support\Facades\Http;

class SteadfastCourier
{
    protected $baseUrl;

    protected $apiKey;

    protected $secretKey;

    protected $contentType;

    protected $webhookSecret;

    public function __construct()
    {
        $this->baseUrl = config('steadfast-courier.base_url');
        $this->apiKey = config('steadfast-courier.api_key');
        $this->secretKey = config('steadfast-courier.secret_key');
        $this->contentType = config('steadfast-courier.content_type', 'application/json');
        $this->webhookSecret = config('steadfast-courier.webhook_secret');
    }

    public function withConfig(string $apiKey, string $secretKey, ?string $baseUrl = null): self
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->baseUrl = $baseUrl ?? $this->baseUrl;

        return $this;
    }

    public function placeOrder(array $data)
    {
        return $this->post('/create_order', $data);
    }

    public function bulkCreateOrders(array $data)
    {
        return $this->post('/create_order/bulk-order', ['data' => $data]);
    }

    public function bulkCreateOrdersExtended(array $data)
    {
        return $this->post('/create_order/bulk-order/extended', ['data' => $data]);
    }

    public function ping()
    {
        return $this->get('/ping', false);
    }

    public function checkDeliveryStatusByConsignmentId($id)
    {
        return $this->get('/status_by_cid/'.rawurlencode((string) $id));
    }

    public function checkDeliveryStatusByInvoiceId($id)
    {
        return $this->get('/status_by_invoice/'.rawurlencode((string) $id));
    }

    public function checkDeliveryStatusByTrackingCode($id)
    {
        return $this->get('/status_by_trackingcode/'.rawurlencode((string) $id));
    }

    public function checkDeliveryStatusWithReturnByConsignmentId($id)
    {
        return $this->get('/status_with_return_status_by_cid/'.rawurlencode((string) $id));
    }

    public function checkDeliveryStatusWithReturnStatusByConsignmentId($id)
    {
        return $this->checkDeliveryStatusWithReturnByConsignmentId($id);
    }

    public function getTrackingsByInvoice($invoice)
    {
        return $this->get('/trackings_by_invoice/'.rawurlencode((string) $invoice));
    }

    public function getTrackingsByInvoiceId($invoice)
    {
        return $this->getTrackingsByInvoice($invoice);
    }

    public function createPickupRequest(array $data)
    {
        return $this->post('/create_pickup_request', $data);
    }

    public function createReturnRequest(array $data)
    {
        return $this->post('/create_return_request', $data);
    }

    public function getReturnRequests(?int $page = null)
    {
        return $this->get('/get_return_requests', true, $page === null ? [] : ['page' => $page]);
    }

    public function getReturnRequest($id)
    {
        return $this->get('/get_return_request/'.rawurlencode((string) $id));
    }

    public function getCurrentBalance()
    {
        return $this->get('/get_balance');
    }

    public function getPayments(?int $page = null)
    {
        return $this->get('/payments', true, $page === null ? [] : ['page' => $page]);
    }

    public function getPayment($paymentId)
    {
        return $this->get('/payments/'.rawurlencode((string) $paymentId));
    }

    public function getPoliceStations()
    {
        return $this->get('/police_stations');
    }

    public function getFraudScore($phone)
    {
        return $this->get('/fraud_check/score/'.rawurlencode((string) $phone));
    }

    public function verifyWebhookSignature(string $payload, string $signature, ?string $secret = null): bool
    {
        $secret = $secret ?? $this->webhookSecret;

        if ($secret === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    public function parseWebhookPayload(string $payload): array
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('The webhook payload must be valid JSON.');
        }

        return $decoded;
    }

    protected function headers(bool $authenticated = true): array
    {
        $headers = ['Content-Type' => $this->contentType];

        if ($authenticated) {
            $headers['Api-Key'] = $this->apiKey;
            $headers['Secret-Key'] = $this->secretKey;
        }

        return $headers;
    }

    protected function get(string $path, bool $authenticated = true, array $query = []): array
    {
        return Http::withHeaders($this->headers($authenticated))
            ->get($this->baseUrl.$path, $query)
            ->json();
    }

    protected function post(string $path, array $data): array
    {
        return Http::withHeaders($this->headers())
            ->post($this->baseUrl.$path, $data)
            ->json();
    }
}
