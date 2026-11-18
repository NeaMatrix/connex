/**
 * Connex debug panel: groups every page action with the Connex calls it made,
 * showing the endpoint on Connex's server and its exact reply.
 */
(function () {
    'use strict';

    var panel = document.querySelector('[data-connex-debug]');
    if (!panel) { return; }

    var list = panel.querySelector('[data-debug-entries]');
    var empty = panel.querySelector('[data-debug-empty]');

    var ACTIONS = {
        '/login/script': 'Load anti-fraud script',
        '/login/otp/confirm': 'Confirm the SMS code',
        '/login/otp': 'Send the SMS code',
        '/login/activation/confirm': 'Confirm activation code',
        '/login/activation': 'Request activation code',
        '/login/access/check': 'Check subscription again',
        '/activation/confirm': 'Confirm activation',
        '/activation': 'Request activation code',
        '/cancellation/confirm': 'Confirm cancellation',
        '/cancellation': 'Request cancellation code',
        '/plan-change/confirm': 'Confirm package change',
        '/plan-change': 'Request package change code',
    };

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined && text !== null) { node.textContent = String(text); }
        return node;
    }

    function pill(text, tone) { return el('span', 'cxd-pill cxd-' + tone, text); }

    function statusTone(status) {
        if (status === null) { return 'bad'; }
        if (status < 300) { return 'ok'; }
        return status < 500 ? 'warn' : 'bad';
    }

    /** Pretty, colour-coded JSON without using innerHTML on server data. */
    function json(value) {
        var pre = el('pre');
        var text = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
        var pattern = /("(?:\\.|[^"\\])*")(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d+)?(?:e[+-]?\d+)?/gi;
        var last = 0;
        var match;
        while ((match = pattern.exec(text)) !== null) {
            pre.appendChild(document.createTextNode(text.slice(last, match.index)));
            var cls = match[1] ? (match[2] ? 'cxd-k' : 'cxd-s') : (match[3] ? 'cxd-b' : 'cxd-n');
            pre.appendChild(el('span', cls, match[1] || match[0]));
            if (match[2]) { pre.appendChild(document.createTextNode(match[2])); }
            last = pattern.lastIndex;
        }
        pre.appendChild(document.createTextNode(text.slice(last)));
        return pre;
    }

    function section(title, value, open) {
        var details = el('details');
        details.open = open;
        details.appendChild(el('summary', '', title));
        details.appendChild(json(value));
        return details;
    }

    function actionName(url) {
        var path = url.replace(location.origin, '').split('?')[0];
        var keys = Object.keys(ACTIONS);
        for (var i = 0; i < keys.length; i++) {
            if (path.slice(-keys[i].length) === keys[i]) { return ACTIONS[keys[i]]; }
        }
        return path;
    }

    function call(x) {
        var box = el('div', 'cxd-call');
        var body = x.response && typeof x.response === 'object' ? x.response : null;
        var code = body ? body.messageCode : undefined;
        var payload = body && (body.success || body.failed || body.data || body.errors);
        var message = payload && typeof payload === 'object' ? (payload.message || (payload.password && payload.password[0])) : null;

        var line = el('div', 'cxd-line');
        line.appendChild(pill(x.method, 'm'));
        line.appendChild(el('span', 'cxd-url', x.url || ('/' + x.endpoint)));
        box.appendChild(line);

        var meta = el('div', 'cxd-line');
        meta.style.marginTop = '4px';
        meta.appendChild(pill(x.status === null ? 'NO RESPONSE' : 'HTTP ' + x.status, statusTone(x.status)));
        if (code !== undefined) { meta.appendChild(pill('messageCode ' + code, code === '00' || code === '02' ? 'ok' : 'bad')); }
        if (x.duration_ms !== null && x.duration_ms !== undefined) { meta.appendChild(el('span', 'cxd-dim', x.duration_ms + ' ms')); }
        meta.appendChild(el('span', 'cxd-time', x.at));
        box.appendChild(meta);

        if (x.error) { box.appendChild(el('p', 'cxd-msg', '⚠ ' + x.error)); }
        else if (message) { box.appendChild(el('p', 'cxd-msg', '“' + message + '”')); }

        box.appendChild(section('Request we sent to Connex', x.request, false));
        box.appendChild(section('Response from Connex (raw)', x.error || x.response, true));
        return box;
    }

    document.addEventListener('connex:debug', function (event) {
        var detail = event.detail;
        var page = Object.assign({}, detail.page);
        delete page.connex_debug;
        if (typeof page.script === 'string') { page.script = '<anti-fraud script, ' + page.script.length + ' bytes>'; }

        var card = el('li', 'cxd-action');
        var head = el('header');
        head.appendChild(el('b', '', actionName(detail.url)));
        head.appendChild(el('span', 'cxd-dim', detail.url.replace(location.origin, '')));
        head.appendChild(pill('page got HTTP ' + detail.status, statusTone(detail.status)));
        card.appendChild(head);

        if (detail.exchanges.length === 0) {
            card.appendChild(el('div', 'cxd-call cxd-dim', 'No call to Connex (answered by our server, e.g. validation or rate limit).'));
        }
        detail.exchanges.forEach(function (x) { card.appendChild(call(x)); });

        var ours = el('div', 'cxd-call');
        ours.appendChild(section('Our server\u2019s answer to the page', page, false));
        card.appendChild(ours);

        empty.hidden = true;
        list.appendChild(card);
        list.scrollTop = list.scrollHeight;
    });

    panel.querySelector('[data-debug-clear]').addEventListener('click', function () {
        list.textContent = '';
        empty.hidden = false;
    });
})();
