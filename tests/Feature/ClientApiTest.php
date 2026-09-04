<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_client_and_deposit_via_api(): void
    {
        $response = $this->postJson('/api/clients', ['name' => 'Ана']);
        $response->assertCreated();
        $clientId = $response->json('data.id');

        $this->postJson("/api/clients/{$clientId}/deposit", ['amount' => 1000])
            ->assertCreated()
            ->assertJsonPath('data.cash_balance', 1000);
    }

    public function test_withdrawal_above_balance_returns_422_via_api(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->postJson("/api/clients/{$client->id}/deposit", ['amount' => 100]);

        $this->postJson("/api/clients/{$client->id}/withdraw", ['amount' => 150])
            ->assertStatus(422);

        $this->getJson("/api/clients/{$client->id}")
            ->assertJsonPath('data.cash_balance', 100);
    }

    public function test_sell_above_holdings_returns_422_via_api(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->postJson("/api/clients/{$client->id}/deposit", ['amount' => 1000]);
        $this->postJson("/api/clients/{$client->id}/buy", [
            'instrument' => 'AAPL', 'quantity' => 5, 'price' => 100,
        ]);

        $this->postJson("/api/clients/{$client->id}/sell", [
            'instrument' => 'AAPL', 'quantity' => 8, 'price' => 120,
        ])->assertStatus(422);
    }

    public function test_negative_amount_is_rejected_by_validation(): void
    {
        $client = Client::create(['name' => 'Ана']);

        $this->postJson("/api/clients/{$client->id}/deposit", ['amount' => -50])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_zero_amount_is_rejected_by_validation(): void
    {
        $client = Client::create(['name' => 'Ана']);

        $this->postJson("/api/clients/{$client->id}/deposit", ['amount' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_fractional_quantity_is_rejected_by_validation(): void
    {
        $client = Client::create(['name' => 'Ана']);
        $this->postJson("/api/clients/{$client->id}/deposit", ['amount' => 1000]);

        $this->postJson("/api/clients/{$client->id}/buy", [
            'instrument' => 'AAPL', 'quantity' => 2.5, 'price' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('quantity');
    }
}