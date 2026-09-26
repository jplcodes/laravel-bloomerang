<?php

namespace JplCodes\Bloomerang\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\LazyCollection;
use JplCodes\Bloomerang\Bloomerang as BloomerangClient;
use JplCodes\Bloomerang\CallMode;
use JplCodes\Bloomerang\Data\User;
use JplCodes\Bloomerang\Resources\Constituents;

/**
 * @method static Constituents constituents()
 * @method static User currentUser()
 * @method static mixed request(string $method, string $path, array $query = [], ?array $body = null)
 * @method static LazyCollection paginate(string $path, array $query = [])
 * @method static BloomerangClient inJobMode()
 * @method static BloomerangClient inRequestMode()
 * @method static CallMode mode()
 * @method static BloomerangClient withToken(string $token)
 * @method static string baseUrl()
 *
 * @see BloomerangClient
 */
final class Bloomerang extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BloomerangClient::class;
    }
}
