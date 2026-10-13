(function () {
    'use strict';
    function initialize() {
        var notice = document.querySelector('[data-panel-runtime]');
        var retry = document.querySelector('[data-panel-runtime-retry]');
        if (!notice) return;
        var interval;
        function isReady() {
            return document.documentElement.getAttribute('data-tc-auth') === 'ready';
        }
        function check() {
            if (isReady()) {
                notice.setAttribute('hidden', '');
                if (interval) window.clearInterval(interval);
            }
        }
        if (retry) retry.addEventListener('click', function () { window.location.reload(); });
        window.setTimeout(function () {
            if (!isReady()) notice.removeAttribute('hidden');
        }, 3500);
        if (typeof window.MutationObserver === 'function') {
            var observer = new window.MutationObserver(check);
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-tc-auth'] });
        } else {
            interval = window.setInterval(check, 250);
        }
        check();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}());
