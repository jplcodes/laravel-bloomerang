<?php

namespace JplCodes\Bloomerang\Data;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * One of a constituent's membership schedules.
 */
final readonly class Membership
{
    private function __construct(
        public ?int $scheduleId,
        public ?string $programName,
        public ?string $levelName,
        public ?string $status,
        public ?string $renewalDate,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheduleId: $data['MembershipScheduleId'] ?? null,
            programName: $data['MembershipProgramName'] ?? null,
            levelName: $data['MembershipLevelName'] ?? null,
            status: $data['MembershipStatus'] ?? null,
            renewalDate: $data['MembershipRenewalDate'] ?? null,
            raw: $data,
        );
    }

    public function isCurrent(): bool
    {
        return strcasecmp(trim((string) $this->status), 'Current') === 0;
    }

    public function renewsOn(): ?CarbonImmutable
    {
        if ($this->renewalDate === null || trim($this->renewalDate) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($this->renewalDate);
        } catch (Throwable) {
            return null;
        }
    }
}
