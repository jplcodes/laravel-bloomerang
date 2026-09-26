<?php

namespace JplCodes\Bloomerang\Tests;

use Illuminate\Support\Facades\Http;
use JplCodes\Bloomerang\BloomerangServiceProvider;
use JplCodes\Bloomerang\Facades\Bloomerang;
use Monolog\Handler\TestHandler;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public const string API_KEY = 'test-key-not-a-real-bloomerang-key';

    public const string BASE_URL = 'https://bloomerang.test/v2';

    protected function setUp(): void
    {
        parent::setUp();

        // No test may ever reach Bloomerang: every request must be faked.
        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [BloomerangServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Bloomerang' => Bloomerang::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('bloomerang.api_key', self::API_KEY);
        $app['config']->set('bloomerang.base_url', self::BASE_URL);
        $app['config']->set('bloomerang.job_mode.jitter_ms', 0);
        $app['config']->set('bloomerang.logging.channel', 'bloomerang-test');
        $app['config']->set('logging.channels.bloomerang-test', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
            'level' => 'debug',
        ]);
    }

    /**
     * The handler that captures everything the package logs during a test.
     */
    public function logHandler(): TestHandler
    {
        return $this->app['log']->channel('bloomerang-test')->getLogger()->getHandlers()[0];
    }
}
