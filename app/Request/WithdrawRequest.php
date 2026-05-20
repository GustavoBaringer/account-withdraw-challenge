<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'in:PIX'],
            'pix' => ['required_if:method,PIX', 'array'],
            'pix.type' => ['required_if:method,PIX', 'string', 'in:email,cpf,cnpj,phone,random'],
            'pix.key' => ['required_if:method,PIX', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'schedule' => ['nullable', 'date_format:Y-m-d H:i', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'Withdrawal method is required.',
            'method.in' => 'Only PIX method is supported.',
            'pix.required_if' => 'PIX data is required when method is PIX.',
            'pix.type.required_if' => 'PIX key type is required.',
            'pix.type.in' => 'PIX key type must be one of: email, cpf, cnpj, phone, random.',
            'pix.key.required_if' => 'PIX key is required.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.gt' => 'Amount must be greater than zero.',
            'schedule.date_format' => 'Schedule must be in format Y-m-d H:i (e.g., 2026-01-01 15:00).',
            'schedule.after' => 'Scheduled date must be in the future.',
        ];
    }
}
