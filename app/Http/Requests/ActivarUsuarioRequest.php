<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivarUsuarioRequest extends FormRequest
{
    /**
     * La autorización se delega en UsuarioPolicy@activar, invocada desde
     * el controlador con $this->authorize().
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
        return [];
    }
}
