<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MandiriQris extends BaseConfig
{
    public $environment = 'sandbox'; // 'sandbox' or 'production'
    public $clientId = '';
    public $clientSecret = '';
    public $merchantNmid = '';
    public $merchantName = 'Toko Online';
    public $merchantCity = 'Jakarta';
    public $qrisExpiryMinutes = 5;
    public $timeout = 30;
}
