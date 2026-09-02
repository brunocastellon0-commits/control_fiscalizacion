<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Adjunto;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdjuntoService
{
    /**
     * Guarda un archivo adjunto dentro de la transacción del caller.
     *
     * Se calcula el hash SHA-256 del contenido ORIGINAL (antes de persistir)
     * y ese hash se usa como nombre del archivo guardado, lo que refuerza la
     * cadena de custodia y evita duplicados. NO abre transacción propia: es
     * el caller quien decide el punto de commit/rollback.
     */
    public function guardarParaActuado(
        Actuado $actuado,
        UploadedFile $archivo,
        Usuario $subidoPor,
        string $disco = 'local',
    ): Adjunto {
        $hash = hash_file('sha256', $archivo->getRealPath());

        $nombre = $hash.'.'.$archivo->getClientOriginalExtension();
        $ruta = $archivo->storeAs(
            'adjuntos/'.$actuado->expediente_id,
            $nombre,
            $disco,
        );

        try {
            $adjunto = Adjunto::create([
                'actuado_id' => $actuado->id,
                'nombre_original' => $archivo->getClientOriginalName(),
                'ruta_almacenamiento' => $ruta,
                'hash_sha256' => $hash,
                'mime_type' => $archivo->getMimeType(),
                'tamanio_bytes' => $archivo->getSize(),
                'subido_por' => $subidoPor->id,
                'subido_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Si falla la persistencia en BD, no dejar archivo físico huérfano.
            Storage::disk($disco)->delete($ruta);

            throw $e;
        }




        // 'subido_at' se resuelve con useCurrent() en BD; refresh trae el valor.
        return $adjunto->refresh();
    }
}
