<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function index(): JsonResponse
    {
        $clients = Client::all()->map(fn (Client $client) => $this->present($client));

        return response()->json(['data' => $clients]);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return response()->json(['data' => $this->present($client)], 201);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json(['data' => $this->present($client)]);
    }

    public function transactions(Client $client): JsonResponse
    {
        $transactions = $client->transactions()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'instrument' => $t->instrument,
                'quantity' => $t->quantity,
                'price' => $t->price !== null ? (float) $t->price : null,
                'created_at' => $t->created_at,
            ]);

        return response()->json(['data' => $transactions]);
    }

    private function present(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'cash_balance' => $this->accounts->getCashBalance($client),
            'holdings' => $this->accounts->getHoldings($client),
        ];
    }
}