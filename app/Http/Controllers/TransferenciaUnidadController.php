<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarRecepcionRequest;
use App\Http\Requests\RemitirUnidadRequest;
use App\Models\Expediente;
use App\Services\TransferenciaUnidadService;
use Illuminate\Http\JsonResponse;

class TransferenciaUnidadController extends Controller
{
    public function __construct(
        protected TransferenciaUnidadService $transferenciaService,
    ) {}

    /**
     * CTR-04 (RF-05): remisión física de la causa a una unidad externa.
     * Solo la Encargada (política remitirUnidad en RemitirUnidadRequest).
     *
     * @return JsonResponse 201 {transferencia_id, estado: REMITIDO_UNIDAD_EXTERNA}
     */
    public function remitir(RemitirUnidadRequest $request, Expediente $expediente): JsonResponse
    {
        $datos = $request->validated();

        $transferencia = $this->transferenciaService->remitir(
            expediente: $expediente,
            encargada: $request->user(),
            unidadId: (int) $datos['unidad_id'],
            motivo: $datos['motivo'],
            adjunto: $request->file('adjunto'),
            ipOrigen: $request->ip(),
        );

        return response()->json([
            'transferencia_id' => $transferencia->id,
            'estado' => TransferenciaUnidadService::ESTADO_REMITIDO_UNIDAD_EXTERNA,
        ], 201);
    }

    /**
     * CTR-05 (RF-05): recepción física desde la unidad externa. Solo la
     * Encargada (política registrarRecepcion en RegistrarRecepcionRequest).
     * Restaura la fase procesal exacta de la remisión abierta.
     *
     * @return JsonResponse 200 {estado: "<estado restaurado>"}
     */
    public function registrarRecepcion(RegistrarRecepcionRequest $request, Expediente $expediente): JsonResponse
    {
        $datos = $request->validated();

        $this->transferenciaService->registrarRecepcion(
            expediente: $expediente,
            encargada: $request->user(),
            unidadOrigenId: (int) $datos['unidad_origen_id'],
            motivo: $datos['motivo'],
            adjunto: $request->file('adjunto'),
            ipOrigen: $request->ip(),
        );

        return response()->json([
            'estado' => $expediente->refresh()->estadoActual?->codigo,
        ], 200);
    }
}
