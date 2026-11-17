<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * {"uuid": "..."} to start a plan change, plus {"otp": "1234"} to confirm it.
 */
class PlanChangeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = ['uuid' => ['required', 'string', 'max:100']];

        if ($this->routeIs('*.confirm')) {
            $length = (int) config('connex.otp_length', 4);
            $rules['otp'] = ['required', 'string', "digits:{$length}"];
        }

        return $rules;
    }

    public function planUuid(): string
    {
        return $this->string('uuid')->toString();
    }

    public function otp(): string
    {
        return $this->string('otp')->toString();
    }
}
