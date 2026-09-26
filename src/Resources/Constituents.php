<?php

namespace JplCodes\Bloomerang\Resources;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use JplCodes\Bloomerang\CallMode;
use JplCodes\Bloomerang\Data\Constituent;
use JplCodes\Bloomerang\Data\Household;
use JplCodes\Bloomerang\Pagination\Paginator;
use JplCodes\Bloomerang\Transport;

/**
 * Bloomerang's constituent endpoints: individuals, organizations and households.
 */
final class Constituents
{
    private const CHUNK_SIZE = 50;

    public function __construct(
        private readonly Transport $transport,
        private readonly CallMode $mode,
    ) {}

    public function find(int $id): Constituent
    {
        $data = $this->transport->send($this->mode, 'GET', "constituent/{$id}", expectObject: true);

        return Constituent::fromArray($data);
    }

    /**
     * @param  iterable<int>  $ids
     * @return LazyCollection<int, Constituent>
     */
    public function findMany(iterable $ids): LazyCollection
    {
        return new LazyCollection(function () use ($ids) {
            $unique = [];

            foreach ($ids as $id) {
                $unique[(int) $id] = (int) $id;
            }

            if ($unique === []) {
                return;
            }

            $paginator = new Paginator($this->transport, $this->mode);

            foreach (array_chunk(array_values($unique), self::CHUNK_SIZE) as $chunk) {
                $page = $paginator->walk('constituents', ['id' => implode('|', $chunk)]);

                foreach ($page as $raw) {
                    yield Constituent::fromArray($raw);
                }
            }
        });
    }

    /**
     * @return Collection<int, Constituent|Household>
     */
    public function search(string $text, int $limit = 200): Collection
    {
        $paginator = new Paginator($this->transport, $this->mode);

        return $paginator->walk('constituents/search', ['search' => trim($text)])
            ->take($limit)
            ->map(fn (array $raw): Constituent|Household => ($raw['Type'] ?? null) === 'Household'
                ? Household::fromArray($raw)
                : Constituent::fromArray($raw))
            ->collect();
    }

    /**
     * @return Collection<int, Constituent>
     */
    public function searchByEmail(string $email): Collection
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '') {
            return new Collection;
        }

        return $this->search(trim($email))
            ->filter(fn (Constituent|Household $item): bool => $item instanceof Constituent
                && $item->primaryEmail !== null
                && strtolower(trim($item->primaryEmail)) === $normalized)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException
     */
    public function create(string $firstName, string $lastName, string $email, array $attributes = []): Constituent
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $email = trim($email);

        if ($firstName === '' || $lastName === '' || $email === '') {
            throw new InvalidArgumentException('A first name, last name and email are required to create a constituent.');
        }

        $payload = array_replace_recursive([
            'Type' => 'Individual',
            'FirstName' => $firstName,
            'LastName' => $lastName,
            'PrimaryEmail' => [
                'Type' => 'Home',
                'Value' => $email,
                'IsPrimary' => true,
            ],
        ], $attributes);

        $data = $this->transport->send($this->mode, 'POST', 'constituent', [], $payload, expectObject: true);

        return Constituent::fromArray($data);
    }
}
