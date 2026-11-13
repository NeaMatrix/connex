/**
 * Account page: activate or cancel the subscription, each confirmed by OTP.
 */
(function (Connex) {
    'use strict';

    var root = document.querySelector('[data-connex-account]');
    if (!root) { return; }

    var otpForm = root.querySelector('[data-step="otp"]');
    var otpInput = otpForm.querySelector('input');
    var confirm = otpForm.querySelector('button[type="submit"]');
    var feedback = Connex.feedback(root);
    var pending = null;

    Connex.digitsOnly(otpInput);

    var urls = {
        activate: [root.dataset.activateUrl, root.dataset.activateConfirmUrl],
        cancel: [root.dataset.cancelUrl, root.dataset.cancelConfirmUrl],
    };

    root.querySelectorAll('[data-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            var action = button.dataset.action;
            Connex.busy(button, true);
            feedback.clear();

            Connex.request('POST', urls[action][0]).then(function () {
                pending = action;
                otpForm.hidden = false;
                otpInput.value = '';
                otpInput.focus();
            }).catch(function (error) {
                feedback.error(error.message);
            }).finally(function () {
                Connex.busy(button, false);
            });
        });
    });

    otpForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!pending) { return; }
        Connex.busy(confirm, true);

        Connex.request('POST', urls[pending][1], { otp: otpInput.value }).then(function (data) {
            feedback.success(data.message);
            setTimeout(function () { location.reload(); }, 1200);
        }).catch(function (error) {
            feedback.error(error.message);
        }).finally(function () {
            Connex.busy(confirm, false);
        });
    });

    root.querySelector('[data-close]').addEventListener('click', function () {
        pending = null;
        otpForm.hidden = true;
        feedback.clear();
    });
})(window.Connex);
