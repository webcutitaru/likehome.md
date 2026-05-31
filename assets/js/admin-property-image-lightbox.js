(function () {
    'use strict';

    var lb = document.getElementById('lh-admin-img-lightbox');
    var lbImg = document.getElementById('lh-admin-img-lightbox-img');
    var lbClose = document.getElementById('lh-admin-img-lightbox-close');
    if (!lb || !lbImg || !lbClose) return;

    function open(src) {
        if (!src) return;
        lbImg.src = src;
        lb.classList.remove('hidden');
        lb.classList.add('flex', 'flex-col', 'items-center', 'justify-center');
        lb.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        lbClose.focus();
    }

    function close() {
        lb.classList.add('hidden');
        lb.classList.remove('flex', 'flex-col', 'items-center', 'justify-center');
        lb.setAttribute('aria-hidden', 'true');
        lbImg.removeAttribute('src');
        document.body.style.overflow = '';
    }

    lbClose.addEventListener('click', function (e) {
        e.stopPropagation();
        close();
    });

    lb.addEventListener('click', function (e) {
        if (e.target === lbImg) return;
        close();
    });

    lbImg.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !lb.classList.contains('hidden')) {
            close();
        }
    });

    function bindPreview(container) {
        container.addEventListener('click', function (e) {
            if (e.target.closest('button')) return;
            var cell = e.target.closest('.aspect-square');
            if (!cell || !container.contains(cell)) return;
            var im = cell.querySelector('img');
            if (!im) return;
            var src = im.getAttribute('src');
            if (!src) return;
            open(src);
        });
    }

    function init() {
        var addC = document.getElementById('image_preview_container');
        var editC = document.getElementById('combined_preview');
        if (addC) bindPreview(addC);
        if (editC) bindPreview(editC);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
