<?php
include 'auth.php';
include 'db.php';
include_once 'functions.php';   // dashboardStats()

/* The separate Search page is gone — this page is the search now.
   A plain ?q= is still answered here by PHP, so the form below does
   something real with JavaScript switched off, and a link such as
   index.php?q=UBB+105 can still be pasted into an e-mail. */
$keyword = isset($_GET['q']) ? trim($_GET['q']) : "";
$rows    = array();

if ($keyword !== "") {

    /* % and _ are wildcards inside a LIKE pattern. Escaping them first
       means a worker searching for % finds a % and not the whole
       register. The backslash is escaped first, or it would escape
       the escapes added after it. */
    $escaped = str_replace(
        array('\\', '%', '_'),
        array('\\\\', '\\%', '\\_'),
        $keyword
    );
    $like = "%" . $escaped . "%";

    $stmt = $conn->prepare(
        "SELECT t.transformer_id, t.transformer_code, t.substation_name,
                t.capacity_kva, t.transformer_type, t.serial_no,
                csc.csc_name, csc.csc_code, areas.area_name
         FROM transformers t
         LEFT JOIN csc   ON t.csc_id = csc.csc_id
         LEFT JOIN areas ON csc.area_id = areas.area_id
         WHERE " . txActiveSql('t') . "
           AND (t.transformer_code LIKE ?
             OR t.serial_no LIKE ?
             OR t.substation_name LIKE ?
             OR t.details LIKE ?
             OR csc.csc_name LIKE ?
             OR areas.area_name LIKE ?)
         ORDER BY t.transformer_code"
    );
    $stmt->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* The dashboard's first frame. The same function answers stats_api.php,
   which the script at the bottom asks for a fresh copy of every half
   minute, so the page never shows a number the database does not hold. */
$stats = dashboardStats($conn);

$pageTitle = "Home";
$pageDesc  = "Welcome to the CEB Transformer Management System.";
$activeNav = "home";

/* The search box lives in the heading now.

   It was a button up here and the box down in the body, which looked fine
   and did not work: pressing Search filled the results panel, but that
   panel sits under the whole dashboard, so the answer appeared a screen
   and a half below the fold and nothing seemed to happen. Box and answer
   belong together, so both moved to the top. */
$pageSearch = array(
    'action'      => 'index.php',
    'id'          => 'live-form',
    'input_id'    => 'live-q',
    'name'        => 'q',
    'value'       => $keyword,
    'placeholder' => 'Search by S/N, serial no, substation, CSC or area',
);

include 'header.php';
?>

<?php /* The answer sits directly under the box that asked for it, above the
         dashboard. Both are empty until somebody searches, and an empty
         .search-hero-hints is hidden by the stylesheet, so nothing here
         takes up room on a page nobody has searched on. */ ?>
<p class="search-hero-hints" id="live-hint"><?php
if ($keyword !== "") {
    $n = count($rows);
    echo $n . " match" . ($n === 1 ? "" : "es") . ' for "'
       . htmlspecialchars($keyword) . '".';
}
?></p>

<!-- Results live in here. PHP fills it on a plain ?q= search; the script
     below empties it and writes its own rows once you start typing, so
     there is only ever one set of results on screen. -->
<div id="live-results">
<?php
if ($keyword !== "") {

    $dash = "<span class='field-hint'>&mdash;</span>";

    if (count($rows) === 0) {
        echo "<div class='table-empty'>No transformers found for \""
           . htmlspecialchars($keyword) . "\".</div>";
    } else {
?>
    <?php /* The same five columns as the transformer register, in the same
             order, with the same plain-table row layout. A search result and
             a register row are the same thing, so they should not read as
             two different tables — and the CSC, area and details the search
             looks through are all on the transformer's own page, which the
             row opens. */ ?>
    <div class="table-wrap">
        <table class="data plain-table">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Substation Name</th>
                    <th>Serial No</th>
                    <th>Type</th>
                    <th class="num">Capacity</th>
                </tr>
            </thead>
            <tbody>
<?php
        foreach ($rows as $row) {

            $id = (int)$row['transformer_id'];

            // The whole row opens the transformer; footer.php does the work.
            echo "<tr data-href='transformer.php?id=" . $id . "'>";

            echo "<td data-label='S/N' class='code-cell'>"
               . "<a href='transformer.php?id=" . $id . "'>"
               . htmlspecialchars($row['transformer_code']) . "</a></td>";

            echo "<td data-label='Substation Name'>"
               . (trim((string)$row['substation_name']) !== ''
                  ? htmlspecialchars($row['substation_name']) : $dash) . "</td>";

            echo "<td data-label='Serial No' class='code-cell'>"
               . (trim((string)$row['serial_no']) !== ''
                  ? htmlspecialchars($row['serial_no']) : $dash) . "</td>";

            echo "<td data-label='Type'>"
               . (trim((string)$row['transformer_type']) !== ''
                  ? htmlspecialchars($row['transformer_type']) : $dash) . "</td>";

            echo "<td data-label='Capacity' class='num'>"
               . ($row['capacity_kva'] ? $row['capacity_kva'] . " kVA" : $dash) . "</td>";

            echo "</tr>";
        }
?>
            </tbody>
        </table>
    </div>
<?php
    }
}
?>
</div>


<?php /* =====================================================================
         The dashboard.

         The map, the ring and the work-type list. Everything here is drawn
         from dashboardStats(), and the script at the bottom asks the same
         function for a fresh set every half minute so the figures follow
         the register without anybody pressing reload.
         ===================================================================== */ ?>

<?php /* The four count tiles that used to stand here are gone. The numbers
         they carried are still one click away in the sidebar, and the ring
         already says how many transformers there are. */ ?>

<div class="chart-pair">

    <?php /* The office's own Area & CSC map. The PDF draws each area as a
             mesh of thousands of small triangles, so there was no polygon to
             lift out — the areas were separated by their fill colour, painted
             into a grid, and the edge of each grid traced back into an
             outline. Every traced outline encloses the same area its mesh
             covered, to within a rounding error.

             These are the CEB's real boundaries, not a guess at them. */ ?>
    <div class="panel">
        <div class="panel-head">
            <h2>Uva Province</h2>
            <span class="panel-note">The five CEB areas</span>
        </div>
        <div class="panel-body">
            <div class="chart-map" id="chart-map">
                <p class="field-hint" id="map-fallback">Loading the map&hellip;</p>
            </div>
            <p class="field-hint map-note">
                Boundaries from the office&rsquo;s Uva Province Area &amp; CSC map.
                Each area is the same colour here as in the ring beside it.
            </p>
        </div>
    </div>

    <?php /* Five areas is a share the eye can read at a glance, so this one
             is the ring. The legend carries the names and the numbers, which
             is also what keeps it readable for anyone who cannot separate
             the colours — nothing here is said by colour alone. */ ?>
    <div class="panel">
        <div class="panel-head">
            <h2>Transformers by area</h2>
            <span class="panel-note">Share of the province</span>
        </div>
        <div class="panel-body">

            <div class="chart-donut" id="chart-areas"></div>

            <?php /* The ring is as tall as it needs to be and no taller, which
                     left the panel half empty beside the map. The work still
                     outstanding goes here: one hub, one circle per work type,
                     each carrying how many events it holds and how many of
                     them are still to do. Drawn by the script below, from the
                     same figures the rest of the dashboard uses. */ ?>
            <div class="mini-chart">
                <h3 class="mini-title">Work types</h3>

                <?php /* A plain list with a bulb beside each name, and how
                         many events are booked against it.

                         The count is every event of that type, done or not.
                         Which are still outstanding is the Events register's
                         question — it has a status filter that answers it
                         properly, and a half-version of that here only
                         invited the two to disagree. */ ?>
                <ul class="type-list">
                <?php foreach ($stats['types'] as $i => $t) { ?>
                    <?php /* A type nothing is booked against is dimmed rather
                             than hidden: it is still one of the fourteen the
                             form offers, and a nought is a real answer. */
                           $used = ($t['n'] > 0); ?>
                    <li class="type-item<?php echo $used ? '' : ' is-idle'; ?>"
                        title="<?php echo htmlspecialchars($t['label'] . ': '
                               . ($used ? number_format($t['n'])
                                          . ' event' . ($t['n'] === 1 ? '' : 's')
                                        : 'nothing booked yet')); ?>">
                        <svg class="type-bulb" viewBox="0 0 16 20" width="15" height="19"
                             aria-hidden="true">
                            <circle class="bulb-glass slot-<?php echo ($i % 8) + 1; ?>"
                                    cx="8" cy="7.5" r="6" />
                            <rect class="bulb-cap" x="5" y="13.5" width="6" height="5" rx="1.5" />
                        </svg>
                        <span class="type-name"><?php
                            echo htmlspecialchars($t['label']); ?></span>
                        <span class="type-count"><?php
                            echo number_format($t['n']); ?></span>
                    </li>
                <?php } ?>
                </ul>
            </div>
        </div>
    </div>

</div>




<script>
(function () {

    var box     = document.getElementById('live-q');
    var form    = document.getElementById('live-form');
    var hint    = document.getElementById('live-hint');
    var results = document.getElementById('live-results');

    // If anything is missing, leave the plain form alone and do nothing.
    if (!box || !form || !results) { return; }

    // One character is enough. A leading-wildcard LIKE reads the whole table
    // whatever the length, so on this register a single letter costs about
    // the same as a whole word — roughly 3 milliseconds. Keep this the same
    // number as the check in search_api.php.
    var MIN_CHARS = 1;
    var SHORT_MSG = (MIN_CHARS === 1)
        ? 'Type a letter or a number to search.'
        : 'Type at least ' + MIN_CHARS + ' characters.';

    var DELAY     = 250;   // milliseconds to wait after the last keystroke

    var timer   = null;
    var lastReq = 0;       // counts requests, so a slow reply cannot
    var shownReq = 0;      // overwrite a newer one
    var broken  = false;   // set if the live search cannot be reached, so
                           // Enter goes back to submitting the form

    // The Search button now sits in the top right corner beside the light
    // and dark switch, outside the form, tied to it by its form attribute.
    // Results appear as you type, so it is not needed to run a search — it
    // is there for the keyboard, and for JavaScript being switched off.

    function clearResults(message) {
        results.textContent = '';
        if (hint) { hint.textContent = message || ''; }
    }

    function cell(row, label, text, cls) {
        var td = document.createElement('td');
        td.setAttribute('data-label', label);
        if (cls) { td.className = cls; }
        // textContent, never innerHTML: a Details field holding < or >
        // is then shown as typed and can never become markup.
        td.textContent = (text === null || text === '') ? '—' : text;
        row.appendChild(td);
        return td;
    }

    function render(data) {

        results.textContent = '';

        if (data.error) {
            var err = document.createElement('div');
            err.className = 'message message-error';
            err.textContent = data.error;
            results.appendChild(err);
            if (hint) { hint.textContent = ''; }
            return;
        }

        if (!data.rows || data.rows.length === 0) {
            var none = document.createElement('div');
            none.className = 'table-empty';
            none.textContent = 'No transformers found for "' + box.value.trim() + '".';
            results.appendChild(none);
            if (hint) { hint.textContent = ''; }
            return;
        }

        if (hint) {
            hint.textContent = (data.total > data.shown)
                ? 'Showing ' + data.shown + ' of ' + data.total + ' matches — refine the search to narrow it down.'
                : data.total + ' match' + (data.total === 1 ? '' : 'es') + '.';
        }

        var wrap = document.createElement('div');
        wrap.className = 'table-wrap';

        var table = document.createElement('table');
        // plain-table, and the same five columns PHP prints above, so the
        // list does not change shape the moment you start typing.
        table.className = 'data plain-table';

        var thead = document.createElement('thead');
        var hrow  = document.createElement('tr');
        var heads = ['S/N', 'Substation Name', 'Serial No', 'Type', 'Capacity'];

        for (var h = 0; h < heads.length; h++) {
            var th = document.createElement('th');
            th.textContent = heads[h];
            if (heads[h] === 'Capacity') { th.className = 'num'; }
            hrow.appendChild(th);
        }
        thead.appendChild(hrow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');

        for (var i = 0; i < data.rows.length; i++) {

            var r  = data.rows[i];
            var tr = document.createElement('tr');

            /* The whole row opens the transformer. The handler in footer.php
               listens on the document, so a row written here after the page
               has loaded works exactly like one PHP printed. */
            tr.setAttribute('data-href', 'transformer.php?id=' + r.id);

            // S/N is the link through to the full record.
            var td = document.createElement('td');
            td.setAttribute('data-label', 'S/N');
            td.className = 'code-cell';
            var a = document.createElement('a');
            a.href = 'transformer.php?id=' + r.id;
            a.textContent = r.code;
            td.appendChild(a);
            tr.appendChild(td);

            cell(tr, 'Substation Name', r.name);
            cell(tr, 'Serial No', r.serial, 'code-cell');
            cell(tr, 'Type', r.txtype);
            cell(tr, 'Capacity', r.kva ? r.kva + ' kVA' : '', 'num');

            tbody.appendChild(tr);
        }

        table.appendChild(tbody);
        wrap.appendChild(table);
        results.appendChild(wrap);
    }

    function search() {

        var q = box.value.trim();

        if (q.length < MIN_CHARS) {
            clearResults(SHORT_MSG);
            return;
        }

        lastReq++;
        var myReq = lastReq;

        if (hint) { hint.textContent = 'Searching…'; }

        fetch('search_api.php?q=' + encodeURIComponent(q), {
            credentials: 'same-origin'
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            // A slow reply for an older keystroke must not replace a
            // newer one that has already been drawn.
            if (myReq < shownReq) { return; }
            shownReq = myReq;
            render(data);
        })
        .catch(function () {
            if (myReq < shownReq) { return; }
            shownReq = myReq;
            broken = true;
            results.textContent = '';
            var err = document.createElement('div');
            err.className = 'message message-error';
            err.textContent = 'The search could not be reached. Check your connection, then press Enter to search the ordinary way.';
            results.appendChild(err);
            if (hint) { hint.textContent = ''; }
        });
    }

    // Restart the timer on every keystroke, so one search runs after the
    // typing stops rather than one search per letter.
    box.addEventListener('input', function () {
        if (timer) { clearTimeout(timer); }
        timer = setTimeout(search, DELAY);
    });

    // Enter must not reload the page while the live search is working.
    // If it has stopped working, let the form submit normally instead —
    // PHP at the top of this page answers ?q= on its own.
    form.addEventListener('submit', function (e) {
        if (broken) { return; }
        e.preventDefault();
        if (timer) { clearTimeout(timer); }
        search();
    });

    box.focus();

})();
</script>


<script>
/* ---- the dashboard ------------------------------------------------------

   Draws the Uva map and the transformers-by-area ring, and keeps their
   figures current: it redraws them in place when a fresh set of numbers
   arrives, so nothing flickers and nothing is drawn twice.

   If this script does not run, the page keeps the tables PHP wrote and
   the dashboard is still readable — just not a picture. */
(function () {

    var REFRESH = 30000;                      // how often to ask, in ms
    var data    = <?php echo json_encode($stats, JSON_HEX_TAG | JSON_HEX_AMP); ?>;

    // Only the map and the area ring are drawn here now; the CSC bars and
    // the month chart were taken off the home page.
    if (!document.getElementById('chart-areas')) { return; }

    var SVG = 'http://www.w3.org/2000/svg';

    function node(name, attrs) {
        var n = document.createElementNS(SVG, name);
        for (var k in attrs) { n.setAttribute(k, attrs[k]); }
        return n;
    }

    // ---- the map ---------------------------------------------------------
    /* Longitude degrees get narrower towards the poles, so a plain lon/lat
       plot would stretch the island sideways. Multiplying longitude by the
       cosine of the middle latitude keeps the shape honest, which at 7°N is
       a correction of about 1%. Small, but it costs one line. */
    var mapData = null;   // national outlines, for the locator only
    var areaMap = null;   // the office Area & CSC map, traced to outlines

    function projector(bbox, w, h, pad) {
        var lon0 = bbox[0], lon1 = bbox[1], lat0 = bbox[2], lat1 = bbox[3];
        var k    = Math.cos((lat0 + lat1) / 2 * Math.PI / 180);
        var dx   = (lon1 - lon0) * k, dy = (lat1 - lat0);
        var s    = Math.min((w - pad * 2) / dx, (h - pad * 2) / dy);
        var ox   = (w - dx * s) / 2, oy = (h - dy * s) / 2;
        return function (lon, lat) {
            return [ox + (lon - lon0) * k * s, oy + (lat1 - lat) * s];
        };
    }

    function ringPath(ring, p) {
        var d = '';
        for (var i = 0; i < ring.length; i++) {
            var q = p(ring[i][0], ring[i][1]);
            d += (i ? 'L' : 'M') + q[0].toFixed(1) + ' ' + q[1].toFixed(1);
        }
        return d + 'Z';
    }

    function drawMap(areas) {

        var host = document.getElementById('chart-map');
        if (!host || !areaMap) { return; }

        var counts = {}, total = 0;
        for (var a = 0; a < areas.length; a++) {
            counts[areas[a].label] = areas[a].n;
            total += areas[a].n;
        }

        host.textContent = '';

        var VW = areaMap.viewBox[0], VH = areaMap.viewBox[1];

        var svg = node('svg', {
            viewBox: '0 0 ' + VW + ' ' + VH,
            role: 'img',
            'aria-label': 'Map of Uva Province divided into the five CEB areas'
        });
        svg.classList.add('map-svg');

        var tip = document.createElement('div');
        tip.className = 'chart-tip';
        tip.hidden = true;

        /* The shapes drawn are the CSCs, not the areas: each one wears its
           area's colour, so the five areas still read as five blocks, and
           the gap between CSCs is the office map's own dividing line. The
           area names are written over the top, and a CSC gives its own name
           and count on hover. */
        var cscCounts = {};
        for (var c = 0; c < (data.csc || []).length; c++) {
            cscCounts[data.csc[c].label] = data.csc[c].n;
        }

        for (var s = 0; s < (areaMap.csc || []).length; s++) {

            var cs   = areaMap.csc[s];
            var cn   = cscCounts[cs.name] || 0;
            var cg   = node('g', { class: 'map-csc' });

            for (var q = 0; q < cs.paths.length; q++) {
                cg.appendChild(node('path', {
                    d: cs.paths[q],
                    class: 'csc-shape donut-' + slotFor(cs.area, 0) }));
            }

            (function (nm, num, areaName) {
                cg.addEventListener('mouseenter', function (e) {
                    tip.textContent = nm + ' CSC — ' + num.toLocaleString()
                                    + ' transformers (' + areaName + ' area)';
                    tip.hidden = false;
                    var b = host.getBoundingClientRect();
                    tip.style.left = (e.clientX - b.left) + 'px';
                    tip.style.top  = (e.clientY - b.top) + 'px';
                });
                cg.addEventListener('mousemove', function (e) {
                    var b = host.getBoundingClientRect();
                    tip.style.left = (e.clientX - b.left) + 'px';
                    tip.style.top  = (e.clientY - b.top) + 'px';
                });
                cg.addEventListener('mouseleave', function () { tip.hidden = true; });
            })(cs.name, cn, cs.area);

            svg.appendChild(cg);
        }

        for (var i = 0; i < areaMap.areas.length; i++) {

            var ar   = areaMap.areas[i];
            var n    = counts[ar.name] || 0;
            var pct  = total > 0 ? (n / total * 100) : 0;
            var slot = slotFor(ar.name, i);

            var g = node('g', { class: 'map-area' });

            /* No fill when the CSCs are drawn — they already cover the area,
               and a second fill on top would hide their dividing lines. */
            if (!areaMap.csc || !areaMap.csc.length) {
                for (var p = 0; p < ar.paths.length; p++) {
                    g.appendChild(node('path', {
                        d: ar.paths[p], class: 'area-shape donut-' + slot }));
                }
            }

            /* Name above, count below, both anchored inside the shape. The
               name is what identifies the area; the colour only supports it,
               which is what keeps the map readable for anyone who cannot
               separate the five hues. */
            var t1 = node('text', {
                x: ar.cx, y: ar.cy - 6, class: 'area-name', 'text-anchor': 'middle' });
            t1.textContent = ar.name;
            g.appendChild(t1);

            var t2 = node('text', {
                x: ar.cx, y: ar.cy + 26, class: 'area-count', 'text-anchor': 'middle' });
            t2.textContent = n.toLocaleString();
            g.appendChild(t2);

            (function (name, num, share) {
                g.addEventListener('mouseenter', function (e) {
                    tip.textContent = name + ' — ' + num.toLocaleString()
                                    + ' transformers (' + share.toFixed(1) + '%)';
                    tip.hidden = false;
                    var b = host.getBoundingClientRect();
                    tip.style.left = (e.clientX - b.left) + 'px';
                    tip.style.top  = (e.clientY - b.top) + 'px';
                });
                g.addEventListener('mousemove', function (e) {
                    var b = host.getBoundingClientRect();
                    tip.style.left = (e.clientX - b.left) + 'px';
                    tip.style.top  = (e.clientY - b.top) + 'px';
                });
                g.addEventListener('mouseleave', function () { tip.hidden = true; });
            })(ar.name, n, pct);

            svg.appendChild(g);
        }

        host.appendChild(svg);

        /* A locator, so somebody who does not already know the province can
           place it. Drawn from the national outlines, which is the only job
           that file still does. */
        if (mapData) {
            var iW = 78, iH = 104;
            var ip = projector(mapData.bbox, iW, iH, 4);
            var inset = node('svg', { viewBox: '0 0 ' + iW + ' ' + iH, 'aria-hidden': 'true' });
            inset.classList.add('map-inset');

            for (var t = 0; t < mapData.provinces.length; t++) {
                var pv = mapData.provinces[t];
                for (var u = 0; u < pv.rings.length; u++) {
                    inset.appendChild(node('path', {
                        d: ringPath(pv.rings[u], ip),
                        class: pv.uva ? 'inset-uva' : 'inset-other' }));
                }
            }
            host.appendChild(inset);
        }

        host.appendChild(tip);
    }

    // ---- the area ring ---------------------------------------------------
    /* Slot order is fixed, never cycled: a colour belongs to the area that
       holds it, so a refresh that reorders the areas does not repaint them
       into each other's colours. */
    /* Fixed by name, never by rank. If it were assigned by position in a
       sorted list, the day Monaragala overtook Wellawaya the two would
       swap colours, and anyone who had learned "Wellawaya is the orange
       one" would be reading the wrong slice. The map and the ring share
       this table, so an area is the same colour in both. */
    var SLOTS = {
        'Badulla':       's1',
        'Diyathalawa':   's2',
        'Monaragala':    's3',
        'Wellawaya':     's4',
        'Mahiyanganaya': 's5'
    };

    function slotFor(name, i) {
        return SLOTS[name] || ('s' + ((i % 5) + 1));
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

    function drawDonut(id, rows, unit) {

        var host = document.getElementById(id);
        if (!host) { return; }

        var total = 0;
        for (var i = 0; i < rows.length; i++) { total += rows[i].n; }
        if (total < 1) { return; }

        host.textContent = '';

        var S = 240, cx = 120, cy = 120, rO = 104, rI = 64;

        var svg = node('svg', {
            viewBox: '0 0 ' + S + ' ' + S,
            role: 'img',
            'aria-label': 'Transformers by area, as a share of the province'
        });
        svg.classList.add('donut-svg');

        var legend = document.createElement('div');
        legend.className = 'donut-legend';

        var angle = -Math.PI / 2;      // start at twelve o'clock
        var paths = [];

        for (var j = 0; j < rows.length; j++) {

            var sweep = (rows[j].n / total) * Math.PI * 2;
            var slot  = slotFor(rows[j].label, j);
            var pct   = (rows[j].n / total * 100);

            var p = node('path', {
                d: arc(cx, cy, rO, rI, angle, angle + sweep),
                class: 'donut-slice donut-' + slot
            });
            svg.appendChild(p);
            paths.push(p);

            // The legend is the identity channel; the colour only supports it.
            var row = document.createElement('div');
            row.className = 'legend-row';
            row.innerHTML =
                '<span class="legend-dot donut-' + slot + '"></span>' +
                '<span class="legend-name"></span>' +
                '<span class="legend-value"></span>' +
                '<span class="legend-pct"></span>';
            row.querySelector('.legend-name').textContent  = rows[j].label;
            row.querySelector('.legend-value').textContent = rows[j].n.toLocaleString();
            row.querySelector('.legend-pct').textContent   = pct.toFixed(1) + '%';

            (function (path, legendRow, r, pc) {
                function on()  { path.classList.add('is-on');    legendRow.classList.add('is-on'); }
                function off() { path.classList.remove('is-on'); legendRow.classList.remove('is-on'); }
                path.addEventListener('mouseenter', on);
                path.addEventListener('mouseleave', off);
                legendRow.addEventListener('mouseenter', on);
                legendRow.addEventListener('mouseleave', off);
                path.setAttribute('tabindex', '-1');
                var t = node('title');
                t.textContent = r.label + ' — ' + r.n.toLocaleString() + ' ' + unit
                              + ' (' + pc.toFixed(1) + '%)';
                path.appendChild(t);
            })(p, row, rows[j], pct);

            legend.appendChild(row);
            angle += sweep;
        }

        // The hole is not empty space — it holds the number the ring adds up to.
        var mid = node('text', { x: cx, y: cy - 2, class: 'donut-total', 'text-anchor': 'middle' });
        mid.textContent = total.toLocaleString();
        svg.appendChild(mid);

        var sub = node('text', { x: cx, y: cy + 18, class: 'donut-sub', 'text-anchor': 'middle' });
        sub.textContent = unit;
        svg.appendChild(sub);

        host.appendChild(svg);
        host.appendChild(legend);
    }

    // ---- put a whole set of numbers on screen ----------------------------
    function render(d) {

        data = d;

        drawDonut('chart-areas', d.areas, 'transformers');
        drawMap(d.areas);
    }

    render(data);

    /* The outlines are a separate file so the browser caches them once and
       the page itself stays small. They never change, so one fetch does for
       the life of the tab; only the counts are asked for again.

       If it cannot be loaded the rest of the dashboard is untouched — the
       map panel says so and the ring beside it still has every number. */
    /* Two files: the office's own area boundaries, which are the map, and
       the national outlines, which are only the little locator. The area
       map is what matters, so the drawing waits for that one; the locator
       is added if and when it turns up. */
    fetch('uva-areas.json')
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (m) {
            if (!m || !m.areas) { throw new Error('no area map'); }
            areaMap = m;
            drawMap(data.areas);
        })
        .catch(function () {
            var f = document.getElementById('map-fallback');
            if (f) { f.textContent = 'The map could not be loaded. The figures beside it are unaffected.'; }
        });

    fetch('uva-map.json')
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (m) {
            if (!m || !m.provinces) { return; }
            mapData = m;
            if (areaMap) { drawMap(data.areas); }
        })
        .catch(function () { /* the locator is a nicety, not the map */ });

    // ---- keep it live ----------------------------------------------------
    /* A failed refresh is left alone on purpose: the figures already on
       screen are real, they are just a minute older, and replacing them
       with an error would be worse. The "updated" time stops moving, which
       is the honest signal that something is wrong. */
    function refresh() {
        fetch('stats_api.php', { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) { if (d && d.areas) { render(d); } })
            .catch(function () { /* keep what is on screen */ });
    }

    // Asking every half minute while nobody is looking is wasted work, so
    // the timer only runs while the tab is actually on screen.
    var timer = setInterval(refresh, REFRESH);

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearInterval(timer);
        } else {
            refresh();
            timer = setInterval(refresh, REFRESH);
        }
    });

})();
</script>

<?php include 'footer.php'; ?>
