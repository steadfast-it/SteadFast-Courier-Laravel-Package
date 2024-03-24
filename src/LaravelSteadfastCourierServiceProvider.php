<?php

namespace SteadFast\SteadFastCourierLaravelPackage;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelSteadfastCourierServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {

        $package
            ->name('steadfast-courier')
            ->hasConfigFile();
    }
}
