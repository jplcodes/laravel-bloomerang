<?php

namespace JplCodes\Bloomerang\Data;

use JplCodes\Bloomerang\Exceptions\MembershipDataMissing;
use JplCodes\Bloomerang\Exceptions\UnexpectedResponse;
use TypeError;

/**
 * A Bloomerang individual or organization constituent.
 */
final readonly class Constituent
{
    /**
     * @param  list<array<string, mixed>>|null  $membershipData
     */
    private function __construct(
        public int $id,
        public ?int $accountNumber,
        public ?string $type,
        public ?string $status,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $fullName,
        public ?string $primaryEmail,
        private ?array $membershipData,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! array_key_exists('Id', $data) || ! is_int($data['Id'])) {
            throw new UnexpectedResponse('Bloomerang constituent data is missing a valid Id.');
        }

        try {
            return new self(
                id: $data['Id'],
                accountNumber: $data['AccountNumber'] ?? null,
                type: $data['Type'] ?? null,
                status: $data['Status'] ?? null,
                firstName: $data['FirstName'] ?? null,
                lastName: $data['LastName'] ?? null,
                fullName: $data['FullName'] ?? null,
                primaryEmail: $data['PrimaryEmail']['Value'] ?? null,
                membershipData: self::membershipDataFrom($data),
                raw: $data,
            );
        } catch (TypeError) {
            throw new UnexpectedResponse('Bloomerang constituent data has a field of the wrong type.');
        }
    }

    /**
     * The live API sends the membership array as `Memberships`; the published specification calls it `Membership`.
     * Both are read, and a `Memberships` key wins whenever it is present, even when null.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>|null
     */
    private static function membershipDataFrom(array $data): ?array
    {
        $membership = array_key_exists('Memberships', $data) ? $data['Memberships'] : ($data['Membership'] ?? null);

        if ($membership === null) {
            return null;
        }

        if (! is_array($membership) || ! array_is_list($membership)) {
            throw new UnexpectedResponse('Bloomerang constituent membership data must be a list.');
        }

        foreach ($membership as $entry) {
            if (! is_array($entry)) {
                throw new UnexpectedResponse('Bloomerang constituent membership entry must be an array.');
            }
        }

        return $membership;
    }

    /**
     * Whether Bloomerang included membership data for this constituent, even an empty list.
     */
    public function hasMembershipData(): bool
    {
        return $this->membershipData !== null;
    }

    /**
     * @return list<Membership>
     *
     * @throws MembershipDataMissing
     */
    public function memberships(): array
    {
        if ($this->membershipData === null) {
            throw new MembershipDataMissing('Bloomerang did not include membership data for this constituent.');
        }

        return array_map(Membership::fromArray(...), $this->membershipData);
    }
}
