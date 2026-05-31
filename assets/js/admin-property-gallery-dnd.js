/**
 * Drag-and-drop reorder for property gallery (add + edit admin).
 * Requires SortableJS (global Sortable) loaded before this file.
 *
 * Before upload: resizes wide images to max LH_GALLERY_PREPARE_UPLOAD.maxWidth (defaults 1920)
 * and re-encodes as WebP client-side using webpQuality01 (defaults 0.88) — matches server
 * LH_PROPERTY_IMAGE_MAX_WIDTH / LH_PROPERTY_WEBP_QUALITY when set inline in PHP.
 */
(function () {
    'use strict';

    function isAllowedImageFile(file) {
        if (!file) return false;
        var t = file.type || '';
        if (t.indexOf('image/') === 0) return true;
        return /\.(jpe?g|png|webp)$/i.test(file.name || '');
    }

    function lhGalleryGetPrepareOpts() {
        var d = typeof window !== 'undefined' ? window.LH_GALLERY_PREPARE_UPLOAD : null;
        var maxW =
            d && typeof d.maxWidth === 'number' && d.maxWidth >= 640 && d.maxWidth <= 8192 ? d.maxWidth : 1920;
        var q =
            d && typeof d.webpQuality01 === 'number' && d.webpQuality01 > 0 && d.webpQuality01 <= 1
                ? d.webpQuality01
                : 0.88;
        return { maxWidth: maxW, webpQuality01: q };
    }

    /** @returns {Promise<File>} */
    function lhGalleryFallbackJpeg(canvas, originalFile, quality01) {
        return new Promise(function (resolve) {
            try {
                canvas.toBlob(
                    function (blob) {
                        if (!blob || !originalFile) {
                            resolve(originalFile);
                            return;
                        }
                        var base = (originalFile.name || 'photo').replace(/\.[^/.]+$/, '') || 'photo';
                        resolve(
                            new File([blob], base + '_lh.jpg', {
                                type: 'image/jpeg',
                                lastModified: Date.now(),
                            })
                        );
                    },
                    'image/jpeg',
                    Math.min(quality01, 0.92)
                );
            } catch (e) {
                resolve(originalFile);
            }
        });
    }

    /**
     * Downscale-to-max-width + encode WebP before upload (smaller payloads on slow uploads).
     * Falls back to original file on unsupported canvas/WebP / errors.
     * @returns {Promise<File>}
     */
    function lhPrepareGalleryFileForUpload(file) {
        return new Promise(function (resolve) {
            if (!file || !isAllowedImageFile(file)) {
                resolve(file);
                return;
            }
            var opts = lhGalleryGetPrepareOpts();
            var img = new Image();
            img.onload = function () {
                try {
                    var w = img.naturalWidth || 0;
                    var h = img.naturalHeight || 0;
                    if (!w || !h) {
                        resolve(file);
                        return;
                    }
                    var tw = w;
                    var th = h;
                    if (w > opts.maxWidth) {
                        tw = opts.maxWidth;
                        th = Math.max(1, Math.round((h * opts.maxWidth) / w));
                    }
                    var canvas = document.createElement('canvas');
                    canvas.width = tw;
                    canvas.height = th;
                    var ctx = canvas.getContext('2d');
                    if (!ctx) {
                        resolve(file);
                        return;
                    }
                    ctx.imageSmoothingEnabled = true;
                    ctx.drawImage(img, 0, 0, tw, th);
                    var q = opts.webpQuality01;
                    canvas.toBlob(
                        function (blob) {
                            if (!blob) {
                                lhGalleryFallbackJpeg(canvas, file, q).then(resolve);
                                return;
                            }
                            var base = (file.name || 'photo').replace(/\.[^/.]+$/, '') || 'photo';
                            resolve(
                                new File([blob], base + '_lh.webp', {
                                    type: 'image/webp',
                                    lastModified: Date.now(),
                                })
                            );
                        },
                        'image/webp',
                        q
                    );
                } catch (e2) {
                    resolve(file);
                }
            };
            img.onerror = function () {
                resolve(file);
            };
            var reader = new FileReader();
            reader.onload = function (ev) {
                var dataUrl = ev && ev.target && ev.target.result ? String(ev.target.result) : '';
                if (!dataUrl) {
                    resolve(file);
                    return;
                }
                img.src = dataUrl;
            };
            reader.onerror = function () {
                resolve(file);
            };
            reader.readAsDataURL(file);
        });
    }

    function createDragHandle() {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className =
            'lh-gallery-drag absolute left-2 top-2 z-20 flex h-9 w-9 items-center justify-center rounded-xl bg-slate-900/75 text-white shadow-md backdrop-blur-sm hover:bg-slate-800 cursor-grab active:cursor-grabbing';
        btn.setAttribute('aria-label', 'Reordonează');
        btn.setAttribute('title', 'Trage pentru a reordona');
        btn.innerHTML = '<i data-lucide="grip-vertical" class="h-4 w-4"></i>';
        btn.addEventListener('mousedown', function (e) {
            e.stopPropagation();
        });
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
        });
        return btn;
    }

    function refreshLucide() {
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    }

    function initSortable(container) {
        if (!container || typeof Sortable === 'undefined') {
            return null;
        }
        if (container._lhSortable) {
            return container._lhSortable;
        }
        container._lhSortable = new Sortable(container, {
            animation: 160,
            handle: '.lh-gallery-drag',
            draggable: '.lh-gallery-item',
            ghostClass: 'lh-gallery-sort-ghost',
            chosenClass: 'lh-gallery-sort-chosen',
        });
        return container._lhSortable;
    }

    function lhGalleryAppendAddRow(container, preparedFile) {
        var wrap = document.createElement('div');
        wrap.className = 'lh-gallery-item relative';
        wrap._lhGalleryFile = preparedFile;
        var inner = document.createElement('div');
        inner.className =
            'relative aspect-square rounded-2xl overflow-hidden border border-slate-100 shadow-sm bg-white cursor-zoom-in';
        var imgEl = document.createElement('img');
        imgEl.className = 'w-full h-full object-cover';
        imgEl.alt = '';
        var thumbReader = new FileReader();
        thumbReader.onload = function (ev) {
            var dataUrl = ev && ev.target && ev.target.result ? String(ev.target.result) : '';
            if (dataUrl) {
                imgEl.src = dataUrl;
            }
        };
        thumbReader.onerror = function () {};
        thumbReader.readAsDataURL(preparedFile);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className =
            'absolute top-2 right-2 bg-slate-900/80 text-white p-2 rounded-xl hover:bg-red-500 transition-colors backdrop-blur-sm';
        btn.setAttribute('aria-label', 'Elimină');
        btn.innerHTML = '<i data-lucide="x" class="w-4 h-4"></i>';
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            wrap.remove();
        });
        var badge = document.createElement('div');
        badge.className =
            'absolute bottom-2 left-2 px-2 py-0.5 bg-cta text-[8px] font-black text-white rounded uppercase tracking-tighter';
        badge.textContent = 'Live Preview';
        inner.appendChild(imgEl);
        inner.appendChild(btn);
        inner.appendChild(badge);
        wrap.appendChild(createDragHandle());
        wrap.appendChild(inner);
        container.appendChild(wrap);
        refreshLucide();
    }

    function lhGalleryAppendEditNewRow(container, preparedFile) {
        var wrap = document.createElement('div');
        wrap.className = 'lh-gallery-item lh-gallery-new relative';
        wrap._lhGalleryFile = preparedFile;
        var inner = document.createElement('div');
        inner.className =
            'relative aspect-square cursor-zoom-in rounded-2xl overflow-hidden border-2 border-cta/40 shadow-md bg-white';
        var reader = new FileReader();
        reader.onload = function (e) {
            var blobUrl = e.target && e.target.result ? String(e.target.result) : '';
            var im = document.createElement('img');
            im.src = blobUrl;
            im.className = 'w-full h-full object-cover opacity-90';
            im.alt = '';
            var tint = document.createElement('div');
            tint.className = 'absolute inset-0 bg-cta/10 pointer-events-none';
            tint.setAttribute('aria-hidden', 'true');
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className =
                'lh-gallery-remove-new absolute top-2 right-2 bg-slate-900 text-white p-2 rounded-xl hover:bg-red-500 transition-colors';
            rm.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4"></i>';
            rm.addEventListener('click', function (ev) {
                ev.stopPropagation();
                wrap.remove();
            });
            var badge = document.createElement('div');
            badge.className =
                'absolute bottom-2 left-2 px-2 py-0.5 bg-cta text-[8px] font-black text-white rounded uppercase';
            badge.textContent = 'Nou';
            inner.appendChild(im);
            inner.appendChild(tint);
            inner.appendChild(rm);
            inner.appendChild(badge);
            refreshLucide();
        };
        reader.readAsDataURL(preparedFile);
        wrap.appendChild(createDragHandle());
        wrap.appendChild(inner);
        container.appendChild(wrap);
    }

    /** --- Add property --- */
    function lhGalleryAddOnFileInput(input) {
        var container = document.getElementById('image_preview_container');
        if (!container || !input || !input.files) return;
        var raw = Array.from(input.files).filter(isAllowedImageFile);
        input.value = '';
        if (raw.length === 0) return;
        raw
            .reduce(function (seq, file) {
                return seq.then(function () {
                    return lhPrepareGalleryFileForUpload(file).then(function (prepared) {
                        lhGalleryAppendAddRow(container, prepared);
                    });
                });
            }, Promise.resolve())
            .catch(function () {
                /* ignore */
            });
    }

    function lhGalleryCollectAddOrderedFiles() {
        var container = document.getElementById('image_preview_container');
        if (!container) return [];
        var out = [];
        container.querySelectorAll('.lh-gallery-item').forEach(function (el) {
            if (el._lhGalleryFile) out.push(el._lhGalleryFile);
        });
        return out;
    }

    function lhGalleryInitAdd() {
        var container = document.getElementById('image_preview_container');
        var input = document.getElementById('image_input');
        if (!container || !input) return;
        initSortable(container);
        input.addEventListener('change', function () {
            lhGalleryAddOnFileInput(input);
        });
    }

    /** --- Edit property --- */
    function handleNewImages(input) {
        var container = document.getElementById('combined_preview');
        if (!container || !input || !input.files) return;
        var raw = Array.from(input.files).filter(isAllowedImageFile);
        input.value = '';
        if (raw.length === 0) return;
        raw
            .reduce(function (seq, file) {
                return seq.then(function () {
                    return lhPrepareGalleryFileForUpload(file).then(function (prepared) {
                        lhGalleryAppendEditNewRow(container, prepared);
                    });
                });
            }, Promise.resolve())
            .catch(function () {
                /* ignore */
            });
    }

    function lhGalleryCollectEditNewFiles() {
        var container = document.getElementById('combined_preview');
        if (!container) return [];
        var out = [];
        container.querySelectorAll('.lh-gallery-item.lh-gallery-new').forEach(function (el) {
            if (el._lhGalleryFile) out.push(el._lhGalleryFile);
        });
        return out;
    }

    function lhGalleryInitEdit() {
        var container = document.getElementById('combined_preview');
        if (!container) return;
        initSortable(container);
        if (!container._lhGalleryRemoveDelegated) {
            container._lhGalleryRemoveDelegated = true;
            container.addEventListener('click', function (e) {
                var del = e.target.closest('.lh-gallery-remove-existing');
                if (!del) return;
                e.preventDefault();
                var row = del.closest('.lh-gallery-item');
                if (row) row.remove();
            });
        }
        refreshLucide();
    }

    function lhGalleryBoot() {
        if (document.getElementById('image_preview_container') && document.getElementById('addPropertyForm')) {
            lhGalleryInitAdd();
        }
        if (document.getElementById('combined_preview') && document.getElementById('editForm')) {
            lhGalleryInitEdit();
        }
    }

    window.lhGalleryAddOnFileInput = lhGalleryAddOnFileInput;
    window.lhGalleryCollectAddOrderedFiles = lhGalleryCollectAddOrderedFiles;
    window.handleNewImages = handleNewImages;
    window.lhGalleryCollectEditNewFiles = lhGalleryCollectEditNewFiles;
    window.lhPrepareGalleryFileForUpload = lhPrepareGalleryFileForUpload;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', lhGalleryBoot);
    } else {
        lhGalleryBoot();
    }
})();
