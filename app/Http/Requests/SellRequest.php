<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instrument' => ['required', 'string', 'max:20'],
            'quantity' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'gt:0'],
        ];
    }
}