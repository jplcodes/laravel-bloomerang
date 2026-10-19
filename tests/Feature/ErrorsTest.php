<?php

use Illuminate\Support\Facades\Http;
use JplCodes\Bloomerang\Exceptions\AuthenticationFailed;
use JplCodes\Bloomerang\Exceptions\BloomerangException;
use JplCodes\Bloomerang\Exceptions\NotFound;
use JplCodes\Bloomerang\Exceptions\RateLimited;
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

it('keeps the ErrorCode from the error body', function (int $status, array $body, string $exception) {
    Http::fake(['*' => Http::response($body, $status)]);

    try {
        Bloomerang::currentUser();
    } catch (BloomerangException $thrown) {
        expect($thrown)->toBeInstanceOf($exception)
            ->and($thrown->errorCode)->toBe($body['ErrorCode']);

        return;
    }

    $this->fail('No exception was thrown.');
})->with([
    '400' => fn () => [400, bloomerangFixture('error-400'), RequestRejected::class],
    '401' => fn () => [401, bloomerangFixture('error-401'), AuthenticationFailed::class],
    '404' => fn () => [404, bloomerangFixture('error-404'), NotFound::class],
    '429' => fn () => [429, bloomerangFixture('error-400'), RateLimited::class],
    '500' => fn () => [500, bloomerangFixture('error-400'), ServerError::class],
]);

it('has no error code when the error body does not carry one', function (mixed $body) {
    Http::fake(['*' => Http::response($body, 400)]);

    try {
        Bloomerang::currentUser();
    } catch (RequestRejected $thrown) {
        expect($thrown->errorCode)->toBeNull();

        return;
    }

    $this->fail('RequestRejected was not thrown.');
})->with([
    'empty' => [''],
    'not JSON' => ['<html>Bad Request</html>'],
    'a list' => [[4001]],
    'an ErrorCode that is not an integer' => [['Message' => 'Example message.', 'ErrorCode' => '4001']],
]);

it('never puts the error body message in the exception or the logs', function () {
    Http::fake(['*' => Http::response(['Message' => 'No match for ada@example.org', 'ErrorCode' => 4041], 404)]);

    try {
        Bloomerang::constituents()->find(1001);
    } catch (NotFound $thrown) {
        expect($thrown->getMessage())->not->toContain('ada@example.org')
            ->and(json_encode(get_object_vars($thrown)))->not->toContain('ada@example.org');

        foreach ($this->logHandler()->getRecords() as $record) {
            expect(json_encode([$record->message, $record->context]))->not->toContain('ada@example.org');
        }

        return;
    }

    $this->fail('NotFound was not thrown.');
});

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

it('does not follow redirects, so the key never leaves the configured host', function () {
    Http::fake(['bloomerang.test/v2/user/current' => Http::response(status: 302, headers: ['Location' => 'https://elsewhere.test/'])]);

    try {
        Bloomerang::currentUser();
    } catch (RequestRejected $exception) {
        expect($exception->status)->toBe(302);
        Http::assertSentCount(1);

        return;
    }

    $this->fail('RequestRejected was not thrown.');
});
