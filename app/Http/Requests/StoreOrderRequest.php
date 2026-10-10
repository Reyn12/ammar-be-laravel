<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'order_type' => ['required', Rule::in(['dine_in', 'takeaway'])],
            'table_token' => ['required_if:order_type,dine_in', 'nullable', 'string', 'max:64'],
            'payment_method' => ['required', Rule::in(['qris', 'cash'])],

            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'min:2', 'max:100'],
            'customer.phone' => ['required', 'string', 'regex:/^(\+62|62|0)8\d{7,12}$/'],
            'customer.email' => ['nullable', 'email', 'max:150'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
            'items.*.addon_ids' => ['nullable', 'array'],
            'items.*.addon_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** Spasi dan strip pada nomor telepon dibuang sebelum validasi. */
    protected function prepareForValidation(): void
    {
        $customer = $this->input('customer');

        if (is_array($customer) && isset($customer['phone']) && is_string($customer['phone'])) {
            $customer['phone'] = preg_replace('/[\s-]/', '', $customer['phone']);
            $this->merge(['customer' => $customer]);
        }
    }
}
