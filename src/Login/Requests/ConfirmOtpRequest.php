<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmOtpRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $length = (int) config('connex.otp_length', 4);

        return [
            'otp' => ['required', 'string', "digits:{$length}"],
        ];
    }

    public function otp(): string
    {
        return $this->string('otp')->toString();
    }
}
