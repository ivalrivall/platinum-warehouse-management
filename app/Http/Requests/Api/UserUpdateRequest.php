<?php

namespace App\Http\Requests\Api;

use App\Enums\UserType;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->tokenCan('user_update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $this->user->id,
            'password' => 'required',
            'phone' => 'required',
            'address' => 'nullable',
            'type' => ['nullable', new EnumValue(UserType::class, false)],
        ];
    }
}
