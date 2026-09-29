<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    /**
     * La autorización se delega en UsuarioPolicy@gestionar.
     */
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
            'ci' => ['required', 'string', 'max:20', 'unique:usuarios,ci'],
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'cargo' => ['nullable', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'unique:usuarios,username'],
            'password' => ['required', 'string', 'min:8'],
            'rol_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}
