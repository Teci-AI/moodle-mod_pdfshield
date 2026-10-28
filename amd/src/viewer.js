// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * PDF Shield viewer: draws the document's pages on canvases, without any download, print or open control.
 *
 * Two sources: the PDF itself, drawn by PDF.js (thirdparty/pdfjs, script execution not supported by PDF.js 6), or page
 * images made on the server (the PDF never reaches the browser). Both are fetched by POST with the session key and the
 * viewer's header: a copied address does not work elsewhere. Pages are drawn when they come into view and released
 * when far away. In PDF mode the watermark is drawn on each page here; page images already carry it.
 *
 * @module     mod_pdfshield/viewer
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {
    'use strict';

    const ZOOMS = [0.5, 0.75, 1, 1.25, 1.5, 2, 3];
    const IMAGE_CACHE = 40;

    /**
     * A request from the viewer: POST, session key in the body, the viewer's header.
     *
     * @param {String} script file.php or page.php
     * @param {Object} params
     * @returns {Promise<Response>}
     */
    function post(script, params) {
        const body = new URLSearchParams(Object.assign({sesskey: M.cfg.sesskey}, params));
        return fetch(M.cfg.wwwroot + '/mod/pdfshield/' + script, {
            method: 'POST',
            body: body,
            headers: {'X-PDFShield': '1'},
            credentials: 'same-origin',
            cache: 'no-store',
        }).then(function(response) {
            if (!response.ok) {
                const error = new Error('HTTP ' + response.status);
                error.status = response.status;
                throw error;
            }
            return response;
        });
    }

    /**
     * Language strings (Moodle 4.5 and 5.x).
     *
     * @param {Array} requests
     * @returns {Promise<Array>}
     */
    function strings(requests) {
        const get = Str.getStrings || Str.get_strings;
        return Promise.resolve(get(requests.map(function(r) {
            return {key: r[0], component: 'mod_pdfshield', param: r[1]};
        })));
    }

    /**
     * Draws the watermark text across a canvas: diagonal, repeated, faint (as page.php does on page images).
     *
     * @param {HTMLCanvasElement} canvas
     * @param {String} text
     */
    function drawWatermark(canvas, text) {
        const ctx = canvas.getContext('2d');
        const w = canvas.width;
        const h = canvas.height;
        const size = Math.max(10, Math.round(w / 42));
        ctx.save();
        ctx.font = size + 'px sans-serif';
        ctx.fillStyle = 'rgba(90, 90, 90, 0.23)';
        ctx.translate(w / 2, h / 2);
        ctx.rotate(-Math.PI / 6);
        const stepx = ctx.measureText(text).width + size * 4;
        const stepy = size * 7;
        const reach = Math.hypot(w, h);
        let row = 0;
        for (let y = -reach; y < reach; y += stepy, row++) {
            for (let x = -reach + (row % 2) * stepx / 2; x < reach; x += stepx) {
                ctx.fillText(text, x, y);
            }
        }
        ctx.restore();
    }

    /**
     * Width of text in a font family, for placing a match inside a run of text (relative widths only).
     *
     * @param {String} text
     * @param {String} family
     * @returns {Number}
     */
    function measure(text, family) {
        measure.ctx = measure.ctx || document.createElement('canvas').getContext('2d');
        measure.ctx.font = '100px ' + family;
        return measure.ctx.measureText(text).width;
    }

    /**
     * The PDF, drawn by PDF.js.
     */
    class PdfSource {
        /**
         * @param {Number} cmid
         */
        constructor(cmid) {
            this.cmid = cmid;
            this.sizes = {};
            this.texts = {};
        }

        /**
         * Fetches the PDF and opens it.
         *
         * @returns {Promise}
         */
        async open() {
            const base = M.cfg.wwwroot + '/mod/pdfshield/thirdparty/pdfjs/';
            const [response, pdfjs] = await Promise.all([
                post('file.php', {id: this.cmid}),
                import(base + 'pdf.min.js'),
            ]);
            pdfjs.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.js';
            this.pdfjs = pdfjs;
            const data = new Uint8Array(await response.arrayBuffer());
            this.doc = await pdfjs.getDocument({
                data: data,
                cMapUrl: base + 'cmaps/',
                cMapPacked: true,
                standardFontDataUrl: base + 'standard_fonts/',
                wasmUrl: base + 'wasm/',
                iccUrl: base + 'iccs/',
                enableXfa: false,
            }).promise;
            this.count = this.doc.numPages;
            const first = (await this.doc.getPage(1)).getViewport({scale: 1});
            this.sizes[1] = {w: first.width, h: first.height};
        }

        /**
         * Page size at scale 1 (the first page's until the page itself is known).
         *
         * @param {Number} n
         * @returns {Object} {w, h}
         */
        size(n) {
            return this.sizes[n] || this.sizes[1];
        }

        /**
         * Draws a page.
         *
         * @param {Number} n
         * @param {HTMLCanvasElement} canvas
         * @param {Number} cssWidth
         * @returns {Promise<Object>} the page's CSS viewport, for search highlights
         */
        async render(n, canvas, cssWidth) {
            const page = await this.doc.getPage(n);
            const base = page.getViewport({scale: 1});
            this.sizes[n] = {w: base.width, h: base.height};
            const scale = cssWidth / base.width;
            const ratio = window.devicePixelRatio || 1;
            const viewport = page.getViewport({scale: scale * ratio});
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            await page.render({canvas: canvas, viewport: viewport}).promise;
            return page.getViewport({scale: scale});
        }

        /**
         * A page's text items (for search), each with its font family, kept once read.
         *
         * @param {Number} n
         * @returns {Promise<Array>}
         */
        async text(n) {
            if (!this.texts[n]) {
                const page = await this.doc.getPage(n);
                const content = await page.getTextContent();
                this.texts[n] = content.items.filter(function(item) {
                    return typeof item.str === 'string' && item.str.length;
                }).map(function(item) {
                    const style = content.styles[item.fontName];
                    return Object.assign({family: style ? style.fontFamily : 'sans-serif'}, item);
                });
            }
            return this.texts[n];
        }
    }

    /**
     * Page images made on the server.
     */
    class ImageSource {
        /**
         * @param {Number} cmid
         * @param {Number} count
         */
        constructor(cmid, count) {
            this.cmid = cmid;
            this.count = count;
            this.sizes = {};
            this.blobs = new Map();
        }

        /**
         * Gets the first page, to know the page shape.
         *
         * @returns {Promise}
         */
        async open() {
            const bitmap = await createImageBitmap(await this.image(1));
            this.sizes[1] = {w: bitmap.width, h: bitmap.height};
            bitmap.close();
        }

        /**
         * A page image, kept for the last pages seen.
         *
         * @param {Number} n
         * @returns {Promise<Blob>}
         */
        async image(n) {
            if (this.blobs.has(n)) {
                const blob = this.blobs.get(n);
                this.blobs.delete(n);
                this.blobs.set(n, blob);
                return blob;
            }
            const blob = await (await post('page.php', {id: this.cmid, page: n})).blob();
            this.blobs.set(n, blob);
            if (this.blobs.size > IMAGE_CACHE) {
                this.blobs.delete(this.blobs.keys().next().value);
            }
            return blob;
        }

        /**
         * Page size (the first page's until the page itself is known).
         *
         * @param {Number} n
         * @returns {Object} {w, h}
         */
        size(n) {
            return this.sizes[n] || this.sizes[1];
        }

        /**
         * Draws a page.
         *
         * @param {Number} n
         * @param {HTMLCanvasElement} canvas
         * @param {Number} cssWidth
         * @returns {Promise<null>}
         */
        async render(n, canvas, cssWidth) {
            const bitmap = await createImageBitmap(await this.image(n));
            this.sizes[n] = {w: bitmap.width, h: bitmap.height};
            const ratio = window.devicePixelRatio || 1;
            canvas.width = Math.floor(cssWidth * ratio);
            canvas.height = Math.floor(cssWidth * ratio * bitmap.height / bitmap.width);
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            bitmap.close();
            return null;
        }
    }

    /**
     * The viewer on the activity page.
     */
    class Viewer {
        /**
         * @param {HTMLElement} root
         * @param {Object} config {cmid, mode, pages, search, watermark}
         * @param {Object} text language strings
         */
        constructor(root, config, text) {
            this.root = root;
            this.config = config;
            this.text = text;
            this.pagesEl = root.querySelector('[data-region="pages"]');
            this.zoom = 1;
            this.current = 1;
            this.layoutVersion = 0;
            this.pages = [];
            this.matches = [];
            this.matchIndex = -1;
            this.source = config.mode === 'images' ? new ImageSource(config.cmid, config.pages) : new PdfSource(config.cmid);
        }

        /**
         * Opens the document and builds the pages.
         *
         * @returns {Promise}
         */
        async start() {
            this.guard();
            try {
                await this.source.open();
            } catch (e) {
                this.status(e.status === 403 ? this.text.sessionended : this.text.loaderror);
                return;
            }
            this.pagesEl.textContent = '';
            this.pagesEl.setAttribute('aria-busy', 'false');
            for (let n = 1; n <= this.source.count; n++) {
                const div = document.createElement('div');
                div.className = 'mod-pdfshield-page';
                div.setAttribute('role', 'img');
                div.setAttribute('aria-label', this.text.page + ' ' + n);
                div.dataset.page = String(n);
                const canvas = document.createElement('canvas');
                div.appendChild(canvas);
                this.pagesEl.appendChild(div);
                this.pages[n] = {div: div, canvas: canvas, version: -1, viewport: null};
            }
            const total = this.root.querySelector('[data-region="pagetotal"]');
            total.textContent = this.text.pageof.replace('{total}', this.source.count);
            const input = this.root.querySelector('[data-region="pagenumber"]');
            input.max = String(this.source.count);
            input.disabled = false;
            this.observer = new IntersectionObserver(this.visible.bind(this), {root: this.pagesEl, rootMargin: '150% 0px'});
            this.pages.forEach((page) => this.observer.observe(page.div));
            this.bind();
            this.layout();
        }

        /**
         * No right-click menu, dragging, copying or selecting inside the viewer.
         */
        guard() {
            ['contextmenu', 'dragstart', 'copy', 'cut', 'selectstart'].forEach((type) => {
                this.root.addEventListener(type, function(e) {
                    if (type !== 'selectstart' || !e.target.closest('input')) {
                        e.preventDefault();
                    }
                });
            });
        }

        /**
         * Toolbar, keyboard, scrolling and resizing.
         */
        bind() {
            const input = this.root.querySelector('[data-region="pagenumber"]');
            this.root.addEventListener('click', (e) => {
                const button = e.target.closest('[data-action]');
                if (!button) {
                    return;
                }
                const action = button.dataset.action;
                if (action === 'previous') {
                    this.goTo(this.current - 1);
                } else if (action === 'next') {
                    this.goTo(this.current + 1);
                } else if (action === 'zoomin' || action === 'zoomout' || action === 'zoomfit') {
                    this.setZoom(action);
                } else if (action === 'fullscreen') {
                    this.fullscreen();
                } else if (action === 'nextmatch' || action === 'previousmatch') {
                    this.step(action === 'nextmatch' ? 1 : -1);
                }
            });
            input.addEventListener('change', () => this.goTo(parseInt(input.value, 10) || 1));
            this.pagesEl.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight') {
                    this.goTo(this.current + 1);
                    e.preventDefault();
                } else if (e.key === 'ArrowLeft') {
                    this.goTo(this.current - 1);
                    e.preventDefault();
                }
            });
            let ticking = false;
            this.pagesEl.addEventListener('scroll', () => {
                if (!ticking) {
                    ticking = true;
                    window.requestAnimationFrame(() => {
                        ticking = false;
                        this.track();
                    });
                }
            });
            let width = this.pagesEl.clientWidth;
            new ResizeObserver(() => {
                if (Math.abs(this.pagesEl.clientWidth - width) > 4) {
                    width = this.pagesEl.clientWidth;
                    this.layout();
                }
            }).observe(this.pagesEl);
            const form = this.root.querySelector('[data-region="searchform"]');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.search(this.root.querySelector('[data-region="searchinput"]').value);
                });
            }
        }

        /**
         * Sizes every page for the current width and zoom; visible pages are drawn again.
         */
        layout() {
            const keep = this.current;
            const style = window.getComputedStyle(this.pagesEl);
            const inner = this.pagesEl.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            this.width = Math.max(200, Math.floor(inner * this.zoom));
            this.layoutVersion++;
            this.pages.forEach((page, n) => {
                const size = this.source.size(n);
                page.div.style.width = this.width + 'px';
                page.div.style.height = Math.round(this.width * size.h / size.w) + 'px';
                page.version = -1;
            });
            this.root.querySelector('[data-region="zoomlevel"]').textContent = Math.round(this.zoom * 100) + '%';
            this.goTo(keep);
            this.pages.forEach((page, n) => {
                if (page.near) {
                    this.draw(n);
                }
            });
        }

        /**
         * Pages coming near the visible area are drawn; pages far away are released.
         *
         * @param {Array} entries
         */
        visible(entries) {
            entries.forEach((entry) => {
                const n = parseInt(entry.target.dataset.page, 10);
                const page = this.pages[n];
                page.near = entry.isIntersecting;
                if (entry.isIntersecting) {
                    this.draw(n);
                } else if (page.version !== -1) {
                    page.canvas.width = 0;
                    page.canvas.height = 0;
                    page.version = -1;
                }
            });
        }

        /**
         * Draws one page for the current layout, then its watermark and search highlights.
         *
         * @param {Number} n
         */
        async draw(n) {
            const page = this.pages[n];
            const version = this.layoutVersion;
            if (!this.width || page.version === version) {
                return;
            }
            page.version = version;
            const canvas = document.createElement('canvas');
            try {
                page.viewport = await this.source.render(n, canvas, this.width);
            } catch (e) {
                page.version = -1;
                if (e.status === 403) {
                    this.status(this.text.sessionended);
                }
                return;
            }
            if (page.version !== version || this.layoutVersion !== version) {
                return;
            }
            if (this.config.mode === 'pdf' && this.config.watermark) {
                drawWatermark(canvas, this.config.watermark);
            }
            page.div.replaceChild(canvas, page.canvas);
            page.canvas = canvas;
            const size = this.source.size(n);
            page.div.style.height = Math.round(this.width * size.h / size.w) + 'px';
            this.highlight(n);
        }

        /**
         * Shows a page.
         *
         * @param {Number} n
         */
        goTo(n) {
            n = Math.min(Math.max(1, n), this.source.count);
            const page = this.pages[n];
            if (!page) {
                return;
            }
            this.pagesEl.scrollTop = page.div.offsetTop - parseFloat(window.getComputedStyle(this.pagesEl).paddingTop);
            this.setCurrent(n);
        }

        /**
         * The page at the top third of the view is the current page.
         */
        track() {
            const line = this.pagesEl.scrollTop + this.pagesEl.clientHeight / 3;
            for (let n = 1; n < this.pages.length; n++) {
                const div = this.pages[n].div;
                if (div.offsetTop + div.offsetHeight > line) {
                    this.setCurrent(n);
                    return;
                }
            }
        }

        /**
         * Updates the toolbar for the current page.
         *
         * @param {Number} n
         */
        setCurrent(n) {
            this.current = n;
            this.root.querySelector('[data-region="pagenumber"]').value = String(n);
            this.root.querySelector('[data-action="previous"]').disabled = n <= 1;
            this.root.querySelector('[data-action="next"]').disabled = n >= this.source.count;
        }

        /**
         * Zooms in or out by one step, or back to the page width.
         *
         * @param {String} action zoomin, zoomout or zoomfit
         */
        setZoom(action) {
            const i = ZOOMS.indexOf(this.zoom);
            if (action === 'zoomfit') {
                this.zoom = 1;
            } else if (action === 'zoomin' && i < ZOOMS.length - 1) {
                this.zoom = ZOOMS[i + 1];
            } else if (action === 'zoomout' && i > 0) {
                this.zoom = ZOOMS[i - 1];
            }
            this.layout();
        }

        /**
         * Full screen on and off.
         */
        fullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (this.root.requestFullscreen) {
                this.root.requestFullscreen();
            }
        }

        /**
         * Finds a word or phrase in every page and shows the first match.
         *
         * @param {String} term
         */
        async search(term) {
            term = term.trim().toLocaleLowerCase();
            this.matches = [];
            this.matchIndex = -1;
            if (term) {
                for (let n = 1; n <= this.source.count; n++) {
                    (await this.source.text(n)).forEach((item) => {
                        const str = item.str.toLocaleLowerCase();
                        let at = str.indexOf(term);
                        while (at !== -1) {
                            this.matches.push({page: n, item: item, start: at, length: term.length});
                            at = str.indexOf(term, at + term.length);
                        }
                    });
                }
            }
            this.step(1);
            this.pages.forEach((page, n) => this.highlight(n));
        }

        /**
         * Moves to the next or previous match.
         *
         * @param {Number} direction 1 or -1
         */
        step(direction) {
            const status = this.root.querySelector('[data-region="searchstatus"]');
            if (!this.matches.length) {
                status.textContent = this.root.querySelector('[data-region="searchinput"]').value.trim() ?
                    this.text.searchnone : '';
                return;
            }
            const previous = this.matches[this.matchIndex];
            this.matchIndex = (this.matchIndex + direction + this.matches.length) % this.matches.length;
            const match = this.matches[this.matchIndex];
            status.textContent = this.text.searchresult.replace('{current}', this.matchIndex + 1)
                .replace('{total}', this.matches.length);
            this.goTo(match.page);
            this.highlight(match.page);
            if (previous && previous.page !== match.page) {
                this.highlight(previous.page);
            }
        }

        /**
         * Marks the matches on a drawn page (boxes only: the text itself is never put in the page).
         *
         * @param {Number} n
         */
        highlight(n) {
            const page = this.pages[n];
            if (!page || this.config.mode !== 'pdf') {
                return;
            }
            let layer = page.div.querySelector('.mod-pdfshield-highlights');
            if (layer) {
                layer.remove();
            }
            const matches = this.matches.filter((m) => m.page === n);
            if (!matches.length || !page.viewport) {
                return;
            }
            layer = document.createElement('div');
            layer.className = 'mod-pdfshield-highlights';
            const util = this.source.pdfjs.Util;
            const current = this.matches[this.matchIndex];
            matches.forEach((match) => {
                const tx = util.transform(page.viewport.transform, match.item.transform);
                const height = Math.hypot(tx[2], tx[3]);
                const width = match.item.width * page.viewport.scale;
                // Where the match sits in its run of text: measured in the run's font family, scaled to its real width.
                const str = match.item.str;
                const full = measure(str, match.item.family) || 1;
                const before = measure(str.slice(0, match.start), match.item.family);
                const inside = measure(str.slice(match.start, match.start + match.length), match.item.family);
                const box = document.createElement('span');
                box.style.left = (tx[4] + width * before / full) + 'px';
                box.style.top = (tx[5] - height) + 'px';
                box.style.width = Math.max(2, width * inside / full) + 'px';
                box.style.height = height + 'px';
                if (match === current) {
                    box.className = 'current';
                }
                layer.appendChild(box);
            });
            page.div.appendChild(layer);
        }

        /**
         * A message in place of the pages.
         *
         * @param {String} message
         */
        status(message) {
            this.pagesEl.setAttribute('aria-busy', 'false');
            this.pagesEl.textContent = '';
            const p = document.createElement('p');
            p.className = 'mod-pdfshield-status';
            p.setAttribute('role', 'alert');
            p.textContent = message;
            this.pagesEl.appendChild(p);
        }
    }

    return {
        /**
         * Starts the viewer on the activity page.
         *
         * @param {Object} config {cmid, mode, pages, search, watermark}
         */
        init: function(config) {
            const root = document.querySelector('[data-region="pdfshield"]');
            if (!root) {
                return;
            }
            strings([
                ['pageof', '{total}'], ['loaderror'], ['sessionended'], ['searchnone'],
                ['searchresult', {current: '{current}', total: '{total}'}], ['page'],
            ]).then(function(s) {
                const text = {pageof: s[0], loaderror: s[1], sessionended: s[2], searchnone: s[3], searchresult: s[4],
                    page: s[5]};
                return new Viewer(root, config, text).start();
            }).catch(function() {
                root.querySelector('[data-region="pages"]').setAttribute('aria-busy', 'false');
            });
        },
    };
});
