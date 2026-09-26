<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use JplCodes\Bloomerang\Exceptions\MissingCredentials;
use JplCodes\Bloomerang\Facades\Bloomerang;
use JplCodes\Bloomerang\Tests\TestCase;

it('sends the API key on every call', function () {
    Http::fake([
        'bloomerang.test/v2/user/current' => Http::response(bloomerangFixture('user-current')),
        'bloomerang.test/v2/constituent/*' => Http::response(bloomerangFixture('constituent')),
        'bloomerang.test/v2/constituents/search*' => Http::response(bloomerangFixture('search-one')),
    ]);

    Bloomerang::currentUser();
    Bloomerang::inJobMode()->constituents()->find(1001);
    Bloomerang::constituents()->search('ada');
    Bloomerang::request('GET', 'constituent/1001');

    Http::assertSentCount(4);
    expect(Http::recorded()->every(fn (array $pair) => $pair[0]->header('X-API-KEY') === [TestCase::API_KEY]
        && ! $pair[0]->hasHeader('Authorization')))->toBeTrue();
});

it('sends a supplied bearer token instead of the API key', function () {
    Http::fake(['*' => Http::response(bloomerangFixture('user-current'))]);

    Bloomerang::withToken('an-oauth-access-token')->currentUser();

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer an-oauth-access-token')
        && ! $request->hasHeader('X-API-KEY'));
});

it('keeps the bearer token when the mode changes', function () {
    Http::fake(['*' => Http::response(bloomerangFixture('user-current'))]);

    Bloomerang::withToken('an-oauth-access-token')->inJobMode()->currentUser();

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer an-oauth-access-token'));
});

it('refuses to call Bloomerang without a key', function (?string $key) {
    config(['bloomerang.api_key' => $key]);
    Http::fake();

    expect(fn () => Bloomerang::currentUser())->toThrow(MissingCredentials::class);
    Http::assertNothingSent();
})->with(['null' => [null], 'empty' => ['']]);

it('refuses an empty bearer token', function () {
    Http::fake();

    expect(fn () => Bloomerang::withToken('')->currentUser())->toThrow(MissingCredentials::class);
    Http::assertNothingSent();
});
