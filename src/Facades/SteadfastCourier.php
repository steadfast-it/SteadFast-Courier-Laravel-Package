<?php

namespace SteadFast\SteadFastCourierLaravelPackage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Orders
 *
 * @method static mixed placeOrder(array $data)
 * @method static mixed bulkCreateOrders(array $data)
 *
 * Delivery status and tracking
 * @method static mixed checkDeliveryStatusByConsignmentId(int|string $id)
 * @method static mixed checkDeliveryStatusByInvoiceId(int|string $id)
 * @method static mixed checkDeliveryStatusByTrackingCode(int|string $id)
 * @method static mixed getTrackingsByInvoice(int|string $invoice)
 *
 * Return requests
 * @method static mixed createReturnRequest(array $data)
 * @method static mixed getReturnRequests()
 * @method static mixed getReturnRequest(int|string $id)
 *
 * Pickup requests
 * @method static mixed createPickupRequest(array $data)
 *
 * Account
 * @method static mixed getCurrentBalance()
 * @method static mixed getPayments()
 * @method static mixed getPayment(int|string $paymentId)
 *
 * Reference data
 * @method static mixed getPoliceStations()
 * @method static mixed fraudCheck(int|string $phone)
 *
 * Multi-tenant credentials
 * @method static \SteadFast\SteadFastCourierLaravelPackage\SteadfastCourier withConfig(string $apiKey, string $secretKey, ?string $baseUrl = null)
 *
 * @see \SteadFast\SteadFastCourierLaravelPackage\SteadfastCourier
 */
class SteadfastCourier extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \SteadFast\SteadFastCourierLaravelPackage\SteadfastCourier::class;
    }
}
