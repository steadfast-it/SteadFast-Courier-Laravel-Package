<?php

namespace SteadFast\SteadFastCourierLaravelPackage;

use Illuminate\Support\Facades\Http;

class SteadfastCourier
{

    protected $baseUrl;
    protected $apiKey;
    protected $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('steadfast-courier.base_url');
        $this->apiKey = config('steadfast-courier.api_key');
        $this->secretKey = config('steadfast-courier.secret_key');
    }

    public function placeOrder($data)
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl.'/create_order', json_encode($data));

        return $response->json();
    }

    public function bulkCreateOrders($data)
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl.'/create_order/bulk-order', ['data' => json_encode($data)]);

        return $response->json();
    }

    public function checkDeliveryStatusByConsignmentId($id)
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl.'/status_by_cid/'.$id);

        return $response->json();
    }


    public function checkDeliveryStatusByInvoiceId($id)
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl.'/status_by_invoice/'.$id);

        return $response->json();
    }

    public function checkDeliveryStatusByTrackingCode($id)
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl.'/status_by_trackingcode/'.$id);

        return $response->json();
    }

    public function getCurrentBalance()
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl.'/get_balance');

        return $response->json();
    }
}
