<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use JplCodes\Bloomerang\Facades\Bloomerang;

it('returns the Bloomerang user who owns the key', function () {
    Http::fake(['bloomerang.test/v2/user/current' => Http::response(bloomerangFixture('user-current'))]);

    $user = Bloomerang::currentUser();

    expect($user->id)->toBe(3001)
        ->and($user->name)->toBe('Example Integration User')
        ->and($user->email)->toBe('integration@example.org')
        ->and($user->userName)->toBe('integration.user')
        ->and($user->isActive)->toBeTrue()
        ->and($user->permissionLevel)->toBe('Standard');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://bloomerang.test/v2/user/current');
});
