<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Mandiri QRIS Configuration
 */
$config['mandiri_qris'] = array(
    'client_id' => getenv('MANDIRI_CLIENT_ID') ?: '',
    'client_secret' => getenv('MANDIRI_CLIENT_SECRET') ?: '',
    'base_url' => getenv('MANDIRI_BASE_URL') ?: 'https://api.mandiri.co.id',
    'sandbox' => getenv('MANDIRI_SANDBOX') === 'true',
    'merchant_id' => getenv('MANDIRI_MERCHANT_ID') ?: '',
    'terminal_id' => getenv('MANDIRI_TERMINAL_ID') ?: '',
);
