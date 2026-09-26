<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use JplCodes\Bloomerang\Exceptions\BloomerangException;
use JplCodes\Bloomerang\Facades\Bloomerang;
use JplCodes\Bloomerang\Tests\TestCase;
use Monolog\LogRecord;

beforeEach(fn () => Sleep::fake());

/**
 * Everything logged, as one string per record.
 *
 * @return list<string>
 */
function loggedText(TestCase $test): array
{
    return array_map(
        fn (LogRecord $record): string => $record->message.' '.json_encode($record->context),
        $test->logHandler()->getRecords(),
    );
}

it('logs each call with a correlation id', function () {
    Http::fake(['*' => Http::response(bloomerangFixture('user-current'))]);

    Bloomerang::currentUser();

    $records = $this->logHandler()->getRecords();

    expect($records)->toHaveCount(1)
        ->and(Str::isUuid($records[0]->context['correlation_id']))->toBeTrue()
        ->and($records[0]->context)->toMatchArray([
            'method' => 'GET',
            'path' => 'user/current',
            'status' => 200,
            'attempt' => 1,
            'mode' => 'request',
        ])
        ->and($records[0]->context)->toHaveKey('duration_ms');
});

it('uses one correlation id across the retries of a call', function () {
    Http::fake(['*' => Http::sequence()->push(status: 503)->push(bloomerangFixture('user-current'))]);

    Bloomerang::inJobMode()->currentUser();

    $records = $this->logHandler()->getRecords();

    expect($records)->toHaveCount(2)
        ->and($records[0]->context['correlation_id'])->toBe($records[1]->context['correlation_id'])
        ->and([$records[0]->context['attempt'], $records[1]->context['attempt']])->toBe([1, 2])
        ->and($records[0]->level->getName())->toBe('WARNING');
});

it('logs query parameter names but not their values', function () {
    Http::fake(['*' => Http::response(bloomerangFixture('search-one'))]);

    Bloomerang::constituents()->searchByEmail('ada@example.org');

    $record = $this->logHandler()->getRecords()[0];

    expect($record->context['query_keys'])->toEqualCanonicalizing(['search', 'skip', 'take'])
        ->and(implode("\n", loggedText($this)))->not->toContain('ada@example.org');
});

it('never logs the API key or a bearer token', function () {
    Http::fake(['*' => Http::sequence()
        ->push(bloomerangFixture('user-current'))
        ->push(status: 401)
        ->push(status: 500)
        ->pushFailedConnection()
        ->push(bloomerangFixture('user-current')),
    ]);

    Bloomerang::currentUser();
    rescue(fn () => Bloomerang::currentUser(), report: false);
    rescue(fn () => Bloomerang::currentUser(), report: false);
    rescue(fn () => Bloomerang::currentUser(), report: false);
    Bloomerang::withToken('an-oauth-access-token')->currentUser();

    $logged = implode("\n", loggedText($this));

    expect($this->logHandler()->getRecords())->toHaveCount(5)
        ->and($logged)->not->toContain(TestCase::API_KEY)
        ->and($logged)->not->toContain('an-oauth-access-token');
});

it('keeps the key and the response body out of exceptions', function (Closure $response) {
    Http::fake(['*' => $response()]);

    try {
        Bloomerang::constituents()->find(1001);
    } catch (BloomerangException $exception) {
        expect((string) $exception)->not->toContain(TestCase::API_KEY)
            ->and((string) $exception)->not->toContain('a private donor note')
            ->and($exception->getMessage())->toContain('GET constituent/1001')
            ->and($exception->getMessage())->toContain($exception->correlationId);

        return;
    }

    $this->fail('No exception was thrown.');
})->with([
    'unauthorized' => [fn () => Http::response(['Message' => 'a private donor note'], 401)],
    'rejected' => [fn () => Http::response(['Message' => 'a private donor note'], 400)],
    'server error' => [fn () => Http::response(['Message' => 'a private donor note'], 500)],
    'not JSON' => [fn () => Http::response('<html>a private donor note</html>', 200)],
    'network failure' => [fn () => Http::failedConnection('a private donor note')],
]);

it('writes to the configured channel only when logging is on', function () {
    config(['bloomerang.logging.enabled' => false]);
    Http::fake(['*' => Http::response(bloomerangFixture('user-current'))]);

    Bloomerang::currentUser();

    expect($this->logHandler()->getRecords())->toBeEmpty();
});
