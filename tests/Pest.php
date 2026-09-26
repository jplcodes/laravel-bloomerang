<?php

use JplCodes\Bloomerang\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Decode a fixture from tests/Fixtures. Fixtures follow the shapes in Bloomerang's
 * published OpenAPI specification; every value in them is made up.
 *
 * @return array<mixed>
 */
function bloomerangFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/Fixtures/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * A GET /constituents page holding a copy of the constituent fixture for each id.
 *
 * @param  list<int>  $ids
 * @return array<string, mixed>
 */
function constituentsPage(array $ids, ?int $totalFiltered = null, int $start = 0): array
{
    $constituent = bloomerangFixture('constituent');

    return [
        'Total' => 4200,
        'TotalFiltered' => $totalFiltered ?? count($ids),
        'Start' => $start,
        'ResultCount' => count($ids),
        'Results' => array_map(fn (int $id): array => [...$constituent, 'Id' => $id], $ids),
    ];
}
