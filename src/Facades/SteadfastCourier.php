<?php

namespace SteadFast\SteadFastCourierLaravelPackage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \SteadFast\SteadFastCourierLaravelPackage\SteadfastCourier
 */
class SteadfastCourier extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \SteadFast\SteadFastCourierLaravelPackage\SteadfastCourier::class;
    }
}
