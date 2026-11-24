<?php

namespace App\Http\Resources\Concerns;

use App\Models\Expediente;

trait IncluyeDocumentosExpediente
{
    /**
     * @return array{url: string, nombre: string}|null
     */
    protected function documentoBoletaApertura(Expediente $expediente): ?array
    {
        if (blank($expediente->boleta_apertura_path)) {
            return null;
        }

        return [
            'url' => url('/api/expedientes/'.$expediente->id.'/boleta-apertura'),
            'nombre' => 'boleta-apertura-'.$expediente->nurej_code.'.pdf',
        ];
    }

    /**
     * @return array{url: string, nombre: string}|null
     */
    protected function documentoFichaSorteo(Expediente $expediente): ?array
    {
        if (blank($expediente->ficha_sorteo_path)) {
            return null;
        }

        return [
            'url' => url('/api/expedientes/'.$expediente->id.'/ficha-sorteo'),
            'nombre' => 'ficha-sorteo-'.$expediente->nurej_code.'.pdf',
        ];
    }
}
