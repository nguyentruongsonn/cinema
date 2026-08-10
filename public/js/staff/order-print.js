(function () {
    'use strict';

    const printButton = document.getElementById('printDocumentButton');
    printButton?.addEventListener('click', () => window.print());

    if (document.body.dataset.autoPrint === 'true') {
        window.addEventListener('load', () => {
            window.setTimeout(() => window.print(), 250);
        }, { once: true });
    }
})();
