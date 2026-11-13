<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo frontend</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; }
        button { padding: 10px 16px; font-size: 16px; cursor: pointer; }
        pre { background: #f3f4f6; padding: 12px; overflow: auto; white-space: pre-wrap; word-break: break-all; }
    </style>
</head>
<body>
    <h1>Demo frontend</h1>
    <p>Stands in for a mobile app or a separate web frontend. Any Libyan number works; the code is <b>1234</b>.</p>
    <button id="login">Sign in with Connex</button>
    <button id="popup">Sign in in a popup</button>
    <h2>Result</h2>
    <pre id="out">Not signed in.</pre>

    <script>
        const CLIENT = 'demo';
        const REDIRECT_URI = @json(url('/demo/callback'));
        const out = document.getElementById('out');

        const base64url = (bytes) => btoa(String.fromCharCode(...new Uint8Array(bytes)))
            .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

        async function loginUrl(responseMode) {
            const verifier = base64url(crypto.getRandomValues(new Uint8Array(32)));
            const challenge = base64url(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier)));
            const state = base64url(crypto.getRandomValues(new Uint8Array(16)));
            sessionStorage.setItem('connex', JSON.stringify({ verifier, state }));

            return '/connex/login?' + new URLSearchParams({
                client: CLIENT, redirect_uri: REDIRECT_URI, state,
                code_challenge: challenge, code_challenge_method: 'S256',
                response_mode: responseMode, lang: 'en',
            });
        }

        async function exchange(code, state) {
            const saved = JSON.parse(sessionStorage.getItem('connex') || '{}');
            if (state !== saved.state) { out.textContent = 'State mismatch, refusing the code.'; return; }

            const tokenResponse = await fetch('/connex/api/token', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ client: CLIENT, code, redirect_uri: REDIRECT_URI, code_verifier: saved.verifier }),
            }).then((r) => r.json());

            const me = tokenResponse.token
                ? await fetch('/api/me', { headers: { Authorization: 'Bearer ' + tokenResponse.token, Accept: 'application/json' } }).then((r) => r.json())
                : null;

            out.textContent = JSON.stringify({ token_response: tokenResponse, api_me: me }, null, 2);
        }

        document.getElementById('login').onclick = async () => { location.assign(await loginUrl('query')); };
        document.getElementById('popup').onclick = async () => { window.open(await loginUrl('web_message'), 'connex', 'width=420,height=640'); };

        window.addEventListener('message', (event) => {
            if (event.origin === location.origin && event.data?.type === 'connex:login') {
                exchange(event.data.code, event.data.state);
            }
        });

        const params = new URLSearchParams(location.search);
        if (params.get('code')) { exchange(params.get('code'), params.get('state')); history.replaceState({}, '', '/demo'); }
        if (params.get('error')) { out.textContent = 'Login cancelled: ' + params.get('error'); }
    </script>
</body>
</html>
