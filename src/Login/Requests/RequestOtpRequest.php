<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Requests;

use Illuminate\Foundation\Http\FormRequest;
use NeaMatrix\Connex\Rules\ValidMsisdn;

class RequestOtpRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'msisdn' => ['required', 'string', 'max:20', new ValidMsisdn],
            'accepted_terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['accepted_terms.accepted' => __('connex::errors.terms_required')];
    }

    public function msisdn(): string
    {
        return $this->string('msisdn')->toString();
    }
}
