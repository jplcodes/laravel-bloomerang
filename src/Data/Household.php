<?php

namespace JplCodes\Bloomerang\Data;

use JplCodes\Bloomerang\Exceptions\UnexpectedResponse;
use TypeError;

/**
 * A Bloomerang household, grouping several constituents together.
 */
final readonly class Household
{
    /**
     * @param  list<int>  $memberIds
     */
    private function __construct(
        public int $id,
        public ?string $fullName,
        public ?int $headId,
        public array $memberIds,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! array_key_exists('Id', $data) || ! is_int($data['Id'])) {
            throw new UnexpectedResponse('Bloomerang household data is missing a valid Id.');
        }

        try {
            return new self(
                id: $data['Id'],
                fullName: $data['FullName'] ?? null,
                headId: $data['HeadId'] ?? null,
                memberIds: array_map(intval(...), $data['MemberIds'] ?? []),
                raw: $data,
            );
        } catch (TypeError) {
            throw new UnexpectedResponse('Bloomerang household data has a field of the wrong type.');
        }
    }
}
