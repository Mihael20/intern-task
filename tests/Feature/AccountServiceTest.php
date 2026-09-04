<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InsufficientHoldingsException;
use App\Models\Client;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountService $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = app(AccountService::class);
    }

    public function test_deposit_increases_cash_balance(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->accounts->deposit($client, 1000);
        $this->assertEquals(1000.0, $this->accounts->getCashBalance($client));
    }

    public function test_withdrawal_decreases_cash_balance(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->accounts->deposit($client, 1000);
        $this->accounts->withdraw($client, 300);
        $this->assertEquals(700.0, $this->accounts->getCashBalance($client));
    }

    public function test_withdrawal_above_balance_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->accounts->deposit($client, 100);

        $this->expectException(InsufficientFundsException::class);

        try {
            $this->accounts->withdraw($client, 150);
        } finally {
            $this->assertEquals(100.0, $this->accounts->getCashBalance($client));
        }
    }

    public function test_buy_decreases_cash_and_increases_holding(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->accounts->deposit($client, 1000);
        $this->accounts->buy($client, 'AAPL', 5, 100);
        $this->assertEquals(500.0, $this->accounts->getCashBalance($client));
        $this->assertEquals(5, $this->accounts->getHoldings($client)['AAPL']);
    }

    public function test_buy_above_available_cash_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->accounts->deposit($client, 1000);
        $this->accounts->buy($client, 'AAPL', 5, 100);

        $this->expectException(InsufficientFundsException::class);

        try {
            $this->accounts->buy($client, 'AAPL', 10, 100);
        } finally {
            $this->assertEquals(500.0, $this->accounts->getCashBalance($client));
            $this->assertEquals(5, $this->accounts->getHoldings($client)['AAPL']);
        }
    }

    public function test_sell_increases_cash_and_decreases_holding(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->accounts->deposit($client, 1000);
        $this->accounts->buy($client, 'AAPL', 5, 100);
        $this->accounts->sell($client, 'AAPL', 3, 120);
        $this->assertEquals(860.0, $this->accounts->getCashBalance($client));
        $this->assertEquals(2, $this->accounts->getHoldings($client)['AAPL']);
    }

    public function test_sell_above_held_quantity_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->accounts->deposit($client, 1000);
        $this->accounts->buy($client, 'AAPL', 5, 100);

        $this->expectException(InsufficientHoldingsException::class);

        try {
            $this->accounts->sell($client, 'AAPL', 8, 120);
        } finally {
            $this->assertEquals(500.0, $this->accounts->getCashBalance($client));
            $this->assertEquals(5, $this->accounts->getHoldings($client)['AAPL']);
        }
    }

    public function test_selling_an_instrument_never_bought_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->accounts->deposit($client, 1000);

        $this->expectException(InsufficientHoldingsException::class);
        $this->accounts->sell($client, 'TSLA', 1, 50);
    }

    public function test_clients_are_fully_independent(): void
    {
        $ana = Client::create(['name' => 'Ана']);
        $marko = Client::create(['name' => 'Марко']);

        $this->accounts->deposit($ana, 1000);
        $this->accounts->deposit($marko, 50);

        $this->assertEquals(1000.0, $this->accounts->getCashBalance($ana));
        $this->assertEquals(50.0, $this->accounts->getCashBalance($marko));
    }

    public function test_holdings_with_zero_quantity_are_not_listed(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->accounts->deposit($client, 1000);
        $this->accounts->buy($client, 'AAPL', 5, 100);
        $this->accounts->sell($client, 'AAPL', 5, 110);

        $this->assertArrayNotHasKey('AAPL', $this->accounts->getHoldings($client));
    }
}