/* No Days Off Club - comportamentos leves (o sistema funciona mesmo sem JavaScript) */
(function () {
    'use strict';
    var meta = document.querySelector('meta[name="base-path"]');
    var base = meta ? meta.getAttribute('content') : '';

    // PWA: service worker (só funciona em HTTPS ou localhost)
    var secure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    if ('serviceWorker' in navigator && secure) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' }).catch(function () {});
        });
    }

    function toast(text) {
        var el = document.createElement('div');
        el.className = 'flash flash-ok';
        el.setAttribute('role', 'status');
        el.style.position = 'fixed';
        el.style.top = '12px';
        el.style.left = '16px';
        el.style.right = '16px';
        el.style.zIndex = '60';
        el.style.maxWidth = '528px';
        el.style.margin = '0 auto';
        el.textContent = text;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 2200);
    }

    // Avisos: fechar no X e sumir sozinho
    document.querySelectorAll('.flash').forEach(function (el) {
        var close = el.querySelector('[data-dismiss]');
        if (close) { close.addEventListener('click', function () { el.remove(); }); }
        if (el.classList.contains('flash-ok')) { setTimeout(function () { el.remove(); }, 7000); }
    });

    document.addEventListener('submit', function (ev) {
        var f = ev.target;
        if (!f || !f.getAttribute) { return; }

        // Confirmação antes de ações sem volta
        var msg = f.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) { ev.preventDefault(); return; }

        // Curtir sem recarregar a página
        if (f.hasAttribute('data-like') && window.fetch) {
            ev.preventDefault();
            fetch(f.action, {
                method: 'POST',
                body: new FormData(f),
                headers: { 'X-Requested-With': 'fetch' },
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (!d || !d.ok) { f.submit(); return; }
                var btn = f.querySelector('.act');
                btn.classList.toggle('on', d.liked);
                btn.setAttribute('aria-pressed', d.liked ? 'true' : 'false');
                btn.querySelector('[data-count]').textContent = d.count;
                if (d.earned > 0) { toast('+' + d.earned + (d.earned === 1 ? ' ponto' : ' pontos')); }
            }).catch(function () { f.submit(); });
        }
    });

    // Enviar o formulário ao trocar um seletor (ex.: mês do ranking)
    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (t && t.hasAttribute && t.hasAttribute('data-autosubmit') && t.form) { t.form.submit(); }
    });

    // Confirmação em botões que escolhem a ação (ex.: Recusar resgate)
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest ? ev.target.closest('[data-confirm-click]') : null;
        if (b && !window.confirm(b.getAttribute('data-confirm-click'))) { ev.preventDefault(); }
    });

    // Escolher o plano: preenche a data de vencimento (cadastro) e mostra o valor do plano no campo "valor combinado"
    document.addEventListener('change', function (ev) {
        var sel = ev.target;
        if (!sel || !sel.hasAttribute || !sel.hasAttribute('data-plan-select')) { return; }
        var opt = sel.options[sel.selectedIndex];
        var days = parseInt(opt.getAttribute('data-days'), 10);
        var dateSel = sel.getAttribute('data-date-target');
        if (dateSel && days) {
            var input = document.querySelector(dateSel);
            if (input) {
                var d = new Date();
                d.setDate(d.getDate() + days);
                var mm = ('0' + (d.getMonth() + 1)).slice(-2), dd = ('0' + d.getDate()).slice(-2);
                input.value = d.getFullYear() + '-' + mm + '-' + dd;
            }
        }
        var priceSel = sel.getAttribute('data-price-target');
        if (priceSel) {
            var price = document.querySelector(priceSel);
            if (price) {
                var p = opt.getAttribute('data-price');
                price.placeholder = p ? 'Padrão do plano: R$ ' + p : 'Igual ao do plano';
            }
        }
    });

    // Copiar texto (mensagem de boas-vindas)
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest ? ev.target.closest('[data-copy]') : null;
        if (!b) { return; }
        var target = document.querySelector(b.getAttribute('data-copy'));
        if (target && navigator.clipboard) {
            navigator.clipboard.writeText(target.innerText || target.textContent).then(function () {
                b.textContent = 'Copiado';
            });
        }
    });
})();
