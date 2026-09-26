<?php

use JplCodes\Bloomerang\Bloomerang;

it('falls back to the public base URL when none is set', function (?string $baseUrl) {
    config(['bloomerang.base_url' => $baseUrl]);

    expect(app(Bloomerang::class)->baseUrl())->toBe('https://api.bloomerang.co/v2');
})->with(['null' => [null], 'empty' => ['']]);

it('publishes its config file', function () {
    $this->artisan('vendor:publish', ['--tag' => 'bloomerang-config', '--force' => true])->assertSuccessful();

    expect(config_path('bloomerang.php'))->toBeFile();

    unlink(config_path('bloomerang.php'));
});
