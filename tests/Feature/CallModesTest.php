<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use JplCodes\Bloomerang\CallMode;
use JplCodes\Bloomerang\Exceptions\AuthenticationFailed;
use JplCodes\Bloomerang\Exceptions\ConnectionFailed;
use JplCodes\Bloomerang\Exceptions\NotFound;
use JplCodes\Bloomerang\Exceptions\RateLimited;
use JplCodes\Bloomerang\Exceptions\RequestRejected;
use JplCodes\Bloomerang\Exceptions\ServerError;
use JplCodes\Bloomerang\Facades\Bloomerang;

beforeEach(fn () => Sleep::fake());

it('uses request mode unless told otherwise', function () {
    expect(Bloomerang::mode())->toBe(CallMode::Request)
        ->and(Bloomerang::inJobMode()->mode())->toBe(CallMode::Job)
        ->and(Bloomerang::inJobMode()->inRequestMode()->mode())->toBe(CallMode::Request)
        ->and(Bloomerang::mode())->toBe(CallMode::Request);
});

describe('request mode', function () {
    it('uses the short timeouts', function () {
        $options = null;
        Http::fake(function (Request $request, array $sent) use (&$options) {
            $options = $sent;

            return Http::response(bloomerangFixture('user-current'));
        });

        Bloomerang::currentUser();

        expect($options['timeout'])->toEqual(4)
            ->and($options['connect_timeout'])->toEqual(2);
    });

    it('never retries', function (Closure $response, string $exception) {
        Http::fake(['*' => $response()]);

        expect(fn () => Bloomerang::currentUser())->toThrow($exception);

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    })->with([
        'rate limited' => [fn () => Http::response(status: 429, headers: ['Retry-After' => '7']), RateLimited::class],
        'server error' => [fn () => Http::response(status: 503), ServerError::class],
        'timeout or network failure' => [fn () => Http::failedConnection(), ConnectionFailed::class],
    ]);

    it('reports how long Bloomerang asked us to wait', function () {
        Http::fake(['*' => Http::response(status: 429, headers: ['Retry-After' => '7'])]);

        try {
            Bloomerang::currentUser();
        } catch (RateLimited $exception) {
            expect($exception->retryAfterSeconds)->toBe(7);

            return;
        }

        $this->fail('RateLimited was not thrown.');
    });
});

describe('job mode', function () {
    it('uses the long timeouts', function () {
        $options = null;
        Http::fake(function (Request $request, array $sent) use (&$options) {
            $options = $sent;

            return Http::response(bloomerangFixture('user-current'));
        });

        Bloomerang::inJobMode()->currentUser();

        expect($options['timeout'])->toEqual(30)
            ->and($options['connect_timeout'])->toEqual(5);
    });

    it('retries a 429 after the time Bloomerang asks for', function () {
        Http::fake(['*' => Http::sequence()
            ->push(status: 429, headers: ['Retry-After' => '7'])
            ->push(bloomerangFixture('user-current')),
        ]);

        expect(Bloomerang::inJobMode()->currentUser()->id)->toBe(3001);

        Http::assertSentCount(2);
        Sleep::assertSequence([Sleep::for(7)->seconds()]);
    });

    it('caps a long Retry-After', function () {
        Http::fake(['*' => Http::sequence()
            ->push(status: 429, headers: ['Retry-After' => '3600'])
            ->push(bloomerangFixture('user-current')),
        ]);

        Bloomerang::inJobMode()->currentUser();

        Sleep::assertSequence([Sleep::for(60)->seconds()]);
    });

    it('backs off between retries on server errors', function () {
        Http::fake(['*' => Http::sequence()
            ->push(status: 503)
            ->push(status: 502)
            ->push(bloomerangFixture('user-current')),
        ]);

        expect(Bloomerang::inJobMode()->currentUser()->id)->toBe(3001);

        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(1000)->milliseconds(), Sleep::for(2000)->milliseconds()]);
    });

    it('retries a timeout or network failure', function () {
        Http::fake(['*' => Http::sequence()
            ->pushFailedConnection()
            ->push(bloomerangFixture('user-current')),
        ]);

        expect(Bloomerang::inJobMode()->currentUser()->id)->toBe(3001);
        Http::assertSentCount(2);
    });

    it('gives up after the configured retries', function () {
        Http::fake(['*' => Http::response(status: 500)]);

        expect(fn () => Bloomerang::inJobMode()->currentUser())->toThrow(ServerError::class);

        Http::assertSentCount(4);
    });

    it('does not retry errors that will not change', function (int $status, string $exception) {
        Http::fake(['*' => Http::response(status: $status)]);

        expect(fn () => Bloomerang::inJobMode()->currentUser())->toThrow($exception);

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    })->with([
        '400' => [400, RequestRejected::class],
        '401' => [401, AuthenticationFailed::class],
        '403' => [403, AuthenticationFailed::class],
        '404' => [404, NotFound::class],
    ]);
});

describe('writes', function () {
    it('never retries a create after a server error or timeout, so no duplicate is made', function (Closure $response, string $exception) {
        Http::fake(['*' => $response()]);

        expect(fn () => Bloomerang::inJobMode()->constituents()->create('Barbara', 'Newperson', 'barbara@example.org'))
            ->toThrow($exception);

        Http::assertSentCount(1);
    })->with([
        'server error' => [fn () => Http::response(status: 503), ServerError::class],
        'timeout or network failure' => [fn () => Http::failedConnection(), ConnectionFailed::class],
    ]);

    it('retries a create that was rate limited', function () {
        Http::fake(['*' => Http::sequence()
            ->push(status: 429)
            ->push(bloomerangFixture('constituent-created')),
        ]);

        expect(Bloomerang::inJobMode()->constituents()->create('Barbara', 'Newperson', 'barbara@example.org')->id)->toBe(1005);
        Http::assertSentCount(2);
    });
});
