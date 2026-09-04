<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Services\AccountService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = app(AccountService::class);

        $ana = Client::create(['name' => 'Ана Петровска']);
        $accounts->deposit($ana, 1000);
        $accounts->buy($ana, 'AAPL', 5, 100);
        $accounts->sell($ana, 'AAPL', 3, 120);

        $marko = Client::create(['name' => 'Марко Стојаноски']);
        $accounts->deposit($marko, 5000);
        $accounts->buy($marko, 'MSFT', 10, 300);
        $accounts->buy($marko, 'TSLA', 4, 250);
        $accounts->withdraw($marko, 500);
        $accounts->sell($marko, 'MSFT', 4, 320);

        $jana = Client::create(['name' => 'Јана Илиевска']);
        $accounts->deposit($jana, 250);
    }
}