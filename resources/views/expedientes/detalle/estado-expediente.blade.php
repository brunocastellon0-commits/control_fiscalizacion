<div class="space-y-6">
<div class="bg-white rounded-xl border border-verde-institucional/45 shadow-sm overflow-hidden">
    <div class="bg-verde-institucional/15 px-4 py-3 rounded-t-xl border-b border-verde-institucional/25">
        <h3 class="text-sm font-bold text-verde-profundo uppercase"><i class="fa-solid fa-circle-check text-verde-institucional mr-2"></i> Estado del expediente</h3>
    </div>
    <div class="p-4 space-y-4 bg-verde-institucional/[0.06]">
        <div class="flex items-start gap-3 rounded-lg border border-verde-institucional/25 bg-white p-3">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-verde-institucional/15 text-verde-profundo">
                <i class="fa-solid fa-circle-check"></i>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-grafito" x-text="estadoPrincipal()"></p>
                <p class="text-[11px] text-gris mt-0.5">
                    Sub estado:
                    <span class="font-medium text-grafito" x-text="subestado()"></span>
                </p>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-t border-verde-institucional/20 pt-3">
            <div class="min-w-0">
                <dt class="text-[10px] font-bold uppercase tracking-wider text-verde-profundo/80">Fecha ingreso</dt>
                <dd class="text-sm text-grafito" x-text="formatDate(expediente.fecha_ingreso) || '—'"></dd>
            </div>
            <div class="min-w-0">
                <dt class="text-[10px] font-bold uppercase tracking-wider text-verde-profundo/80">Reglamento</dt>
                <dd class="text-sm text-grafito" x-text="expediente.reglamento?.codigo || '—'"></dd>
            </div>
        </dl>

        <div class="border-t border-verde-institucional/20 pt-3 space-y-2">
            <p class="text-[10px] font-bold uppercase tracking-wider text-verde-profundo/80">Plazo de la fase actual</p>

            <template x-if="expediente.sem_plazo">
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold text-grafito uppercase" x-text="plazoVigenteFase()?.tipo_plazo || 'Plazo'"></span>
                        <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded text-[10px] font-bold border shrink-0"
                            :class="getSemaforoClass(expediente.sem_plazo.codigo_color)">
                            <span class="rounded-full h-2 w-2 shrink-0" :class="getSemaforoDotClass(expediente.sem_plazo.codigo_color)"></span>
                            <span x-text="semEtiqueta(expediente.sem_plazo.codigo_color)"></span>
                        </span>
                    </div>

                    <template x-if="!expediente.sem_plazo.es_fuera_de_plazo">
                        <div>
                            <p class="text-sm text-grafito">
                                <span class="text-lg font-bold" x-text="expediente.sem_plazo.dias_restantes ?? '—'"></span>
                                días hábiles restantes
                                <span class="text-gris" x-show="plazoVigenteFase()?.dias_habiles_otorgados">
                                    de <span x-text="plazoVigenteFase()?.dias_habiles_otorgados"></span> otorgados
                                </span>
                            </p>
                            <div class="mt-2 flex items-center justify-between text-[11px] text-gris">
                                <span class="inline-flex items-center gap-1.5 font-medium text-grafito">
                                    <i class="fa-solid fa-hourglass-half animate-pulse text-verde-institucional"></i>
                                    Avance del plazo
                                </span>
                                <span class="font-semibold text-grafito"
                                    x-text="`${Math.min(100, Math.max(0, Math.round((expediente.sem_plazo.porcentaje_consumido ?? 0) * 100)))}%`"></span>
                            </div>
                            <div class="mt-1 h-2.5 w-full rounded-full bg-gris-claro overflow-hidden" role="progressbar"
                                aria-label="Porcentaje de plazo consumido"
                                :aria-valuenow="Math.round((expediente.sem_plazo.porcentaje_consumido ?? 0) * 100)" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full transition-all duration-500 animate-pulse"
                                    :class="getSemaforoDotClass(expediente.sem_plazo.codigo_color)"
                                    :style="`width: ${Math.min(100, Math.max(0, Math.round((expediente.sem_plazo.porcentaje_consumido ?? 0) * 100)))}%`"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="expediente.sem_plazo.es_fuera_de_plazo">
                        <p class="text-sm font-medium text-[#B53F12]">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                            <template x-if="(expediente.sem_plazo.dias_atrasados ?? 0) > 0">
                                <span>
                                    Vencido hace
                                    <span class="font-bold" x-text="expediente.sem_plazo.dias_atrasados"></span>
                                    día(s) hábil(es)
                                </span>
                            </template>
                            <template x-if="(expediente.sem_plazo.dias_atrasados ?? 0) === 0">
                                <span>Venció</span>
                            </template>
                            el <span class="font-bold" x-text="formatDate(expediente.sem_plazo.fecha_limite)"></span>
                        </p>
                    </template>
                </div>
            </template>

            <template x-if="!expediente.sem_plazo">
                <p class="text-xs text-gris">Sin plazo vigente para este expediente.</p>
            </template>
        </div>

        <div class="border-t border-gris-claro pt-3 space-y-2" x-show="esAc022()" x-cloak>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gris">Ampliación de plazo</p>
            <template x-if="tieneActuado('ACT_APROBAR_AMPLIACION')">
                <p class="text-xs font-semibold text-green-800"><i class="fa-solid fa-circle-check mr-1"></i> Ampliación de 5 días hábiles aprobada.</p>
            </template>
            <template x-if="!tieneActuado('ACT_APROBAR_AMPLIACION') && !tieneActuado('ACT_SOLICITAR_AMPLIACION')">
                <p class="text-xs text-gris">La ampliación excepcional de 5 días hábiles debe ser aprobada por la Encargada.</p>
            </template>
            <template x-if="tieneActuado('ACT_SOLICITAR_AMPLIACION') && !tieneActuado('ACT_APROBAR_AMPLIACION')">
                <p class="text-xs text-amber-800"><i class="fa-solid fa-hourglass-half mr-1"></i> Ampliación solicitada, en espera de la Encargada.</p>
            </template>
            <form @submit.prevent="submitAmpliacion()"
                x-show="!esEncargada() && pasoEditable() && estadoCodigo() === 'EN_EJECUCION' && !tieneActuado('ACT_SOLICITAR_AMPLIACION')"
                x-cloak class="space-y-2">
                <textarea x-model="formAmpliacion.justificacion" rows="2" minlength="10" required
                    title="Justificación de la solicitud de ampliación (obligatoria)"
                    placeholder="Fundamente la solicitud de ampliación"
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"></textarea>
                <button type="submit" :disabled="submittingAmpliacion"
                    title="Solicita 5 días hábiles adicionales sujetos a aprobación de la Encargada"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-verde-institucional text-verde-profundo text-sm font-semibold hover:bg-verde-institucional/10 disabled:opacity-50">
                    <i class="fa-solid fa-paper-plane"></i> Solicitar ampliación
                </button>
            </form>
        </div>
    </div>
</div>

<div id="panel-derivacion-transparencia"
    x-show="(!esEncargada() && modalIncompetenciaOpen) || (esEncargada() && tieneSolicitudIncompetenciaAbierta())"
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    :class="destacarTransparencia
        ? 'ring-2 ring-[#F15A24]/45 shadow-[0_0_20px_rgba(241,90,36,0.22)] animate-pulse'
        : 'shadow-sm'"
    class="bg-white rounded-xl border border-[#F15A24]/45 overflow-hidden transition-all duration-300">
    <div class="bg-[#F15A24]/15 px-4 py-3 border-b border-[#F15A24]/25 flex items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-[#B53F12] uppercase flex items-center gap-2">
            <i class="fa-solid fa-share-from-square text-[#F15A24]"></i>
            Derivación a Transparencia (Ley N° 974)
        </h3>
        <button type="button" x-show="!esEncargada()" @click="modalIncompetenciaOpen = false" title="Cerrar solicitud" aria-label="Cerrar solicitud"
            class="text-[#B53F12]/70 hover:text-[#B53F12] text-sm">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <template x-if="!esEncargada()">
        <form @submit.prevent="submitIncompetenciaSolicitud()" class="p-4 space-y-3 bg-[#F15A24]/[0.06]">
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Unidad de destino <span class="text-[#F15A24]">*</span></label>
                <select x-model="formIncompetencia.unidad_destino_id" required
                    class="w-full border border-[#F15A24]/30 rounded-lg text-sm px-3 py-2 bg-white focus:border-[#F15A24] focus:ring-[#F15A24]/30">
                    <option value="">-- Seleccione unidad --</option>
                    <template x-for="u in unidadesTransparencia" :key="u.id">
                        <option :value="u.id" x-text="u.nombre"></option>
                    </template>
                </select>
                <p x-show="cargandoUnidadesTransparencia" class="text-[11px] text-gris mt-1">
                    <i class="fa-solid fa-spinner fa-spin mr-1"></i>Cargando unidades...
                </p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Motivo / justificación <span class="text-[#F15A24]">*</span></label>
                <textarea x-model="formIncompetencia.motivo" rows="3" minlength="20" required
                    placeholder="Explique los indicios que fundamentan la derivación a Transparencia (mín. 20 caracteres)"
                    class="w-full border border-[#F15A24]/30 rounded-lg text-sm px-3 py-2 bg-white focus:border-[#F15A24] focus:ring-[#F15A24]/30"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Documento de respaldo (PDF) <span class="text-[#F15A24]">*</span></label>
                <input type="file" id="adjuntoIncompetencia" accept="application/pdf" required
                    @change="manejarArchivoIncompetencia($event)"
                    class="w-full text-xs text-gris file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#F15A24]/15 file:text-[#B53F12] hover:file:bg-[#F15A24]/25">
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-[#F15A24]/15">
                <button type="button" @click="modalIncompetenciaOpen = false"
                    class="px-3 py-1.5 rounded-lg bg-white border border-gris-claro text-xs font-medium text-grafito hover:bg-gris-claro/40">
                    Cancelar
                </button>
                <button type="submit" :disabled="submittingIncompetencia"
                    class="px-4 py-1.5 rounded-lg bg-[#F15A24]/85 text-white text-xs font-semibold hover:bg-[#F15A24] disabled:opacity-50">
                    <i class="fa-solid" :class="submittingIncompetencia ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                    <span class="ml-1" x-text="submittingIncompetencia ? 'Enviando...' : 'Enviar solicitud'"></span>
                </button>
            </div>
        </form>
    </template>

    <template x-if="esEncargada() && tieneSolicitudIncompetenciaAbierta()">
        <div class="p-4 space-y-3 bg-[#F15A24]/[0.06]">
            <div class="rounded-lg border border-[#F15A24]/20 bg-white p-3 space-y-2">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#B53F12]">Solicitud pendiente de visto bueno</p>
                <p class="text-xs text-grafito whitespace-pre-line" x-text="solicitudIncompetenciaAbierta()?.descripcion || 'Sin detalle'"></p>
                <template x-if="(solicitudIncompetenciaAbierta()?.adjuntos || []).length > 0">
                    <button type="button" @click="abrirAdjuntoActuado(solicitudIncompetenciaAbierta())"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-azul-petroleo hover:underline">
                        <i class="fa-solid fa-file-pdf text-[#F15A24]"></i> Ver documento de respaldo (PDF)
                    </button>
                </template>
            </div>

            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Observaciones (opcional si rechaza)</label>
                <textarea x-model="observacionesRechazoIncompetencia" rows="2"
                    placeholder="Indique el motivo en caso de observar la derivación..."
                    class="w-full border border-[#F15A24]/30 rounded-lg text-sm px-3 py-2 bg-white focus:border-[#F15A24] focus:ring-[#F15A24]/30"></textarea>
            </div>

            <div class="flex flex-wrap justify-end gap-2 pt-2 border-t border-[#F15A24]/15">
                <button type="button" @click="resolverIncompetencia(false, observacionesRechazoIncompetencia || null)"
                    :disabled="submittingVbIncompetencia"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#F15A24]/40 bg-[#F15A24]/10 text-[#B53F12] text-xs font-semibold hover:bg-[#F15A24]/20 disabled:opacity-50">
                    <i class="fa-solid fa-xmark"></i> Rechazar derivación
                </button>
                <button type="button" @click="resolverIncompetencia(true)"
                    :disabled="submittingVbIncompetencia"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-verde-profundo text-white text-xs font-semibold hover:bg-verde-institucional disabled:opacity-50">
                    <i class="fa-solid" :class="submittingVbIncompetencia ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    <span x-text="submittingVbIncompetencia ? 'Procesando...' : 'Aprobar derivación'"></span>
                </button>
            </div>
        </div>
    </template>
</div>
</div>
