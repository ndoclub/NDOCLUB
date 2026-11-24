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

    function toast(text, bad) {
        var el = document.createElement('div');
        el.className = 'flash ' + (bad ? 'flash-err' : 'flash-ok');
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
        setTimeout(function () { el.remove(); }, bad ? 4200 : 2200);
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

    // Lista de pacientes: marcar todos / contar os marcados (para atribuir responsável em lote)
    function countChecked() {
        var n = document.querySelectorAll('input[data-check-item]:checked').length;
        var out = document.querySelector('[data-check-count]');
        if (out) { out.textContent = n; }
    }
    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (!t || !t.hasAttribute) { return; }
        if (t.hasAttribute('data-check-all')) {
            var items = document.querySelectorAll('input[data-check-item]');
            for (var i = 0; i < items.length; i++) { items[i].checked = t.checked; }
            countChecked();
        } else if (t.hasAttribute('data-check-item')) {
            var all = document.querySelector('input[data-check-all]');
            if (all) { all.checked = document.querySelectorAll('input[data-check-item]:not(:checked)').length === 0; }
            countChecked();
        }
    });

    // Menus de "mais opções" (lista de pacientes, usuário no alto): fecham ao clicar fora ou apertar Esc
    function closeMenus(except) {
        var open = document.querySelectorAll('details.ra-menu[open], details.user-menu[open]');
        for (var i = 0; i < open.length; i++) { if (open[i] !== except) { open[i].removeAttribute('open'); } }
    }
    document.addEventListener('click', function (ev) {
        var inside = ev.target.closest ? ev.target.closest('details.ra-menu, details.user-menu') : null;
        closeMenus(inside);
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { closeMenus(null); }
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
        var fillSel = sel.getAttribute('data-price-fill');
        if (fillSel) {
            var amount = document.querySelector(fillSel);
            if (amount && !amount.getAttribute('data-touched')) {
                var pv = opt.getAttribute('data-price');
                amount.value = pv ? pv : '';
            }
        }
        var priceSel = sel.getAttribute('data-price-target');
        if (priceSel) {
            var price = document.querySelector(priceSel);
            if (price) {
                var p = opt.getAttribute('data-price');
                price.placeholder = p ? p : 'Igual ao plano';
            }
        }
    });

    // Mostrar/esconder campos conforme uma caixa de marcar (ex.: "É atleta" abre o perfil esportivo)
    document.addEventListener('change', function (ev) {
        var box = ev.target;
        if (!box || !box.hasAttribute || !box.hasAttribute('data-reveal')) { return; }
        var target = document.querySelector(box.getAttribute('data-reveal'));
        if (target) { target.hidden = !box.checked; }
    });

    // Financeiro: formulário de pagamento (cadastro / renovar / registrar)
    //  - cartão em R$ (cai sozinho): mostra bandeira e taxa; esconde "já recebida"
    //  - sem forma escolhida ("só cadastrar"): esconde parcelas, datas e taxas
    //  - forma "cartão": 1ª parcela daqui a 30 dias; outras formas: hoje e "1ª já recebida" marcada
    //  - taxa: preenchida pela tabela de taxas (bandeira x parcelas), a não ser que a pessoa já tenha digitado
    //  - moeda estrangeira com parcela recebida: pede quanto caiu em reais
    function isoDate(d) {
        var mm = ('0' + (d.getMonth() + 1)).slice(-2), dd = ('0' + d.getDate()).slice(-2);
        return d.getFullYear() + '-' + mm + '-' + dd;
    }
    function setHidden(list, hide) {
        for (var i = 0; i < list.length; i++) { list[i].hidden = hide; }
    }
    function syncFee(form) {
        var raw = form.getAttribute('data-fees');
        var brand = form.querySelector('[data-pay-brand]');
        var n = form.querySelector('[data-pay-n]');
        var fee = form.querySelector('[data-pay-fee]');
        if (!raw || !brand || !n || !fee || fee.getAttribute('data-touched')) { return; }
        var map = {};
        try { map = JSON.parse(raw) || {}; } catch (e) { map = {}; }
        var count = parseInt(n.value, 10) || 1;
        var band = count <= 1 ? 1 : (count <= 6 ? 2 : 3);
        var v = map[brand.value] ? map[brand.value][band] : undefined;
        fee.value = (v === undefined || v === null) ? '' : String(v).replace('.', ',');
    }
    function syncPay(form) {
        var m = form.querySelector('[data-pay-method]');
        var cur = form.querySelector('[data-pay-currency]');
        var method = m ? m.value : '';
        var currency = cur ? cur.value : 'BRL';
        var auto = method === 'cartao' && currency === 'BRL';
        var none = method === '' || method === 'none';
        setHidden(form.querySelectorAll('[data-pay-details]'), none);
        setHidden(form.querySelectorAll('[data-auto-only]'), !auto);
        setHidden(form.querySelectorAll('[data-auto-hide]'), auto);
        var box = form.querySelector('[data-pay-paid]');
        var cnt = form.querySelector('[data-pay-paidcount]');
        var field = form.querySelector('[data-brl-field]');
        if (field) {
            var paid = box ? box.checked : (cnt ? parseInt(cnt.value, 10) > 0 : false);
            field.hidden = !(currency !== 'BRL' && !auto && !none && paid);
        }
        syncFee(form);
    }
    document.addEventListener('input', function (ev) {
        var t = ev.target;
        if (t && t.hasAttribute && (t.hasAttribute('data-pay-due') || t.hasAttribute('data-pay-fee') || t.hasAttribute('data-pay-amount'))) {
            t.setAttribute('data-touched', '1');
        }
    });
    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) { return; }
        var form = t.closest('[data-pay-form]');
        if (!form) { return; }
        if (t.hasAttribute('data-pay-method')) {
            var card = t.value === 'cartao';
            var due = form.querySelector('[data-pay-due]');
            if (due && !due.getAttribute('data-touched')) {
                var d = new Date();
                if (card) { d.setDate(d.getDate() + 30); }
                due.value = isoDate(d);
            }
            var box = form.querySelector('[data-pay-paid]');
            if (box) { box.checked = !card; }
        }
        syncPay(form);
    });
    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('[data-pay-form]');
        for (var i = 0; i < forms.length; i++) { syncPay(forms[i]); }
    });

    // Botão de clipe (comprovante): fica verde quando há arquivo escolhido
    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (!t || !t.hasAttribute || !t.hasAttribute('data-file-btn')) { return; }
        var label = t.closest('label');
        if (label) { label.classList.toggle('has-file', !!(t.files && t.files.length)); }
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

    // Funil do CRM (Kanban): arrastar o cartão para outra coluna muda a etapa do lead (o mesmo que "Mover para…" no cartão).
    // Convertido e Perdido não são colunas: soltar na faixa "Finalizar" abre o fluxo próprio (converter / marcar como perdido).
    // O cartão só muda de coluna depois que o servidor confirma. Sem JavaScript ficam o seletor "Mover para…" e o botão "Mover".
    // O Follow-up (09.4) usa o mesmo quadro, com colunas por PRAZO (data-follow): só "Hoje" e "Amanhã" aceitam soltar (data-drop = dias a partir de hoje),
    // e o seletor "Reagendar para…" do cartão faz o mesmo para as outras datas. Reagendar só troca a data do retorno.
    function fmtMoney(v) {
        var whole = Math.abs(v - Math.round(v)) < 0.005;
        var parts = (whole ? String(Math.round(v)) : v.toFixed(2).replace('.', ',')).split(',');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return 'R$ ' + parts.join(',');
    }
    function initBoard() {
        var board = document.querySelector('[data-board]');
        if (!board || !window.fetch) { return; }
        board.classList.add('is-js');
        var csrf = board.getAttribute('data-csrf') || '';
        var follow = board.hasAttribute('data-follow');
        var bar = document.querySelector('[data-final]');
        var dragged = null;
        var busy = false;

        function columns() { return Array.prototype.slice.call(board.querySelectorAll('.kcol')); }
        function num(el, name) { var v = parseFloat(el.getAttribute(name)); return isNaN(v) ? 0 : v; }
        function clearOver() {
            Array.prototype.forEach.call(document.querySelectorAll('.is-over'), function (el) { el.classList.remove('is-over'); });
        }
        function zoneOf(ev) {
            return ev.target && ev.target.closest ? ev.target.closest('.kcol, [data-final-to]') : null;
        }
        // Onde o cartão vai cair. Se soltou na folga entre duas colunas (ou na borda do quadro), vale a coluna mais próxima:
        // ninguém precisa acertar o pixel.
        function zoneAt(ev) {
            var z = zoneOf(ev);
            if (z) { return z; }
            var r = board.getBoundingClientRect();
            if (ev.clientX < r.left || ev.clientX > r.right || ev.clientY < r.top || ev.clientY > r.bottom) { return null; }
            var best = null, bestD = 21;
            columns().forEach(function (c) {
                var cr = c.getBoundingClientRect();
                var d = ev.clientX < cr.left ? cr.left - ev.clientX : (ev.clientX > cr.right ? ev.clientX - cr.right : 0);
                if (d < bestD) { bestD = d; best = c; }
            });
            return best;
        }
        // Se as colunas não cabem na tela, arrastar perto da borda do quadro rola para o lado sozinho.
        function autoScroll(ev) {
            if (board.scrollWidth <= board.clientWidth + 2) { return; }
            var r = board.getBoundingClientRect();
            if (ev.clientY < r.top - 40 || ev.clientY > r.bottom + 40) { return; }
            if (ev.clientX < r.left + 70) { board.scrollLeft -= 24; } else if (ev.clientX > r.right - 70) { board.scrollLeft += 24; }
        }
        // Setas ‹ › (só aparecem quando as colunas não cabem): rolam o quadro sem precisar arrastar a barra.
        var nav = document.querySelector('[data-board-nav]');
        function navState() {
            if (!nav) { return; }
            var over = board.scrollWidth > board.clientWidth + 4;
            nav.hidden = !over;
            if (!over) { return; }
            var prev = nav.querySelector('[data-board-go="-1"]');
            var next = nav.querySelector('[data-board-go="1"]');
            if (prev) { prev.disabled = board.scrollLeft <= 2; }
            if (next) { next.disabled = board.scrollLeft >= board.scrollWidth - board.clientWidth - 2; }
        }
        if (nav) {
            nav.addEventListener('click', function (ev) {
                var b = ev.target && ev.target.closest ? ev.target.closest('[data-board-go]') : null;
                if (!b) { return; }
                var first = board.querySelector('.kcol');
                var w = first ? first.getBoundingClientRect().width + 12 : 260;
                var dx = parseInt(b.getAttribute('data-board-go'), 10) * Math.max(w, board.clientWidth - w);
                var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (board.scrollBy) { board.scrollBy({ left: dx, behavior: calm ? 'auto' : 'smooth' }); } else { board.scrollLeft += dx; }
            });
            board.addEventListener('scroll', navState, { passive: true });
            window.addEventListener('resize', navState);
            window.addEventListener('load', navState);
            navState();
        }
        function setCount(col, delta) {
            var out = col.querySelector('[data-count]');
            if (out) {
                var n = Math.max(0, (parseInt(out.textContent, 10) || 0) + delta);
                var word = n === 1 ? 'lead' : 'leads';
                out.textContent = n;
                var unit = col.querySelector('[data-unit]');
                if (unit) { unit.textContent = word; }
                var wrap = col.querySelector('[data-count-wrap]');
                if (wrap) { wrap.setAttribute('title', n + ' ' + word + ' ' + (col.getAttribute('data-where') || 'nesta etapa')); }
            }
            var empty = col.querySelector('[data-empty]');
            if (empty) { empty.hidden = col.querySelectorAll('.kcard').length > 0; }
        }
        // receita potencial: só as colunas marcadas em Configurar têm a soma (.kcol-sum); o total do alto acompanha
        function adjustSum(col, hasPrice, price, sign) {
            var s = col.querySelector('.kcol-sum');
            if (!s) { return false; }
            var sum = num(s, 'data-sum') + sign * (hasPrice ? price : 0);
            var un = Math.max(0, num(s, 'data-unpriced') + (hasPrice ? 0 : sign));
            s.setAttribute('data-sum', String(sum));
            s.setAttribute('data-unpriced', String(un));
            s.textContent = fmtMoney(sum) + (un > 0 ? ' · ' + un + ' sem plano' : '');
            return true;
        }
        function adjustTotal(hasPrice, price, sign) {
            var t = document.querySelector('[data-pot-total]');
            if (t) {
                var v = num(t, 'data-total') + sign * (hasPrice ? price : 0);
                t.setAttribute('data-total', String(v));
                t.textContent = fmtMoney(v);
            }
            var u = document.querySelector('[data-pot-unpriced]');
            if (u) {
                var n = Math.max(0, num(u, 'data-n') + (hasPrice ? 0 : sign));
                u.setAttribute('data-n', String(n));
                u.textContent = n;
                var wrap = u.closest('[data-pot-unpriced-wrap]');
                if (wrap) { wrap.hidden = n === 0; }
            }
        }
        function rebuildSelect(card, col) {
            var sel = card.querySelector('.kc-move select');
            if (!sel) { return; }
            var grp = sel.querySelector('optgroup');
            for (var i = sel.children.length - 1; i > 0; i--) {
                if (sel.children[i].tagName === 'OPTION') { sel.removeChild(sel.children[i]); }
            }
            columns().forEach(function (c) {
                if (c === col || c.getAttribute('data-active') === '0') { return; }
                var o = document.createElement('option');
                o.value = c.getAttribute('data-stage');
                o.textContent = c.getAttribute('data-name');
                sel.insertBefore(o, grp);
            });
            sel.selectedIndex = 0;
        }
        function resetSelect(card) {
            var sel = card.querySelector('.kc-move select');
            if (sel) { sel.selectedIndex = 0; }
        }
        function move(card, to, target) {
            var from = card.closest('.kcol');
            if (busy || !from || (target && target === from)) { resetSelect(card); return; }
            var form = card.querySelector('.kc-move');
            busy = true;
            card.classList.add('is-busy');
            var fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('stage_id', to);
            fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    busy = false;
                    card.classList.remove('is-busy');
                    if (!d || !d.ok) { toast((d && d.error) || 'Não foi possível mover o cartão. Atualize a página e tente de novo.', true); resetSelect(card); return; }
                    if (d.redirect) { window.location.href = d.redirect; return; }
                    if (!target) { window.location.reload(); return; }
                    var body = target.querySelector('.kcol-body');
                    var first = body.querySelector('.kcard');
                    body.insertBefore(card, first || body.querySelector('[data-empty]'));
                    var raw = card.getAttribute('data-price');
                    var hasPrice = raw !== null && raw !== '';
                    var price = hasPrice ? parseFloat(raw) : 0;
                    var wasPot = adjustSum(from, hasPrice, price, -1);
                    var isPot = adjustSum(target, hasPrice, price, 1);
                    if (wasPot && !isPot) { adjustTotal(hasPrice, price, -1); }
                    if (!wasPot && isPot) { adjustTotal(hasPrice, price, 1); }
                    setCount(from, -1);
                    setCount(target, 1);
                    rebuildSelect(card, target);
                    toast(d.message || 'Etapa atualizada.');
                })
                .catch(function () { window.location.reload(); });
        }

        // Follow-up: troca a data do retorno. Sem alvo (seletor do cartão), o servidor diz em que faixa o cartão passa a ficar.
        function reschedule(card, days, target) {
            var from = card.closest('.kcol');
            if (busy || !from || (target && target === from)) { resetSelect(card); return; }
            var form = card.querySelector('.kc-move');
            busy = true;
            card.classList.add('is-busy');
            var fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('days', days);
            fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    busy = false;
                    card.classList.remove('is-busy');
                    if (!d || !d.ok) { toast((d && d.error) || 'Não foi possível reagendar. Atualize a página e tente de novo.', true); resetSelect(card); return; }
                    var dest = columns().filter(function (c) { return c.getAttribute('data-bucket') === d.bucket; })[0];
                    if (!dest) { window.location.reload(); return; }
                    var fu = card.querySelector('.kc-fu .fu');
                    if (fu) { fu.className = 'fu fu-' + d.state; fu.textContent = d.text; }
                    var dt = card.querySelector('[data-fu-date]');
                    if (dt) { dt.textContent = d.date; }
                    if (dest !== from) {
                        var body = dest.querySelector('.kcol-body');
                        body.insertBefore(card, body.querySelector('.kcard') || body.querySelector('[data-empty]'));
                        setCount(from, -1);
                        setCount(dest, 1);
                    }
                    resetSelect(card);
                    toast(d.message || 'Retorno reagendado.');
                })
                .catch(function () { window.location.reload(); });
        }
        // no Follow-up só as colunas com data-drop (Hoje, Amanhã) aceitam o cartão
        function canDrop(z) {
            return !follow || (z.classList.contains('kcol') && z.hasAttribute('data-drop'));
        }

        board.addEventListener('dragstart', function (ev) {
            var card = ev.target && ev.target.closest ? ev.target.closest('.kcard') : null;
            if (!card) { return; }
            dragged = card;
            card.classList.add('is-dragging');
            board.classList.add('is-dragging-board');
            ev.dataTransfer.effectAllowed = 'move';
            try { ev.dataTransfer.setData('text/plain', card.getAttribute('data-lead') || ''); } catch (e) {}
            if (bar) { bar.hidden = false; }
        });
        document.addEventListener('dragend', function () {
            if (dragged) { dragged.classList.remove('is-dragging'); }
            board.classList.remove('is-dragging-board');
            dragged = null;
            clearOver();
            if (bar) { bar.hidden = true; }
        });
        document.addEventListener('dragover', function (ev) {
            if (!dragged) { return; }
            autoScroll(ev);
            var z = zoneAt(ev);
            if (!z || !canDrop(z)) { clearOver(); return; }
            ev.preventDefault();
            ev.dataTransfer.dropEffect = 'move';
            if (!z.classList.contains('is-over')) { clearOver(); z.classList.add('is-over'); }
        });
        document.addEventListener('drop', function (ev) {
            if (!dragged) { return; }
            var z = zoneAt(ev);
            if (!z || !canDrop(z)) { return; }
            ev.preventDefault();
            var card = dragged;
            clearOver();
            if (follow) { reschedule(card, z.getAttribute('data-drop'), z); return; }
            if (z.hasAttribute('data-final-to')) { move(card, z.getAttribute('data-final-to'), null); } else { move(card, z.getAttribute('data-stage'), z); }
        });
        // "Mover para…" no cartão (celular, teclado): mesma ação do arrastar
        board.addEventListener('change', function (ev) {
            var sel = ev.target;
            if (!sel || !sel.closest || !sel.closest('.kc-move') || !sel.value) { return; }
            var card = sel.closest('.kcard');
            if (follow) {
                // "Outra data…": a ficha do lead tem o campo de data
                if (sel.value === 'date') { window.location.href = sel.closest('.kc-move').getAttribute('data-ficha') || '#'; return; }
                reschedule(card, sel.value, null);
                return;
            }
            var col = columns().filter(function (c) { return c.getAttribute('data-stage') === sel.value; })[0] || null;
            move(card, sel.value, col);
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initBoard); } else { initBoard(); }

    // Abas pequenas (Visão executiva > Distribuição da carteira: Plano | Sexo | Modalidade).
    // Sem JavaScript os painéis aparecem todos, um embaixo do outro.
    function initSegTabs() {
        var boxes = document.querySelectorAll('[data-segtabs]');
        Array.prototype.forEach.call(boxes, function (box) {
            var bar = box.querySelector('[role="tablist"]');
            var tabs = box.querySelectorAll('[data-seg]');
            var panels = box.querySelectorAll('[data-seg-panel]');
            if (!bar || !tabs.length) { return; }
            function show(name, focus) {
                Array.prototype.forEach.call(tabs, function (t) {
                    var on = t.getAttribute('data-seg') === name;
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                    t.tabIndex = on ? 0 : -1;
                    if (on && focus) { t.focus(); }
                });
                Array.prototype.forEach.call(panels, function (p) {
                    p.hidden = p.getAttribute('data-seg-panel') !== name;
                });
            }
            box.classList.add('is-js');
            bar.hidden = false;
            show(tabs[0].getAttribute('data-seg'), false);
            Array.prototype.forEach.call(tabs, function (t, i) {
                t.addEventListener('click', function () { show(t.getAttribute('data-seg'), false); });
                t.addEventListener('keydown', function (ev) {
                    var j = ev.key === 'ArrowRight' ? i + 1 : (ev.key === 'ArrowLeft' ? i - 1 : -1);
                    if (j < 0 || j >= tabs.length) { return; }
                    ev.preventDefault();
                    show(tabs[j].getAttribute('data-seg'), true);
                });
            });
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initSegTabs); } else { initSegTabs(); }
})();
