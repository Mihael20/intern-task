<?php

namespace App\Services;

use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InsufficientHoldingsException;
use App\Models\Client;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function deposit(Client $client, float $amount): Transaction
    {
        return DB::transaction(function () use ($client, $amount) {
            return Transaction::create([
                'client_id' => $client->id,
                'type' => Transaction::TYPE_DEPOSIT,
                'amount' => $amount,
            ]);
        });
    }

    public function withdraw(Client $client, float $amount): Transaction
    {
        return DB::transaction(function () use ($client, $amount) {
            $balance = $this->getCashBalance($client, lock: true);

            if ($amount > $balance) {
                throw new InsufficientFundsException(
                    "Клиентот „{$client->name}“ има само {$balance} на располагање, ".
                    "не може да подигне {$amount}."
                );
            }

            return Transaction::create([
                'client_id' => $client->id,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'amount' => $amount,
            ]);
        });
    }

    public function buy(Client $client, string $instrument, int $quantity, float $price): Transaction
    {
        return DB::transaction(function () use ($client, $instrument, $quantity, $price) {
            $instrument = strtoupper(trim($instrument));
            $cost = round($quantity * $price, 2);

            $balance = $this->getCashBalance($client, lock: true);

            if ($cost > $balance) {
                throw new InsufficientFundsException(
                    "Купувањето чини {$cost}, а клиентот „{$client->name}“ има само {$balance}."
                );
            }

            return Transaction::create([
                'client_id' => $client->id,
                'type' => Transaction::TYPE_BUY,
                'instrument' => $instrument,
                'quantity' => $quantity,
                'price' => $price,
                'amount' => $cost,
            ]);
        });
    }

    public function sell(Client $client, string $instrument, int $quantity, float $price): Transaction
    {
        return DB::transaction(function () use ($client, $instrument, $quantity, $price) {
            $instrument = strtoupper(trim($instrument));

            $holdings = $this->getHoldings($client, lock: true);
            $held = $holdings[$instrument] ?? 0;

            if ($quantity > $held) {
                throw new InsufficientHoldingsException(
                    "Клиентот „{$client->name}“ поседува само {$held} парчиња од {$instrument}, ".
                    "не може да продаде {$quantity}."
                );
            }

            $proceeds = round($quantity * $price, 2);

            return Transaction::create([
                'client_id' => $client->id,
                'type' => Transaction::TYPE_SELL,
                'instrument' => $instrument,
                'quantity' => $quantity,
                'price' => $price,
                'amount' => $proceeds,
            ]);
        });
    }

    public function getCashBalance(Client $client, bool $lock = false): float
    {
        $query = Transaction::where('client_id', $client->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $balance = 0.0;

        foreach ($query->get() as $t) {
            $balance += match ($t->type) {
                Transaction::TYPE_DEPOSIT, Transaction::TYPE_SELL => (float) $t->amount,
                Transaction::TYPE_WITHDRAWAL, Transaction::TYPE_BUY => -(float) $t->amount,
            };
        }

        return round($balance, 2);
    }

    public function getHoldings(Client $client, bool $lock = false): array
    {
        $query = Transaction::where('client_id', $client->id)
            ->whereIn('type', [Transaction::TYPE_BUY, Transaction::TYPE_SELL]);

        if ($lock) {
            $query->lockForUpdate();
        }

        $holdings = [];

        foreach ($query->get() as $t) {
            $sign = $t->type === Transaction::TYPE_BUY ? 1 : -1;
            $holdings[$t->instrument] = ($holdings[$t->instrument] ?? 0) + $sign * $t->quantity;
        }

        return array_filter($holdings, fn (int $qty) => $qty > 0);
    }
}