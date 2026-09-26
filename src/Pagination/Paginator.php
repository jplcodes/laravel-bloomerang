<?php

namespace JplCodes\Bloomerang\Pagination;

use Illuminate\Support\LazyCollection;
use JplCodes\Bloomerang\CallMode;
use JplCodes\Bloomerang\Exceptions\UnexpectedResponse;
use JplCodes\Bloomerang\Transport;

/**
 * Walks a Bloomerang list endpoint page by page using skip/take.
 *
 * @internal
 */
final class Paginator
{
    private const TAKE = 50;

    public function __construct(
        private readonly Transport $transport,
        private readonly CallMode $mode,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function walk(string $path, array $query = []): LazyCollection
    {
        return new LazyCollection(function () use ($path, $query) {
            $skip = 0;

            while (true) {
                $page = $this->transport->send($this->mode, 'GET', $path, [
                    ...$query,
                    'skip' => (string) $skip,
                    'take' => (string) self::TAKE,
                ], expectObject: true);

                if (! isset($page['Results']) || ! is_array($page['Results'])) {
                    throw new UnexpectedResponse("Bloomerang sent a page without results for GET {$path}.");
                }

                $results = $page['Results'];
                $count = count($results);

                if ($count === 0) {
                    return;
                }

                foreach ($results as $result) {
                    yield $result;
                }

                $totalFiltered = $page['TotalFiltered'] ?? null;

                if ($totalFiltered !== null) {
                    if ($skip + $count >= $totalFiltered) {
                        return;
                    }
                } elseif ($count < self::TAKE) {
                    return;
                }

                $skip += $count;
            }
        });
    }
}
