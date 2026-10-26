<?php
/* ---------------------------------------------------------------------------
   csc_breakdown.php — "Transformers by type", the analysis under the CSC
   register on csc.php.

   Summary tiles, then the "By CSC" table: one row per CSC with its
   Distribution, Bulk and Bulk & Dist. counts, Other, the total and a split
   bar. Choosing a CSC opens its details on the same page — a ring with the
   counts, one tab per type, and the transformers of that type.

   Analysis only: nothing here adds, edits or removes anything, and the
   register above it is untouched.

   Drawn entirely in the browser from two endpoints:
     csc_stats_api.php          the counts per CSC and type (GROUP BY)
     csc_transformers_api.php   one CSC's transformers of one type, 50 a page

   Included by csc.php, after header.php, so the session is already checked.
   --------------------------------------------------------------------------- */
?>

<section class="cscx" id="cscx" aria-labelledby="cscx-title">

    <h2 class="cscx-title" id="cscx-title">Transformers by type</h2>

    <?php /* One tile for the total, one per type the register holds, and
             Unspecified for the blank ones, so the tiles always add up to
             the first. They follow the filters on the table below. */ ?>
    <div class="stats stats-compact cscx-tiles" id="cscx-tiles" aria-live="polite"></div>

    <div class="panel">
        <div class="panel-head">
            <h2>By CSC</h2>
            <span class="panel-note" id="cscx-updated">Counted from the register</span>
        </div>

        <div class="toolbar">
            <div class="search-box" role="search">

                <?php /* Shown only when the CSCs span more than one province.
                         Today every CSC is in Uva, and a dropdown with one
                         choice in it is not a filter. */ ?>
                <div class="filter-group" id="cscx-prov-group" hidden>
                    <label for="cscx-prov">Province</label>
                    <select id="cscx-prov">
                        <option value="">All provinces</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="cscx-area">Area</label>
                    <select id="cscx-area">
                        <option value="">All areas</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="panel-body">
            <div class="cscx-layout" id="cscx-layout">

                <div class="cscx-main">
                    <div class="cscx-scroll">
                        <table class="data plain-table compact cscx-table" id="cscx-table">
                            <caption id="cscx-caption">
                                Choose a CSC to see its transformers.
                            </caption>
                            <thead><tr id="cscx-head"></tr></thead>
                            <tbody id="cscx-body">
                                <tr><td><div class="table-empty">Loading the counts&hellip;</div></td></tr>
                            </tbody>
                            <tfoot><tr id="cscx-foot"></tr></tfoot>
                        </table>
                    </div>
                </div>

                <?php /* The details. Beside the table on a wide screen, under
                         it everywhere else; the stylesheet decides which. */ ?>
                <aside class="cscx-detail" id="cscx-detail" hidden aria-labelledby="cscx-d-name">

                    <div class="cscx-detail-head">
                        <div class="cscx-detail-title">
                            <h3 id="cscx-d-name" tabindex="-1"></h3>
                            <p class="cscx-detail-sub" id="cscx-d-sub"></p>
                        </div>
                        <button type="button" class="btn btn-secondary btn-small" id="cscx-close">Close</button>
                    </div>

                    <div class="table-empty" id="cscx-d-empty" hidden>
                        No transformers recorded for this CSC.
                    </div>

                    <div id="cscx-d-body">
                        <div class="chart-donut cscx-donut" id="cscx-donut"></div>

                        <div class="cscx-tabs" role="tablist" aria-label="Transformer type"
                             id="cscx-tabs"></div>

                        <div role="tabpanel" id="cscx-panel" aria-live="polite">
                            <div class="cscx-list-tools">
                                <input type="search" id="cscx-lq" autocomplete="off"
                                       placeholder="Search S/N, serial no or substation"
                                       aria-label="Search this list">
                                <span class="cscx-list-count" id="cscx-lcount"></span>
                            </div>

                            <div class="cscx-scroll">
                                <table class="data plain-table compact cscx-list">
                                    <thead>
                                        <tr>
                                            <th>S/N</th>
                                            <th>Serial No</th>
                                            <th>Substation</th>
                                            <th class="num">kVA</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cscx-lbody"></tbody>
                                </table>
                            </div>

                            <div class="cscx-pager" id="cscx-pager" hidden>
                                <button type="button" class="btn btn-secondary btn-small" id="cscx-prev">&lsaquo; Previous</button>
                                <span id="cscx-pageinfo"></span>
                                <button type="button" class="btn btn-secondary btn-small" id="cscx-next">Next &rsaquo;</button>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>

<script>
(function () {

    // The page size is the endpoint's ($perPage); it says so in every answer.
    var SVGNS = 'http://www.w3.org/2000/svg';

    function $(id) { return document.getElementById(id); }

    var areaSel   = $('cscx-area'),   provSel = $('cscx-prov'),
        tiles     = $('cscx-tiles'),  head    = $('cscx-head'),
        body      = $('cscx-body'),   foot    = $('cscx-foot'),  layout  = $('cscx-layout'),
        detail    = $('cscx-detail'), dName   = $('cscx-d-name'), dSub   = $('cscx-d-sub'),
        dEmpty    = $('cscx-d-empty'), dBody  = $('cscx-d-body'), donut  = $('cscx-donut'),
        tabs      = $('cscx-tabs'),   listQ   = $('cscx-lq'),    lCount  = $('cscx-lcount'),
        lBody     = $('cscx-lbody'),  pager   = $('cscx-pager'), pageInfo = $('cscx-pageinfo'),
        prevBtn   = $('cscx-prev'),   nextBtn = $('cscx-next'),  updated = $('cscx-updated'),
        caption   = $('cscx-caption');

    if (!body) { return; }

    var data     = null;   // the last answer from csc_stats_api.php
    var selected = null;   // id of the CSC in the panel, or null
    var tab      = null;   // index into data.types of the open tab
    var page     = 1;
    var statsSeq = 0, listSeq = 0;   // a slow answer never overwrites a newer one

    // ---- small helpers -----------------------------------------------------
    function typeName(t)  { return t.label !== '' ? t.label : 'Unspecified'; }
    function shortName(t) { return typeName(t).replace(' & Distribution', ' & Dist.'); }
    function colour(t)    { return 'txs-' + t.slot; }
    function fmt(n)       { return Number(n).toLocaleString(); }
    function pct(n, of)   { return of > 0 ? (n / of * 100).toFixed(1) + '%' : '0.0%'; }

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls)  { n.className = cls; }
        if (text !== undefined && text !== null) { n.textContent = text; }
        return n;
    }

    function dot(t) {
        var d = el('span', 'legend-dot ' + colour(t));
        d.setAttribute('aria-hidden', 'true');
        return d;
    }

    function svg(tag, attrs) {
        var n = document.createElementNS(SVGNS, tag);
        for (var k in attrs) { if (attrs.hasOwnProperty(k)) { n.setAttribute(k, attrs[k]); } }
        return n;
    }

    function cscLabel(r) {
        if (r.noCsc)   { return r.name; }
        return r.name + (r.deleted ? ' (deleted CSC)' : '');
    }

    /* fetch, and turn every way it can go wrong into one Error with words
       a person can act on. A PHP error page is HTML, not JSON — that lands
       here too rather than as a blank table. */
    function getJSON(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) {
                return r.json()
                    .catch(function () { return null; })
                    .then(function (b) {
                        if (!r.ok || !b || b.error) {
                            var e = new Error(b && b.error ? b.error
                                    : 'The server did not answer properly (' + r.status + ').');
                            e.status = r.status;
                            throw e;
                        }
                        return b;
                    });
            }, function () {
                throw new Error('The server could not be reached. Check the connection and try again.');
            });
    }

    // A message across a whole table, with a Retry button when it helps.
    function tableMessage(tbody, cols, text, retry) {
        tbody.textContent = '';
        var tr = el('tr'), td = el('td'), box = el('div', 'table-empty');
        td.colSpan = cols;
        box.appendChild(el('span', null, text));
        if (retry) {
            var b = el('button', 'btn btn-secondary btn-small cscx-retry', 'Try again');
            b.type = 'button';
            b.addEventListener('click', retry);
            box.appendChild(b);
        }
        td.appendChild(box);
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    function errorText(e) {
        return e.status === 401
            ? e.message + ' Reload the page to sign in.'
            : e.message;
    }

    // ---- the counts --------------------------------------------------------
    function loadStats() {
        var my = ++statsSeq;
        tableMessage(body, head.children.length || 1, 'Loading the counts…');
        foot.textContent = '';
        tiles.setAttribute('aria-busy', 'true');

        // Active transformers only: removed ones are never counted here.
        getJSON('csc_stats_api.php?active=1')
            .then(function (d) {
                if (my !== statsSeq) { return; }
                data = d;
                tiles.removeAttribute('aria-busy');
                updated.textContent = (d.activeOnly ? 'Active transformers' : 'All transformers, removed included')
                                    + ' · counted ' + d.updated;
                fillFilters();
                render();
                if (selected !== null) { openCsc(selected, true); }
            })
            .catch(function (e) {
                if (my !== statsSeq) { return; }
                tiles.removeAttribute('aria-busy');
                tiles.textContent = '';
                head.textContent  = '';
                tableMessage(body, 1, 'The counts could not be loaded. ' + errorText(e), loadStats);
            });
    }

    /* The area and province lists come from the CSCs themselves, so an area
       with no CSC is never offered, and a new one appears by itself. What
       was chosen stays chosen across a reload. */
    function fillFilters() {
        var areas = {}, provs = {}, provCount = 0;
        for (var i = 0; i < data.rows.length; i++) {
            var r = data.rows[i];
            if (r.areaId !== null)     { areas[r.areaId] = r.area; }
            if (r.provinceId !== null && !provs[r.provinceId]) { provs[r.provinceId] = r.province; provCount++; }
        }
        refill(areaSel, areas, 'All areas');
        refill(provSel, provs, 'All provinces');
        $('cscx-prov-group').hidden = (provCount < 2);
        if (provCount < 2) { provSel.value = ''; }
    }

    function refill(sel, map, allText) {
        var keep = sel.value, ids = Object.keys(map);
        ids.sort(function (a, b) { return String(map[a]).localeCompare(String(map[b])); });
        sel.textContent = '';
        sel.appendChild(new Option(allText, ''));
        for (var i = 0; i < ids.length; i++) { sel.appendChild(new Option(map[ids[i]], ids[i])); }
        sel.value = map.hasOwnProperty(keep) ? keep : '';
    }

    function visibleRows() {
        var a = areaSel.value, p = provSel.value, out = [];
        for (var i = 0; i < data.rows.length; i++) {
            var r = data.rows[i];
            if (a !== '' && String(r.areaId) !== a) { continue; }
            if (p !== '' && String(r.provinceId) !== p) { continue; }
            out.push(r);
        }
        return out;   // already in order: biggest first, "No CSC assigned" last
    }

    /* The table's columns. The office's own types — Distribution, Bulk and
       Bulk & Dist. — each have one; everything else the register holds (the
       imported variants and the blanks) is added together as Other, so a row
       still adds up to its Total. Other only appears while there is some.
       The tiles above and the details panel name every type separately. */
    function columns() {
        var named = [], other = [], otherTotal = 0;
        for (var t = 0; t < data.types.length; t++) {
            if (data.types[t].office) { named.push(t); }
            else { other.push(t); otherTotal += data.types[t].total; }
        }
        return { named: named, other: otherTotal > 0 ? other : [] };
    }

    function sumOf(counts, list) {
        var s = 0;
        for (var i = 0; i < list.length; i++) { s += counts[list[i]]; }
        return s;
    }

    function render() {
        var rows = visibleRows(), types = data.types, nT = types.length, cols = columns();
        var nCols = 1 + cols.named.length + (cols.other.length ? 1 : 0) + 2;

        // Totals of what is on screen — tiles, footer and bars all use these.
        var sums = [], total = 0, max = 0;
        for (var t = 0; t < nT; t++) { sums[t] = 0; }
        for (var i = 0; i < rows.length; i++) {
            for (t = 0; t < nT; t++) { sums[t] += rows[i].counts[t]; }
            total += rows[i].total;
            if (rows[i].total > max) { max = rows[i].total; }
        }

        // ---- tiles ----
        tiles.textContent = '';
        var realCscs = 0;
        for (i = 0; i < rows.length; i++) { if (!rows[i].noCsc) { realCscs++; } }
        tiles.appendChild(tile(null, 'Total transformers', total,
                               realCscs + ' CSC' + (realCscs === 1 ? '' : 's') + ' shown'));
        for (t = 0; t < nT; t++) {
            tiles.appendChild(tile(types[t], typeName(types[t]), sums[t], pct(sums[t], total) + ' of total'));
        }

        // ---- caption: what Other is made of, in words ----
        var otherNames = [];
        for (i = 0; i < cols.other.length; i++) { otherNames.push(typeName(types[cols.other[i]])); }
        caption.textContent = 'Choose a CSC to see its transformers.'
            + (otherNames.length > 1 ? ' Other is ' + otherNames.join(', ') + '.' : '');

        // ---- head ----
        head.textContent = '';
        head.appendChild(el('th', null, 'CSC'));
        for (i = 0; i < cols.named.length; i++) {
            var ty = types[cols.named[i]];
            var th = el('th', 'num cscx-col-type');
            th.title = typeName(ty);
            th.appendChild(dot(ty));
            th.appendChild(document.createTextNode(shortName(ty)));
            head.appendChild(th);
        }
        if (cols.other.length) {
            // Just the blanks, now the Bulk variants count as Bulk: call it that.
            var oth = el('th', 'num cscx-col-type', cols.other.length === 1 ? otherNames[0] : 'Other');
            oth.title = otherNames.join(', ');
            head.appendChild(oth);
        }
        head.appendChild(el('th', 'num', 'Total'));
        head.appendChild(el('th', 'cscx-col-bar', 'Split'));

        // ---- body ----
        body.textContent = '';
        if (rows.length === 0) {
            tableMessage(body, nCols, data.rows.length === 0
                ? 'No CSCs recorded yet.'
                : 'No CSC matches these filters.');
        }
        for (i = 0; i < rows.length; i++) { body.appendChild(cscRow(rows[i], max, cols)); }

        // ---- foot ----
        foot.textContent = '';
        foot.appendChild(el('td', null, 'Total'));
        for (i = 0; i < cols.named.length; i++) { foot.appendChild(el('td', 'num', fmt(sums[cols.named[i]]))); }
        if (cols.other.length) { foot.appendChild(el('td', 'num', fmt(sumOf(sums, cols.other)))); }
        foot.appendChild(el('td', 'num cscx-total', fmt(total)));
        foot.appendChild(el('td'));
    }

    function tile(t, label, n, note) {
        var box = el('div', 'stat cscx-tile' + (t ? ' cscx-tile-type' : ''));
        var lab = el('span', 'stat-label');
        if (t) { lab.appendChild(dot(t)); }
        lab.appendChild(document.createTextNode(label));
        box.appendChild(lab);
        box.appendChild(el('span', 'stat-value', fmt(n)));
        box.appendChild(el('span', 'stat-note', note));
        return box;
    }

    function countCell(n) {
        return el('td', 'num' + (n === 0 ? ' cscx-zero' : ''), fmt(n));
    }

    function cscRow(r, max, cols) {
        var types = data.types;
        var tr = el('tr', r.id === selected ? 'is-selected' : '');
        tr.setAttribute('data-csc', r.id);
        if (r.noCsc || r.deleted) { tr.classList.add('cscx-odd-row'); }

        // A real button, so the keyboard can open a CSC as well as the mouse.
        // The area goes underneath the name rather than in a column of its own.
        var td = el('td', 'cscx-name');
        var b = el('button', 'cscx-open', cscLabel(r));
        b.type = 'button';
        b.setAttribute('aria-expanded', r.id === selected ? 'true' : 'false');
        b.setAttribute('aria-controls', 'cscx-detail');
        td.appendChild(b);
        if (r.area) { td.appendChild(el('span', 'cscx-area', r.area)); }
        tr.appendChild(td);

        for (var i = 0; i < cols.named.length; i++) { tr.appendChild(countCell(r.counts[cols.named[i]])); }
        if (cols.other.length) { tr.appendChild(countCell(sumOf(r.counts, cols.other))); }
        tr.appendChild(el('td', 'num cscx-total', fmt(r.total)));

        // The bar is as long as the CSC is big; inside it, the split by type.
        var bt = el('td', 'cscx-col-bar');
        if (r.total > 0) {
            var bar = el('span', 'cap-bar cscx-bar');
            bar.style.width = (r.total / max * 100) + '%';
            bar.setAttribute('role', 'img');
            var said = [], parts = [];
            for (var t = 0; t < types.length; t++) {
                if (r.counts[t] > 0) { parts.push(t); said.push(typeName(types[t]) + ' ' + fmt(r.counts[t])); }
            }
            for (var j = 0; j < parts.length; j++) {
                var ty  = types[parts[j]];
                var seg = el('span', 'cap-seg ' + colour(ty)
                            + (j === 0 ? ' is-first' : '') + (j === parts.length - 1 ? ' is-last' : ''));
                seg.style.width = (r.counts[parts[j]] / r.total * 100) + '%';
                seg.title = typeName(ty) + ': ' + fmt(r.counts[parts[j]]);
                bar.appendChild(seg);
            }
            bar.setAttribute('aria-label', said.join(', '));
            bt.appendChild(bar);
        } else {
            bt.appendChild(el('span', 'cscx-zero', 'None'));
        }
        tr.appendChild(bt);
        return tr;
    }

    function findRow(id) {
        if (!data) { return null; }
        for (var i = 0; i < data.rows.length; i++) { if (data.rows[i].id === id) { return data.rows[i]; } }
        return null;
    }

    function markSelected() {
        var trs = body.querySelectorAll('tr[data-csc]');
        for (var i = 0; i < trs.length; i++) {
            var on = (Number(trs[i].getAttribute('data-csc')) === selected);
            trs[i].classList.toggle('is-selected', on);
            var b = trs[i].querySelector('.cscx-open');
            if (b) { b.setAttribute('aria-expanded', on ? 'true' : 'false'); }
        }
    }

    // ---- the details panel -------------------------------------------------
    /* refresh is true when the counts have just been reloaded under an open
       panel (the Active switch): the same CSC and tab stay open, but the
       list goes back to page 1 because its length has changed. */
    function openCsc(id, refresh) {
        var r = findRow(id);
        if (!r) { closeCsc(false); return; }

        selected = id;
        markSelected();
        layout.classList.add('is-open');
        detail.hidden = false;

        dName.textContent = cscLabel(r);
        var sub = [];
        if (r.area) { sub.push(r.area + ' Area'); }
        sub.push(fmt(r.total) + ' transformer' + (r.total === 1 ? '' : 's')
                 + (data.activeOnly ? ' (active)' : ' (removed included)'));
        dSub.textContent = sub.join(' · ');

        page = 1;

        if (r.total === 0) {
            dEmpty.hidden = false;
            dBody.hidden  = true;
            tab = null;
        } else {
            dEmpty.hidden = true;
            dBody.hidden  = false;
            drawDonut(r);

            // Keep the open tab if this CSC still has some of that type;
            // otherwise open its biggest type.
            if (!refresh || tab === null || !(r.counts[tab] > 0)) {
                tab = 0;
                for (var t = 0; t < r.counts.length; t++) { if (r.counts[t] > r.counts[tab]) { tab = t; } }
                if (!refresh) { listQ.value = ''; }
            }
            drawTabs(r);
            loadList();
        }

        if (!refresh) {
            dName.focus({ preventScroll: true });
            // Under the table, the panel may be off the bottom of the screen.
            if (getComputedStyle(layout).display !== 'grid') {
                var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                detail.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            }
        }
    }

    function closeCsc(giveFocusBack) {
        var was = selected;
        selected = null;
        tab = null;
        ++listSeq;
        detail.hidden = true;
        layout.classList.remove('is-open');
        markSelected();
        if (giveFocusBack && was !== null) {
            var b = body.querySelector('tr[data-csc="' + was + '"] .cscx-open');
            if (b) { b.focus(); }
        }
    }

    /* The ring: the CSC's types as shares of its total. The list beside it
       carries every name, count and percentage in plain ink — the colour
       only supports it. */
    function drawDonut(r) {
        var types = data.types;
        donut.textContent = '';

        var S = 240, cx = 120, cy = 120, rO = 104, rI = 64;
        var ring = svg('svg', { viewBox: '0 0 ' + S + ' ' + S, role: 'img',
                                'aria-label': 'Transformers in ' + r.name + ' by type' });
        ring.setAttribute('class', 'donut-svg');

        var legend = el('div', 'donut-legend');
        var angle  = -Math.PI / 2;

        for (var t = 0; t < types.length; t++) {
            var n = r.counts[t];
            if (n === 0) { continue; }

            var sweep = n / r.total * Math.PI * 2;
            var name  = typeName(types[t]);

            // One arc cannot draw a whole circle — its two ends are the same
            // point — so a CSC with only one type is drawn as two halves.
            var halves = sweep > Math.PI * 1.999
                ? [[angle, angle + Math.PI], [angle + Math.PI, angle + Math.PI * 2]]
                : [[angle, angle + sweep]];
            var paths = [];
            for (var h = 0; h < halves.length; h++) {
                var p = svg('path', { d: arc(cx, cy, rO, rI, halves[h][0], halves[h][1]),
                                      'class': 'donut-slice ' + colour(types[t]) });
                var tip = svg('title', {});
                tip.textContent = name + ' — ' + fmt(n) + ' (' + pct(n, r.total) + ')';
                p.appendChild(tip);
                ring.appendChild(p);
                paths.push(p);
            }

            var row = el('div', 'legend-row');
            row.appendChild(dot(types[t]));
            row.appendChild(el('span', 'legend-name', name));
            row.appendChild(el('span', 'legend-value', fmt(n)));
            row.appendChild(el('span', 'legend-pct', pct(n, r.total)));
            legend.appendChild(row);

            /* .chart-donut fades every slice while the pointer is over it and
               lets .is-on bring one back, so the slice and its legend line
               light up together — the same as the ring on the home page. */
            (function (ps, lr) {
                function on(v) {
                    for (var i = 0; i < ps.length; i++) { ps[i].classList.toggle('is-on', v); }
                    lr.classList.toggle('is-on', v);
                }
                var all = ps.concat([lr]);
                for (var i = 0; i < all.length; i++) {
                    all[i].addEventListener('mouseenter', function () { on(true); });
                    all[i].addEventListener('mouseleave', function () { on(false); });
                }
            })(paths, row);

            angle += sweep;
        }

        var mid = svg('text', { x: cx, y: cy - 2, 'class': 'donut-total', 'text-anchor': 'middle' });
        mid.textContent = fmt(r.total);
        ring.appendChild(mid);
        var under = svg('text', { x: cx, y: cy + 18, 'class': 'donut-sub', 'text-anchor': 'middle' });
        under.textContent = 'transformers';
        ring.appendChild(under);

        donut.appendChild(ring);
        donut.appendChild(legend);
    }

    function arc(cx, cy, rO, rI, a0, a1) {
        var big = (a1 - a0) > Math.PI ? 1 : 0;
        var x0o = cx + rO * Math.cos(a0), y0o = cy + rO * Math.sin(a0);
        var x1o = cx + rO * Math.cos(a1), y1o = cy + rO * Math.sin(a1);
        var x1i = cx + rI * Math.cos(a1), y1i = cy + rI * Math.sin(a1);
        var x0i = cx + rI * Math.cos(a0), y0i = cy + rI * Math.sin(a0);
        return 'M' + x0o + ' ' + y0o +
               'A' + rO + ' ' + rO + ' 0 ' + big + ' 1 ' + x1o + ' ' + y1o +
               'L' + x1i + ' ' + y1i +
               'A' + rI + ' ' + rI + ' 0 ' + big + ' 0 ' + x0i + ' ' + y0i + 'Z';
    }

    // One tab per type this CSC holds, each with its count: "Bulk (8)".
    function drawTabs(r) {
        var types = data.types;
        tabs.textContent = '';
        for (var t = 0; t < types.length; t++) {
            if (r.counts[t] === 0) { continue; }
            var b = el('button', 'cscx-tab');
            b.type = 'button';
            b.id   = 'cscx-tab-' + t;
            b.setAttribute('role', 'tab');
            b.setAttribute('aria-controls', 'cscx-panel');
            b.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            b.tabIndex = (t === tab) ? 0 : -1;
            b.setAttribute('data-type', t);
            b.appendChild(dot(types[t]));
            b.appendChild(document.createTextNode(typeName(types[t]) + ' (' + fmt(r.counts[t]) + ')'));
            tabs.appendChild(b);
        }
        $('cscx-panel').setAttribute('aria-labelledby', 'cscx-tab-' + tab);
    }

    function chooseTab(t, focus) {
        tab = t;
        page = 1;
        var all = tabs.querySelectorAll('.cscx-tab');
        for (var i = 0; i < all.length; i++) {
            var on = Number(all[i].getAttribute('data-type')) === t;
            all[i].setAttribute('aria-selected', on ? 'true' : 'false');
            all[i].tabIndex = on ? 0 : -1;
            if (on && focus) { all[i].focus(); }
        }
        $('cscx-panel').setAttribute('aria-labelledby', 'cscx-tab-' + t);
        loadList();
    }

    // ---- the list under the tabs -------------------------------------------
    function loadList() {
        var r = findRow(selected);
        if (!r || tab === null) { return; }

        var my = ++listSeq;
        tableMessage(lBody, 5, 'Loading…');
        lCount.textContent = '';
        pager.hidden = true;

        var q = 'csc=' + encodeURIComponent(r.id)
              + '&type=' + encodeURIComponent(data.types[tab].label)
              + '&active=' + (data.activeOnly ? '1' : '0')
              + '&q=' + encodeURIComponent(listQ.value.trim())
              + '&page=' + page;

        getJSON('csc_transformers_api.php?' + q)
            .then(function (d) {
                if (my !== listSeq) { return; }
                drawList(d);
            })
            .catch(function (e) {
                if (my !== listSeq) { return; }
                tableMessage(lBody, 5, 'The list could not be loaded. ' + errorText(e), loadList);
            });
    }

    function drawList(d) {
        page = d.page;
        lBody.textContent = '';

        var searching = listQ.value.trim() !== '';

        if (d.rows.length === 0) {
            tableMessage(lBody, 5, searching ? 'No transformer in this list matches the search.'
                                             : 'No transformers of this type.');
        }

        var dash = '—';
        for (var i = 0; i < d.rows.length; i++) {
            var x  = d.rows[i];
            var tr = el('tr', x.removed ? 'row-inactive' : '');

            var td = el('td', 'code-cell');
            var a  = el('a', null, x.code);
            a.href = 'transformer.php?id=' + encodeURIComponent(x.id);
            td.appendChild(a);
            tr.appendChild(td);

            tr.appendChild(el('td', 'code-cell cscx-serial', x.serial && x.serial.trim() !== '' ? x.serial : dash));
            tr.appendChild(el('td', null, x.name && x.name.trim() !== '' ? x.name : dash));
            tr.appendChild(el('td', 'num', x.kva > 0 ? fmt(x.kva) : dash));

            var st = el('td');
            st.appendChild(x.removed ? el('span', 'badge badge-cancelled', 'Removed') : el('span', null, 'Active'));
            tr.appendChild(st);

            lBody.appendChild(tr);
        }

        // "Showing N of M": M is everything of this type, N what is on screen.
        var shown;
        if (d.pages > 1) {
            var from = (d.page - 1) * d.perPage + 1;
            shown = 'Showing ' + fmt(from) + '–' + fmt(from + d.rows.length - 1)
                  + ' of ' + fmt(d.matched) + (searching ? ' matches (' + fmt(d.total) + ' in all)' : '');
        } else {
            shown = 'Showing ' + fmt(d.matched) + ' of ' + fmt(d.total);
        }
        lCount.textContent = shown;

        pager.hidden = d.pages < 2;
        pageInfo.textContent = 'Page ' + d.page + ' of ' + d.pages;
        prevBtn.disabled = d.page <= 1;
        nextBtn.disabled = d.page >= d.pages;
    }

    // ---- wiring ------------------------------------------------------------
    areaSel.addEventListener('change', function () { if (data) { render(); } });
    provSel.addEventListener('change', function () { if (data) { render(); } });

    // A click anywhere on a CSC's row opens it, not only on its name.
    body.addEventListener('click', function (e) {
        var tr = e.target.closest ? e.target.closest('tr[data-csc]') : null;
        if (!tr) { return; }
        if (window.getSelection && String(window.getSelection()) !== '' && !e.target.closest('button')) { return; }
        var id = Number(tr.getAttribute('data-csc'));
        if (id === selected && e.target.closest('button')) { closeCsc(true); return; }
        openCsc(id, false);
    });

    $('cscx-close').addEventListener('click', function () { closeCsc(true); });

    detail.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeCsc(true); }
    });

    tabs.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('.cscx-tab') : null;
        if (b) { chooseTab(Number(b.getAttribute('data-type')), false); }
    });

    // Arrow keys move along the tabs, the way a tab strip is expected to.
    tabs.addEventListener('keydown', function (e) {
        var all = tabs.querySelectorAll('.cscx-tab');
        if (!all.length) { return; }
        var at = 0;
        for (var i = 0; i < all.length; i++) { if (all[i] === document.activeElement) { at = i; } }
        var to = null;
        if (e.key === 'ArrowRight') { to = (at + 1) % all.length; }
        if (e.key === 'ArrowLeft')  { to = (at - 1 + all.length) % all.length; }
        if (e.key === 'Home')       { to = 0; }
        if (e.key === 'End')        { to = all.length - 1; }
        if (to === null) { return; }
        e.preventDefault();
        chooseTab(Number(all[to].getAttribute('data-type')), true);
    });

    var typing = null;
    listQ.addEventListener('input', function () {
        clearTimeout(typing);
        typing = setTimeout(function () { page = 1; loadList(); }, 250);
    });

    prevBtn.addEventListener('click', function () { if (page > 1) { page--; loadList(); } });
    nextBtn.addEventListener('click', function () { page++; loadList(); });

    loadStats();
})();
</script>
