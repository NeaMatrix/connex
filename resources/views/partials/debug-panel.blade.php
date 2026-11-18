{{--
    Connex debug panel (config "connex.debug.enabled"): the raw request we sent
    to Connex and its exact reply, for every step on this page.
--}}
@if (config('connex.debug.enabled'))
@php($nonce = \NeaMatrix\Connex\Support\Assets::nonce())
<section class="connex-debug" data-connex-debug dir="ltr" aria-label="Connex debug log">
    <header>
        <strong>Connex debug log</strong>
        <span>raw requests &amp; responses · OTP, password and token hidden</span>
        <button type="button" data-debug-clear>Clear</button>
    </header>
    <ol data-debug-entries></ol>
</section>
<style @if ($nonce) nonce="{{ $nonce }}" @endif>
    .connex-debug { width: 100%; max-width: 720px; margin: 24px auto 0; border: 1px dashed #f59e0b; border-radius: 10px; background: #fffbeb; color: #1f2937; font: 12px/1.5 ui-monospace, SFMono-Regular, Menlo, monospace; text-align: left; }
    .connex-debug header { display: flex; flex-wrap: wrap; gap: 6px 12px; align-items: center; padding: 8px 12px; border-bottom: 1px dashed #f59e0b; }
    .connex-debug header span { color: #92400e; flex: 1; }
    .connex-debug header button { border: 1px solid #f59e0b; border-radius: 6px; background: #fff; padding: 2px 10px; font: inherit; cursor: pointer; }
    .connex-debug ol { list-style: none; margin: 0; padding: 0; max-height: 60vh; overflow: auto; }
    .connex-debug li { padding: 10px 12px; border-bottom: 1px solid #fde68a; }
    .connex-debug li:empty { display: none; }
    .connex-debug .cxd-head { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-weight: 700; }
    .connex-debug .cxd-code { padding: 0 6px; border-radius: 4px; background: #e5e7eb; }
    .connex-debug .cxd-ok { background: #dcfce7; color: #166534; }
    .connex-debug .cxd-bad { background: #fee2e2; color: #991b1b; }
    .connex-debug .cxd-time { color: #6b7280; font-weight: 400; margin-inline-start: auto; }
    .connex-debug pre { margin: 6px 0 0; padding: 8px; border-radius: 6px; background: #fff; white-space: pre-wrap; overflow-wrap: anywhere; }
    .connex-debug .cxd-label { margin-top: 6px; color: #6b7280; }
</style>
<script @if ($nonce) nonce="{{ $nonce }}" @endif>
    (function () {
        var panel = document.querySelector('[data-connex-debug]');
        var list = panel.querySelector('[data-debug-entries]');

        function el(tag, className, text) {
            var node = document.createElement(tag);
            if (className) { node.className = className; }
            if (text !== undefined) { node.textContent = text; }
            return node;
        }

        function block(label, value) {
            var wrap = el('div');
            wrap.appendChild(el('div', 'cxd-label', label));
            wrap.appendChild(el('pre', '', typeof value === 'string' ? value : JSON.stringify(value, null, 2)));
            return wrap;
        }

        document.addEventListener('connex:debug', function (event) {
            var detail = event.detail;

            detail.exchanges.forEach(function (x) {
                var code = x.response && typeof x.response === 'object' ? x.response.messageCode : undefined;
                var ok = code === '00' || code === '02';
                var item = el('li');
                var head = el('div', 'cxd-head');
                head.appendChild(el('span', '', x.method + ' /' + x.endpoint));
                head.appendChild(el('span', 'cxd-code', 'HTTP ' + (x.status === null ? '—' : x.status)));
                if (code !== undefined) { head.appendChild(el('span', 'cxd-code ' + (ok ? 'cxd-ok' : 'cxd-bad'), 'messageCode ' + code)); }
                head.appendChild(el('span', 'cxd-time', x.at));
                item.appendChild(head);
                item.appendChild(block('request sent to Connex', x.request));
                item.appendChild(block(x.error ? 'connection error' : 'raw response from Connex', x.error || x.response));
                list.appendChild(item);
            });

            var page = Object.assign({}, detail.page);
            delete page.connex_debug;
            var note = el('li');
            note.appendChild(el('div', 'cxd-head', '→ page got HTTP ' + detail.status + ' from ' + detail.url.replace(location.origin, '')));
            note.appendChild(block('our answer to the page', page));
            list.appendChild(note);
            list.scrollTop = list.scrollHeight;
        });

        panel.querySelector('[data-debug-clear]').addEventListener('click', function () { list.textContent = ''; });
    })();
</script>
@endif
