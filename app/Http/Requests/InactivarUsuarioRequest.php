<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InactivarUsuarioRequest extends FormRequest
{
    /**
     * La autorización se delega en UsuarioPolicy@inactivar (un solo lugar de
     * verdad), invocada desde el controlador con $this->authorize().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Acción sin entrada de formulario: no hay campos que validar. Las reglas
     * de negocio (auto-inactivación, objetivo ya inactivo) se validan en
     * SeguridadSesionesService@expulsar.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
