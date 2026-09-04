<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuyRequest;
use App\Http\Requests\DepositRequest;
use App\Http\Requests\SellRequest;
use App\Http\Requests\WithdrawRequest;
use App\Models\Client;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function deposit(DepositRequest $request, Client $client): JsonResponse
    {
        $transaction = $this->accounts->deposit($client, (float) $request->validated('amount'));

        return $this->respond($client, $transaction);
    }

    public function withdraw(WithdrawRequest $request, Client $client): JsonResponse
    {
        $transaction = $this->accounts->withdraw($client, (float) $request->validated('amount'));

        return $this->respond($client, $transaction);
    }

    public function buy(BuyRequest $request, Client $client): JsonResponse
    {
        $data = $request->validated();

        $transaction = $this->accounts->buy(
            $client,
            $data['instrument'],
            (int) $data['quantity'],
            (float) $data['price'],
        );

        return $this->respond($client, $transaction);
    }

    public function sell(SellRequest $request, Client $client): JsonResponse
    {
        $data = $request->validated();

        $transaction = $this->accounts->sell(
            $client,
            $data['instrument'],
            (int) $data['quantity'],
            (float) $data['price'],
        );

        return $this->respond($client, $transaction);
    }

    private function respond(Client $client, $transaction): JsonResponse
    {
        return response()->json([
            'data' => [
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'instrument' => $transaction->instrument,
                    'quantity' => $transaction->quantity,
                    'price' => $transaction->price !== null ? (float) $transaction->price : null,
                ],
                'cash_balance' => $this->accounts->getCashBalance($client),
                'holdings' => $this->accounts->getHoldings($client),
            ],
        ], 201);
    }
}