<?php

use JplCodes\Bloomerang\Data\Household;
use JplCodes\Bloomerang\Exceptions\UnexpectedResponse;

it('maps the household fields', function () {
    $household = Household::fromArray(bloomerangFixture('search-one')['Results'][2]);

    expect($household->id)->toBe(2001)
        ->and($household->fullName)->toBe('The Example Household')
        ->and($household->headId)->toBe(1001)
        ->and($household->memberIds)->toBe([1001, 1012]);
});

it('rejects member ids that are not integers', function () {
    Household::fromArray([...bloomerangFixture('search-one')['Results'][2], 'MemberIds' => [1001, 'abc']]);
})->throws(UnexpectedResponse::class);
