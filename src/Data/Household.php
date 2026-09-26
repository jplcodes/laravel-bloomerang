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

        $memberIds = $data['MemberIds'] ?? [];

        if (! is_array($memberIds) || ! array_is_list($memberIds) || array_filter($memberIds, fn (mixed $id): bool => ! is_int($id)) !== []) {
            throw new UnexpectedResponse('Bloomerang household MemberIds must be a list of integers.');
        }

        try {
            return new self(
                id: $data['Id'],
                fullName: $data['FullName'] ?? null,
                headId: $data['HeadId'] ?? null,
                memberIds: $memberIds,
                raw: $data,
            );
        } catch (TypeError) {
            throw new UnexpectedResponse('Bloomerang household data has a field of the wrong type.');
        }
    }
}
