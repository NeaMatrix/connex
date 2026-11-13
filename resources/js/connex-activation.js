/**
 * Login page, activation step (login.require_access): the number signed in but
 * its subscription grants no access. The subscriber activates with an OTP, or
 * checks again after topping up; then the login finishes like any other.
 *
 * Exposes window.ConnexActivation.show(data, finish).
 */
(function (Connex, global) {
    'use strict';

    var root = document.querySelector('[data-connex-login]');
    if (!root) { return; }

    var step = root.querySelector('[data-step="activation"]');
    var message = step.querySelector('[data-access-message]');
    var activate = step.querySelector('[data-activate]');
    var check = step.querySelector('[data-check-access]');
    var otpForm = step.querySelector('[data-step="activation-otp"]');
    var otpInput = otpForm.querySelector('input');
    var confirm = otpForm.querySelector('button[type="submit"]');
    var feedback = Connex.feedback(root);
    var finish = null;

    Connex.digitsOnly(otpInput);

    function show(data, onFinish) {
        finish = onFinish;
        root.querySelectorAll('form[data-step="phone"], form[data-step="otp"]').forEach(function (form) { form.hidden = true; });
        step.hidden = false;
        otpForm.hidden = true;
        message.textContent = data.message;
        // An active subscription that failed to renew needs balance, not activation.
        activate.hidden = data.access === 'insufficient_balance';
    }

    function settle(data) {
        if (data.step === 'activation') {
            show(data, finish);
            return null;
        }
        return finish(data);
    }

    function run(button, action) {
        Connex.busy(button, true);
        feedback.clear();
        return action().catch(function (error) {
            feedback.error(error.message);
        }).finally(function () {
            Connex.busy(button, false);
        });
    }

    activate.addEventListener('click', function () {
        run(activate, function () {
            return Connex.request('POST', root.dataset.activationUrl).then(function () {
                otpForm.hidden = false;
                otpInput.value = '';
                otpInput.focus();
            });
        });
    });

    check.addEventListener('click', function () {
        run(check, function () {
            return Connex.request('POST', root.dataset.accessCheckUrl).then(settle);
        });
    });

    otpForm.addEventListener('submit', function (event) {
        event.preventDefault();
        run(confirm, function () {
            return Connex.request('POST', root.dataset.activationConfirmUrl, { otp: otpInput.value }).then(settle);
        });
    });

    global.ConnexActivation = { show: show };
})(window.Connex, window);
