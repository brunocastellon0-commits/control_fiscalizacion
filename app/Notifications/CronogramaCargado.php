<?php

namespace App\Notifications;

use App\Models\Actuado;
use App\Models\Expediente;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CronogramaCargado extends Notification
{
    use Queueable;

    public function __construct(
        public Expediente $expediente,
        public Actuado $actuado,
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
            'titulo' => 'Cronograma de trabajo cargado',
            'detalle' => sprintf(
                'El expediente %s tiene un cronograma cargado. Solo se notifica a la Encargada; no requiere su aprobación.',
                $this->expediente->nurej_code,
            ),
            'tipo' => 'CRONOGRAMA_CARGADO',
            'expediente_id' => $this->expediente->id,
            'actuado_id' => $this->actuado->id,
            'requiere_accion' => false,
        ];
    }
}
