<?php

namespace JplCodes\Bloomerang\Data;

use JplCodes\Bloomerang\Exceptions\UnexpectedResponse;

/**
 * The Bloomerang user who owns the credentials used for a call.
 */
final readonly class User
{
    private function __construct(
        public int $id,
        public ?string $name,
        public ?string $email,
        public ?string $userName,
        public ?bool $isActive,
        public ?string $permissionLevel,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! array_key_exists('Id', $data) || ! is_int($data['Id'])) {
            throw new UnexpectedResponse('Bloomerang user data is missing a valid Id.');
        }

        return new self(
            id: $data['Id'],
            name: $data['Name'] ?? null,
            email: $data['Email'] ?? null,
            userName: $data['UserName'] ?? null,
            isActive: $data['IsActive'] ?? null,
            permissionLevel: $data['PermissionLevel'] ?? null,
            raw: $data,
        );
    }
}
