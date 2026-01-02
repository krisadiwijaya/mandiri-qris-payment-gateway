<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['mandiri_qris'] = array(
    'environment' => 'sandbox', // 'sandbox' or 'production'
    'client_id' => 'your_client_id_here',
    'client_secret' => 'your_client_secret_here',
    'merchant_nmid' => 'your_merchant_nmid_here',
    'merchant_name' => 'Toko Online',
    'merchant_city' => 'Jakarta',
    'qris_expiry_minutes' => 5,
    'timeout' => 30,
);
