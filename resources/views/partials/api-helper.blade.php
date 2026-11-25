<script>
    // apiFetch: cliente HTTP con sesión stateful (cookies HttpOnly + CSRF).
    // Adjunta la cookie XSRF-TOKEN y lanza/alimenta el sistema de toasts.
    window.apiFetch = async function (url, options = {}) {
        const opts = options || {};
        const method = (opts.method || 'GET').toUpperCase();
        const headers = opts.headers || {};

        headers['Accept'] = 'application/json';

        if (opts.body && !(opts.body instanceof FormData) && method !== 'GET' && method !== 'HEAD') {
            headers['Content-Type'] = 'application/json';
        }

        if (method !== 'GET' && method !== 'HEAD') {
            const xsrf = document.cookie
                .split('; ')
                .find(row => row.startsWith('XSRF-TOKEN='));
            if (xsrf) {
                headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf.split('=')[1]);
            }
        }

        let body = opts.body;
        if (body && !(body instanceof FormData) && typeof body !== 'string') {
            body = JSON.stringify(body);
        }

        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers,
            body: method === 'GET' || method === 'HEAD' ? undefined : body,
        });

        const data = await response.json().catch(() => ({}));

        if (response.status === 401) {
            window.location.href = '/login';
            throw new Error('Sesión expirada. Inicie sesión nuevamente.');
        }

        return { ok: response.ok, status: response.status, data };
    };

    // apiToast: muestra notificaciones temporales (éxito, error, validación).
    window.apiToast = function (tipo, mensaje, errores = null) {
        const detalle = errores
            ? Object.values(errores).flat().map(e => `• ${e}`).join('\n')
            : null;
        window.dispatchEvent(new CustomEvent('toast', {
            detail: { tipo, mensaje, detalle },
        }));
    };

    // imprimirPdf: abre el dialogo nativo de impresion del navegador sin
    // abandonar la pagina ni descargar el archivo. Las cabeceras de seguridad
    // de la aplicacion prohiben incrustar respuestas en iframes, por eso el
    // HTML de ?impresion=1 se obtiene por fetch y se monta en el propio
    // documento antes de llamar a window.print().
    window.imprimirPdf = async function ({ url, titulo = 'Documento PDF', alCerrar = null } = {}) {
        if (!url) {
            window.apiToast('error', 'El documento PDF no está disponible.');
            return;
        }

        if (!document.getElementById('estilo-impresion-documento')) {
            const estilo = document.createElement('style');

            estilo.id = 'estilo-impresion-documento';
            estilo.textContent = `
                @media print {
                    html, body { margin: 0 !important; padding: 0 !important; height: auto !important; }
                    body.imprimiendo-documento > *:not(.documento-impresion) { display: none !important; }
                    body.imprimiendo-documento .documento-impresion {
                        display: block !important;
                        width: 100% !important;
                    }
                }
            `;
            document.head.appendChild(estilo);
        }

        // La API devuelve URLs absolutas con el APP_URL configurado; al
        // reconstruir la ruta sobre el origen real se garantiza same-origin.
        const destino = new URL(url, window.location.href);
        const separador = destino.search ? '&' : '?';
        const ruta = destino.pathname + destino.search + separador + 'impresion=1';
        const contenedor = document.createElement('div');

        contenedor.className = 'documento-impresion';
        contenedor.style.display = 'none';
        contenedor.setAttribute('aria-hidden', 'true');
        contenedor.setAttribute('data-titulo', titulo);

        let limpiezaRealizada = false;
        let temporizadorRespaldo = null;

        const limpiar = () => {
            if (limpiezaRealizada) {
                return;
            }
            limpiezaRealizada = true;
            window.removeEventListener('afterprint', limpiar);
            clearTimeout(temporizadorRespaldo);
            document.body.classList.remove('imprimiendo-documento');
            contenedor.remove();

            if (typeof alCerrar === 'function') {
                alCerrar();
            }
        };

        const fallar = (mensaje, error = null) => {
            if (error) {
                console.error('imprimirPdf', error);
            }
            window.apiToast('error', mensaje);
            limpiar();
        };

        try {
            const respuesta = await fetch(ruta, {
                credentials: 'same-origin',
                headers: { 'Accept': 'text/html' },
            });

            if (respuesta.status === 401) {
                fallar('Sesión expirada. Inicie sesión nuevamente.');
                return;
            }

            if (!respuesta.ok) {
                fallar(`No se pudo cargar el documento para imprimir (HTTP ${respuesta.status}).`);
                return;
            }

            const tipoContenido = respuesta.headers.get('content-type') || '';

            if (!tipoContenido.includes('text/html')) {
                fallar(`El documento para imprimir no es HTML (${tipoContenido || 'sin tipo'}).`);
                return;
            }

            const documento = new DOMParser().parseFromString(await respuesta.text(), 'text/html');

            documento.querySelectorAll('style').forEach((estiloDocumento) => {
                contenedor.appendChild(estiloDocumento.cloneNode(true));
            });

            Array.from(documento.body.childNodes).forEach((nodo) => {
                contenedor.appendChild(nodo.cloneNode(true));
            });

            document.body.appendChild(contenedor);

            window.addEventListener('afterprint', limpiar);
            temporizadorRespaldo = setTimeout(limpiar, 60000);

            document.body.classList.add('imprimiendo-documento');
            window.focus();
            window.print();
        } catch (e) {
            const mensaje = e?.message
                ? `No se pudo abrir la ventana de impresión: ${e.message}`
                : 'No se pudo abrir la ventana de impresión.';
            fallar(mensaje, e);
        }
    };
</script>
