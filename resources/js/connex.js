/**
 * Shared helpers for the Connex pages: JSON requests with CSRF, error display,
 * and digits-only OTP fields. Exposed as window.Connex.
 */
(function (global) {
    'use strict';

    var csrf = document.querySelector('meta[name="csrf-token"]');

    function request(method, url, body) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf ? csrf.content : '',
            },
            body: body ? JSON.stringify(body) : undefined,
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (response.ok) {
                    return data;
                }
                var error = new Error(firstMessage(data) || response.statusText);
                error.status = response.status;
                error.data = data;
                throw error;
            });
        });
    }

    function firstMessage(data) {
        if (data && data.errors) {
            var first = Object.keys(data.errors)[0];
            return first ? data.errors[first][0] : null;
        }
        return data ? data.message : null;
    }

    function feedback(root) {
        var errorEl = root.querySelector('[data-error]');
        var successEl = root.querySelector('[data-success]');

        return {
            error: function (message) {
                if (successEl) { successEl.hidden = true; }
                errorEl.textContent = message;
                errorEl.hidden = !message;
            },
            success: function (message) {
                errorEl.hidden = true;
                if (successEl) {
                    successEl.textContent = message;
                    successEl.hidden = !message;
                }
            },
            clear: function () {
                errorEl.hidden = true;
                if (successEl) { successEl.hidden = true; }
            },
        };
    }

    function digitsOnly(input) {
        input.addEventListener('input', function () {
            input.value = input.value.replace(/\D+/g, '').slice(0, input.maxLength);
        });
    }

    function busy(button, isBusy) {
        button.disabled = isBusy;
        button.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    }

    global.Connex = { request: request, feedback: feedback, digitsOnly: digitsOnly, busy: busy };
})(window);
