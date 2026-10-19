<?php

use JplCodes\Bloomerang\Data\Constituent;
use JplCodes\Bloomerang\Data\Membership;
use JplCodes\Bloomerang\Exceptions\MembershipDataMissing;
use JplCodes\Bloomerang\Exceptions\UnexpectedResponse;

it('maps the constituent fields', function () {
    $constituent = Constituent::fromArray(bloomerangFixture('constituent'));

    expect($constituent->id)->toBe(1001)
        ->and($constituent->accountNumber)->toBe(1)
        ->and($constituent->type)->toBe('Individual')
        ->and($constituent->status)->toBe('Active')
        ->and($constituent->firstName)->toBe('Ada')
        ->and($constituent->lastName)->toBe('Example')
        ->and($constituent->fullName)->toBe('Ada Example')
        ->and($constituent->primaryEmail)->toBe('ada@example.org')
        ->and($constituent->raw)->toBe(bloomerangFixture('constituent'));
});

it('keeps every membership entry', function () {
    $constituent = Constituent::fromArray(bloomerangFixture('constituent-memberships-several'));

    expect($constituent->hasMembershipData())->toBeTrue()
        ->and($constituent->memberships())->toHaveCount(3)
        ->each->toBeInstanceOf(Membership::class)
        ->and(array_map(fn (Membership $membership) => $membership->status, $constituent->memberships()))
        ->toBe(['Lapsed', 'Current', 'Pending Review']);
});

it('treats an empty membership array as no membership', function () {
    $constituent = Constituent::fromArray(bloomerangFixture('constituent-membership-empty'));

    expect($constituent->hasMembershipData())->toBeTrue()
        ->and($constituent->memberships())->toBe([]);
});

it('refuses to read memberships when the membership key is absent', function () {
    $constituent = Constituent::fromArray(bloomerangFixture('constituent-membership-absent'));

    expect($constituent->hasMembershipData())->toBeFalse();

    $constituent->memberships();
})->throws(MembershipDataMissing::class);

it('treats a null membership value as absent', function (string $key) {
    $data = bloomerangFixture('constituent');
    unset($data['Memberships']);

    $constituent = Constituent::fromArray([...$data, $key => null]);

    expect($constituent->hasMembershipData())->toBeFalse();
})->with(['Memberships', 'Membership']);

it('still reads the Membership key the specification documents', function () {
    $data = bloomerangFixture('constituent-memberships-several');
    $data['Membership'] = $data['Memberships'];
    unset($data['Memberships']);

    $constituent = Constituent::fromArray($data);

    expect($constituent->hasMembershipData())->toBeTrue()
        ->and(array_map(fn (Membership $membership) => $membership->status, $constituent->memberships()))
        ->toBe(['Lapsed', 'Current', 'Pending Review']);
});

it('prefers the Memberships key the live API sends when both keys are present', function () {
    $constituent = Constituent::fromArray([
        ...bloomerangFixture('constituent'),
        'Memberships' => [['MembershipStatus' => 'Current']],
        'Membership' => [['MembershipStatus' => 'Lapsed']],
    ]);

    expect($constituent->memberships())->toHaveCount(1)
        ->and($constituent->memberships()[0]->status)->toBe('Current');
});

it('treats a null Memberships value as absent even when the Membership key has entries', function () {
    $constituent = Constituent::fromArray([
        ...bloomerangFixture('constituent'),
        'Memberships' => null,
        'Membership' => [['MembershipStatus' => 'Current']],
    ]);

    expect($constituent->hasMembershipData())->toBeFalse();
});

it('has no primary email when the constituent has none', function () {
    $data = bloomerangFixture('constituent');
    unset($data['PrimaryEmail']);

    expect(Constituent::fromArray($data)->primaryEmail)->toBeNull();
});

it('rejects data without an id', function () {
    $data = bloomerangFixture('constituent');
    unset($data['Id']);

    Constituent::fromArray($data);
})->throws(UnexpectedResponse::class);

it('rejects a membership value that is not a list', function (string $key) {
    $data = bloomerangFixture('constituent');
    unset($data['Memberships']);

    Constituent::fromArray([...$data, $key => 'Current']);
})->with(['Memberships', 'Membership'])->throws(UnexpectedResponse::class);

it('rejects fields of the wrong type', function () {
    Constituent::fromArray([...bloomerangFixture('constituent'), 'FirstName' => ['not', 'a', 'string']]);
})->throws(UnexpectedResponse::class);

it('rejects a membership entry with fields of the wrong type', function () {
    $constituent = Constituent::fromArray([...bloomerangFixture('constituent'), 'Memberships' => [['MembershipStatus' => ['Current']]]]);

    $constituent->memberships();
})->throws(UnexpectedResponse::class);
