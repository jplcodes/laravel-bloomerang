<?php

use Illuminate\Support\Facades\Http;
use JplCodes\Bloomerang\Exceptions\BloomerangException;
use JplCodes\Bloomerang\Exceptions\RequestRejected;
use JplCodes\Bloomerang\Exceptions\ServerError;
use JplCodes\Bloomerang\Exceptions\UnexpectedResponse;
use JplCodes\Bloomerang\Facades\Bloomerang;

it('maps each status to its exception and keeps the status', function (int $status, string $exception) {
    Http::fake(['*' => Http::response(status: $status)]);

    try {
        Bloomerang::currentUser();
    } catch (BloomerangException $thrown) {
        expect($thrown)->toBeInstanceOf($exception)
            ->and($thrown->status)->toBe($status);

        return;
    }

    $this->fail('No exception was thrown.');
})->with([
    '422' => [422, RequestRejected::class],
    '500' => [500, ServerError::class],
    '504' => [504, ServerError::class],
]);

it('rejects a successful response that is not JSON', function () {
    Http::fake(['*' => Http::response('<html>maintenance</html>')]);

    Bloomerang::currentUser();
})->throws(UnexpectedResponse::class);

it('rejects a page without results', function () {
    Http::fake(['*' => Http::response(['Total' => 1])]);

    Bloomerang::constituents()->search('ada');
})->throws(UnexpectedResponse::class);

it('rejects a constituent response that is a list', function () {
    Http::fake(['*' => Http::response([bloomerangFixture('constituent')])]);

    Bloomerang::constituents()->find(1001);
})->throws(UnexpectedResponse::class);

it('rejects a successful response that is empty or not an object', function (mixed $body) {
    Http::fake(['bloomerang.test/v2/constituent/1001' => Http::response($body)]);

    Bloomerang::constituents()->find(1001);
})->throws(UnexpectedResponse::class)->with([
    'empty' => [''],
    'a JSON string' => ['"ok"'],
]);
