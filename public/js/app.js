(function () {
    'use strict';

    function getGlobalInitData() {
        try {
            return window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp.initData : '';
        } catch (e) {
            return '';
        }
    }

    function getStoredInitData() {
        try {
            return sessionStorage.getItem('tg_init_data') || '';
        } catch (e) {
            return '';
        }
    }

    function setStoredInitData(value) {
        try {
            if (value) sessionStorage.setItem('tg_init_data', value);
        } catch (e) {}
    }

    function currentInitData() {
        return getStoredInitData() || getGlobalInitData();
    }

    function ensureInitDataParam(url) {
        var initData = currentInitData();
        if (!initData) return url;

        var anchorIndex = url.indexOf('#');
        var anchor = '';
        if (anchorIndex !== -1) {
            anchor = url.substring(anchorIndex);
            url = url.substring(0, anchorIndex);
        }

        var sep = url.indexOf('?') !== -1 ? '&' : '?';
        var hasParam = /[?&]initData=/.test(url);
        if (!hasParam) {
            url += sep + 'initData=' + encodeURIComponent(initData);
        }

        return url + anchor;
    }

    function decorateLinks() {
        document.querySelectorAll('a:not([data-tg-decorated])').forEach(function (link) {
            link.setAttribute('data-tg-decorated', '1');
            var originalHref = link.getAttribute('href');
            if (!originalHref || originalHref.charAt(0) === '#' || originalHref.startsWith('javascript:')) return;
            if (link.origin && link.origin !== window.location.origin) return;

            link.addEventListener('click', function (e) {
                if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
                if (link.target && link.target === '_blank') return;
                var decorated = ensureInitDataParam(link.getAttribute('href'));
                if (decorated !== link.getAttribute('href')) {
                    e.preventDefault();
                    window.location.href = decorated;
                }
            });
        });
    }

    function ensureFormInitData() {
        document.querySelectorAll('form:not([data-tg-form-decorated])').forEach(function (form) {
            form.setAttribute('data-tg-form-decorated', '1');
            form.addEventListener('submit', function () {
                var initData = currentInitData();
                if (!initData) return;
                var existing = form.querySelector('input[name="initData"]');
                if (!existing) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'initData';
                    form.appendChild(input);
                    existing = input;
                }
                existing.value = initData;
            });
        });
    }

    function setupBackButton(tg) {
        var backLink = document.querySelector('.back-link');
        if (!backLink) {
            tg.BackButton.hide();
            return;
        }
        tg.BackButton.show();
        tg.BackButton.onClick(function () {
            var href = backLink.getAttribute('href');
            if (href) {
                window.location.href = href;
            } else if (window.history.length > 1) {
                window.history.back();
            }
        });
    }

    function updateNewsBadge() {
        try {
            var badge = document.getElementById('news-badge');
            if (!badge) return;
            var total = parseInt(localStorage.getItem('news_total') || '0', 10);
            var read = JSON.parse(localStorage.getItem('news_read') || '[]');
            var unread = total - read.length;
            if (unread > 0) {
                badge.textContent = unread > 99 ? '99+' : String(unread);
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        } catch (e) {}
    }

    function initWebApp() {
        if (window.Telegram && window.Telegram.WebApp) {
            var tg = window.Telegram.WebApp;
            tg.expand();
            tg.ready();
            setStoredInitData(tg.initData || '');
            document.body.classList.add('is-tma');
            setupBackButton(tg);
        }
        decorateLinks();
        ensureFormInitData();
        updateNewsBadge();
        if (window.lucide) window.lucide.createIcons();
    }

    document.addEventListener('DOMContentLoaded', initWebApp);
    window.updateNewsBadge = updateNewsBadge;
    window.addEventListener('load', function () {
        if (window.lucide) window.lucide.createIcons();
    });
})();
