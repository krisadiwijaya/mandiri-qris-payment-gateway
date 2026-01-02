<?php

use Illuminate\Support\Facades\Route;

Route::post(config('mandiri-qris.webhook.path'), 'MandiriQris\Laravel\WebhookController@handle')
    ->name('mandiri-qris.webhook');
