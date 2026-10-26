<?php
include 'auth.php';
include 'db.php';
include_once 'functions.php';

$pageTitle = "Events";
$activeNav = "tasks";

// Which status is being shown? 0 means all of them.
$filterStatus = isset($_GET['status']) ? (int)$_GET['status'] : 0;

/* ---- Live search --------------------------------------------------------
   One box, matched against the S/N, the Proposal No and the work type.

   Every event is still printed; the ones that do not match are printed
   with the hidden attribute. That way the script at the foot of the page
   can bring them back the moment a letter is deleted, without asking the
   server again, and with JavaScript off the box still works as a plain
   form — the page simply reloads with the same rows hidden.

   PHP and the script must compare the same way: lower case, spaces
   removed, so "ubb001" finds UBB 001 and "pl/26" finds PL/26/0084. */
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

function evSearchText($s) {
    return preg_replace('/\s+/', '', mb_strtolower((string)$s, 'UTF-8'));
}
$needle = evSearchText($q);

$pageSearch = array(
    'action'      => 'tasks.php',
    'id'          => 'ev-search',
    'input_id'    => 'ev-q',
    'name'        => 'q',
    'value'       => $q,
    'placeholder' => 'Search by S/N, Proposal No or work type',
    'hidden'      => $filterStatus > 0 ? array('status' => $filterStatus) : array(),
    'wide'        => true,     // the full width of the heading, up to "Events"
);

include 'header.php';

// Counts for the summary tiles. These always show the full totals,
// so a worker can see every status even while one is filtered.
$counts = array();
$res = $conn->query("SELECT s.status_id, s.status_name, COUNT(t.task_id) AS n
                     FROM statuses s
                     LEFT JOIN tasks t ON t.status_id = s.status_id AND t.is_deleted = 0
                     GROUP BY s.status_id, s.status_name
                     ORDER BY s.status_id");
while ($r = $res->fetch_assoc()) {
    $counts[] = $r;
}
?>

<div class="stats">
<?php foreach ($counts as $c) {
        $sid    = (int)$c['status_id'];
        $active = ($filterStatus === $sid) ? ' stat-active' : ''; ?>
    <a class="stat stat-link<?php echo $active; ?>" data-status="<?php echo $sid; ?>"
       href="tasks.php?status=<?php echo $sid; ?><?php
           echo $q !== '' ? '&amp;q=' . urlencode($q) : ''; ?>">
        <span class="stat-label"><?php echo htmlspecialchars($c['status_name']); ?></span>
        <span class="stat-value"><?php echo $c['n']; ?></span>
    </a>
<?php } ?>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Event Register</h2>
        <span class="panel-note">
            <span id="ev-count"></span>
            <?php if ($filterStatus > 0) { ?>
                <a href="tasks.php<?php echo $q !== '' ? '?q=' . urlencode($q) : ''; ?>"
                   class="action-link" id="ev-all">Show all events</a>
            <?php } else { ?>
                Newest first
            <?php } ?>
        </span>
    </div>

    <?php /* plain-table keeps the classic row layout, one line per event,
             the same as the transformer register. Everything not listed here
             — status, who it is assigned to, CSC, description, cost, the
             date — is on the event's own page, which the row opens.

             The status tiles above still filter the list, and an event with
             nobody on it still tints the row amber, so neither of those two
             columns took its meaning away with it. */ ?>
    <div class="table-wrap">
        <table class="data plain-table">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Work Type</th>
                    <th>Proposal No</th>
                    <th>Capacity Change</th>
                    <th>Work Details</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $where = "WHERE t.is_deleted = 0";
            if ($filterStatus > 0) { $where .= " AND t.status_id = ?"; }

            /* date_created is still what the register is sorted by, it is
               just no longer a column on screen. */
            $sql = "SELECT t.task_id, t.request_id,
                           t.capacity_before, t.capacity_after,
                           tx.transformer_code,
                           tt.type_name, tt.type_code,
                           (SELECT GROUP_CONCAT(st.subtype_name
                                                ORDER BY st.subtype_id SEPARATOR ', ')
                              FROM task_subtype_links l
                              JOIN task_subtypes st ON l.subtype_id = st.subtype_id
                             WHERE l.task_id = t.task_id) AS subtype_list
                    FROM tasks t
                    LEFT JOIN transformers tx ON t.transformer_id = tx.transformer_id
                    LEFT JOIN task_types tt  ON t.type_id = tt.type_id
                    $where
                    ORDER BY t.date_created DESC";

            $stmt = $conn->prepare($sql);
            if ($filterStatus > 0) { $stmt->bind_param("i", $filterStatus); }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Cable counts for every event on show, in one query rather than
            // one per row.
            $ids = array();
            foreach ($rows as $r) { $ids[] = (int)$r['task_id']; }
            $cableTotals = abcGridTotals($conn, $ids);

            if (count($rows) == 0) {
                echo "<tr><td colspan='5'><div class='table-empty'>No events to show.</div></td></tr>";
            }

            $shown = 0;

            foreach ($rows as $row) {

                $id   = $row['task_id'];
                $dash = "<span class='field-hint'>&mdash;</span>";

                // What the search box is matched against, in the same form
                // the script uses: S/N, Proposal No, work type name and code.
                $hay = evSearchText($row['transformer_code']) . '|'
                     . evSearchText($row['request_id'])       . '|'
                     . evSearchText($row['type_name'])        . '|'
                     . evSearchText($row['type_code']);
                $match = ($needle === '' || strpos($hay, $needle) !== false);
                if ($match) { $shown++; }

                /* The whole row opens the event. data-href is picked up by
                   the handler in footer.php; the S/N below is still a real
                   link, so the keyboard and "open in new tab" keep working
                   and the row is not a button only a mouse can press.

                   Unassigned rows used to be tinted amber. The tint is a
                   solid colour, so it painted over the page's background,
                   and with 438 of 440 events unassigned it covered nearly
                   the whole register while marking almost nothing. The
                   column it belonged to is gone too, so the tint went with
                   it and every row is now the same translucent glass. */
                echo "<tr data-href='event.php?id=$id' class='ev-row'"
                   . " data-search='" . htmlspecialchars($hay, ENT_QUOTES) . "'"
                   . ($match ? "" : " hidden") . ">";

                /* The S/N comes first because the first cell is the tile's
                   title. It used to be the proposal number, but new jobs no
                   longer get one, and every event has a transformer. */
                echo "<td data-label='S/N' class='code-cell'>"
                   . "<a href='event.php?id=$id'>"
                   . htmlspecialchars($row['transformer_code']) . "</a></td>";

                echo "<td data-label='Work Type'>"
                   . (trim((string)$row['type_name']) !== ''
                      ? htmlspecialchars($row['type_name']) : $dash) . "</td>";

                echo "<td data-label='Proposal No' class='code-cell'>"
                   . (trim((string)$row['request_id']) !== ''
                      ? htmlspecialchars($row['request_id']) : $dash) . "</td>";

                echo "<td data-label='Capacity Change' class='cap-change'>";
                if ($row['capacity_after']) {
                    echo $row['capacity_before']
                       . " <span class='cap-arrow'>&rarr;</span> "
                       . "<span class='cap-new'>" . $row['capacity_after'] . " kVA</span>";
                } else {
                    echo $row['capacity_before'] ? $row['capacity_before'] . " kVA" : $dash;
                }
                echo "</td>";

                // ABC Conversion has no tick-box options; its work detail is
                // the cable grid, so the count stands here instead of a dash.
                $detail = $row['subtype_list'] ? htmlspecialchars($row['subtype_list']) : '';
                if (isset($cableTotals[$id])) {
                    $cables = abcNum($cableTotals[$id]) . ' km';
                    $detail = $detail ? $detail . ' &middot; ' . $cables : $cables;
                }

                echo "<td data-label='Work Details'"
                   . ($row['subtype_list'] ? " title='" . htmlspecialchars($row['subtype_list']) . "'" : "")
                   . ">" . ($detail !== '' ? $detail : $dash) . "</td>";

                echo "</tr>";
            }

            // Shown when the search matches nothing; the script toggles it.
            if (count($rows) > 0) {
                echo "<tr id='ev-none'" . ($shown === 0 ? "" : " hidden") . ">"
                   . "<td colspan='5'><div class='table-empty'>No events match your search.</div></td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var box   = document.getElementById('ev-q');
    var rows  = document.querySelectorAll('tr.ev-row');
    var none  = document.getElementById('ev-none');
    var count = document.getElementById('ev-count');
    if (!box || !rows.length) { return; }

    // Must match evSearchText() in PHP: lower case, no spaces.
    function norm(s) { return String(s).toLowerCase().replace(/\s+/g, ''); }

    function apply() {
        var needle = norm(box.value), shown = 0;

        for (var i = 0; i < rows.length; i++) {
            var hit = (needle === '' || rows[i].getAttribute('data-search').indexOf(needle) !== -1);
            rows[i].hidden = !hit;
            if (hit) { shown++; }
        }

        if (none)  { none.hidden = (shown !== 0); }
        if (count) {
            count.textContent = (needle === '') ? '' : shown + ' of ' + rows.length + ' events — ';
        }

        /* Keep the address, the status tiles and "Show all events" in step
           with the box, so a reload, a shared link or a tile click keeps
           the search instead of losing it. */
        var q = box.value.trim();
        if (window.history && history.replaceState) {
            var url = new URL(window.location.href);
            if (q) { url.searchParams.set('q', q); } else { url.searchParams.delete('q'); }
            history.replaceState(null, '', url);
        }
        var links = document.querySelectorAll('a.stat-link[data-status], #ev-all');
        for (var j = 0; j < links.length; j++) {
            var u = new URL(links[j].href);
            if (q) { u.searchParams.set('q', q); } else { u.searchParams.delete('q'); }
            links[j].href = u;
        }
    }

    box.addEventListener('input', apply);

    // Enter would reload the page for the same answer; the list is already filtered.
    document.getElementById('ev-search').addEventListener('submit', function (e) {
        e.preventDefault(); apply();
    });

    apply();
})();
</script>

<?php include 'footer.php'; ?>