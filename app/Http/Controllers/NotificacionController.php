<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function resumen(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $notificaciones = $user->unreadNotifications()
                ->latest()
                ->limit(20)
                ->get()
                ->map(function ($n) {
                    return [
                        'id' => $n->id,
                        'tipo' => $n->type,
                        'data' => $n->data,
                        'created_at' => optional($n->created_at)->toIso8601String(),
                        'read_at' => optional($n->read_at)->toIso8601String(),
                    ];
                });

            return response()->json([
                'data' => $notificaciones,
                'meta' => [
                    'no_leidas' => $user->unreadNotifications()->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'meta' => ['no_leidas' => 0],
                'message' => 'No se pudieron cargar las notificaciones en este momento.',
            ]);
        }
    }

    public function marcarLeida(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'string'],
            'marcar_todas' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        if (! empty($validated['marcar_todas'])) {
            $user->unreadNotifications->markAsRead();
        } elseif (! empty($validated['id'])) {
            $notificacion = $user->notifications()->where('id', $validated['id'])->first();
            if ($notificacion) {
                $notificacion->markAsRead();
            }
        }

        return response()->json([
            'ok' => true,
            'meta' => ['no_leidas' => $user->unreadNotifications()->count()],
        ]);
    }
}
