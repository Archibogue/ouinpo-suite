(function() {
    function markResourcesRead() {
        try {
            if (window.__OUINPO_CLEAR_RES_UNREAD) {
                window.__OUINPO_CLEAR_RES_UNREAD();
            }
        } catch (error) {}

        try {
            if (window.OuInPoRes && window.OuInPoRes.endpoint) {
                var url = window.OuInPoRes.endpoint + (window.OuInPoRes.endpoint.indexOf('?') > -1 ? '&' : '?') + 'mark=1&limit=1';

                fetch(url, {
                    headers: { 'X-WP-Nonce': window.OuInPoRes.nonce, 'Accept': 'application/json' },
                    credentials: 'same-origin'
                }).catch(function() {});
            }
        } catch (error) {}
    }

    function initResourceDomainFilter() {
        var select = document.querySelector('.ouinpo-res-select');
        var blocks = Array.prototype.slice.call(document.querySelectorAll('.ouinpo-chapter[data-domain]'));

        if (!select || !blocks.length) return;

        function applyFilter(value) {
            blocks.forEach(function(block) {
                var domain = block.getAttribute('data-domain');

                if (value === 'all' || domain === value) {
                    block.style.display = '';
                } else {
                    block.style.display = 'none';
                }
            });
        }

        select.addEventListener('change', function() {
            applyFilter(select.value || 'all');
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        markResourcesRead();
        initResourceDomainFilter();
    });
})();
