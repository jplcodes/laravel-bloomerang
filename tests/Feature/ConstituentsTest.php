<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use JplCodes\Bloomerang\Data\Constituent;
use JplCodes\Bloomerang\Data\Household;
use JplCodes\Bloomerang\Exceptions\NotFound;
use JplCodes\Bloomerang\Facades\Bloomerang;

describe('find', function () {
    it('gets one constituent with its memberships', function () {
        Http::fake(['bloomerang.test/v2/constituent/1002' => Http::response(bloomerangFixture('constituent-memberships-several'))]);

        $constituent = Bloomerang::constituents()->find(1002);

        expect($constituent)->toBeInstanceOf(Constituent::class)
            ->and($constituent->id)->toBe(1002)
            ->and($constituent->memberships())->toHaveCount(3);

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://bloomerang.test/v2/constituent/1002');
    });

    it('reports absent membership data rather than an empty list', function () {
        Http::fake(['bloomerang.test/v2/constituent/1004' => Http::response(bloomerangFixture('constituent-membership-absent'))]);

        expect(Bloomerang::constituents()->find(1004)->hasMembershipData())->toBeFalse();
    });

    it('throws NotFound for an unknown id without retrying, even in job mode', function () {
        Http::fake(['bloomerang.test/v2/constituent/*' => Http::response(status: 404)]);

        expect(fn () => Bloomerang::inJobMode()->constituents()->find(404404))->toThrow(NotFound::class);

        Http::assertSentCount(1);
    });
});

describe('findMany', function () {
    it('sends ids 50 at a time, joined with a pipe', function () {
        $ids = range(1, 120);

        Http::fake(['bloomerang.test/v2/constituents*' => function (Request $request) {
            return Http::response(constituentsPage(array_map(intval(...), explode('|', $request->data()['id']))));
        }]);

        $constituents = Bloomerang::constituents()->findMany($ids);

        expect($constituents)->toBeInstanceOf(LazyCollection::class)
            ->and($constituents->map(fn (Constituent $constituent) => $constituent->id)->all())->toBe($ids);

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request->data()['id'] === implode('|', range(1, 50))
            && (int) $request->data()['take'] === 50 && (int) $request->data()['skip'] === 0);
        Http::assertSent(fn (Request $request) => $request->data()['id'] === implode('|', range(51, 100)));
        Http::assertSent(fn (Request $request) => $request->data()['id'] === implode('|', range(101, 120)));
    });

    it('includes the membership data of each constituent', function () {
        Http::fake(['bloomerang.test/v2/constituents*' => Http::response(constituentsPage([1001, 1002]))]);

        $constituents = Bloomerang::constituents()->findMany([1001, 1002])->all();

        expect($constituents[0]->hasMembershipData())->toBeTrue()
            ->and($constituents[0]->memberships()[0]->isCurrent())->toBeTrue();
    });

    it('follows further pages within a batch', function () {
        Http::fake(['bloomerang.test/v2/constituents*' => Http::sequence()
            ->push(constituentsPage([1, 2], totalFiltered: 3))
            ->push(constituentsPage([3], totalFiltered: 3, start: 2)),
        ]);

        $ids = Bloomerang::constituents()->findMany([1, 2, 3])
            ->map(fn (Constituent $constituent) => $constituent->id)
            ->all();

        expect($ids)->toBe([1, 2, 3]);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => (int) $request->data()['skip'] === 2);
    });

    it('asks for each id once', function () {
        Http::fake(['bloomerang.test/v2/constituents*' => Http::response(constituentsPage([7, 8]))]);

        Bloomerang::constituents()->findMany([7, 8, 7, 8])->all();

        Http::assertSent(fn (Request $request) => $request->data()['id'] === '7|8');
    });

    it('leaves out ids Bloomerang does not return', function () {
        Http::fake(['bloomerang.test/v2/constituents*' => Http::response(constituentsPage([1]))]);

        expect(Bloomerang::constituents()->findMany([1, 2])->all())->toHaveCount(1);
    });

    it('sends nothing for an empty list', function () {
        Http::fake();

        expect(Bloomerang::constituents()->findMany([])->all())->toBe([]);
        Http::assertNothingSent();
    });
});

describe('search', function () {
    it('returns people and households, without membership data', function () {
        Http::fake(['bloomerang.test/v2/constituents/search*' => Http::response(bloomerangFixture('search-one'))]);

        $results = Bloomerang::constituents()->search('ada');

        expect($results)->toBeInstanceOf(Collection::class)->toHaveCount(4)
            ->and($results[0])->toBeInstanceOf(Constituent::class)
            ->and($results[0]->hasMembershipData())->toBeFalse()
            ->and($results[2])->toBeInstanceOf(Household::class)
            ->and($results[2]->memberIds)->toBe([1001, 1012]);

        Http::assertSent(fn (Request $request) => $request->data()['search'] === 'ada'
            && (int) $request->data()['take'] === 50);
    });

    it('walks every page', function () {
        $page = bloomerangFixture('search-several');

        Http::fake(['bloomerang.test/v2/constituents/search*' => Http::sequence()
            ->push([...$page, 'TotalFiltered' => 5])
            ->push([...$page, 'Start' => 3, 'TotalFiltered' => 5, 'ResultCount' => 2, 'Results' => array_slice($page['Results'], 0, 2)]),
        ]);

        expect(Bloomerang::constituents()->search('example'))->toHaveCount(5);
        Http::assertSentCount(2);
    });
});

describe('searchByEmail', function () {
    it('finds nobody when only partial matches come back', function () {
        Http::fake(['bloomerang.test/v2/constituents/search*' => Http::response(bloomerangFixture('search-none'))]);

        expect(Bloomerang::constituents()->searchByEmail('ada@example.org'))->toBeEmpty();
    });

    it('keeps the one exact primary email match, ignoring case and spaces', function () {
        Http::fake(['bloomerang.test/v2/constituents/search*' => Http::response(bloomerangFixture('search-one'))]);

        $matches = Bloomerang::constituents()->searchByEmail(' ada@example.ORG');

        expect($matches)->toHaveCount(1)
            ->and($matches->first()->id)->toBe(1001);

        Http::assertSent(fn (Request $request) => $request->data()['search'] === 'ada@example.ORG');
    });

    it('returns every exact match when there are several', function () {
        Http::fake(['bloomerang.test/v2/constituents/search*' => Http::response(bloomerangFixture('search-several'))]);

        $matches = Bloomerang::constituents()->searchByEmail('ada@example.org');

        expect($matches->map(fn (Constituent $constituent) => $constituent->id)->values()->all())->toBe([1001, 1013]);
    });
});

describe('create', function () {
    it('creates an individual with a primary email', function () {
        Http::fake(['bloomerang.test/v2/constituent' => Http::response(bloomerangFixture('constituent-created'))]);

        $constituent = Bloomerang::constituents()->create('Barbara', 'Newperson', 'barbara@example.org');

        expect($constituent->id)->toBe(1005);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://bloomerang.test/v2/constituent'
            && $request['Type'] === 'Individual'
            && $request['FirstName'] === 'Barbara'
            && $request['LastName'] === 'Newperson'
            && $request['PrimaryEmail']['Value'] === 'barbara@example.org');
    });

    it('sends extra attributes along', function () {
        Http::fake(['bloomerang.test/v2/constituent' => Http::response(bloomerangFixture('constituent-created'))]);

        Bloomerang::constituents()->create('Barbara', 'Newperson', 'barbara@example.org', ['MiddleName' => 'Q']);

        Http::assertSent(fn (Request $request) => $request['MiddleName'] === 'Q' && $request['Type'] === 'Individual');
    });

    it('requires a first and last name', function (string $firstName, string $lastName) {
        Http::fake();

        expect(fn () => Bloomerang::constituents()->create($firstName, $lastName, 'someone@example.org'))
            ->toThrow(InvalidArgumentException::class);

        Http::assertNothingSent();
    })->with([
        'no first name' => ['', 'Newperson'],
        'no last name' => ['Barbara', '  '],
    ]);
});
