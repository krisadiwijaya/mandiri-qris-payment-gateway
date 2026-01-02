<?php

namespace Mandiri\Qris\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string getAccessToken()
 * @method static array createQris(float $amount, string $reference, string|null $callbackUrl = null)
 * @method static array checkStatus(string $qrId, string $reference)
 * @method static void clearToken()
 *
 * @see \Mandiri\Qris\MandiriQrisClient
 */
class MandiriQris extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'mandiri-qris';
    }
}
