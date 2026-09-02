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
</script>
