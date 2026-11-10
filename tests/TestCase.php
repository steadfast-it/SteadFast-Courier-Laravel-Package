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
        $app['config']->set('steadfast.base_url', 'https://portal.packzy.com/api/v1');
        $app['config']->set('steadfast.api_key', 'test-api-key');
        $app['config']->set('steadfast.secret_key', 'test-secret-key');
        $app['config']->set('steadfast.timeout', 5);
        $app['config']->set('steadfast.connect_timeout', 2);
        $app['config']->set('steadfast.retry.times', 2);
        $app['config']->set('steadfast.retry.sleep', 0);
    }
}
