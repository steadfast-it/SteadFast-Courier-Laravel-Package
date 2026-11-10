<?php

namespace SteadFast\SteadFastCourierLaravelPackage\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SteadFast\SteadFastCourierLaravelPackage\LaravelSteadfastCourierServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelSteadfastCourierServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('steadfast-courier.base_url', 'https://portal.packzy.com/api/v1');
        $app['config']->set('steadfast-courier.api_key', 'test-api-key');
        $app['config']->set('steadfast-courier.secret_key', 'test-secret-key');
        $app['config']->set('steadfast-courier.timeout', 5);
        $app['config']->set('steadfast-courier.connect_timeout', 2);
        $app['config']->set('steadfast-courier.retry.times', 2);
        $app['config']->set('steadfast-courier.retry.sleep', 0);
    }
}
