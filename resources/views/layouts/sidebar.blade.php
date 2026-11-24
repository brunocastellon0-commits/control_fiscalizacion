@php
    $logoExpandido = 'images/logoBlanco.png';
    $logoColapsado = 'images/vertical-cm-logo.png';
@endphp

<aside id="sidebar-principal" x-cloak
    :class="sidebarOpen
        ? 'w-[260px] translate-x-0'
        : 'w-[260px] -translate-x-full lg:w-[104px] lg:translate-x-0 rail'"
    class="fixed inset-y-0 left-0 z-50 sidebar-gradient text-white shadow-xl flex flex-col overflow-hidden transition-all duration-200 ease-in-out">

    <div class="h-24 shrink-0 flex items-center justify-center transition-colors duration-300"
        :class="sidebarOpen ? 'px-4' : 'px-2'">
        <img src="{{ asset($logoExpandido) }}" alt="Consejo de la Magistratura"
            class="h-14 w-auto max-w-full object-contain" :class="sidebarOpen ? '' : 'lg:hidden'">
        <img src="{{ asset($logoColapsado) }}" alt="Consejo de la Magistratura"
            class="h-20 w-auto max-w-full object-contain" :class="sidebarOpen ? 'hidden' : 'hidden lg:block'">
    </div>

    <nav class="sidebar-nav flex-1 overflow-y-auto py-6 text-[13px]" :class="sidebarOpen ? 'px-4' : 'px-2'">
        <div class="sidebar-section px-3 pb-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/45">
            Principal
        </div>

        <template x-if="usuario?.rol && usuario.rol !== 'ENCARGADA' && usuario.rol !== 'ADMIN' && usuario.rol !== 'VENTANILLA'">
            <div class="space-y-1">
                <a href="/expedientes" title="Bandeja de entrada"
                    :class="activo('/expedientes') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-inbox w-5 text-center text-[15px]"
                        :class="activo('/expedientes') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Bandeja de entrada</span>
                </a>
            </div>
        </template>

        <template x-if="usuario?.rol === 'ENCARGADA'">
            <div class="space-y-1 mt-1">
                <a href="/encargada/dashboard" title="Panel de encargada"
                    :class="activo('/encargada/dashboard') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-chart-line w-5 text-center text-[15px]"
                        :class="activo('/encargada/dashboard') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Panel de encargada</span>
                </a>

                <a href="/encargada/bandeja-supervision" title="Bandeja de supervisión"
                    :class="activo('/encargada/bandeja-supervision') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-list-check w-5 text-center text-[15px]"
                        :class="activo('/encargada/bandeja-supervision') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Bandeja de supervisión</span>
                </a>

                <a href="/encargada/historial" title="Historial general"
                    :class="activo('/encargada/historial') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-clock-rotate-left w-5 text-center text-[15px]"
                        :class="activo('/encargada/historial') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Historial general</span>
                </a>

                <a href="/bandeja/sorteo" title="Sorteo de expedientes"
                    :class="activo('/bandeja/sorteo') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-shuffle w-5 text-center text-[15px]"
                        :class="activo('/bandeja/sorteo') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Sorteo de expedientes</span>
                </a>
            </div>
        </template>

        <template x-if="usuario?.rol === 'VENTANILLA'">
            <div class="space-y-1 mt-1">
                <a href="/expedientes/nuevo" title="Apertura de causa"
                    :class="activo('/expedientes/nuevo') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-folder-plus w-5 text-center text-[15px]"
                        :class="activo('/expedientes/nuevo') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Apertura de causa</span>
                </a>

                <a href="/ventanilla/aperturas" title="Mis aperturas"
                    :class="activo('/ventanilla/aperturas') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-clock-rotate-left w-5 text-center text-[15px]"
                        :class="activo('/ventanilla/aperturas') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Mis aperturas</span>
                </a>
            </div>
        </template>

        <template x-if="usuario?.rol === 'TECNICO' || usuario?.rol === 'AUD_JURIDICO' || usuario?.rol === 'AUD_FINANCIERO'">
            <div class="space-y-1 mt-1">
                <a href="/operativo/historial" title="Historial de expedientes"
                    :class="activo('/operativo/historial') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-clock-rotate-left w-5 text-center text-[15px]"
                        :class="activo('/operativo/historial') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Historial de expedientes</span>
                </a>
            </div>
        </template>

        <template x-if="usuario?.rol === 'AUD_FINANCIERO'">
            <div class="space-y-1 mt-1">
                <div class="sidebar-section px-3 pb-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/45">
                    Financiero
                </div>
                <a href="/financiero/dashboard" title="Panel financiero"
                    :class="activo('/financiero/dashboard') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-chart-pie w-5 text-center text-[15px]"
                        :class="activo('/financiero/dashboard') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Panel financiero</span>
                </a>
            </div>
        </template>

        <template x-if="usuario?.rol === 'ADMIN'">
            <div class="space-y-1 mt-1">
                <div class="sidebar-section px-3 pb-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/45">
                    Administración
                </div>

                <a href="/administrador/dashboard" title="Panel administrativo"
                    :class="activo('/administrador/dashboard') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-chart-line w-5 text-center text-[15px]"
                        :class="activo('/administrador/dashboard') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Panel administrativo</span>
                </a>

                <a href="/administrador/usuarios" title="Usuarios"
                    :class="activo('/administrador/usuarios') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-users w-5 text-center text-[15px]"
                        :class="activo('/administrador/usuarios') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Usuarios</span>
                </a>

                <a href="/administrador/monitoreo" title="Monitoreo de expedientes"
                    :class="activo('/administrador/monitoreo') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-binoculars w-5 text-center text-[15px]"
                        :class="activo('/administrador/monitoreo') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Monitoreo de expedientes</span>
                </a>

                <a href="/administrador/feriados" title="Feriados"
                    :class="activo('/administrador/feriados') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-calendar-days w-5 text-center text-[15px]"
                        :class="activo('/administrador/feriados') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Feriados</span>
                </a>

                <a href="/administrador/requisitos" title="Requisitos de admisibilidad"
                    :class="activo('/administrador/requisitos') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-list-check w-5 text-center text-[15px]"
                        :class="activo('/administrador/requisitos') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Requisitos</span>
                </a>
            </div>
        </template>

        <template x-if="usuario?.rol && usuario.rol !== 'ADMIN' && usuario.rol !== 'VENTANILLA'">
            <div class="space-y-1 mt-4">
                <div class="sidebar-section px-3 pb-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/45">
                    Reportes
                </div>

                <a href="/reportes" title="Reportes institucionales del SRS"
                    :class="activo('/reportes') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                    class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                    <i class="fa-solid fa-chart-column w-5 text-center text-[15px]"
                        :class="activo('/reportes') ? 'text-verde-profundo' : 'text-white/70'"></i>
                    <span class="sidebar-label">Reportes</span>
                </a>
            </div>
        </template>
    </nav>

    <div class="sidebar-footer shrink-0 border-t border-white/10 py-4" :class="sidebarOpen ? 'px-4' : 'px-3'">
        <div class="sidebar-user flex items-center gap-3 mb-3">
            <div
                class="w-9 h-9 rounded-full bg-white text-verde-profundo flex items-center justify-center text-sm font-bold shrink-0">
                <span x-text="inicialUsuario()"></span>
            </div>
            <div class="sidebar-user-info min-w-0">
                <p class="text-[13px] font-semibold text-white truncate" x-text="usuario?.nombre || 'Cargando usuario...'"></p>
                <p class="text-[11px] text-white/60 truncate" x-text="rolLegible(usuario?.rol) || 'Sin rol'"></p>
            </div>
        </div>

        <button @click="cerrarSesion" :disabled="cerrandoSesion"
            class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-white/35 text-xs font-medium text-white hover:bg-white/10 transition focus:outline-none focus:ring-2 focus:ring-white/40 disabled:opacity-60 disabled:cursor-not-allowed"
            :class="sidebarOpen ? '' : 'lg:py-3'" aria-label="Cerrar sesión">
            <i class="fa-solid" :class="cerrandoSesion ? 'fa-spinner fa-spin' : 'fa-arrow-right-from-bracket'"></i>
            <span class="sidebar-label" x-text="cerrandoSesion ? 'Cerrando...' : 'Cerrar sesión'"></span>
        </button>
    </div>
</aside>
