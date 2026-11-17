/**
 * Account page: activate, cancel or change package, each confirmed by OTP.
 */
(function (Connex) {
    'use strict';

    var root = document.querySelector('[data-connex-account]');
    if (!root) { return; }

    var otpForm = root.querySelector('[data-step="otp"]');
    var otpInput = otpForm.querySelector('input');
    var confirm = otpForm.querySelector('button[type="submit"]');
    var plansForm = root.querySelector('[data-step="plans"]');
    var feedback = Connex.feedback(root);
    var pending = null;

    Connex.digitsOnly(otpInput);

    var urls = {
        activate: [root.dataset.activateUrl, root.dataset.activateConfirmUrl],
        cancel: [root.dataset.cancelUrl, root.dataset.cancelConfirmUrl],
        plan: [root.dataset.planUrl, root.dataset.planConfirmUrl],
    };

    /** Ask Connex for an OTP, then show the OTP form for this action. */
    function start(action, button, body) {
        Connex.busy(button, true);
        feedback.clear();

        Connex.request('POST', urls[action][0], body).then(function () {
            pending = { action: action, body: body || {} };
            otpForm.hidden = false;
            otpInput.value = '';
            otpInput.focus();
        }).catch(function (error) {
            feedback.error(error.message);
        }).finally(function () {
            Connex.busy(button, false);
        });
    }

    root.querySelectorAll('[data-action]').forEach(function (button) {
        button.addEventListener('click', function () { start(button.dataset.action, button); });
    });

    var togglePlans = root.querySelector('[data-toggle-plans]');
    if (togglePlans && plansForm) {
        togglePlans.addEventListener('click', function () { plansForm.hidden = !plansForm.hidden; });

        plansForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var chosen = plansForm.querySelector('input[name="uuid"]:checked');
            if (!chosen) { return; }
            start('plan', plansForm.querySelector('button[type="submit"]'), { uuid: chosen.value });
        });
    }

    otpForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!pending) { return; }
        Connex.busy(confirm, true);

        var body = Object.assign({}, pending.body, { otp: otpInput.value });

        Connex.request('POST', urls[pending.action][1], body).then(function (data) {
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
