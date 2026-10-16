<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'quantity_change' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
