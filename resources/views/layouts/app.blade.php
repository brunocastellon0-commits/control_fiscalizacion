<!DOCTYPE html>
<html lang="es" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'Sistema de Control y Fiscalización') | Consejo de la Magistratura</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .bg-verde-profundo {
            background-color: #0F4C3A;
        }

        .bg-verde-institucional {
            background-color: #1E7A52;
        }

        .bg-verde-claro {
            background-color: #DCEBDD;
        }

        .bg-azul-petroleo {
            background-color: #2A5F6F;
        }

        .bg-marfil {
            background-color: #F4F1E6;
        }

        .bg-grafito {
            background-color: #3A4240;
        }

        .bg-gris-claro {
            background-color: #F3F5F2;
        }

        .text-verde-profundo {
            color: #0F4C3A;
        }

        .text-verde-institucional {
            color: #1E7A52;
        }

        .text-azul-petroleo {
            color: #2A5F6F;
        }

        .text-grafito {
            color: #3A4240;
        }

        .text-gris {
            color: #7A8A84;
        }

        .hover\:bg-verde-profundo:hover {
            background-color: #0B3A2C;
        }

        .hover\:bg-verde-institucional:hover {
            background-color: #17603F;
        }

        .hover\:bg-verde-claro:hover {
            background-color: #D3E6D7;
        }

        .bg-institucional-gradient {
            background: linear-gradient(135deg, #0F4C3A 0%, #17603F 55%, #0B3A2C 100%);
        }

        .sidebar-gradient {
            background: linear-gradient(180deg, #0F4C3A 0%, #0B3A2C 100%);
        }

        /* Transiciones suaves de textos al abrir/cerrar el sidebar */
        .sidebar-label,
        .sidebar-user-info {
            max-width: 180px;
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
            transition: opacity 90ms ease;
        }

        .sidebar-section {
            max-height: 40px;
            overflow: hidden;
            transition: opacity 90ms ease;
        }

        .sidebar-item {
            transition: gap 200ms ease, padding 200ms ease, background-color 200ms ease, color 200ms ease;
        }

        .sidebar-nav,
        .sidebar-footer {
            transition: padding 200ms ease;
        }

        .sidebar-user {
            transition: gap 200ms ease;
        }

        @media (min-width: 1024px) {
            /* Al abrir, el texto aparece cuando el sidebar ya recuperó su ancho */
            #sidebar-principal:not(.rail) .sidebar-label,
            #sidebar-principal:not(.rail) .sidebar-user-info,
            #sidebar-principal:not(.rail) .sidebar-section {
                transition: opacity 90ms ease 190ms;
            }

            /* Al cerrar, los textos desaparecen de inmediato (sin recortarse) */
            #sidebar-principal.rail .sidebar-label,
            #sidebar-principal.rail .sidebar-user-info {
                max-width: 0;
                opacity: 0;
                transition: none;
            }

            #sidebar-principal.rail .sidebar-section {
                max-height: 0;
                padding-bottom: 0;
                opacity: 0;
                transition: none;
            }

            #sidebar-principal.rail .sidebar-item {
                gap: 0;
                padding-left: 34px;
                padding-right: 0;
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
            }

            #sidebar-principal.rail .sidebar-user {
                justify-content: center;
                gap: 0;
            }
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-50%);
            }

            100% {
                transform: translateX(50%);
            }
        }

        .animate-shimmer {
            animation: shimmer 3s ease-in-out infinite;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
    <link rel="icon" type="image/png" href="{{ asset('images/logo3.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo3.png') }}">
</head>

<body class="h-full bg-gris-claro antialiased">
    @include('partials.api-helper')

    @php
        $usuarioAutenticado = auth()->user();
        $usuarioInicial = [
            'nombre' => trim(($usuarioAutenticado?->nombres ?? '') . ' ' . ($usuarioAutenticado?->apellidos ?? '')),
            'rol' => $usuarioAutenticado?->rol?->codigo,
        ];

        // Rutas de los logos del sidebar. Los archivos van en public/images/.
        // $logoExpandido: sidebar abierto (logo horizontal).
        // $logoColapsado: rail de íconos (logo compacto).
        $logoExpandido = 'images/logoBlanco.png';
        $logoColapsado = 'images/vertical-cm-logo.png';
    @endphp

    <div x-data="workstation({{ Js::from(['usuario' => $usuarioInicial]) }})"
        @toast.window="mostrarToast($event.detail)" class="min-h-full">
        <div x-init="$nextTick(() => { cargarUsuario(); initSidebar(); })">

            <!-- Overlay para móvil -->
            <div x-show="sidebarOpen" x-cloak @click="toggleSidebar"
                class="fixed inset-0 bg-black/40 z-40 lg:hidden transition-opacity"></div>

            <!-- Sidebar de altura completa (colapsable a rail de íconos) -->
            <aside id="sidebar-principal" x-cloak
                :class="sidebarOpen
                    ? 'w-[260px] translate-x-0'
                    : 'w-[260px] -translate-x-full lg:w-[104px] lg:translate-x-0 rail'"
                class="fixed inset-y-0 left-0 z-50 sidebar-gradient text-white shadow-xl flex flex-col overflow-hidden transition-all duration-200 ease-in-out">

                <!-- Logo institucional -->
                <div class="h-24 shrink-0 flex items-center justify-center transition-colors duration-300"
                    :class="sidebarOpen ? 'px-4' : 'px-2'">
                    <!-- Logo del sidebar abierto -->
                    <img src="{{ asset($logoExpandido) }}" alt="Consejo de la Magistratura"
                        class="h-14 w-auto max-w-full object-contain" :class="sidebarOpen ? '' : 'lg:hidden'">
                    <!-- Logo del rail colapsado -->
                    <img src="{{ asset($logoColapsado) }}" alt="Consejo de la Magistratura"
                        class="h-20 w-auto max-w-full object-contain" :class="sidebarOpen ? 'hidden' : 'hidden lg:block'">
                </div>

                <!-- Navegación -->
                <nav class="sidebar-nav flex-1 overflow-y-auto py-6 text-[13px]" :class="sidebarOpen ? 'px-4' : 'px-2'">
                    <div class="sidebar-section px-3 pb-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/45">
                        Principal
                    </div>

                    <div class="space-y-1">
                        <a href="/expedientes" title="Bandeja de entrada"
                            :class="activo('/expedientes') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                            class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                            <i class="fa-solid fa-inbox w-5 text-center text-[15px]"
                                :class="activo('/expedientes') ? 'text-verde-profundo' : 'text-white/70'"></i>
                            <span class="sidebar-label">Bandeja de entrada</span>
                        </a>
                    </div>

                    <template x-if="usuario?.rol === 'ENCARGADA'">
                        <div class="space-y-1 mt-1">
                            <a href="/encargada/dashboard" title="Dashboard"
                                :class="activo('/encargada/dashboard') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                                class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                                <i class="fa-solid fa-chart-line w-5 text-center text-[15px]"
                                    :class="activo('/encargada/dashboard') ? 'text-verde-profundo' : 'text-white/70'"></i>
                                <span class="sidebar-label">Dashboard</span>
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

                    <template x-if="usuario?.rol === 'TECNICO'">
                        <div class="space-y-1 mt-1">
                            <a href="/expedientes/nuevo" title="Apertura de causa"
                                :class="activo('/expedientes/nuevo') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                                class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                                <i class="fa-solid fa-folder-plus w-5 text-center text-[15px]"
                                    :class="activo('/expedientes/nuevo') ? 'text-verde-profundo' : 'text-white/70'"></i>
                                <span class="sidebar-label">Apertura de causa</span>
                            </a>
                        </div>
                    </template>

                    <template x-if="usuario?.rol === 'ADMIN'">
                        <div class="space-y-1 mt-6">
                            <div class="sidebar-section px-3 pb-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/45">
                                Administración
                            </div>

                            <a href="/administrador/dashboard" title="Dashboard"
                                :class="activo('/administrador/dashboard') ? 'bg-white text-verde-profundo shadow-sm' : 'text-white/85 hover:bg-white/10'"
                                class="sidebar-item flex items-center gap-3 px-3.5 py-2.5 rounded-lg font-medium">
                                <i class="fa-solid fa-chart-line w-5 text-center text-[15px]"
                                    :class="activo('/administrador/dashboard') ? 'text-verde-profundo' : 'text-white/70'"></i>
                                <span class="sidebar-label">Dashboard</span>
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
                        </div>
                    </template>
                </nav>

                <!-- Usuario y cierre de sesión -->
                <div class="sidebar-footer shrink-0 border-t border-white/10 py-4" :class="sidebarOpen ? 'px-4' : 'px-3'">
                    <div class="sidebar-user flex items-center gap-3 mb-3">
                        <div
                            class="w-9 h-9 rounded-full bg-white text-verde-profundo flex items-center justify-center text-sm font-bold shrink-0">
                            <span x-text="inicialUsuario()"></span>
                        </div>
                        <div class="sidebar-user-info min-w-0">
                            <p class="text-[13px] font-semibold text-white truncate" x-text="usuario?.nombre || '...'"></p>
                            <p class="text-[11px] text-white/60 truncate" x-text="rolLegible(usuario?.rol)"></p>
                        </div>
                    </div>

                    <button @click="cerrarSesion"
                        class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-white/35 text-xs font-medium text-white hover:bg-white/10 transition focus:outline-none focus:ring-2 focus:ring-white/40"
                        :class="sidebarOpen ? '' : 'lg:py-3'" aria-label="Cerrar sesión">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span class="sidebar-label">Cerrar sesión</span>
                    </button>
                </div>
            </aside>

            <!-- Header + contenido -->
            <div class="min-h-screen flex flex-col transition-[padding] duration-200 ease-in-out"
                :class="sidebarOpen ? 'lg:pl-[260px]' : 'lg:pl-[104px]'">

                <!-- Barra superior -->
                <header
                    class="sticky top-0 z-30 h-[72px] shrink-0 bg-white border-b border-black/5 flex items-center gap-3 sm:gap-4 px-4 sm:px-6">
                    <button @click="toggleSidebar" :aria-expanded="sidebarOpen ? 'true' : 'false'"
                        aria-controls="sidebar-principal" aria-label="Mostrar u ocultar menú lateral"
                        class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-[#E7F0E7] text-verde-profundo hover:bg-[#DCEBDD] transition focus:outline-none focus:ring-2 focus:ring-verde-institucional/40">
                        <i class="fa-solid fa-bars text-base"></i>
                    </button>

                    <h1 class="text-base sm:text-lg font-bold text-verde-profundo truncate">
                        @yield('titulo', 'Panel')
                    </h1>

                    <div class="flex-1"></div>

                    <div class="hidden md:block text-xs text-gris text-right whitespace-nowrap">
                        Consejo de la Magistratura <span class="text-gris/50">·</span> Control y Fiscalización
                    </div>
                </header>

                <!-- Contenido -->
                <main class="flex-1 bg-gris-claro">
                    @yield('contenido')
                </main>
            </div>

            <!-- Sistema de toasts -->
            <div class="fixed bottom-4 right-4 z-[60] space-y-2 w-full max-w-sm">
                <template x-for="t in toasts" :key="t.id">
                    <div x-show="t.visible" x-transition
                        class="rounded-lg border shadow-lg text-sm p-3.5 flex items-start gap-3" :class="{
                            'bg-[#F15A24]/10 border-[#F15A24]/30 text-[#B53F12]': t.tipo === 'error',
                            'bg-[#8CC63F]/10 border-[#8CC63F]/40 text-[#3F5E1B]': t.tipo === 'exito',
                            'bg-[#27A9E0]/10 border-[#27A9E0]/30 text-[#145D82]': t.tipo === 'info'
                        }">
                        <i :class="{
                            'fa-solid fa-circle-exclamation': t.tipo === 'error',
                            'fa-solid fa-circle-check': t.tipo === 'exito',
                            'fa-solid fa-circle-info': t.tipo === 'info'
                        }" class="mt-0.5 shrink-0"></i>
                        <div>
                            <p class="font-semibold leading-snug" x-text="t.mensaje"></p>
                            <pre v-if="t.detalle" x-show="t.detalle" class="mt-1 text-xs whitespace-pre-line font-sans"
                                x-text="t.detalle"></pre>
                        </div>
                        <button @click="cerrarToast(t.id)" class="ml-auto text-xs opacity-60 hover:opacity-100">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function workstation(initial = {}) {
            return {
                usuario: initial.usuario ?? null,
                toasts: [],
                _toastId: 0,
                sidebarOpen: (() => {
                    try {
                        const guardado = localStorage.getItem('cf_sidebar_open');
                        if (guardado !== null) {
                            return guardado === '1';
                        }
                    } catch (e) {
                        // localStorage no disponible
                    }
                    return window.innerWidth >= 1024;
                })(),

                initSidebar() {
                    // En pantallas pequeñas el sidebar arranca siempre oculto
                    if (window.innerWidth < 1024) {
                        this.sidebarOpen = false;
                    }
                },

                toggleSidebar() {
                    this.sidebarOpen = !this.sidebarOpen;
                    try {
                        localStorage.setItem('cf_sidebar_open', this.sidebarOpen ? '1' : '0');
                    } catch (e) {
                        // localStorage no disponible
                    }
                },

                activo(ruta) {
                    const actual = window.location.pathname;
                    if (ruta === '/expedientes') {
                        // Resalta la bandeja en /expedientes y en el detalle /expedientes/{id},
                        // pero no en /expedientes/nuevo (que tiene su propio enlace).
                        return actual === '/expedientes'
                            || (actual.startsWith('/expedientes/') && actual !== '/expedientes/nuevo');
                    }
                    return actual === ruta || actual.startsWith(ruta + '/');
                },

                inicialUsuario() {
                    const nombre = (this.usuario?.nombre ?? '').trim();
                    return nombre ? nombre.charAt(0).toUpperCase() : '?';
                },

                rolLegible(rol) {
                    const etiquetas = {
                        ADMIN: 'Administrador',
                        ENCARGADA: 'Encargada',
                        TECNICO: 'Técnico',
                    };
                    return etiquetas[rol] ?? (rol || '');
                },

                async cargarUsuario() {
                    try {
                        const { ok, data } = await window.apiFetch('/api/me');
                        if (ok) {
                            this.usuario = data;
                            this.usuario.nombre = [data.nombres, data.apellidos].filter(Boolean).join(' ');
                            this.usuario.rol = data.rol?.codigo || '';
                        }
                    } catch (error) {
                        // 401 redirige a /login desde apiFetch
                    }
                },

                async cerrarSesion() {
                    try {
                        await window.apiFetch('/api/logout', { method: 'POST' });
                    } catch (error) {
                        // Ya redirigirá apiFetch si 401; de lo contrario seguimos
                    }
                    window.location.href = '/login';
                },

                mostrarToast(detalle) {
                    const id = ++this._toastId;
                    this.toasts.push({ id, visible: true, ...detalle });
                    setTimeout(() => this.cerrarToast(id), detalle.tipo === 'error' ? 6000 : 3500);
                },

                cerrarToast(id) {
                    const toast = this.toasts.find(t => t.id === id);
                    if (toast) {
                        toast.visible = false;
                        setTimeout(() => {
                            this.toasts = this.toasts.filter(t => t.id !== id);
                        }, 200);
                    }
                },
            };
        }
    </script>
</body>

</html>
