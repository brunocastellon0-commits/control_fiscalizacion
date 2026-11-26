<div x-data="notificacionesPlazos()" x-init="init()" class="relative" @keydown.escape.window="abierto = false">
    <button type="button" @click="toggle()"
        class="relative inline-flex items-center justify-center w-10 h-10 rounded-xl text-gris hover:bg-gris-claro/60 transition"
        :aria-expanded="abierto ? 'true' : 'false'" aria-label="Notificaciones">
        <i class="fa-regular fa-bell text-base"></i>
        <span x-show="meta.no_leidas > 0" x-text="meta.no_leidas > 99 ? '99+' : meta.no_leidas"
            class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-[#F15A24] text-white text-[10px] font-bold flex items-center justify-center"></span>
    </button>

    <div x-show="abierto" x-cloak @click.outside="abierto = false" x-transition
        class="absolute right-0 mt-2 w-80 sm:w-96 bg-white border border-gris-claro rounded-xl shadow-xl z-50 overflow-hidden">
        <div class="px-4 py-3 border-b border-gris-claro flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-grafito">Notificaciones</p>
                <p class="text-xs text-gris" x-text="meta.no_leidas + ' sin leer'"></p>
            </div>
            <button type="button" @click="marcarTodas()" class="text-xs text-verde-profundo hover:underline" :disabled="cargando || meta.no_leidas === 0">Marcar todas</button>
        </div>

        <div class="max-h-80 overflow-y-auto">
            <template x-if="cargando">
                <div class="p-4 text-sm text-gris"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando notificaciones...</div>
            </template>
            <template x-if="!cargando && error">
                <div class="p-4 text-sm text-[#B53F12]">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i><span x-text="error"></span>
                    <button type="button" class="ml-2 underline" @click="cargar()">Reintentar</button>
                </div>
            </template>
            <template x-if="!cargando && !error && items.length === 0">
                <div class="p-6 text-center text-sm text-gris">
                    <i class="fa-regular fa-bell-slash text-2xl opacity-40 mb-2"></i>
                    <p>No tiene notificaciones por ahora.</p>
                </div>
            </template>
            <template x-if="!cargando && items.length > 0">
                <ul class="divide-y divide-gris-claro">
                    <template x-for="n in items" :key="n.id">
                        <li class="p-3 hover:bg-gris-claro/30">
                            <div class="flex items-start gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-grafito font-medium" x-text="n.data?.titulo || n.data?.mensaje || 'Notificación'"></p>
                                    <p class="text-xs text-gris mt-0.5" x-show="n.data?.detalle || n.data?.descripcion" x-text="n.data?.detalle || n.data?.descripcion"></p>
                                    <a x-show="n.adjunto_url" :href="n.adjunto_url" target="_blank"
                                        class="inline-flex items-center gap-1 mt-1.5 text-[11px] text-verde-profundo hover:underline">
                                        <i class="fa-solid fa-file-pdf"></i> Ver PDF
                                    </a>
                                    <p class="text-[11px] text-gris mt-1" x-text="formatearFecha(n.created_at)"></p>
                                </div>
                                <button type="button" class="text-xs text-verde-profundo hover:underline shrink-0" @click="marcarLeida(n.id)" x-show="!n.read_at">Leída</button>
                            </div>
                        </li>
                    </template>
                </ul>
            </template>
        </div>
    </div>

    <script>
        function notificacionesPlazos() {
            return {
                abierto: false,
                cargando: false,
                error: null,
                items: [],
                meta: { no_leidas: 0 },
                async init() { await this.cargar(true); },
                async toggle() {
                    this.abierto = !this.abierto;
                    if (this.abierto) await this.cargar();
                },
                async cargar(silencioso = false) {
                    if (!silencioso) this.cargando = true;
                    this.error = null;
                    try {
                        const { ok, data } = await window.apiFetch('/api/notificaciones/resumen');
                        if (ok) {
                            this.items = data.data || [];
                            this.meta = data.meta || { no_leidas: 0 };
                        } else {
                            this.items = [];
                            this.meta = { no_leidas: 0 };
                            this.error = data?.message || null;
                        }
                    } catch (e) {
                        if (!silencioso) this.error = 'No se pudieron cargar las notificaciones.';
                    } finally {
                        if (!silencioso) this.cargando = false;
                    }
                },
                async marcarLeida(id) {
                    try {
                        const { ok, data } = await window.apiFetch('/api/notificaciones/marcar-leida', { method: 'POST', body: { id } });
                        if (ok) {
                            this.meta.no_leidas = data?.meta?.no_leidas ?? this.meta.no_leidas;
                            await this.cargar(true);
                        }
                    } catch (e) {}
                },
                async marcarTodas() {
                    try {
                        const { ok, data } = await window.apiFetch('/api/notificaciones/marcar-leida', { method: 'POST', body: { marcar_todas: true } });
                        if (ok) {
                            this.meta.no_leidas = data?.meta?.no_leidas ?? 0;
                            await this.cargar();
                        }
                    } catch (e) {}
                },
                formatearFecha(fecha) {
                    if (!fecha) return '';
                    const d = new Date(fecha);
                    return isNaN(d) ? '' : d.toLocaleString('es-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
                }
            };
        }
    </script>
</div>
