<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    /**
     * La autorización se delega en UsuarioPolicy@gestionar.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El campo 'activo' NO se edita aquí: los cambios de estado tienen su
     * propio flujo auditado (UsuarioPolicy@activar / @inactivar +
     * SeguridadSesionesService), separado de la edición de datos.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usuarioId = $this->route('usuario')?->id;

        return [
            'ci' => ['required', 'string', 'max:20', Rule::unique('usuarios', 'ci')->ignore($usuarioId)],
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'cargo' => ['nullable', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', Rule::unique('usuarios', 'username')->ignore($usuarioId)],
            'password' => ['nullable', 'string', 'min:8'],
            'rol_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}
