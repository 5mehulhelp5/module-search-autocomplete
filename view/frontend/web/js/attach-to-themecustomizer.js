/*!
 * Panth Search Autocomplete - attach mode for Panth_ThemeCustomizer.
 *
 * The ThemeCustomizer module ships its own Luma header-icons template
 * (app/code/Panth/ThemeCustomizer/view/frontend/templates/luma/header-icons.phtml)
 * which already provides:
 *   - a magnifying-glass trigger button (#panth-search-toggle)
 *   - a hidden full-width search bar (#panth-search-bar)
 *   - a plain text input (#panth-search-input) submitting to /catalogsearch/result
 *
 * Rather than render a SECOND parallel search box, this script finds
 * the existing input on page load, attaches our autocomplete dropdown
 * BELOW it, wires every event (debounced fetch, popular, recent,
 * highlighted matches, keyboard nav, security headers, honeypot,
 * form_key) and lets the existing trigger / open / close UX from
 * ThemeCustomizer continue to drive the open/close state.
 *
 * Bridge model:
 *   - We do NOT touch the existing input element other than wiring
 *     event listeners and reading its current value.
 *   - We do NOT touch the existing form submit - pressing Enter still
 *     submits to /catalogsearch/result, exactly as before.
 *   - We INJECT a hidden form_key input + a hidden honeypot input so
 *     the AJAX requests pass our security validator.
 *   - We INJECT a sibling .psac-dropdown div positioned below the input
 *     and the same scroll/popular/recent/highlight UX as the standalone
 *     overlay variant.
 *
 * Read config from window.PSAC_CONFIG (rendered inline by the Luma
 * layout XML before this file loads).
 */
(function () {
    'use strict';

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }
    function escapeRegExp(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
    function highlight(text, query) {
        var raw = String(text == null ? '' : text);
        if (!query) return escapeHtml(raw);
        var re;
        try { re = new RegExp(escapeRegExp(query), 'ig'); } catch (e) { return escapeHtml(raw); }
        var out = '', last = 0, m;
        while ((m = re.exec(raw)) !== null) {
            if (!m[0].length) { re.lastIndex++; continue; }
            out += escapeHtml(raw.slice(last, m.index))
                + '<mark class="psac-hl">' + escapeHtml(m[0]) + '</mark>';
            last = m.index + m[0].length;
        }
        return out + escapeHtml(raw.slice(last));
    }
    function readFormKey(fallback) {
        var m = document.cookie.match(/(?:^|;\s*)form_key=([^;]+)/);
        if (m) {
            try { return decodeURIComponent(m[1]); } catch (e) { return m[1]; }
        }
        return fallback || '';
    }
    function viewAllLabel(template, query) {
        return escapeHtml(template).replace('%1', function () { return escapeHtml(query); });
    }
    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    function attach(input, cfg) {
        var form = input.form;
        if (!form) return;

        // Inject hidden form_key + honeypot so the security validator
        // accepts our AJAX requests. Both fields are added once, on
        // attach, and never re-rendered.
        if (!form.querySelector('input[name="form_key"]')) {
            var fk = document.createElement('input');
            fk.type = 'hidden';
            fk.name = 'form_key';
            fk.value = cfg.formKey || '';
            form.appendChild(fk);
        }
        if (!form.querySelector('input[name="' + cfg.honeypotName + '"]')) {
            var hp = document.createElement('label');
            hp.className = 'psac-honeypot';
            hp.setAttribute('aria-hidden', 'true');
            hp.style.cssText = 'position:absolute !important;left:-9999px !important;width:1px !important;height:1px !important;opacity:0 !important;pointer-events:none !important;';
            var hpi = document.createElement('input');
            hpi.type = 'text';
            hpi.name = cfg.honeypotName;
            hpi.tabIndex = -1;
            hpi.autocomplete = 'off';
            hpi.value = '';
            hp.appendChild(hpi);
            form.appendChild(hp);
        }

        // Inject the dropdown sibling. The dropdown is appended to the
        // .panth-search-inner max-width container so it sits exactly
        // under the form, never wider than the page content.
        var dropdown = document.createElement('div');
        dropdown.className = 'psac-dropdown psac-attached-dropdown';
        dropdown.setAttribute('role', 'listbox');
        dropdown.id = 'psac-listbox-' + Math.random().toString(36).slice(2, 10);
        dropdown.hidden = true;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', dropdown.id);
        input.setAttribute('aria-expanded', 'false');
        var inner = form.parentElement;
        if (!inner) return;
        inner.style.position = 'relative';
        inner.appendChild(dropdown);

        // Loading bar.
        var loading = document.createElement('div');
        loading.className = 'psac-loading psac-attached-loading';
        loading.hidden = true;
        inner.appendChild(loading);

        // The bar wrapping element (for click-outside detection).
        var bar = document.getElementById('panth-search-bar') || inner;

        var state = {
            query: '', products: [], categories: [], pages: [], popular: [], recent: [], didYouMean: '',
            activeIdx: -1, controller: null, cache: new Map(),
        };
        try {
            state.recent = (JSON.parse(localStorage.getItem('psac_recent') || '[]') || []).slice(0, 6);
        } catch (e) { state.recent = []; }

        function hasResults() { return (state.products.length + state.categories.length + state.pages.length) > 0; }
        function hasQuery()   { return state.query.length >= cfg.minLength; }
        function open()  { dropdown.hidden = false; input.setAttribute('aria-expanded', 'true'); }
        function close() {
            dropdown.hidden = true; state.activeIdx = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function fetch(q, popularOnly) {
            var key = (popularOnly ? 'pop:' : 'q:') + q;
            if (state.cache.has(key)) { apply(state.cache.get(key), popularOnly); return; }
            if (state.controller) { try { state.controller.abort(); } catch (e) {} }
            state.controller = new AbortController();
            if (!popularOnly) loading.hidden = false;
            var url = new URL(cfg.endpoint, window.location.origin);
            url.searchParams.set('q', popularOnly ? '  '.padEnd(cfg.minLength, ' ') : q);
            var fkInput = form.querySelector('input[name="form_key"]');
            url.searchParams.set('form_key', readFormKey(fkInput ? fkInput.value : cfg.formKey));
            url.searchParams.set(cfg.honeypotName, '');
            window.fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: state.controller.signal,
            }).then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
              .then(function (data) { state.cache.set(key, data); apply(data, popularOnly); })
              .catch(function () {})
              .finally(function () { loading.hidden = true; render(); });
        }
        function apply(data, popularOnly) {
            if (popularOnly) { state.popular = data.popular || []; render(); return; }
            state.products   = data.products   || [];
            state.categories = data.categories || [];
            state.pages      = data.pages      || [];
            state.didYouMean = (data && data.did_you_mean) || '';
            state.popular    = data.popular    || [];
            state.activeIdx  = -1;
            open(); render();
        }

        function flatHrefs() {
            var arr = [];
            state.products.forEach(function (p) { arr.push(p.url); });
            if (cfg.showCategories) state.categories.forEach(function (c) { arr.push(c.url); });
            if (cfg.showPages) state.pages.forEach(function (p) { arr.push(p.url); });
            return arr;
        }
        function optionAttrs(i) {
            var selected = state.activeIdx === i;
            return ' id="' + dropdown.id + '-opt-' + i + '" role="option" aria-selected="' + (selected ? 'true' : 'false') + '"';
        }

        function render() {
            if (dropdown.hidden) return;
            input.removeAttribute('aria-activedescendant');
            var optIdx = 0;
            var html = '<div class="psac-scroll">';

            if (!state.query && state.recent.length) {
                html += '<div class="psac-section-heading psac-heading-row"><span>' + escapeHtml(cfg.i18n.recent) + '</span>'
                      + '<button type="button" class="psac-recent-clear" data-psac-recent-clear>' + escapeHtml(cfg.i18n.clear) + '</button></div><div class="psac-popular-wrap">';
                state.recent.forEach(function (term) {
                    html += '<button type="button" class="psac-popular-tag" data-psac-pick="' + escapeHtml(term) + '">'
                          + '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                          + '<span>' + escapeHtml(term) + '</span></button>';
                });
                html += '</div>';
            }
            if (state.popular.length && cfg.showPopular) {
                html += '<div class="psac-section-heading">' + escapeHtml(cfg.i18n.popular) + '</div><div class="psac-popular-wrap">';
                state.popular.forEach(function (row) {
                    html += '<button type="button" class="psac-popular-tag" data-psac-pick="' + escapeHtml(row.text) + '">'
                          + '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67z"/></svg>'
                          + '<span>' + escapeHtml(row.text) + '</span></button>';
                });
                html += '</div>';
            }
            if (state.products.length) {
                html += '<div class="psac-section-heading">' + escapeHtml(cfg.i18n.products) + '</div>';
                state.products.forEach(function (p) {
                    var idx = optIdx++;
                    var active = state.activeIdx === idx ? ' is-active' : '';
                    html += '<a class="psac-row' + active + '" href="' + escapeHtml(p.url) + '"' + optionAttrs(idx) + '>';
                    if (cfg.showImage) {
                        html += '<div class="psac-product-image">';
                        if (p.image) {
                            html += '<img src="' + escapeHtml(p.image) + '" alt="' + escapeHtml(p.name) + '" width="56" height="56" loading="lazy"/>';
                        } else {
                            html += '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#d4d4d4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/></svg>';
                        }
                        html += '</div>';
                    }
                    html += '<div class="psac-product-info">'
                          + '<span class="psac-product-name">' + highlight(p.name, state.query) + '</span>';
                    if (p.sku) html += '<span class="psac-product-sku">' + escapeHtml(p.sku) + '</span>';
                    if (cfg.showPrice && p.price) {
                        html += '<span class="psac-product-price"><span>' + escapeHtml(p.price.final) + '</span>';
                        if (p.price.has_special) html += '<span class="psac-old">' + escapeHtml(p.price.regular) + '</span>';
                        html += '</span>';
                    }
                    html += '</div></a>';
                });
            }
            if (state.categories.length && cfg.showCategories) {
                html += '<div class="psac-section-heading">' + escapeHtml(cfg.i18n.categories) + '</div>';
                state.categories.forEach(function (c) {
                    var idx = optIdx++;
                    var active = state.activeIdx === idx ? ' is-active' : '';
                    html += '<a class="psac-cat-row' + active + '" href="' + escapeHtml(c.url) + '"' + optionAttrs(idx) + '>'
                          + '<span class="psac-cat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h18M3 12h18M3 17h18"/></svg></span>'
                          + '<span class="psac-cat-name">' + highlight(c.name, state.query)
                          + (c.context ? '<span class="psac-cat-context">' + escapeHtml(c.context) + '</span>' : '')
                          + '</span>';
                    if (c.count > 0) html += '<span class="psac-cat-count">' + c.count + ' items</span>';
                    html += '</a>';
                });
            }
            if (state.pages.length && cfg.showPages) {
                html += '<div class="psac-section-heading">' + escapeHtml(cfg.i18n.pages) + '</div>';
                state.pages.forEach(function (p) {
                    var idx = optIdx++;
                    var active = state.activeIdx === idx ? ' is-active' : '';
                    html += '<a class="psac-page-row' + active + '" href="' + escapeHtml(p.url) + '"' + optionAttrs(idx) + '>'
                          + '<span class="psac-page-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg></span>'
                          + '<span class="psac-page-name">' + highlight(p.title, state.query) + '</span></a>';
                });
            }
            if (hasQuery() && !hasResults()) {
                html += '<div class="psac-empty">'
                      + '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35M8 11h6"/></svg>'
                      + '<p>' + escapeHtml(cfg.i18n.noResults) + '</p></div>';
            }
            html += '</div>';
            if (hasQuery() && hasResults()) {
                var viewAllTerm = state.didYouMean || state.query;
                var viewAll = cfg.viewAllUrl + '?q=' + encodeURIComponent(viewAllTerm);
                var label = viewAllLabel(cfg.i18n.viewAll, viewAllTerm);
                html += '<a class="psac-view-all" href="' + escapeHtml(viewAll) + '">'
                      + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>'
                      + '<span>' + label + '</span></a>';
            }
            if (hasQuery() && hasResults() && state.didYouMean && cfg.i18n.didYouMean) {
                html = '<div class="psac-correction" role="status">'
                     + escapeHtml(cfg.i18n.didYouMean).replace('%1', function () { return '<strong>' + escapeHtml(state.didYouMean) + '</strong>'; })
                     + '</div>' + html;
            }
            dropdown.innerHTML = html;
            var recentClear = dropdown.querySelector('[data-psac-recent-clear]');
            if (recentClear) {
                recentClear.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    state.recent = [];
                    try { localStorage.removeItem('psac_recent'); } catch (err) {}
                    render();
                    input.focus();
                });
            }
            var isEmpty = !dropdown.textContent.trim();
            dropdown.classList.toggle('psac-is-empty', isEmpty);
            input.setAttribute('aria-expanded', isEmpty ? 'false' : 'true');
            if (state.activeIdx >= 0 && state.activeIdx < optIdx) {
                input.setAttribute('aria-activedescendant', dropdown.id + '-opt-' + state.activeIdx);
            }
            dropdown.querySelectorAll('[data-psac-pick]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var term = btn.getAttribute('data-psac-pick') || '';
                    input.value = term; state.query = term;
                    fetch(term); input.focus();
                });
            });
        }
        function saveRecent(term) {
            if (!term) return;
            var list = state.recent.filter(function (t) { return t !== term; });
            list.unshift(term);
            state.recent = list.slice(0, 6);
            try { localStorage.setItem('psac_recent', JSON.stringify(state.recent)); } catch (e) {}
        }

        var debounced = debounce(function () {
            state.query = input.value || '';
            if (state.query.length < cfg.minLength) {
                state.products = []; state.categories = []; state.pages = []; state.didYouMean = '';
                open(); fetch('', true); render(); return;
            }
            fetch(state.query);
        }, cfg.debounceMs);

        input.addEventListener('input', debounced);
        input.addEventListener('focus', function () {
            open();
            if (!state.query && state.popular.length === 0) fetch('', true);
            render();
        });
        input.addEventListener('keydown', function (e) {
            var hrefs = flatHrefs();
            if (e.key === 'ArrowDown') {
                e.preventDefault(); open();
                if (!hrefs.length) return;
                state.activeIdx = (state.activeIdx + 1) % hrefs.length; render();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!hrefs.length) return;
                state.activeIdx = (state.activeIdx - 1 + hrefs.length) % hrefs.length; render();
            } else if (e.key === 'Enter') {
                if (state.activeIdx >= 0 && hrefs[state.activeIdx]) {
                    e.preventDefault(); saveRecent(state.query);
                    window.location.href = hrefs[state.activeIdx];
                }
            } else if (e.key === 'Escape') { close(); }
        });
        if (form) form.addEventListener('submit', function () {
            if (state.didYouMean && input.value === state.query) { input.value = state.didYouMean; }
            if (input.value) saveRecent(input.value);
            Array.prototype.forEach.call(form.querySelectorAll('input[name="form_key"], input[name="' + cfg.honeypotName + '"]'), function (field) {
                field.disabled = true;
            });
        });
        if (form) window.addEventListener('pageshow', function () {
            Array.prototype.forEach.call(form.querySelectorAll('input[name="form_key"], input[name="' + cfg.honeypotName + '"]'), function (field) {
                field.disabled = false;
            });
        });
        // Click outside the bar closes the dropdown.
        document.addEventListener('click', function (e) {
            var path = e.composedPath ? e.composedPath() : [];
            if (path.indexOf(bar) === -1 && !bar.contains(e.target)) close();
        });

        // Render initial popular state.
        render();
    }

    function init() {
        var cfg = window.PSAC_CONFIG;
        if (!cfg || !cfg.endpoint) return;
        var input = document.getElementById('panth-search-input');
        if (!input) {
            // Panth_ThemeCustomizer is disabled - leave the standalone
            // fallback wrap visible so autocomplete.js handles it.
            return;
        }
        if (input.dataset.psacAttached === '1') return;
        input.dataset.psacAttached = '1';
        try {
            attach(input, cfg);
            // Hide the standalone fallback trigger + overlay because we
            // are now bridging into the existing ThemeCustomizer search.
            var fallback = document.querySelector('[data-psac-fallback]');
            if (fallback) { fallback.style.display = 'none'; }
        } catch (e) {
            if (window.console) console.warn('[PanthSearchAutocomplete] attach failed', e);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
