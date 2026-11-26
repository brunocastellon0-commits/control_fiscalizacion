<?php

namespace App\Notifications;

use App\Models\Actuado;
use App\Models\Adjunto;
use App\Models\Expediente;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AvisoParaRevision extends Notification
{
    use Queueable;

    public function __construct(
        public Expediente $expediente,
        public Actuado $actuado,
        public string $titulo,
        public string $detalle,
        public string $categoria,
        public ?Adjunto $adjunto = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => $this->titulo,
            'detalle' => $this->detalle,
            'tipo' => strtoupper($this->categoria),
            'categoria' => $this->categoria,
            'expediente_id' => $this->expediente->id,
            'actuado_id' => $this->actuado->id,
            'adjunto_id' => $this->adjunto?->id,
            'requiere_accion' => false,
        ];
    }
}
