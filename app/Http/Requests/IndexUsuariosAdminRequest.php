<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexUsuariosAdminRequest extends FormRequest
{
    /**
     * La autorización se delega en UsuarioPolicy@gestionar, invocada desde
     * el controlador con $this->authorize().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 'activo' llega como string desde query params ('1' | '0'); se valida
     * con la regla boolean (acepta 1/0 y '1'/'0') y se lee en el controlador
     * con $request->boolean('activo').
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rol_id' => ['nullable', 'integer', 'exists:roles,id'],
            'activo' => ['nullable', 'boolean'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ];
    }
}
