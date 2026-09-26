<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Sleep;
use JplCodes\Bloomerang\Exceptions\NotFound;
use JplCodes\Bloomerang\Exceptions\ServerError;
use JplCodes\Bloomerang\Facades\Bloomerang;

beforeEach(fn () => Sleep::fake());

it('sends the method, path, query and body it is given', function () {
    Http::fake(['bloomerang.test/v2/some/endpoint*' => Http::response(['Id' => 42, 'Name' => 'Made up'])]);

    $response = Bloomerang::request('POST', 'some/endpoint', ['flag' => 'yes'], ['Name' => 'Made up']);

    expect($response)->toBe(['Id' => 42, 'Name' => 'Made up']);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://bloomerang.test/v2/some/endpoint?flag=yes'
        && $request->body() === json_encode(['Name' => 'Made up']));
});

it('accepts a path with a leading slash', function () {
    Http::fake(['bloomerang.test/v2/user/current' => Http::response(bloomerangFixture('user-current'))]);

    expect(Bloomerang::request('GET', '/user/current')['Id'])->toBe(3001);
});

it('returns null for an empty response', function () {
    Http::fake(['*' => Http::response(status: 204)]);

    expect(Bloomerang::request('DELETE', 'some/endpoint/1'))->toBeNull();
});

it('refuses a full URL, so the key is only ever sent to the configured base URL', function (string $path) {
    Http::fake();

    expect(fn () => Bloomerang::request('GET', $path))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
})->with([
    'https' => ['https://elsewhere.test/steal'],
    'protocol-relative' => ['//elsewhere.test/steal'],
]);

it('maps errors like the wrapped calls do', function () {
    Http::fake(['*' => Http::response(status: 404)]);

    Bloomerang::request('GET', 'some/endpoint/1');
})->throws(NotFound::class);

it('retries a PUT in job mode but not a POST or PATCH', function (string $method, int $attempts) {
    Http::fake(['*' => Http::sequence()->push(status: 503)->push(['ok' => true])]);

    rescue(fn () => Bloomerang::inJobMode()->request($method, 'some/endpoint', body: ['a' => 1]), report: false);

    Http::assertSentCount($attempts);
})->with([
    'PUT' => ['PUT', 2],
    'POST' => ['POST', 1],
    'PATCH' => ['PATCH', 1],
]);

it('throws the last error when a POST fails', function () {
    Http::fake(['*' => Http::response(status: 503)]);

    Bloomerang::inJobMode()->request('POST', 'some/endpoint', body: ['a' => 1]);
})->throws(ServerError::class);

it('walks any paged endpoint', function () {
    Http::fake(['bloomerang.test/v2/funds*' => Http::sequence()
        ->push(['Total' => 3, 'TotalFiltered' => 3, 'Start' => 0, 'ResultCount' => 2, 'Results' => [['Id' => 1], ['Id' => 2]]])
        ->push(['Total' => 3, 'TotalFiltered' => 3, 'Start' => 2, 'ResultCount' => 1, 'Results' => [['Id' => 3]]]),
    ]);

    $funds = Bloomerang::paginate('funds', ['isActive' => 'true']);

    expect($funds)->toBeInstanceOf(LazyCollection::class)
        ->and($funds->pluck('Id')->all())->toBe([1, 2, 3]);

    Http::assertSent(fn (Request $request) => $request->data() === ['isActive' => 'true', 'skip' => '0', 'take' => '50']);
    Http::assertSent(fn (Request $request) => $request->data()['skip'] === '2');
});
