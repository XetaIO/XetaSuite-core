<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\V1\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use XetaSuite\Models\User;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', Password::defaults()],
            'locale' => ['nullable', 'string', Rule::in(['fr', 'en'])],
            'office_phone' => ['nullable', 'string', 'max:50'],
            'cell_phone' => ['nullable', 'string', 'max:50'],
            'sites' => ['nullable', 'array'],
            'sites.*.id' => ['required_with:sites', 'integer', 'exists:sites,id'],
            'sites.*.roles' => ['nullable', 'array'],
            'sites.*.roles.*' => ['string', 'exists:roles,name'],
            'sites.*.permissions' => ['nullable', 'array'],
            'sites.*.permissions.*' => ['string', 'exists:permissions,name'],
        ];

        // Check if user can assign direct permissions
        if (! $this->user()->can('assignDirectPermission', User::class)) {
            $rules['sites.*.permissions'] = ['prohibited'];
        }

        // Check if user can assign sites
        if (! $this->user()->can('assignSite', User::class)) {
            $rules['sites'] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'username' => __('users.username'),
            'email' => __('users.email'),
            'first_name' => __('users.first_name'),
            'last_name' => __('users.last_name'),
            'password' => __('users.password'),
            'locale' => __('users.locale'),
            'office_phone' => __('users.office_phone'),
            'cell_phone' => __('users.cell_phone'),
            'sites' => __('users.sites'),
            'sites.*.id' => __('users.site'),
            'sites.*.roles' => __('users.roles'),
            'sites.*.permissions' => __('users.permissions'),
        ];
    }
}
