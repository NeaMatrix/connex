/**
 * Login page: anti-fraud script → phone number → OTP → redirect.
 *
 * Connex's DCBProtect script watches the phone step's submit button, so it is
 * enabled only after the script reports "gateway-load" (or the timeout passes).
 */
(function (Connex) {
    'use strict';

    var root = document.querySelector('[data-connex-login]');
    if (!root) { return; }

    var phoneForm = root.querySelector('[data-step="phone"]');
    var otpForm = root.querySelector('[data-step="otp"]');
    var submit = phoneForm.querySelector('button[type="submit"]');
    var confirm = otpForm.querySelector('button[type="submit"]');
    var otpInput = otpForm.querySelector('input');
    var otpLabel = otpForm.querySelector('[data-otp-label]');
    var resend = root.querySelector('[data-resend]');
    var loader = root.querySelector('[data-loader]');
    var retry = root.querySelector('[data-retry]');
    var feedback = Connex.feedback(root);
    var countdown = null;
    var resendLabel = resend.textContent;

    Connex.digitsOnly(otpInput);

    function loadProtection() {
        loader.hidden = false;
        retry.hidden = true;
        feedback.clear();

        return Connex.request('GET', root.dataset.scriptUrl).then(function (data) {
            // Listen before injecting: the script may fire "gateway-load" synchronously.
            var ready = waitForGateway(Number(root.dataset.gatewayTimeout) || 15000);
            var tag = document.createElement('script');
            var nonce = document.querySelector('meta[name="csp-nonce"]');
            if (nonce) { tag.nonce = nonce.content; }
            tag.text = data.script;
            document.body.appendChild(tag);
            document.dispatchEvent(new Event('DCBProtectRun'));
            return ready;
        }).then(function () {
            loader.hidden = true;
            submit.disabled = false;
        }).catch(function (error) {
            loader.hidden = true;
            feedback.error(error.message);
            retry.hidden = false;
        });
    }

    function waitForGateway(timeoutMs) {
        return new Promise(function (resolve) {
            var timer = setTimeout(resolve, timeoutMs);
            document.addEventListener('gateway-load', function () {
                clearTimeout(timer);
                resolve();
            }, { once: true });
        });
    }

    function requestOtp() {
        feedback.clear();
        var terms = phoneForm.elements.accepted_terms;
        return Connex.request('POST', root.dataset.otpUrl, {
            msisdn: phoneForm.elements.msisdn.value,
            accepted_terms: terms ? terms.checked : undefined,
        }).then(function (data) {
            var template = data.awaits_sms_reply ? otpLabel.dataset.smsTemplate : otpLabel.dataset.template;
            otpLabel.textContent = template.replace(':msisdn', data.msisdn);
            phoneForm.hidden = true;
            otpForm.hidden = false;
            otpInput.value = '';
            otpInput.focus();
            startCountdown(data.resend_in);
        });
    }

    function startCountdown(seconds) {
        clearInterval(countdown);
        var remaining = seconds;
        var tick = function () {
            resend.disabled = remaining > 0;
            resend.textContent = remaining > 0
                ? root.dataset.resendTemplate.replace(':seconds', remaining)
                : resendLabel;
            if (remaining-- <= 0) { clearInterval(countdown); }
        };
        tick();
        countdown = setInterval(tick, 1000);
    }

    function handle(button, action) {
        Connex.busy(button, true);
        return action().catch(function (error) {
            feedback.error(error.message);
            if (error.data && error.data.resend_in) { startCountdown(error.data.resend_in); }
            if (error.status === 419) { setTimeout(function () { location.reload(); }, 1500); }
        }).finally(function () {
            Connex.busy(button, false);
        });
    }

    /**
     * Popup logins (response_mode=web_message) hand the code to the opener
     * window; everything else navigates to the redirect (app deep link,
     * frontend callback, or this site).
     */
    function finish(data) {
        if (data.step === 'activation' && window.ConnexActivation) {
            clearInterval(countdown);
            window.ConnexActivation.show(data, finish);
            return null;
        }
        var message = data.web_message;
        if (message && window.opener && message.target_origin) {
            window.opener.postMessage(message.data, message.target_origin);
            window.close();
        } else {
            window.location.assign(data.redirect);
        }
        return new Promise(function () {});
    }

    phoneForm.addEventListener('submit', function (event) {
        event.preventDefault();
        handle(submit, requestOtp);
    });

    otpForm.addEventListener('submit', function (event) {
        event.preventDefault();
        handle(confirm, function () {
            return Connex.request('POST', root.dataset.confirmUrl, { otp: otpInput.value }).then(finish);
        });
    });

    resend.addEventListener('click', function () { handle(resend, requestOtp); });

    root.querySelector('[data-change-number]').addEventListener('click', function () {
        clearInterval(countdown);
        feedback.clear();
        otpForm.hidden = true;
        phoneForm.hidden = false;
        phoneForm.elements.msisdn.focus();
    });

    retry.addEventListener('click', loadProtection);

    loadProtection();
})(window.Connex);
