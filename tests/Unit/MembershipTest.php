<?php

use Carbon\CarbonImmutable;
use JplCodes\Bloomerang\Data\Membership;

it('keeps every field from the specification', function () {
    $membership = Membership::fromArray(bloomerangFixture('constituent')['Membership'][0]);

    expect($membership->scheduleId)->toBe(9001)
        ->and($membership->programName)->toBe('Example Membership Program')
        ->and($membership->levelName)->toBe('Annual')
        ->and($membership->status)->toBe('Current')
        ->and($membership->renewalDate)->toBe('2027-03-01')
        ->and($membership->raw)->toBe(bloomerangFixture('constituent')['Membership'][0]);
});

it('treats only the Current status as current', function (string $status, bool $isCurrent) {
    $membership = Membership::fromArray(['MembershipStatus' => $status]);

    expect($membership->isCurrent())->toBe($isCurrent)
        ->and($membership->status)->toBe($status);
})->with([
    'Current' => ['Current', true],
    'different case and spacing' => [' current ', true],
    'Lapsed' => ['Lapsed', false],
    'Canceled' => ['Canceled', false],
    'an unknown status string' => ['Pending Review', false],
    'empty' => ['', false],
]);

it('is not current when the status is missing', function () {
    expect(Membership::fromArray([])->isCurrent())->toBeFalse()
        ->and(Membership::fromArray([])->status)->toBeNull();
});

it('parses the renewal date when it can', function () {
    $membership = Membership::fromArray(['MembershipRenewalDate' => '2027-03-01']);

    expect($membership->renewsOn())->toBeInstanceOf(CarbonImmutable::class)
        ->and($membership->renewsOn()->toDateString())->toBe('2027-03-01');
});

it('returns no renewal date when it is empty or unreadable', function (?string $renewalDate) {
    $membership = Membership::fromArray(['MembershipRenewalDate' => $renewalDate]);

    expect($membership->renewsOn())->toBeNull()
        ->and($membership->renewalDate)->toBe($renewalDate);
})->with([
    'empty' => [''],
    'null' => [null],
    'not a date' => ['not a date'],
]);
