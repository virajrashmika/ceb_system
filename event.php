<?php
/* ---------------------------------------------------------------------------
   event.php — one event, read only.

   Until now a single event could only be seen by opening the edit form, which
   meant anyone wanting to look something up had to open a page that can change
   it. This page shows everything about one event and changes nothing.

   Anyone signed in may read it. The Edit and Delete links are shown only to
   the roles that require_staff.php lets through, so a worker sees the record
   without being offered buttons that would refuse them.
   --------------------------------------------------------------------------- */
include 'auth.php';
include 'db.php';
include_once 'functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare(
    "SELECT t.*,
            tx.transformer_id, tx.transformer_code, tx.substation_name,
            (NOT " . txActiveSql('tx') . ") AS tx_removed,
            csc.csc_name, csc.csc_code,
            areas.area_name,
            ty.type_name, ty.type_code, ty.description AS type_desc, ty.is_active AS type_active,
            s.status_name, s.description AS status_desc,
            au.full_name AS assigned_name,
            cu.full_name AS created_name,
            (SELECT GROUP_CONCAT(st.subtype_name
                                 ORDER BY st.subtype_id SEPARATOR ', ')
               FROM task_subtype_links l
               JOIN task_subtypes st ON l.subtype_id = st.subtype_id
              WHERE l.task_id = t.task_id) AS subtype_list
     FROM tasks t
     LEFT JOIN transformers tx ON t.transformer_id = tx.transformer_id
     LEFT JOIN csc             ON tx.csc_id = csc.csc_id
     LEFT JOIN areas           ON csc.area_id = areas.area_id
     LEFT JOIN task_types ty   ON t.type_id = ty.type_id
     LEFT JOIN statuses s      ON t.status_id = s.status_id
     LEFT JOIN users au        ON t.assigned_to = au.user_id
     LEFT JOIN users cu        ON t.created_by = cu.user_id
     WHERE t.task_id = ? AND t.is_deleted = 0"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$ev = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ev) {
    $pageTitle = "Event";
    $activeNav = "tasks";
    include 'header.php';
    echo "<div class='message message-error'>Event not found. It may have been deleted.</div>";
    echo "<p><a href='tasks.php' class='btn'>Back to events</a></p>";
    include 'footer.php';
    exit;
}

// Which extra fields does this event's work type ask for? A field is shown
// when the type asks for it, and also when it holds a value anyway — an old
// record whose type has since changed must not hide what it still carries.
$ticked = array();
$tk = $conn->prepare("SELECT subtype_id FROM task_subtype_links WHERE task_id = ?");
$tk->bind_param("i", $id);
$tk->execute();
$tkRes = $tk->get_result();
while ($r = $tkRes->fetch_assoc()) { $ticked[] = (int)$r['subtype_id']; }
$tk->close();

$allowed = taskAllowedFields($conn, (int)$ev['type_id'], $ticked);
$abcGrid = loadAbcGrid($conn, $id);
$loadRdg = loadLoadReading($conn, $id);

/* Edit and Delete are offered only to the roles that could use them.
   require_staff.php on those pages is what actually enforces it; this
   only decides whether the buttons are drawn. */
$mayEdit = roleCan(currentRole(), 'events');

$badgeMap = array(
    'Pending'   => 'badge-pending',
    'Created'   => 'badge-created',
    'Completed' => 'badge-completed',
    'Cancelled' => 'badge-cancelled'
);
$badge = isset($badgeMap[$ev['status_name']]) ? $badgeMap[$ev['status_name']] : '';

$blank = "<span class='field-hint'>Not recorded</span>";

/* Show a value, or a quiet "Not recorded" when there is nothing. */
function evText($v, $blank) {
    return (trim((string)$v) !== '') ? htmlspecialchars($v) : $blank;
}

$typeLabel = $ev['type_name'] ? $ev['type_name'] : 'Not decided yet';

$pageTitle    = $ev['transformer_code'] ? $ev['transformer_code'] : 'Event';
$pageDesc     = $typeLabel . ($ev['csc_name'] ? ' — ' . $ev['csc_name'] : '');
$activeNav    = "tasks";
$showBack     = true;
$backFallback = 'tasks.php';

// The whole history of this event's transformer — the transformer and every
// event on it — so this event's changes are seen next to everything else
// that happened to the same machine. Falls back to this event alone if the
// event somehow has no transformer.
$pageAction = array('History',
                    $ev['transformer_id']
                        ? 'history.php?type=transformer&amp;id=' . (int)$ev['transformer_id']
                        : 'history.php?type=task&amp;id=' . $id,
                    '<path d="M3.5 12a8.5 8.5 0 1 0 2.8-6.3L3 8"/>'
                  . '<path d="M3 3.5V8h4.5"/><path d="M12 7.5V12l3 1.8"/>');

include 'header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2>Event Details</h2>
        <span class="panel-note">
            raised by <?php echo evText($ev['created_name'], 'someone no longer listed'); ?> on
            <?php echo date('d M Y', strtotime($ev['date_created'])); ?>
        </span>
    </div>

    <?php
            $rows = array();

            $rows['Proposal No'] = (trim((string)$ev['request_id']) !== '')
                ? "<span class='code-cell'>" . htmlspecialchars($ev['request_id']) . "</span>"
                : $blank;

            $rows['RID'] = evText($ev['rid'], $blank);

            $rows['Job No'] = (trim((string)$ev['job_no']) !== '')
                ? "<span class='code-cell'>" . htmlspecialchars($ev['job_no']) . "</span>"
                : $blank;

            // The date on the paper. Older records carry only the date they
            // were entered, so that is shown when there is nothing else.
            $rows['Date'] = $ev['task_date']
                ? date('d M Y', strtotime($ev['task_date']))
                : date('d M Y', strtotime($ev['date_created']))
                  . " <span class='field-hint'>(date entered)</span>";

            /* One transformer, or up to five on a job that covers several.
               Each is the S/N, linked to its page, then its substation name.
               An event with only one keeps the plain "Transformer" label it
               always had; T1, T2 … appear only when there is more than one. */
            $txLink = function ($id, $code, $name, $gone) {
                return "<a href='transformer.php?id=" . (int)$id . "'>"
                     . "<span class='code-cell'>" . htmlspecialchars($code) . "</span></a>"
                     . ((trim((string)$name) !== '') ? " &mdash; " . htmlspecialchars($name) : "")
                     . ($gone ? " <span class='field-hint'>removed from the register</span>" : "");
            };

            $extraTx = eventExtraTransformers($conn, $id);

            $rows[$extraTx ? 'Transformer T1' : 'Transformer'] = $ev['transformer_code']
                ? $txLink($ev['transformer_id'], $ev['transformer_code'], $ev['substation_name'], (int)$ev['tx_removed'] === 1)
                : $blank;

            foreach ($extraTx as $x) {
                $rows['Transformer T' . (int)$x['position']]
                    = $txLink($x['id'], $x['code'], $x['substation'], (int)$x['is_removed'] === 1);
            }

            $rows['CSC'] = $ev['csc_name']
                ? htmlspecialchars($ev['csc_name'] . ' (' . $ev['csc_code'] . ')')
                  . ($ev['area_name'] ? " <span class='field-hint'>"
                     . htmlspecialchars($ev['area_name']) . "</span>" : "")
                : $blank;

            $rows['Work Type'] = $ev['type_name']
                ? htmlspecialchars($ev['type_name'] . ' (' . $ev['type_code'] . ')')
                  . (((int)$ev['type_active'] === 0)
                     ? " <span class='field-hint'>no longer offered for new events</span>" : "")
                : $blank;

            $rows['Work Details'] = evText($ev['subtype_list'], $blank);

            // Capacity, shown as the change when there is one.
            if ($ev['capacity_after']) {
                $rows['Capacity'] = "<span class='cap-change'>"
                    . (int)$ev['capacity_before'] . " <span class='cap-arrow'>&rarr;</span> "
                    . "<span class='cap-new'>" . (int)$ev['capacity_after'] . " kVA</span></span>";
            } else {
                $rows['Capacity'] = $ev['capacity_before']
                    ? (int)$ev['capacity_before'] . " kVA <span class='field-hint'>unchanged</span>"
                    : $blank;
            }

            // The conditional fields — shown when the work type asks for one,
            // or when the record holds a value regardless.
            if (!empty($allowed['connections']) || $ev['no_of_connections'] !== null) {
                $rows['No. of Connections'] = ($ev['no_of_connections'] !== null)
                    ? (int)$ev['no_of_connections'] : $blank;
            }
            if (!empty($allowed['feeders']) || $ev['no_of_feeders'] !== null) {
                $rows['No. of Feeders'] = ($ev['no_of_feeders'] !== null)
                    ? (int)$ev['no_of_feeders'] : $blank;
            }
            if (!empty($allowed['length']) || trim((string)$ev['feeder_length']) !== '') {
                $rows['Length'] = evText($ev['feeder_length'], $blank);
            }

            $rows['Estimated Cost'] = ($ev['est_cost'] !== null)
                ? 'Rs. ' . number_format((float)$ev['est_cost'], 2) : $blank;

            $rows['Status'] = "<span class='badge " . $badge . "'>"
                . htmlspecialchars($ev['status_name']) . "</span>";

            $rows['Assigned To'] = $ev['assigned_name']
                ? htmlspecialchars($ev['assigned_name'])
                : "<span class='unassigned-tag'>UNASSIGNED</span>";

            if ($ev['date_completed']) {
                $rows['Completed On'] = date('d M Y, H:i', strtotime($ev['date_completed']));
            }

            $rows['Description'] = (trim((string)$ev['description']) !== '')
                ? nl2br(htmlspecialchars($ev['description'])) : $blank;

            /* Two columns, split so the paperwork numbers stay together on
               the left and what the work actually was reads down the right.
               Description goes across the bottom: it is the one that runs to
               several lines and would leave one column far taller. */
            renderDetailColumns($rows, 6 + count($extraTx), array('Description'));
            ?>

    <div class="panel-body">
        <?php if ($mayEdit) { ?>
            <a href="edit_task.php?id=<?php echo $id; ?>" class="btn">Edit this event</a>
            <span class="action-sep">|</span>
            <a href="delete_task.php?id=<?php echo $id; ?>" class="action-link delete">Delete</a>
            <span class="action-sep">|</span>
        <?php } ?>
        <a href="tasks.php" class="action-link">Back to events</a>
    </div>
</div>

<?php
/* The ABC cable grid, when this event has one. Every number here — the boxes,
   the row totals, the column totals and the grand total — is read and added
   up from the database on this request. Nothing is a stored total. */
if ($abcGrid || !empty($allowed['abcgrid'])) { ?>
<div class="panel">
    <div class="panel-head">
        <h2>Cables</h2>
        <span class="panel-note">
            <?php echo abcNum(abcGridSum($abcGrid)) . ' km in total'; ?>
        </span>
    </div>
    <?php if ($abcGrid) {
        renderAbcGridView($conn, $abcGrid);
    } else { ?>
        <div class="panel-body">
            <div class="table-empty">No cable lengths recorded for this event yet.</div>
        </div>
    <?php } ?>
</div>
<?php } ?>

<?php
/* The Load Reading sheet, when this event has one. The totals and percentages
   are worked out on this request from the stored readings and the capacity
   the event was raised against (capacity_before) — never from the
   transformer's capacity today, so an augmentation later does not change an
   old reading. */
if (lrHasAny($loadRdg) || !empty($allowed['loadreading'])) {
    $lrKva = (int)$ev['capacity_before']; ?>
<div class="panel">
    <div class="panel-head">
        <h2>Load Reading</h2>
        <span class="panel-note">
            <?php if ($lrKva > 0) {
                echo 'Capacity ' . $lrKva . ' kVA when the event was raised'
                   . ' &middot; rated current '
                   . lrFmt($lrKva * 1000 / (LR_LINE_VOLTS * LR_ROOT_THREE)) . ' A';
            } else {
                echo 'Capacity not recorded for this event, so percentages cannot be worked out';
            } ?>
        </span>
    </div>
    <?php if (lrHasAny($loadRdg)) {
        renderLoadReadingView($loadRdg, $lrKva);
    } else { ?>
        <div class="panel-body">
            <div class="table-empty">No load readings recorded for this event yet.</div>
        </div>
    <?php } ?>
</div>
<?php } ?>

<?php include 'footer.php'; ?>
