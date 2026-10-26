<?php
/* Shared helper functions for the CEB System. */

/* ===========================================================================
   Who is who.

   The role list used to be written out twice — once in admin.php and once
   in edit_user.php — and the checks that act on it were spread across
   require_admin.php, require_staff.php, event.php and header.php. Adding a
   role meant finding all six places. It is one list now, and everything
   asks it.

   users.role is varchar(20), not an enum, so a role costs no schema change:
   adding a line here is the whole job.

   'retired' means the role is not offered on the Add User form any more,
   but accounts already holding it keep working and still display properly.
   =========================================================================== */
function userRoles() {
    return array(
        'admin' => array('label' => 'Administrator', 'rank' => 'admin'),
        'ce'    => array('label' => 'CE',            'rank' => 'staff'),
        'ee'    => array('label' => 'EE',            'rank' => 'staff'),
        'es'    => array('label' => 'ES',            'rank' => 'staff'),
        'deu'   => array('label' => 'DEU',           'rank' => 'staff'),
        'dea'   => array('label' => 'DEA',           'rank' => 'staff'),
        "deo"   => array('label' => 'DEO',           'rank' => 'staff'),

        /* A draughtsman keeps the transformer register — adds a machine,
           corrects its details, removes one that was never built. Events
           are not their work, so 'can' names the one area they may change
           and roleCan() refuses the rest. A staff role with no 'can' list
           may change everything, which is what the engineer roles above
           have always been able to do. */
        'dm'    => array('label' => 'Draughtsman',   'rank' => 'staff',
                         'can'   => array('transformers')),

        /* Read only, the same as Guest. The office database already holds
           accounts with this role; before it was listed here it resolved
           to read-only anyway, by the rule that an unknown role is trusted
           with nothing. Naming it here only makes it say "Clerk" on screen
           instead of "CLERK", and puts it on the Add User form. */
        'clerk' => array('label' => 'Clerk',         'rank' => 'read'),

        /* Read only as well, and here for the same reason as Clerk: the
           office database already holds an account on it. It resolved to
           read-only before it was listed, so nothing about what the
           account may do has changed — it simply says "CC" on screen
           because it is named, not because the label fell through. */
        'cc'    => array('label' => 'CC',            'rank' => 'read'),

        'guest' => array('label' => 'Guest',         'rank' => 'read'),

        /* Retired. The tworker account still holds it, so it must keep
           resolving to something — read only, the same as Guest. */
        'worker' => array('label' => 'Worker', 'rank' => 'read', 'retired' => true),
    );
}


/**
 * The roles the Add User and Edit User forms offer. Retired ones are left
 * out, so nobody new is given a role that is on its way out.
 */
function assignableRoles() {
    $out = array();
    foreach (userRoles() as $code => $r) {
        if (empty($r['retired'])) { $out[$code] = $r['label']; }
    }
    return $out;
}


/**
 * What to call a role on screen. An unknown code — one typed straight into
 * the database — comes back as itself rather than as a blank.
 */
function roleLabel($role) {
    $all = userRoles();
    $role = strtolower(trim((string)$role));
    return isset($all[$role]) ? $all[$role]['label'] : strtoupper($role);
}


/** Its rank: 'admin', 'staff' or 'read'. Anything unknown is read only. */
function roleRank($role) {
    $all = userRoles();
    $role = strtolower(trim((string)$role));
    return isset($all[$role]) ? $all[$role]['rank'] : 'read';
}


/** May manage user accounts and the work-type codes. */
function roleIsAdmin($role) {
    return roleRank($role) === 'admin';
}


/**
 * May add, edit and delete records — transformers, CSCs and events.
 * An administrator can do everything a staff member can.
 */
function roleIsStaff($role) {
    $rank = roleRank($role);
    return ($rank === 'admin' || $rank === 'staff');
}


/**
 * The password every new account starts on.
 *
 * The office hands out the same one to everybody rather than inventing a
 * password per person, because nobody is expected to keep it: the system
 * asks for a replacement at the first sign-in and will not show anything
 * else until it gets one. Changing it here changes it everywhere — the Add
 * User form, the hint beside it and the message the administrator reads
 * out are all this one string.
 *
 * It is short on purpose and it is exempt from the eight-character rule
 * that real passwords have to meet. That rule still applies to anything an
 * administrator types into the form by hand, and to what the person
 * finally chooses.
 */
function startingPassword() {
    return 'abc123';
}


/**
 * May this role change records in one particular area?
 *
 * The areas are 'transformers', 'events' and 'csc'. roleIsStaff() asks the
 * blunter question — may this person change anything at all — and is still
 * right wherever the answer does not depend on which register is open.
 *
 * The rules, in order:
 *
 *   An administrator may do everything.
 *   A read-only role may do nothing, whatever the area.
 *   A staff role with no 'can' list may do everything. That is deliberate:
 *     CE, EE, ES, DEU, DEA and DEO were trusted with the whole register
 *     before areas existed and are trusted with it still, so adding this
 *     took nothing away from anybody.
 *   A staff role with a 'can' list may do only what is on it.
 */
function roleCan($role, $area) {

    $rank = roleRank($role);

    if ($rank === 'admin')  { return true; }
    if ($rank !== 'staff')  { return false; }

    $all = userRoles();
    $key = strtolower(trim((string)$role));

    if (!isset($all[$key]['can'])) { return true; }

    return in_array($area, $all[$key]['can'], true);
}


/**
 * May this role delete a transformer?
 *
 * Narrower than roleCan($role, 'transformers'), which also covers adding and
 * editing: removing a machine from the register is the Draughtsman's call
 * alone, so every other role — administrator included — is refused.
 */
function roleCanDeleteTransformer($role) {
    return strtolower(trim((string)$role)) === 'dm';
}


/* ===========================================================================
   Who an event may be assigned to.

   Six ES, by user_id, in the order the office gave them. Not every ES:
   W.K.C. Wijesundara and the shared "Electricity Supreintendent" account
   hold the role too but are not on the list. Held by id, not name, because
   the names on the accounts are spelt differently from the office's list
   ("Mr. P.G. Ajith Thushara").
   =========================================================================== */
function eventAssigneeIds() {
    return array(
        7,    // D.L.H.M. Rathnalal
        8,    // B.S.C. Sarathchandra
        9,    // P.G. Ajith Thushara
        10,   // M.M.A.N. Premalal
        11,   // J.M.C.K. Jayasinghe
        12,   // Imesh Dissanayake
    );
}

/**
 * The people an event may be assigned to, as user_id => full_name, in the
 * order of eventAssigneeIds(). A deleted account drops out.
 */
function eventAssignees(mysqli $conn) {
    $ids  = implode(',', array_map('intval', eventAssigneeIds()));
    $res  = $conn->query("SELECT user_id, full_name FROM users
                          WHERE user_id IN ($ids) AND is_deleted = 0
                          ORDER BY FIELD(user_id, $ids)");
    $list = array();
    while ($u = $res->fetch_assoc()) { $list[(int)$u['user_id']] = $u['full_name']; }
    return $list;
}


/* ===========================================================================
   Removed transformers.

   A transformer is removed when it has a row in removed_transformers (see
   sql/2026-10-06_removed_transformers.sql). That table replaced the old
   transformers.is_deleted flag.

   The transformer's own row stays where it is, because its events point at
   it, but its S/N is changed from UBB 001 to (R)UBB 001 so a new UBB 001
   can be added. The removed_transformers row keeps a copy of its details
   as they were, and when and by whom it was removed.
   =========================================================================== */

/**
 * SQL that is true for a transformer still in the register. Drop it into a
 * WHERE or a JOIN wherever "t.is_deleted = 0" used to be:
 *
 *     "... WHERE " . txActiveSql('t') . " AND ..."
 *
 * $alias is the table name or alias the query uses for transformers. It is
 * always written by us, never taken from the browser.
 */
function txActiveSql($alias = 't') {
    return "NOT EXISTS (SELECT 1 FROM removed_transformers rtx
                        WHERE rtx.transformer_id = $alias.transformer_id)";
}


/**
 * The removal record for one transformer, with the name of whoever removed
 * it — or null when the transformer is still in the register.
 */
function txRemovedInfo(mysqli $conn, int $transformerId) {

    $st = $conn->prepare(
        "SELECT r.*, u.full_name AS removed_by_name
         FROM removed_transformers r
         LEFT JOIN users u ON r.removed_by = u.user_id
         WHERE r.transformer_id = ?"
    );
    $st->bind_param("i", $transformerId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    return $row ?: null;
}


/**
 * Take a transformer out of the register.
 *
 * Copies its present details into removed_transformers and renames its S/N
 * to (R)UBB 001 — or (R2)UBB 001, (R3)… when that is already taken by an
 * earlier removal of the same S/N. Both happen in one transaction, so a
 * failure half way leaves the transformer exactly as it was.
 *
 * Returns array(true, new S/N) or array(false, message).
 */
function removeTransformer(mysqli $conn, int $transformerId, int $userId) {

    $conn->begin_transaction();

    try {
        // FOR UPDATE: two people pressing Delete at once must not both get in.
        $st = $conn->prepare("SELECT * FROM transformers WHERE transformer_id = ? FOR UPDATE");
        $st->bind_param("i", $transformerId);
        $st->execute();
        $tx = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$tx) {
            $conn->rollback();
            return array(false, "That transformer no longer exists.");
        }
        if (txRemovedInfo($conn, $transformerId)) {
            $conn->rollback();
            return array(false, "That transformer has already been removed.");
        }

        // The first free (R) S/N: (R)UBB 001, then (R2)UBB 001, (R3)…
        $code = $tx['transformer_code'];
        $free = $conn->prepare("SELECT 1 FROM transformers WHERE transformer_code = ?");
        $newCode = '';
        for ($n = 1; $n <= 99; $n++) {
            $try = '(R' . ($n === 1 ? '' : $n) . ')' . $code;
            $free->bind_param("s", $try);
            $free->execute();
            if (!$free->get_result()->fetch_row()) { $newCode = $try; break; }
        }
        $free->close();

        if ($newCode === '' || strlen($newCode) > 20) {
            $conn->rollback();
            return array(false, "Could not give " . $code . " a removed S/N.");
        }

        $byVal = ($userId > 0) ? $userId : null;
        $ins = $conn->prepare(
            "INSERT INTO removed_transformers
                (transformer_id, original_code, removed_code, csc_id, substation_name,
                 capacity_kva, transformer_type, lt_feeders, serial_no, proposal_no, details,
                 removed_at, removed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
        );
        $ins->bind_param("issisisisssi",
            $transformerId, $code, $newCode, $tx['csc_id'], $tx['substation_name'],
            $tx['capacity_kva'], $tx['transformer_type'], $tx['lt_feeders'], $tx['serial_no'],
            $tx['proposal_no'], $tx['details'], $byVal);
        $ins->execute();
        $ins->close();

        $up = $conn->prepare("UPDATE transformers SET transformer_code = ? WHERE transformer_id = ?");
        $up->bind_param("si", $newCode, $transformerId);
        $up->execute();
        $up->close();

        $conn->commit();
        return array(true, $newCode);

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        return array(false, "Error: " . $e->getMessage());
    }
}


/**
 * Put a removed transformer back in the register under its old S/N.
 *
 * Refused when a new transformer has taken that S/N since — the two would
 * collide, and which one is the real UBB 001 is a decision for a person.
 *
 * Returns array(true, restored S/N) or array(false, message).
 */
function restoreTransformer(mysqli $conn, int $transformerId) {

    $conn->begin_transaction();

    try {
        $info = txRemovedInfo($conn, $transformerId);
        if (!$info) {
            $conn->rollback();
            return array(false, "That transformer is not removed — it may already have been restored.");
        }

        $code = $info['original_code'];

        $st = $conn->prepare("SELECT 1 FROM transformers WHERE transformer_code = ?");
        $st->bind_param("s", $code);
        $st->execute();
        $taken = (bool)$st->get_result()->fetch_row();
        $st->close();

        if ($taken) {
            $conn->rollback();
            return array(false, "Cannot restore " . $info['removed_code'] . ": a transformer "
                              . $code . " is already in the register.");
        }

        $up = $conn->prepare("UPDATE transformers SET transformer_code = ? WHERE transformer_id = ?");
        $up->bind_param("si", $code, $transformerId);
        $up->execute();
        $up->close();

        $del = $conn->prepare("DELETE FROM removed_transformers WHERE transformer_id = ?");
        $del->bind_param("i", $transformerId);
        $del->execute();
        $del->close();

        $conn->commit();
        return array(true, $code);

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        return array(false, "Error: " . $e->getMessage());
    }
}


/** The signed-in user's role, straight from the session. */
function currentRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : '';
}


/**
 * Is this person still on the password an administrator gave them?
 *
 * login.php reads users.must_change_password once and parks it in the
 * session, so no page after that pays for another query. auth.php acts on
 * it: while it is true the only pages that answer are change_password.php
 * and logout.php.
 */
function mustChangePassword() {
    return !empty($_SESSION['must_change_password']);
}


/**
 * The transformer types the office recognises.
 *
 * Defined here once so add_transformer.php, edit_transformer.php and any
 * later page all offer the same list. The spelling matches the
 * "SIN Number List Uva Province" workbook exactly, including the "&",
 * because the office rule is that data is stored as written.
 *
 * The register also holds older variants that are not on this list —
 * "Bulk (MHP)", "Bulk (HT)", "Bulk (DPP)". Those are never rewritten.
 * See transformerTypeOptions() for how they are kept.
 */
function transformerTypes() {
    return array('Bulk', 'Distribution', 'Bulk & Distribution');
}


/**
 * Decide what a submitted transformer type should be stored as.
 *
 * Anything not on the list becomes NULL, so a hand-made POST cannot put
 * rubbish in the column. The one exception is a value the record already
 * holds: an imported "Bulk (MHP)" must survive somebody opening the edit
 * form and pressing Save without touching the dropdown.
 */
function cleanTransformerType($posted, $existing = null) {

    $posted = trim((string)$posted);

    if ($posted === '') { return null; }

    if (in_array($posted, transformerTypes(), true)) { return $posted; }

    // Not on the list, but unchanged from what is already stored — keep it.
    if ($existing !== null && $posted === trim((string)$existing)) { return $posted; }

    return null;
}


/**
 * Print the <option> tags for a transformer type dropdown.
 *
 * If the record holds a value that is not one of the three — an imported
 * "Bulk (MHP)", say — it is added to the bottom of the list and selected,
 * so saving the form cannot quietly wipe it.
 */
function transformerTypeOptions($current) {

    $current = trim((string)$current);

    echo "<option value=''>-- Not recorded --</option>";

    foreach (transformerTypes() as $t) {
        $sel = ($current === $t) ? " selected" : "";
        echo "<option value='" . htmlspecialchars($t) . "'" . $sel . ">"
             . htmlspecialchars($t) . "</option>";
    }

    if ($current !== '' && !in_array($current, transformerTypes(), true)) {
        echo "<option value='" . htmlspecialchars($current) . "' selected>"
             . htmlspecialchars($current) . " (as imported)</option>";
    }
}


/**
 * True when the stored value is one of the older imported variants.
 * The forms use this to explain why an extra option is showing.
 */
function isLegacyTransformerType($current) {
    $current = trim((string)$current);
    return ($current !== '' && !in_array($current, transformerTypes(), true));
}


/* ===========================================================================
   Conditional fields on the event forms.

   A work type can ask for extra boxes — a new capacity, a number of
   connections, and so on. Which boxes appear is data, not code: each flag
   below is a column on task_types (and, for capacity, on task_subtypes too).

   Everything that knows about these fields reads this one array, so adding
   a fifth is one ALTER plus one line here, instead of the same edit made
   ten times across add_task.php and edit_task.php.

       flag  - the tinyint column on task_types that switches it on
       col   - the column on tasks where the answer is stored
       row   - the id of the <div class="form-row"> the browser shows/hides
       input - the id of the input inside that row
       sub   - true when task_subtypes carries the same flag, so a ticked
               option can also bring the box up
       int   - true when the value is a whole number, false for free text
       kind  - 'scalar' for the four above: one flag, one column, one box.
               'grid' for ABC Conversion, which is twelve numbers in a table
               of their own. A grid keeps 'flag' and 'row' so it is shown and
               hidden by exactly the same rule as the others, but it has no
               'col' and no single 'input', so anything that reads or writes
               one value skips it and the abc* functions below handle it.
               'loadreading' for Load Reading, the same idea again: the
               whole load sheet, handled by the lr* functions.

   Pass $conn to get the grid as well. Without it you get the four scalar
   fields only — which is all the callers that read a single value want.
   =========================================================================== */
function taskExtraFields(?mysqli $conn = null) {

    $fields = array(
        'capacity' => array(
            'flag' => 'needs_capacity',    'col' => 'capacity_after',
            'row'  => 'capacity-row',      'input' => 'capafter',
            'sub'  => true,                'int'   => true,
            'kind' => 'scalar'),
        'connections' => array(
            'flag' => 'needs_connections', 'col' => 'no_of_connections',
            'row'  => 'connections-row',   'input' => 'connections',
            'sub'  => false,               'int'   => true,
            'kind' => 'scalar'),
        'feeders' => array(
            'flag' => 'needs_feeders',     'col' => 'no_of_feeders',
            'row'  => 'feeders-row',       'input' => 'feeders',
            'sub'  => false,               'int'   => true,
            'kind' => 'scalar'),
        'length' => array(
            'flag' => 'needs_length',      'col' => 'feeder_length',
            'row'  => 'length-row',        'input' => 'length',
            'sub'  => false,               'int'   => false,
            'kind' => 'scalar'),
    );

    // Only offered once the ABC tables and the flag column exist, so this
    // file is safe to have on disk before the SQL has been run.
    if ($conn !== null && abcGridReady($conn)) {
        $fields['abcgrid'] = array(
            'flag' => 'needs_abc_grid',    'col' => null,
            'row'  => 'abc-grid-row',      'input' => null,
            'sub'  => false,               'int'   => false,
            'kind' => 'grid');
    }

    // Load Reading is the whole office sheet — two feeder tables, voltages
    // and notes — so like the grid it has no single column or box, and the
    // lr* functions below read and write it.
    if ($conn !== null && loadReadingReady($conn)) {
        $fields['loadreading'] = array(
            'flag' => 'needs_load_reading', 'col' => null,
            'row'  => 'load-reading-row',   'input' => null,
            'sub'  => false,                'int'   => false,
            'kind' => 'loadreading');
    }

    return $fields;
}


/**
 * The flags, per work type and per sub-option, ready to hand to JavaScript.
 *
 * Returns two maps:
 *   types[type_id][fieldKey]       = 1 or 0
 *   subs[subtype_id][fieldKey]     = 1 or 0   (only for fields with sub=true)
 *   single[type_id]                = 1 when the options are choose-one
 */
function taskFieldMaps(mysqli $conn) {

    $fields = taskExtraFields($conn);

    $typeCols = array();
    foreach ($fields as $key => $f) { $typeCols[] = $f['flag']; }

    $types  = array();
    $single = array();

    $res = $conn->query("SELECT type_id, single_choice, " . implode(', ', $typeCols) . "
                         FROM task_types WHERE is_active = 1");
    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['type_id'];
        $types[$id] = array();
        foreach ($fields as $key => $f) {
            $types[$id][$key] = (int)$r[$f['flag']];
        }
        $single[$id] = (int)$r['single_choice'];
    }

    // Only capacity is mirrored on task_subtypes today, but the loop is
    // written from the array so a second one costs nothing.
    $subCols = array();
    foreach ($fields as $key => $f) { if ($f['sub']) { $subCols[$key] = $f['flag']; } }

    $subs = array();
    if ($subCols) {
        $res = $conn->query("SELECT subtype_id, " . implode(', ', $subCols) . "
                             FROM task_subtypes WHERE is_active = 1");
        while ($r = $res->fetch_assoc()) {
            $id = (int)$r['subtype_id'];
            $subs[$id] = array();
            foreach ($subCols as $key => $col) { $subs[$id][$key] = (int)$r[$col]; }
        }
    }

    return array('types' => $types, 'subs' => $subs, 'single' => $single);
}


/**
 * Work out which extra fields this work type and these ticked options allow.
 *
 * The browser hides a box it does not need, but hidden is not blocked —
 * a hand-made POST could still carry a value. Anything not allowed here is
 * thrown away before it reaches the database.
 *
 * Returns an array keyed the same as taskExtraFields(), each true or false.
 */
function taskAllowedFields(mysqli $conn, $typeId, array $subtypeIds = array()) {

    $fields  = taskExtraFields($conn);
    $allowed = array();
    foreach ($fields as $key => $f) { $allowed[$key] = false; }

    $typeId = (int)$typeId;

    if ($typeId > 0) {
        $cols = array();
        foreach ($fields as $key => $f) { $cols[] = $f['flag']; }

        $st = $conn->prepare("SELECT " . implode(', ', $cols) . "
                              FROM task_types WHERE type_id = ?");
        $st->bind_param("i", $typeId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if ($row) {
            foreach ($fields as $key => $f) {
                if ((int)$row[$f['flag']] === 1) { $allowed[$key] = true; }
            }
        }
    }

    // A ticked option can switch a field on even when the type does not.
    $subIds = array();
    foreach ($subtypeIds as $s) { $s = (int)$s; if ($s > 0) { $subIds[] = $s; } }

    if ($subIds) {
        foreach ($fields as $key => $f) {
            if (!$f['sub'] || $allowed[$key]) { continue; }

            $ph = implode(',', array_fill(0, count($subIds), '?'));
            $q  = $conn->prepare("SELECT COUNT(*) AS n FROM task_subtypes
                                  WHERE " . $f['flag'] . " = 1 AND subtype_id IN ($ph)");
            $q->bind_param(str_repeat('i', count($subIds)), ...$subIds);
            $q->execute();
            $n = $q->get_result()->fetch_assoc();
            $q->close();

            if ($n['n'] > 0) { $allowed[$key] = true; }
        }
    }

    return $allowed;
}


/**
 * Take the posted values for the extra fields and return what should be
 * stored — NULL for anything the chosen work type does not allow, and NULL
 * rather than zero for a box left empty, because "not counted yet" is not
 * the same as "none".
 */
function taskExtraValues(array $allowed, array $post) {

    $out = array();

    foreach (taskExtraFields() as $key => $f) {

        // Only one-value fields belong here; a grid stores itself.
        if ($f['kind'] !== 'scalar') { continue; }

        if (empty($allowed[$key])) { $out[$key] = null; continue; }

        $raw = isset($post[$f['input']]) ? trim((string)$post[$f['input']]) : '';

        if ($raw === '') { $out[$key] = null; continue; }

        if ($f['int']) {
            $n = (int)$raw;
            $out[$key] = ($n > 0) ? $n : null;
        } else {
            $out[$key] = $raw;
        }
    }

    return $out;
}


/**
 * Print the conditional rows that are not the capacity dropdown.
 * Capacity has its own kVA list, so both forms still draw that one.
 */
function renderTaskExtraRows(array $values) {

    $labels = array(
        'connections' => array('No. of Connections', 'How many connections this covers.'),
        'feeders'     => array('No. of Feeders',     'How many feeders this covers.'),
        'length'      => array('Length',             'As written on the paperwork, e.g. 1.2 km.'),
    );

    foreach (taskExtraFields() as $key => $f) {

        if (!isset($labels[$key])) { continue; }   // capacity is drawn separately

        $val = isset($values[$key]) ? $values[$key] : '';
        list($label, $hint) = $labels[$key];
        ?>
        <div class="form-row" id="<?php echo $f['row']; ?>" style="display:none;">
            <label for="<?php echo $f['input']; ?>"><?php echo htmlspecialchars($label); ?></label>
            <?php if ($f['int']) { ?>
                <input type="number" min="0" step="1" inputmode="numeric"
                       id="<?php echo $f['input']; ?>" name="<?php echo $f['input']; ?>"
                       value="<?php echo htmlspecialchars((string)$val); ?>">
            <?php } else { ?>
                <input type="text" maxlength="50"
                       id="<?php echo $f['input']; ?>" name="<?php echo $f['input']; ?>"
                       value="<?php echo htmlspecialchars((string)$val); ?>">
            <?php } ?>
            <span class="field-hint"><?php echo htmlspecialchars($hint); ?></span>
        </div>
        <?php
    }
}


/**
 * The one copy of the browser-side logic both event forms use.
 *
 * It shows the tick boxes (or the choose-one dropdown) for the chosen work
 * type, then shows each extra field the type or a ticked option asks for.
 */
function taskFormScript(mysqli $conn, $chosenTx = null) {

    $maps   = taskFieldMaps($conn);
    $fields = taskExtraFields($conn);

    $spec = array();
    foreach ($fields as $key => $f) {
        $spec[$key] = array('row' => $f['row'], 'input' => $f['input'], 'sub' => $f['sub'] ? 1 : 0);
    }
    ?>
var fieldSpec    = <?php echo json_encode($spec); ?>;
var typeNeeds    = <?php echo json_encode($maps['types']); ?>;
var subNeeds     = <?php echo json_encode($maps['subs']); ?>;
var singleChoice = <?php echo json_encode($maps['single']); ?>;

/* Show the options for the chosen work type, then show whichever extra
   boxes that type — or a ticked option — asks for. */
function updateForm() {

    var typeId = document.getElementById('type').value;
    var isOne  = (typeId !== '' && singleChoice[typeId] == 1);

    /* --- tick boxes: only the ones belonging to this work type --- */
    var options = document.querySelectorAll('.subtype-option');
    var shown   = 0;

    for (var i = 0; i < options.length; i++) {
        if (typeId !== '' && options[i].getAttribute('data-type') === typeId) {
            options[i].style.display = 'block';
            shown++;
        } else {
            options[i].style.display = 'none';
            var b = options[i].querySelector('input');
            if (b) { b.checked = false; }
        }
    }

    /* --- choose-one dropdown, for work types like Retail Connection --- */
    var pickRow = document.getElementById('subtype-pick-row');
    var pickSel = document.getElementById('subtype-pick');
    var boxRow  = document.getElementById('subtype-row');

    if (pickSel) {
        var opts = pickSel.options;
        var pickable = 0;
        for (var p = 0; p < opts.length; p++) {
            if (opts[p].value === '') { continue; }
            var belongs = (opts[p].getAttribute('data-type') === typeId);
            opts[p].hidden   = !belongs;
            opts[p].disabled = !belongs;
            if (belongs) { pickable++; }
        }
        if (!isOne || pickable === 0) {
            pickSel.value = '';
            if (pickRow) { pickRow.style.display = 'none'; }
        } else if (pickRow) {
            pickRow.style.display = 'block';
        }
        // The chosen option may no longer belong to this type.
        if (pickSel.value !== '') {
            var cur = pickSel.options[pickSel.selectedIndex];
            if (cur && cur.getAttribute('data-type') !== typeId) { pickSel.value = ''; }
        }
    }

    if (boxRow) {
        boxRow.style.display = (shown > 0 && !isOne) ? 'block' : 'none';
        if (isOne) {
            for (var h = 0; h < options.length; h++) {
                options[h].style.display = 'none';
                var hb = options[h].querySelector('input');
                if (hb) { hb.checked = false; }
            }
        }
    }

    /* --- the extra boxes, one rule for all of them --- */
    for (var key in fieldSpec) {

        if (!fieldSpec.hasOwnProperty(key)) { continue; }

        var want = (typeId !== '' && typeNeeds[typeId] && typeNeeds[typeId][key] == 1);

        if (!want && fieldSpec[key].sub) {
            // a ticked box can ask for it as well
            for (var j = 0; j < options.length; j++) {
                var box = options[j].querySelector('input');
                if (!box) { continue; }
                if (options[j].style.display !== 'none' && box.checked
                    && subNeeds[box.value] && subNeeds[box.value][key] == 1) {
                    want = true;
                }
            }
            // and so can the choose-one dropdown
            if (!want && pickSel && pickSel.value !== ''
                && subNeeds[pickSel.value] && subNeeds[pickSel.value][key] == 1) {
                want = true;
            }
        }

        var row = document.getElementById(fieldSpec[key].row);
        if (!row) { continue; }

        if (want) {
            row.style.display = 'block';
        } else {
            row.style.display = 'none';

            /* Never post a value for a hidden box. A one-box field names its
               input; a grid has twelve, so everything inside the row is
               cleared instead. */
            if (fieldSpec[key].input) {
                var inp = document.getElementById(fieldSpec[key].input);
                if (inp) { inp.value = ''; }
            } else {
                var many = row.querySelectorAll('input, select, textarea');
                for (var m = 0; m < many.length; m++) { many[m].value = ''; }
            }
        }
    }

    /* Emptying a hidden grid changes its totals, so redraw them. */
    if (typeof abcRecalc === 'function') { abcRecalc(); }
    if (typeof lrRecalc  === 'function') { lrRecalc(); }
}

document.getElementById('type').addEventListener('change', updateForm);

var boxes = document.querySelectorAll('.subtype-option input');
for (var k = 0; k < boxes.length; k++) {
    boxes[k].addEventListener('change', updateForm);
}

var pick = document.getElementById('subtype-pick');
if (pick) { pick.addEventListener('change', updateForm); }
    <?php
}


/**
 * Print the tick boxes and the choose-one dropdown for every active option.
 * Both are always in the page; updateForm() decides which one is shown.
 */
function renderSubtypeControls(mysqli $conn, array $ticked) {
    ?>
    <div class="form-row" id="subtype-row" style="display:none;">
        <label>Work Details</label>
        <div id="subtype-boxes">
            <?php
            $subs = $conn->query("SELECT subtype_id, type_id, subtype_name, description
                                  FROM task_subtypes
                                  WHERE is_active = 1
                                  ORDER BY subtype_id");
            while ($sb = $subs->fetch_assoc()) {
                $chk = in_array((int)$sb['subtype_id'], $ticked) ? " checked" : "";
                echo "<label class='subtype-option' data-type='" . (int)$sb['type_id'] . "'"
                   . ($sb['description'] ? " title='" . htmlspecialchars($sb['description']) . "'" : "")
                   . " style='display:none;'>"
                   . "<input type='checkbox' name='subtypes[]' value='" . (int)$sb['subtype_id'] . "'" . $chk . "> "
                   . htmlspecialchars($sb['subtype_name'])
                   . "</label>";
            }
            ?>
        </div>
        <span class="field-hint">Tick every option that applies. You can choose more than one.</span>
    </div>

    <div class="form-row" id="subtype-pick-row" style="display:none;">
        <label for="subtype-pick">Work Details</label>
        <select id="subtype-pick" name="subtype_pick">
            <option value="">-- Select one --</option>
            <?php
            $subs = $conn->query("SELECT subtype_id, type_id, subtype_name, description
                                  FROM task_subtypes st
                                  WHERE is_active = 1
                                  ORDER BY subtype_id");
            while ($sb = $subs->fetch_assoc()) {
                $sel = in_array((int)$sb['subtype_id'], $ticked) ? " selected" : "";
                echo "<option value='" . (int)$sb['subtype_id'] . "'"
                   . " data-type='" . (int)$sb['type_id'] . "'" . $sel . ">"
                   . htmlspecialchars($sb['subtype_name'])
                   . "</option>";
            }
            ?>
        </select>
        <span class="field-hint">Choose the one that applies.</span>
    </div>
    <?php
}


/**
 * Which options should be saved against an event?
 *
 * Only options belonging to the chosen work type are accepted, so nothing
 * can be smuggled in from another type. Options the record ALREADY holds
 * are kept even if they have since been deactivated — otherwise opening an
 * old event and pressing Save would quietly wipe its Work Details.
 */
function resolveSubtypes(mysqli $conn, $typeId, array $posted, array $existing = array()) {

    $typeId = (int)$typeId;
    if ($typeId <= 0) { return array(); }

    /* A choose-one work type holds a single option. If the user has picked
       one, that replaces whatever was there — carrying the old value forward
       as well would leave the event with two answers to a one-answer
       question. Picking nothing still keeps what the record already had. */
    $sc = $conn->prepare("SELECT single_choice FROM task_types WHERE type_id = ?");
    $sc->bind_param("i", $typeId);
    $sc->execute();
    $scRow = $sc->get_result()->fetch_assoc();
    $sc->close();

    $isSingle = ($scRow && (int)$scRow['single_choice'] === 1);

    $valid = array();
    $vs = $conn->prepare("SELECT subtype_id FROM task_subtypes WHERE type_id = ?");
    $vs->bind_param("i", $typeId);
    $vs->execute();
    $r = $vs->get_result();
    while ($v = $r->fetch_assoc()) { $valid[] = (int)$v['subtype_id']; }
    $vs->close();

    $activeOnly = array();
    $as = $conn->prepare("SELECT subtype_id FROM task_subtypes
                          WHERE type_id = ? AND is_active = 1");
    $as->bind_param("i", $typeId);
    $as->execute();
    $r = $as->get_result();
    while ($v = $r->fetch_assoc()) { $activeOnly[] = (int)$v['subtype_id']; }
    $as->close();

    $keep = array();

    foreach ($posted as $sid) {
        $sid = (int)$sid;
        if ($sid > 0 && in_array($sid, $activeOnly, true)) { $keep[] = $sid; }
    }

    // A choose-one type with a fresh pick keeps only that pick.
    if ($isSingle && $keep) {
        return array(reset($keep));
    }

    // Keep what the record already had, if it still belongs to this type.
    // Only retired options need carrying: an active one the user unticked
    // was unticked on purpose.
    foreach ($existing as $sid) {
        $sid = (int)$sid;
        if ($sid > 0 && in_array($sid, $valid, true)
            && !in_array($sid, $activeOnly, true)
            && !in_array($sid, $keep, true)) {
            $keep[] = $sid;
        }
    }

    return array_values(array_unique($keep));
}


/* ===========================================================================
   ABC Conversion — the cable grid.

   Three conversion types down the side, four cable sizes across the top, a
   number in each box. Blank means none.

   It is stored in task_abc_cables, one row per box that has a number in it,
   so an event with three boxes filled uses three rows and not twelve. The
   row totals and the grand total are never stored — they are added up from
   those rows every time they are shown, so there is no second copy of the
   answer that can drift away from the first.

   Everything below returns an empty grid until the tables exist, so these
   functions are safe to call before the SQL has been run.
   =========================================================================== */


/**
 * Are the ABC tables and the flag column in place yet?
 *
 * Asked once per page load. Until the SQL has been run this returns false and
 * the grid simply never appears — the event forms carry on working exactly as
 * they did, instead of stopping with an error about a missing column.
 */
function abcGridReady(mysqli $conn) {

    static $ready = null;
    if ($ready !== null) { return $ready; }

    $ready = false;

    try {
        $res = $conn->query(
            "SELECT
               (SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN ('abc_phase_types','abc_cable_sizes','task_abc_cables')) AS t,
               (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name   = 'task_types'
                   AND column_name  = 'needs_abc_grid') AS c,
               (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name   = 'task_abc_cables'
                   AND column_name  = 'qty'
                   AND data_type IN ('decimal','numeric')) AS d"
        );
        $row = $res ? $res->fetch_assoc() : null;

        /* The qty column has to be a decimal one. The boxes hold kilometres,
           and a whole-number column would take 2.5 and quietly keep 2 or 3 —
           a wrong length in the register with nothing on screen to say so.
           Until the column is right the grid stays away entirely, which is
           noticeable, rather than storing something false, which is not. */
        $ready = ($row
               && (int)$row['t'] === 3
               && (int)$row['c'] === 1
               && (int)$row['d'] === 1);

    } catch (mysqli_sql_exception $e) {
        $ready = false;
    }

    return $ready;
}


/**
 * A length as it should be written: no trailing noughts, no empty decimal.
 *
 * 2 stays 2, 2.5 stays 2.5, 2.500 becomes 2.5, 2.125 keeps all three.
 * Used everywhere a kilometre figure is shown, so the grid, the totals, the
 * lists and the change log all spell the same number the same way.
 */
function abcNum($v) {
    $s = number_format((float)$v, 3, '.', '');
    if (strpos($s, '.') !== false) {
        $s = rtrim(rtrim($s, '0'), '.');
    }
    return ($s === '' || $s === '-0') ? '0' : $s;
}


/**
 * One of the two lists that make up the grid.
 *
 * Retired entries are left out of new grids, but an id passed in $alsoInclude
 * is fetched even when it is retired. That is how an old event keeps showing
 * a cable size the office has since stopped using, instead of quietly losing
 * the number that was recorded against it.
 *
 * Returns  array( id => array('name' => ..., 'active' => true|false) )
 */
function abcLookupList(mysqli $conn, $table, $idCol, $nameCol, array $alsoInclude = array()) {

    if (!abcGridReady($conn)) { return array(); }

    $extra = array();
    foreach ($alsoInclude as $i) {
        $i = (int)$i;
        if ($i > 0) { $extra[$i] = true; }
    }

    // $table, $idCol and $nameCol are written by this file, never by a user.
    // The ids are forced to whole numbers on the line above.
    $sql = "SELECT $idCol AS id, $nameCol AS name, is_active FROM $table WHERE is_active = 1";
    if ($extra) {
        $sql .= " OR $idCol IN (" . implode(',', array_keys($extra)) . ")";
    }
    $sql .= " ORDER BY sort_order, $idCol";

    $out = array();
    $res = $conn->query($sql);
    while ($r = $res->fetch_assoc()) {
        $out[(int)$r['id']] = array(
            'name'   => $r['name'],
            'active' => ((int)$r['is_active'] === 1),
        );
    }

    return $out;
}

function abcPhaseTypes(mysqli $conn, array $alsoInclude = array()) {
    return abcLookupList($conn, 'abc_phase_types', 'phase_type_id', 'phase_name', $alsoInclude);
}

function abcCableSizes(mysqli $conn, array $alsoInclude = array()) {
    return abcLookupList($conn, 'abc_cable_sizes', 'cable_size_id', 'size_name', $alsoInclude);
}


/**
 * Read the grid back for one event.
 * Returns  array( phase_type_id => array( cable_size_id => qty ) )
 */
function loadAbcGrid(mysqli $conn, $taskId) {

    $out = array();
    if (!abcGridReady($conn)) { return $out; }

    $st = $conn->prepare("SELECT phase_type_id, cable_size_id, qty
                          FROM task_abc_cables WHERE task_id = ?");
    $st->bind_param("i", $taskId);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $out[(int)$r['phase_type_id']][(int)$r['cable_size_id']] = (float)$r['qty'];
    }
    $st->close();

    return $out;
}


/**
 * Take what the browser posted and return only what may be stored.
 *
 * A blank box is not an error, it is none — and so is anything that is not a
 * plain whole number, because a letter in a counting box has no meaning. Both
 * are simply left out, which is the same as zero. Boxes naming a conversion
 * type or a cable size that does not exist are dropped as well: the browser
 * cannot be the judge of what the grid contains.
 */
function abcGridFromPost(mysqli $conn, array $post) {

    $out = array();
    if (!abcGridReady($conn)) { return $out; }

    if (!isset($post['abc']) || !is_array($post['abc'])) { return $out; }

    $phases = abcPhaseTypes($conn);
    $sizes  = abcCableSizes($conn);

    foreach ($post['abc'] as $phaseId => $row) {

        $phaseId = (int)$phaseId;
        if (!isset($phases[$phaseId]) || !is_array($row)) { continue; }

        foreach ($row as $sizeId => $raw) {

            $sizeId = (int)$sizeId;
            if (!isset($sizes[$sizeId])) { continue; }

            $raw = trim((string)$raw);

            /* A length in kilometres: digits with at most one point.

               Written forgivingly on purpose. ".5" and "2." are what people
               actually type for half a kilometre and for two, and refusing
               them would throw a real length away over a missing nought.
               Both are read as the number they plainly mean.

               Blank is none, and so is anything that is not a plain number —
               letters, a minus sign, a comma used as the decimal point, a
               space. None of those is an error worth stopping the save for:
               they do not make a length, and a box with no length in it is
               a box with none.

               The figure is rounded to the metre here rather than left for
               the database to round on the way in, so what is shown back
               afterwards is exactly what was stored. */
            if (!preg_match('/^\d{0,7}(\.\d{0,6})?$/', $raw)
                || !preg_match('/\d/', $raw)) { continue; }

            $qty = round((float)$raw, 3);
            if ($qty > 0 && $qty <= 999999) {
                $out[$phaseId][$sizeId] = $qty;
            }
        }
    }

    return $out;
}


/**
 * Write the grid for one event.
 *
 * The whole grid is rewritten each time: the event's own rows are removed and
 * the boxes that have a number go back in. This is the same delete-and-rewrite
 * that the ticked work details already use, and it only ever touches rows
 * belonging to this one event.
 */
function saveAbcGrid(mysqli $conn, $taskId, array $values) {

    if (!abcGridReady($conn)) { return; }

    $taskId = (int)$taskId;

    $del = $conn->prepare("DELETE FROM task_abc_cables WHERE task_id = ?");
    $del->bind_param("i", $taskId);
    $del->execute();
    $del->close();

    if (!$values) { return; }

    $ins = $conn->prepare("INSERT INTO task_abc_cables
                           (task_id, phase_type_id, cable_size_id, qty)
                           VALUES (?, ?, ?, ?)");
    foreach ($values as $phaseId => $row) {
        foreach ($row as $sizeId => $qty) {
            $p = (int)$phaseId; $s = (int)$sizeId; $q = (float)$qty;
            $ins->bind_param("iiid", $taskId, $p, $s, $q);
            $ins->execute();
        }
    }
    $ins->close();
}


/** Add up a grid that is already in hand. Never stored, always counted. */
function abcGridSum(array $values) {
    $n = 0.0;
    foreach ($values as $row) {
        foreach ($row as $qty) { $n += (float)$qty; }
    }
    return $n;
}


/**
 * Grand totals for a list of events, in one query rather than one each.
 * Returns  array( task_id => total )  with no entry for events that have none.
 */
function abcGridTotals(mysqli $conn, array $taskIds) {

    $out = array();
    if (!abcGridReady($conn) || !$taskIds) { return $out; }

    $ids = array();
    foreach ($taskIds as $i) { $i = (int)$i; if ($i > 0) { $ids[$i] = true; } }
    if (!$ids) { return $out; }

    $list = implode(',', array_keys($ids));   // whole numbers only, cast above
    $res  = $conn->query("SELECT task_id, SUM(qty) AS n FROM task_abc_cables
                          WHERE task_id IN ($list) GROUP BY task_id");
    while ($r = $res->fetch_assoc()) {
        $out[(int)$r['task_id']] = (float)$r['n'];
    }

    return $out;
}


/**
 * The grid as it appears on both event forms.
 *
 * Drawn inside the same kind of hidden .form-row the other conditional fields
 * use, so updateForm() shows and hides it with no rule of its own.
 */
function renderAbcGrid(mysqli $conn, array $values) {

    if (!abcGridReady($conn)) { return; }

    // Anything this event already holds stays on the grid even if the office
    // has since retired it.
    $heldPhases = array_keys($values);
    $heldSizes  = array();
    foreach ($values as $row) {
        foreach (array_keys($row) as $s) { $heldSizes[$s] = true; }
    }

    $phases = abcPhaseTypes($conn, $heldPhases);
    $sizes  = abcCableSizes($conn, array_keys($heldSizes));

    if (!$phases || !$sizes) { return; }
    ?>
    <div class="form-row" id="abc-grid-row" style="display:none;">
        <label>Cables</label>
        <div class="table-wrap">
            <table class="data plain-table" id="abc-grid">
                <thead>
                    <tr>
                        <th>Conversion</th>
                        <?php foreach ($sizes as $sid => $s) { ?>
                            <th class="num"><?php echo htmlspecialchars($s['name']);
                                  echo $s['active'] ? '' : ' <span class="field-hint">(retired)</span>'; ?></th>
                        <?php } ?>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($phases as $pid => $p) { ?>
                    <tr>
                        <th><?php echo htmlspecialchars($p['name']);
                              echo $p['active'] ? '' : ' <span class="field-hint">(retired)</span>'; ?></th>
                        <?php foreach ($sizes as $sid => $s) {
                            $v = isset($values[$pid][$sid]) ? abcNum($values[$pid][$sid]) : ''; ?>
                            <td class="num">
                                <input type="number" min="0" max="999999" step="0.001"
                                       inputmode="decimal" class="abc-cell"
                                       name="abc[<?php echo (int)$pid; ?>][<?php echo (int)$sid; ?>]"
                                       value="<?php echo htmlspecialchars((string)$v); ?>">
                            </td>
                        <?php } ?>
                        <td class="num abc-row-total">0</td>
                    </tr>
                <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <?php foreach ($sizes as $sid => $s) { ?>
                            <td class="num abc-col-total">0</td>
                        <?php } ?>
                        <td class="num abc-grand-total">0</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <span class="field-hint">
            Kilometres. Leave a box blank where there is none, and use a point for
            part of a kilometre &mdash; 1.5 is one and a half. The totals add up as
            you type, and are worked out again from the saved figures when the
            event is opened.
        </span>
    </div>
    <?php
}


/**
 * The same grid read-only, for the event detail page.
 * Every number here is read back from the database, totals included.
 */
function renderAbcGridView(mysqli $conn, array $values) {

    if (!abcGridReady($conn)) { return; }

    $heldPhases = array_keys($values);
    $heldSizes  = array();
    foreach ($values as $row) {
        foreach (array_keys($row) as $s) { $heldSizes[$s] = true; }
    }

    $phases = abcPhaseTypes($conn, $heldPhases);
    $sizes  = abcCableSizes($conn, array_keys($heldSizes));

    if (!$phases || !$sizes) { return; }

    $colTotal = array();
    foreach ($sizes as $sid => $s) { $colTotal[$sid] = 0.0; }
    $grand = 0.0;

    echo "<div class='table-wrap'><table class='data plain-table'>";

    echo "<thead><tr><th>Conversion</th>";
    foreach ($sizes as $sid => $s) {
        echo "<th class='num'>" . htmlspecialchars($s['name'])
           . ($s['active'] ? '' : " <span class='field-hint'>(retired)</span>") . "</th>";
    }
    echo "<th class='num'>Total</th></tr></thead><tbody>";

    foreach ($phases as $pid => $p) {

        $rowTotal = 0.0;
        echo "<tr><th>" . htmlspecialchars($p['name'])
           . ($p['active'] ? '' : " <span class='field-hint'>(retired)</span>") . "</th>";

        foreach ($sizes as $sid => $s) {
            $v = isset($values[$pid][$sid]) ? (float)$values[$pid][$sid] : 0.0;
            $rowTotal        += $v;
            $colTotal[$sid]  += $v;
            echo "<td class='num'>"
               . ($v > 0 ? abcNum($v) : "<span class='field-hint'>&mdash;</span>") . "</td>";
        }

        $grand += $rowTotal;
        echo "<td class='num'><strong>" . abcNum($rowTotal) . "</strong></td></tr>";
    }

    echo "</tbody><tfoot><tr><th>Total</th>";
    foreach ($sizes as $sid => $s) {
        echo "<td class='num'>" . abcNum($colTotal[$sid]) . "</td>";
    }
    echo "<td class='num'><strong>" . abcNum($grand) . "</strong></td></tr></tfoot>";

    echo "</table></div>";
}


/**
 * The live totals. Included by both forms, next to taskFormScript().
 *
 * These numbers are for the person typing only. What gets stored is counted
 * again from the boxes on the server, and what gets shown afterwards is
 * counted from the database, so a wrong number here can never be saved.
 */
function abcGridScript() {
    ?>
/* Kilometres add up with a point in them, and adding fractions in binary
   leaves crumbs — 0.1 + 0.2 comes to 0.30000000000000004. Rounding to the
   metre and dropping trailing noughts is what turns that back into 0.3. */
function abcTidy(n) {
    var s = (Math.round(n * 1000) / 1000).toFixed(3);
    if (s.indexOf('.') !== -1) { s = s.replace(/0+$/, '').replace(/\.$/, ''); }
    return s === '' ? '0' : s;
}

function abcRecalc() {

    var table = document.getElementById('abc-grid');
    if (!table) { return; }

    var rows  = table.tBodies[0].rows;
    var grand = 0;
    var cols  = [];

    for (var r = 0; r < rows.length; r++) {

        var cells = rows[r].querySelectorAll('.abc-cell');
        var rowTotal = 0;

        for (var c = 0; c < cells.length; c++) {
            var n = parseFloat(cells[c].value);
            if (isNaN(n) || n < 0) { n = 0; }      // blank is none, not an error
            rowTotal += n;
            cols[c] = (cols[c] || 0) + n;
        }

        var rt = rows[r].querySelector('.abc-row-total');
        if (rt) { rt.textContent = abcTidy(rowTotal); }
        grand += rowTotal;
    }

    var colCells = table.querySelectorAll('.abc-col-total');
    for (var k = 0; k < colCells.length; k++) {
        colCells[k].textContent = abcTidy(cols[k] || 0);
    }

    var g = table.querySelector('.abc-grand-total');
    if (g) { g.textContent = abcTidy(grand); }
}

(function () {
    var cells = document.querySelectorAll('.abc-cell');
    for (var i = 0; i < cells.length; i++) {
        cells[i].addEventListener('input', abcRecalc);
    }
    abcRecalc();
})();
    <?php
}


/**
 * The grid flattened into one named value per box, for the change log.
 *
 * Every box of the grid is listed, not only the filled ones, so that a number
 * being cleared is recorded as a change just like a number being added.
 * logChanges() then writes a row only where the before and after differ.
 */
function abcGridLogValues(mysqli $conn, array $values, array $alsoPhases = array(),
                          array $alsoSizes = array()) {

    $out = array();
    if (!abcGridReady($conn)) { return $out; }

    $phaseIds = array_merge(array_keys($values), $alsoPhases);
    $sizeIds  = $alsoSizes;
    foreach ($values as $row) {
        foreach (array_keys($row) as $s) { $sizeIds[] = $s; }
    }

    $phases = abcPhaseTypes($conn, $phaseIds);
    $sizes  = abcCableSizes($conn, $sizeIds);

    foreach ($phases as $pid => $p) {
        foreach ($sizes as $sid => $s) {
            $label = 'Cables: ' . $p['name'] . ' — ' . $s['name'];
            $out[$label] = isset($values[$pid][$sid])
                         ? abcNum($values[$pid][$sid]) : '';
        }
    }

    return $out;
}


/* ===========================================================================
   Load Reading — the office's "Substation Load Data 2026" sheet.

   Shown on Add Event / Edit Event when the work type has needs_load_reading
   (Load Reading, LR, type_id 15), by the same rule as the ABC grid.

   What is typed is stored as it is: one row in task_load_feeders for each
   feeder that has at least one reading, and one row in task_load_info for
   the time, the six voltages, who collected it and the recommended action.
   A blank box is "not read" and stays NULL — never zero.

   Totals and percentages are never stored. The browser works them out live
   while the boxes are filled in, and event.php works them out again from
   the stored readings, with lrCalc(), every time the event is opened.

   Everything below does nothing until the tables exist, so these functions
   are safe to call before load_reading_schema.sql has been run.
   =========================================================================== */

/* The three numbers in the office sheet's formulas, used exactly as the
   sheet uses them so the system and the paper always give the same answer:

     rated current (A) = kVA * 1000 / (LR_LINE_VOLTS * LR_ROOT_THREE)
     substation load (kVA) = (R + Y + B) * LR_PHASE_VOLTS / 1000

   The sheet uses 1.72 for the square root of three, not 1.732. Keep it —
   "correcting" it would make every percentage disagree with the paper. */
const LR_LINE_VOLTS  = 400;
const LR_ROOT_THREE  = 1.72;
const LR_PHASE_VOLTS = 230;

/** Feeder rows on the sheet, F1 to F7. */
const LR_FEEDERS = 7;


/** The two feeder tables, as the sheet heads them. 1 = with solar. */
function lrSides() {
    return array(1 => 'With Solar Load Reading', 0 => 'Without Solar Load Reading');
}

/** Short names for the two tables, for the change log and error messages. */
function lrSideShort($side) {
    return ((int)$side === 1) ? 'With Solar' : 'Without Solar';
}

/** The four columns of a feeder table. N is recorded but never added in. */
function lrPhases() {
    return array('r' => 'R', 'y' => 'Y', 'b' => 'B', 'n' => 'N');
}

/** The six LV voltages, in the order the sheet lists them. */
function lrVoltages() {
    return array('rn' => 'RN', 'yn' => 'YN', 'bn' => 'BN',
                 'ry' => 'RY', 'rb' => 'RB', 'yb' => 'YB');
}


/**
 * Are the Load Reading tables, the flag column and transformers.lt_feeders
 * in place yet? Asked once per page load. Until they are, the form never
 * appears and the event pages work exactly as before.
 */
function loadReadingReady(mysqli $conn) {

    static $ready = null;
    if ($ready !== null) { return $ready; }

    $ready = false;

    try {
        $res = $conn->query(
            "SELECT
               (SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN ('task_load_feeders','task_load_info')) AS t,
               (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND ((table_name = 'task_types'   AND column_name = 'needs_load_reading')
                     OR (table_name = 'transformers' AND column_name = 'lt_feeders'))) AS c"
        );
        $row = $res ? $res->fetch_assoc() : null;

        $ready = ($row && (int)$row['t'] === 2 && (int)$row['c'] === 2);

    } catch (mysqli_sql_exception $e) {
        $ready = false;
    }

    return $ready;
}


/**
 * An empty sheet. Every value is a string exactly as it would sit in its
 * box, so the same array serves the form, the error redisplay and the log.
 *
 *   feeders[side][feeder_no][phase]   side 1 = with solar, 0 = without
 *   volts[key]                        rn, yn, bn, ry, rb, yb
 *   time, by, action
 */
function lrBlank() {

    $out = array('feeders' => array(), 'volts' => array(),
                 'time' => '', 'by' => '', 'action' => '');

    foreach (lrSides() as $side => $label) {
        for ($f = 1; $f <= LR_FEEDERS; $f++) {
            foreach (lrPhases() as $p => $pl) { $out['feeders'][$side][$f][$p] = ''; }
        }
    }
    foreach (lrVoltages() as $v => $vl) { $out['volts'][$v] = ''; }

    return $out;
}


/**
 * A reading as it should be written back: 60.50 becomes 60.5, 60.00 becomes
 * 60, ".5" becomes 0.5. Blank stays blank. Used for the form, the event page
 * and the change log, so the same number is always spelt the same way.
 */
function lrNum($v) {
    $v = trim((string)$v);
    if ($v === '' || !is_numeric($v)) { return $v; }
    $s = number_format((float)$v, 2, '.', '');
    if (strpos($s, '.') !== false) { $s = rtrim(rtrim($s, '0'), '.'); }
    return $s;
}


/**
 * Is this a reading that may be stored? Digits with at most two decimal
 * places — "125", "125.5", "125." and ".5" all count — and no larger than
 * the column holds. Letters, a minus sign, a comma and spaces inside do not.
 */
function lrValidNum($raw, $intDigits) {
    return (bool)preg_match('/^(\d{1,' . (int)$intDigits . '}(\.\d{0,2})?|\.\d{1,2})$/', $raw);
}


/**
 * Read the sheet from what the browser posted.
 *
 * Returns array($values, $errors). $values is everything as typed, bad
 * boxes included, so the form can be shown again exactly as it was left.
 * $errors is a list of messages, empty when every box is fine.
 */
function loadReadingFromPost(array $post) {

    $values = lrBlank();
    $errors = array();

    $lr = (isset($post['lr']) && is_array($post['lr'])) ? $post['lr'] : array();

    $pick = function ($arr, $key) {
        return (is_array($arr) && isset($arr[$key]) && !is_array($arr[$key]))
             ? trim((string)$arr[$key]) : '';
    };

    // The two feeder tables. Amps: DECIMAL(8,2), so six digits before the point.
    $posted = isset($lr['f']) && is_array($lr['f']) ? $lr['f'] : array();
    foreach (lrSides() as $side => $sideLabel) {
        $sideRows = isset($posted[$side]) && is_array($posted[$side]) ? $posted[$side] : array();
        for ($f = 1; $f <= LR_FEEDERS; $f++) {
            $row = isset($sideRows[$f]) ? $sideRows[$f] : array();
            foreach (lrPhases() as $p => $pLabel) {
                $raw = $pick($row, $p);
                $values['feeders'][$side][$f][$p] = $raw;
                if ($raw !== '' && !lrValidNum($raw, 6)) {
                    $errors[] = '"' . $raw . '" in ' . $sideLabel . ', F' . $f . ' ' . $pLabel;
                }
            }
        }
    }

    // LV voltages: DECIMAL(7,2), five digits before the point.
    $volts = isset($lr['v']) && is_array($lr['v']) ? $lr['v'] : array();
    foreach (lrVoltages() as $v => $vLabel) {
        $raw = $pick($volts, $v);
        $values['volts'][$v] = $raw;
        if ($raw !== '' && !lrValidNum($raw, 5)) {
            $errors[] = '"' . $raw . '" in LV Voltage ' . $vLabel;
        }
    }

    $values['time']   = $pick($lr, 'time');
    $values['by']     = $pick($lr, 'by');
    $values['action'] = $pick($lr, 'action');

    $numberErrors = count($errors);

    if ($values['time'] !== ''
        && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $values['time'])) {
        $errors[] = 'Time of Reading must be a time such as 14:30.';
    }
    if (mb_strlen($values['by']) > 100) {
        $errors[] = 'Data Collected By can be at most 100 characters.';
    }
    if (strlen($values['action']) > 65000) {
        $errors[] = 'Recommended Action is too long.';
    }

    // One sentence for the bad readings, naming each box, so it can be found.
    if ($numberErrors > 0) {
        $bad   = array_slice($errors, 0, $numberErrors);
        $shown = array_slice($bad, 0, 4);
        $more  = count($bad) - count($shown);
        $msg   = 'Load Reading: ' . implode(', ', $shown)
               . ($more > 0 ? ' and ' . $more . ' more' : '')
               . (count($bad) === 1 ? ' is not a valid reading.' : ' are not valid readings.')
               . ' Use digits only, with at most 2 decimal places (e.g. 125.5)'
               . ' — no letters or minus sign. Leave a box blank if it was not read.';
        $errors = array_merge(array($msg), array_slice($errors, $numberErrors));
    }

    return array($values, $errors);
}


/** The messages from loadReadingFromPost() as the one line the page shows. */
function lrErrorMessage(array $errors) {
    return implode(' ', $errors);
}


/** Does this sheet hold anything at all? */
function lrHasAny(array $values) {
    foreach ($values['feeders'] as $rows) {
        foreach ($rows as $row) {
            foreach ($row as $v) { if ($v !== '') { return true; } }
        }
    }
    foreach ($values['volts'] as $v) { if ($v !== '') { return true; } }
    return ($values['time'] !== '' || $values['by'] !== '' || $values['action'] !== '');
}


/** Does one of the two feeder tables hold any reading? */
function lrSideHasAny(array $rows) {
    foreach ($rows as $row) {
        foreach ($row as $v) { if ($v !== '') { return true; } }
    }
    return false;
}


/**
 * Read the sheet back for one event, in the same shape as lrBlank().
 */
function loadLoadReading(mysqli $conn, $taskId) {

    $out = lrBlank();
    if (!loadReadingReady($conn)) { return $out; }

    $taskId = (int)$taskId;

    $st = $conn->prepare("SELECT with_solar, feeder_no, r_amp, y_amp, b_amp, n_amp
                          FROM task_load_feeders WHERE task_id = ?");
    $st->bind_param("i", $taskId);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $side = (int)$r['with_solar'] === 1 ? 1 : 0;
        $f    = (int)$r['feeder_no'];
        if ($f < 1 || $f > LR_FEEDERS) { continue; }
        foreach (lrPhases() as $p => $pl) {
            $v = $r[$p . '_amp'];
            $out['feeders'][$side][$f][$p] = ($v === null) ? '' : lrNum($v);
        }
    }
    $st->close();

    $st = $conn->prepare("SELECT * FROM task_load_info WHERE task_id = ?");
    $st->bind_param("i", $taskId);
    $st->execute();
    $info = $st->get_result()->fetch_assoc();
    $st->close();

    if ($info) {
        foreach (lrVoltages() as $v => $vl) {
            $out['volts'][$v] = ($info['v_' . $v] === null) ? '' : lrNum($info['v_' . $v]);
        }
        $out['time']   = $info['reading_time'] ? substr($info['reading_time'], 0, 5) : '';
        $out['by']     = (string)$info['collected_by'];
        $out['action'] = (string)$info['recommended_action'];
    }

    return $out;
}


/**
 * Write the sheet for one event.
 *
 * The event's own rows are removed and what was filled in goes back: one
 * feeder row for each feeder with at least one reading, and the info row
 * only when there is something to put in it. The same per-event rewrite as
 * the ABC grid; it never touches another event's rows.
 *
 * $values must already have passed loadReadingFromPost() without errors.
 */
function saveLoadReading(mysqli $conn, $taskId, array $values) {

    if (!loadReadingReady($conn)) { return; }

    $taskId = (int)$taskId;

    foreach (array('task_load_feeders', 'task_load_info') as $table) {
        $del = $conn->prepare("DELETE FROM $table WHERE task_id = ?");
        $del->bind_param("i", $taskId);
        $del->execute();
        $del->close();
    }

    // A blank box goes in as NULL — not read — never as zero.
    $num = function ($v) { return ($v === '') ? null : round((float)$v, 2); };

    $ins = $conn->prepare("INSERT INTO task_load_feeders
                           (task_id, with_solar, feeder_no, r_amp, y_amp, b_amp, n_amp)
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($values['feeders'] as $side => $rows) {
        foreach ($rows as $f => $row) {
            if (implode('', $row) === '') { continue; }
            $s = (int)$side; $fn = (int)$f;
            $r = $num($row['r']); $y = $num($row['y']);
            $b = $num($row['b']); $n = $num($row['n']);
            $ins->bind_param("iiidddd", $taskId, $s, $fn, $r, $y, $b, $n);
            $ins->execute();
        }
    }
    $ins->close();

    $infoFilled = ($values['time'] !== '' || $values['by'] !== '' || $values['action'] !== ''
                   || implode('', $values['volts']) !== '');
    if (!$infoFilled) { return; }

    $time   = ($values['time']   !== '') ? $values['time']   : null;
    $by     = ($values['by']     !== '') ? $values['by']     : null;
    $action = ($values['action'] !== '') ? $values['action'] : null;
    $v      = array();
    foreach (lrVoltages() as $k => $kl) { $v[$k] = $num($values['volts'][$k]); }

    $ins = $conn->prepare("INSERT INTO task_load_info
                           (task_id, reading_time, v_rn, v_yn, v_bn, v_ry, v_rb, v_yb,
                            collected_by, recommended_action)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->bind_param("isddddddss", $taskId, $time,
                     $v['rn'], $v['yn'], $v['bn'], $v['ry'], $v['rb'], $v['yb'],
                     $by, $action);
    $ins->execute();
    $ins->close();
}


/**
 * The sheet's calculations for one feeder table — the PHP twin of lrRecalc()
 * in the browser. Nothing here is ever stored.
 *
 * Returns totals (amps), pct (fractions, or null when the capacity is not
 * known), rated (A, or null), kva and loadPct (fraction, or null), and any
 * (false when the table holds no reading at all).
 */
function lrCalc(array $rows, $capacityKva) {

    $tot = array('r' => 0.0, 'y' => 0.0, 'b' => 0.0);
    $any = false;

    foreach ($rows as $row) {
        foreach ($row as $p => $v) {
            if ($v === '' || !is_numeric($v)) { continue; }
            $any = true;
            if (isset($tot[$p])) { $tot[$p] += (float)$v; }
        }
    }

    $kvaCap = (float)$capacityKva;
    $rated  = ($kvaCap > 0) ? $kvaCap * 1000 / (LR_LINE_VOLTS * LR_ROOT_THREE) : null;
    $load   = ($tot['r'] + $tot['y'] + $tot['b']) * LR_PHASE_VOLTS / 1000;

    $pct = array();
    foreach ($tot as $p => $t) { $pct[$p] = $rated ? $t / $rated : null; }

    return array(
        'any'     => $any,
        'totals'  => $tot,
        'rated'   => $rated,
        'pct'     => $pct,
        'kva'     => $load,
        'loadPct' => ($kvaCap > 0) ? $load / $kvaCap : null,
    );
}

/** Amps and kVA: always two decimals, as the sheet shows them. */
function lrFmt($n) {
    return number_format((float)$n, 2, '.', ',');
}

/** A fraction as the sheet's 0.00%, or a dash when it cannot be worked out. */
function lrPct($fraction) {
    return ($fraction === null)
         ? "<span class='field-hint'>&mdash;</span>"
         : number_format($fraction * 100, 2, '.', ',') . '%';
}


/**
 * The Load Reading form, for both event forms.
 *
 * $tx describes the transformer, shown read-only at the top of the form:
 * area, csc, name, type, code, kva, ltf. Add Event passes nothing and fills
 * those in from the browser as the transformer is chosen; Edit Event passes
 * the event's transformer, with kva = the capacity when the event was raised.
 *
 * Drawn inside a hidden .form-row, so updateForm() shows and hides it by the
 * same rule as every other conditional field.
 */
function renderLoadReadingForm(mysqli $conn, array $values, array $tx = array()) {

    if (!loadReadingReady($conn)) { return; }

    $h    = function ($k) use ($tx) {
        return (isset($tx[$k]) && trim((string)$tx[$k]) !== '')
             ? htmlspecialchars((string)$tx[$k]) : '&mdash;';
    };
    $kva  = isset($tx['kva']) ? (int)$tx['kva'] : 0;
    $info = function ($id, $content) { return "<span id='" . $id . "'>" . $content . "</span>"; };
    ?>
    <div class="form-row" id="load-reading-row" style="display:none;"
         data-kva="<?php echo $kva; ?>">
        <label>Load Reading</label>
        <span class="field-hint">
            As on the Substation Load Data sheet. The transformer details come from
            the register; the date is the event's Date below.
        </span>

        <div class="form-row form-grid">
            <div class="form-col"><?php renderDetailTable(array(
                'Area'       => $info('lr-tx-area', $h('area')),
                'CSC'        => $info('lr-tx-csc',  $h('csc')),
                'Substation' => $info('lr-tx-name', $h('name')),
                'Type'       => $info('lr-tx-type', $h('type')),
            )); ?></div>
            <div class="form-col last"><?php renderDetailTable(array(
                'S/N'              => $info('lr-tx-code', $h('code')),
                'Capacity (kVA)'   => $info('lr-tx-kva',  $kva > 0 ? (string)$kva : '&mdash;'),
                'No. of LT Feeders' => $info('lr-tx-ltf', $h('ltf')),
                'Rated Current'    => "<span id='lr-rated'>&mdash;</span>",
            )); ?></div>
        </div>

        <div class="form-grid">
            <div class="form-col">
                <div class="form-row">
                    <label for="lr-time">Time of Reading</label>
                    <input type="time" id="lr-time" name="lr[time]"
                           value="<?php echo htmlspecialchars($values['time']); ?>">
                </div>
            </div>
            <div class="form-col last">
                <div class="form-row">
                    <label for="lr-by">Data Collected By</label>
                    <input type="text" id="lr-by" name="lr[by]" maxlength="100"
                           value="<?php echo htmlspecialchars($values['by']); ?>">
                    <span class="field-hint">Name, or names separated by commas.</span>
                </div>
            </div>
        </div>

        <div class="form-grid">
            <?php $col = 0;
            foreach (lrSides() as $side => $sideLabel) { ?>
            <div class="form-col<?php echo ($col++ === 1) ? ' last' : ''; ?>">
                <div class="form-row">
                    <label><?php echo htmlspecialchars($sideLabel); ?> (A)</label>
                    <div class="table-wrap">
                        <table class="data plain-table lr-feeders" data-side="<?php echo (int)$side; ?>">
                            <thead>
                                <tr>
                                    <th>Feeder</th>
                                    <?php foreach (lrPhases() as $p => $pl) { ?>
                                        <th class="num"><?php echo $pl; ?></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php for ($f = 1; $f <= LR_FEEDERS; $f++) { ?>
                                <tr>
                                    <th>F<?php echo $f; ?></th>
                                    <?php foreach (lrPhases() as $p => $pl) { ?>
                                        <td class="num">
                                            <input type="text" inputmode="decimal" maxlength="10"
                                                   class="lr-cell" data-phase="<?php echo $p; ?>" data-int="6"
                                                   aria-label="<?php echo htmlspecialchars(lrSideShort($side) . ' F' . $f . ' ' . $pl); ?>"
                                                   name="lr[f][<?php echo (int)$side; ?>][<?php echo $f; ?>][<?php echo $p; ?>]"
                                                   value="<?php echo htmlspecialchars($values['feeders'][$side][$f][$p]); ?>">
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <td class="num lr-total" data-phase="r">&mdash;</td>
                                    <td class="num lr-total" data-phase="y">&mdash;</td>
                                    <td class="num lr-total" data-phase="b">&mdash;</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <th>%</th>
                                    <td class="num lr-pct" data-phase="r">&mdash;</td>
                                    <td class="num lr-pct" data-phase="y">&mdash;</td>
                                    <td class="num lr-pct" data-phase="b">&mdash;</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <th>Load</th>
                                    <td class="num lr-load" colspan="2">&mdash;</td>
                                    <td class="num lr-loadpct" colspan="2">&mdash;</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <div class="form-grid">
            <div class="form-col">
                <div class="form-row">
                    <label>LV Voltage (V)</label>
                    <div class="table-wrap">
                        <table class="data plain-table">
                            <tbody>
                            <?php foreach (lrVoltages() as $v => $vl) { ?>
                                <tr>
                                    <th><?php echo $vl; ?></th>
                                    <td class="num">
                                        <input type="text" inputmode="decimal" maxlength="9"
                                               class="lr-volt" data-int="5"
                                               aria-label="LV Voltage <?php echo $vl; ?>"
                                               name="lr[v][<?php echo $v; ?>]"
                                               value="<?php echo htmlspecialchars($values['volts'][$v]); ?>">
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="form-col last">
                <div class="form-row">
                    <label for="lr-action">Recommended Action</label>
                    <textarea id="lr-action" name="lr[action]" rows="6"><?php
                        echo htmlspecialchars($values['action']); ?></textarea>
                </div>
            </div>
        </div>

        <span class="field-hint">
            Amps and volts, with a point for decimals. Leave a box blank if it was not
            read. Totals and percentages work themselves out as you type, from the
            capacity above, and are worked out again from the saved readings when
            the event is opened &mdash; they are never stored.
        </span>
    </div>
    <?php
}


/**
 * The sheet read-only, for event.php. Every total and percentage is worked
 * out here from the stored readings and the event's capacity_before.
 */
function renderLoadReadingView(array $values, $capacityKva) {

    $dash = "<span class='field-hint'>&mdash;</span>";
    $cell = function ($v) use ($dash) { return ($v === '') ? $dash : htmlspecialchars(lrNum($v)); };
    ?>
    <div class="panel-body">
        <div class="form-grid">
            <?php $col = 0;
            foreach (lrSides() as $side => $sideLabel) {
                $rows = $values['feeders'][$side];
                $c    = lrCalc($rows, $capacityKva); ?>
            <div class="form-col<?php echo ($col++ === 1) ? ' last' : ''; ?>">
                <div class="form-row">
                    <label><?php echo htmlspecialchars($sideLabel); ?> (A)</label>
                    <div class="table-wrap">
                        <table class="data plain-table">
                            <thead>
                                <tr>
                                    <th>Feeder</th>
                                    <?php foreach (lrPhases() as $p => $pl) { ?>
                                        <th class="num"><?php echo $pl; ?></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php for ($f = 1; $f <= LR_FEEDERS; $f++) { ?>
                                <tr>
                                    <th>F<?php echo $f; ?></th>
                                    <?php foreach (lrPhases() as $p => $pl) { ?>
                                        <td class="num"><?php echo $cell($rows[$f][$p]); ?></td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                            </tbody>
                            <tfoot>
                                <?php if (!$c['any']) { ?>
                                <tr><td colspan="5"><span class="field-hint">Not read.</span></td></tr>
                                <?php } else { ?>
                                <tr>
                                    <th>Total</th>
                                    <?php foreach (array('r', 'y', 'b') as $p) { ?>
                                        <td class="num"><strong><?php echo lrFmt($c['totals'][$p]); ?></strong></td>
                                    <?php } ?>
                                    <td></td>
                                </tr>
                                <tr>
                                    <th>%</th>
                                    <?php foreach (array('r', 'y', 'b') as $p) { ?>
                                        <td class="num"><?php echo lrPct($c['pct'][$p]); ?></td>
                                    <?php } ?>
                                    <td></td>
                                </tr>
                                <tr>
                                    <th>Load</th>
                                    <td class="num" colspan="2"><strong><?php echo lrFmt($c['kva']); ?> kVA</strong></td>
                                    <td class="num" colspan="2"><?php echo lrPct($c['loadPct']); ?></td>
                                </tr>
                                <?php } ?>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <div class="form-grid">
            <div class="form-col">
                <div class="form-row">
                    <label>LV Voltage (V)</label>
                    <div class="table-wrap">
                        <table class="data plain-table">
                            <tbody>
                            <?php foreach (lrVoltages() as $v => $vl) { ?>
                                <tr>
                                    <th><?php echo $vl; ?></th>
                                    <td class="num"><?php echo $cell($values['volts'][$v]); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="form-col last">
                <?php renderDetailTable(array(
                    'Time of Reading'    => ($values['time'] !== '')
                                            ? htmlspecialchars($values['time']) : $dash,
                    'Data Collected By'  => ($values['by'] !== '')
                                            ? htmlspecialchars($values['by']) : $dash,
                    'Recommended Action' => ($values['action'] !== '')
                                            ? nl2br(htmlspecialchars($values['action'])) : $dash,
                )); ?>
            </div>
        </div>
    </div>
    <?php
}


/**
 * The live totals and percentages. Included by both forms, next to
 * taskFormScript(). For the person typing only — nothing worked out here
 * is posted or stored.
 */
function loadReadingScript() {
    ?>
var LR_LINE_VOLTS  = <?php echo json_encode(LR_LINE_VOLTS); ?>;
var LR_ROOT_THREE  = <?php echo json_encode(LR_ROOT_THREE); ?>;
var LR_PHASE_VOLTS = <?php echo json_encode(LR_PHASE_VOLTS); ?>;

/* Two decimals, rounded the way PHP's number_format() rounds, so the live
   figure and the one on the event page never differ in the last digit. */
function lrFix2(n) {
    return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
}

/* The value of one box: a number, or null when it is blank. A box that is
   not a valid reading counts as blank in the totals and is outlined, so it
   can be found before Save — the server refuses it either way. */
function lrRead(box) {
    var s = box.value.trim();
    if (s === '') { box.removeAttribute('aria-invalid'); return null; }
    var digits = box.getAttribute('data-int') || '6';
    var ok = new RegExp('^(\\d{1,' + digits + '}(\\.\\d{0,2})?|\\.\\d{1,2})$').test(s);
    if (!ok) { box.setAttribute('aria-invalid', 'true'); return null; }
    box.removeAttribute('aria-invalid');
    return parseFloat(s);
}

function lrRecalc() {

    var wrap = document.getElementById('load-reading-row');
    if (!wrap) { return; }

    var kva   = parseFloat(wrap.getAttribute('data-kva'));
    if (!(kva > 0)) { kva = 0; }
    var rated = kva > 0 ? kva * 1000 / (LR_LINE_VOLTS * LR_ROOT_THREE) : 0;
    var dash  = '—';

    var ratedBox = document.getElementById('lr-rated');
    if (ratedBox) { ratedBox.textContent = kva > 0 ? lrFix2(rated) + ' A' : dash; }

    var tables = wrap.querySelectorAll('.lr-feeders');
    for (var t = 0; t < tables.length; t++) {

        var tot = { r: 0, y: 0, b: 0 };
        var any = false;

        var cells = tables[t].querySelectorAll('.lr-cell');
        for (var c = 0; c < cells.length; c++) {
            var v = lrRead(cells[c]);
            if (v === null) { continue; }
            any = true;
            var ph = cells[c].getAttribute('data-phase');
            if (tot.hasOwnProperty(ph)) { tot[ph] += v; }
        }

        var phases = ['r', 'y', 'b'];
        for (var p = 0; p < phases.length; p++) {
            var tc = tables[t].querySelector('.lr-total[data-phase="' + phases[p] + '"]');
            var pc = tables[t].querySelector('.lr-pct[data-phase="' + phases[p] + '"]');
            if (tc) { tc.textContent = any ? lrFix2(tot[phases[p]]) : dash; }
            if (pc) { pc.textContent = (any && rated > 0)
                                       ? lrFix2(tot[phases[p]] / rated * 100) + '%' : dash; }
        }

        var load = (tot.r + tot.y + tot.b) * LR_PHASE_VOLTS / 1000;
        var lc = tables[t].querySelector('.lr-load');
        var lp = tables[t].querySelector('.lr-loadpct');
        if (lc) { lc.textContent = any ? lrFix2(load) + ' kVA' : dash; }
        if (lp) { lp.textContent = (any && kva > 0) ? lrFix2(load / kva * 100) + '%' : dash; }
    }

    var volts = wrap.querySelectorAll('.lr-volt');
    for (var k = 0; k < volts.length; k++) { lrRead(volts[k]); }
}

/* Add Event: show the chosen transformer's details and use its capacity.
   info is null when no transformer is chosen. */
function lrShowTx(info) {
    var wrap = document.getElementById('load-reading-row');
    if (!wrap) { return; }
    var dash = '—';
    var put = function (id, v) {
        var el = document.getElementById(id);
        if (el) { el.textContent = (v === null || v === undefined || String(v) === '') ? dash : v; }
    };
    put('lr-tx-area', info ? info.area : null);
    put('lr-tx-csc',  info ? info.csc  : null);
    put('lr-tx-name', info ? info.name : null);
    put('lr-tx-type', info ? info.type : null);
    put('lr-tx-code', info ? info.code : null);
    put('lr-tx-kva',  info && info.kva > 0 ? info.kva : null);
    put('lr-tx-ltf',  info ? info.ltf  : null);
    wrap.setAttribute('data-kva', info && info.kva > 0 ? info.kva : 0);
    lrRecalc();
}

(function () {
    var boxes = document.querySelectorAll('.lr-cell, .lr-volt');
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].addEventListener('input', lrRecalc);
    }
    lrRecalc();
})();
    <?php
}


/**
 * The sheet flattened into one named value per box, for the change log.
 * Every box is listed, so clearing a reading is recorded as plainly as
 * entering one; logChanges() writes a row only where before and after differ.
 */
function loadReadingLogValues(array $values) {

    $out = array();

    $out['Load Reading: Time'] = $values['time'];

    foreach (lrSides() as $side => $sideLabel) {
        for ($f = 1; $f <= LR_FEEDERS; $f++) {
            foreach (lrPhases() as $p => $pl) {
                $out['Load Reading: ' . lrSideShort($side) . ' F' . $f . ' ' . $pl]
                    = lrNum($values['feeders'][$side][$f][$p]);
            }
        }
    }

    foreach (lrVoltages() as $v => $vl) {
        $out['Load Reading: LV Voltage ' . $vl] = lrNum($values['volts'][$v]);
    }

    $out['Load Reading: Data Collected By']  = $values['by'];
    $out['Load Reading: Recommended Action'] = $values['action'];

    return $out;
}


/**
 * What to call an event on screen.
 *
 * The proposal number names an event on paper, so it is used whenever there
 * is one. Not every job gets one, though, so rather than showing a record
 * with no name at all it falls back to the job number, and then to the
 * event's own id, which always exists.
 *
 * Takes any row carrying request_id / job_no and either task_id or the
 * change log's record_id.
 */
function eventLabel(array $row) {

    if (trim((string)($row['request_id'] ?? '')) !== '') {
        return trim($row['request_id']);
    }

    if (trim((string)($row['job_no'] ?? '')) !== '') {
        return 'Job ' . trim($row['job_no']);
    }

    $id = (int)($row['task_id'] ?? ($row['record_id'] ?? 0));

    return $id > 0 ? 'Event #' . $id : 'Event';
}


/**
 * 12.50 and 12.5 written out the same way, so that the change log can tell
 * them apart from a real edit. Anything that is not a plain decimal number
 * comes back untouched.
 */
function dropTrailingZeros(string $v) {

    if (!preg_match('/^-?\d+\.\d+$/', $v)) { return $v; }

    return rtrim(rtrim($v, '0'), '.');
}


/**
 * Record what changed on a record.
 * Only real differences are written, one row per field.
 */
function logChanges(mysqli $conn, string $recordType, int $recordId,
                    array $before, array $after, int $userId) {

    $stmt = $conn->prepare(
        "INSERT INTO change_log
         (record_type, record_id, field_name, old_value, new_value, changed_by, changed_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );

    foreach ($after as $field => $newVal) {

        $oldVal = isset($before[$field]) ? $before[$field] : null;

        // Treat null and empty string as the same thing.
        $o = ($oldVal === null) ? '' : trim((string)$oldVal);
        $n = ($newVal === null) ? '' : trim((string)$newVal);

        if ($o === $n) { continue; }

        /* 12312312.00 and 12312312 are the same amount of money. MySQL hands
           a DECIMAL column back carrying its trailing zeros and the form
           posts it straight back without them, so comparing the two as text
           called that an edit and wrote a history row for a box nobody had
           touched.

           Only zeros AFTER a decimal point are dropped. Leading zeros are
           left exactly where they are, because job no 0016 and job no 16
           are two different job numbers even though they are the same
           number — and nothing else is touched, so "UBB 105" stays text. */
        if (dropTrailingZeros($o) === dropTrailingZeros($n)) { continue; }

        $oStore = ($o === '') ? null : $o;
        $nStore = ($n === '') ? null : $n;

        $stmt->bind_param("sisssi",
            $recordType, $recordId, $field, $oStore, $nStore, $userId);
        $stmt->execute();
    }

    $stmt->close();
}


/**
 * Print the transformer detail table.
 * Used by transformer.php.
 */
function renderTransformerDetails(mysqli $conn, int $transformerId) {

    $stmt = $conn->prepare(
        "SELECT t.transformer_code, t.substation_name, t.capacity_kva,
                t.transformer_type, t.lt_feeders, t.serial_no, t.proposal_no, t.details,
                csc.csc_name, csc.csc_code,
                areas.area_name, provinces.province_name
         FROM transformers t
         LEFT JOIN csc       ON t.csc_id = csc.csc_id
         LEFT JOIN areas     ON csc.area_id = areas.area_id
         LEFT JOIN provinces ON areas.province_id = provinces.province_id
         WHERE t.transformer_id = ?"
    );
    // Removed transformers are shown too — transformer.php marks them as removed.
    $stmt->bind_param("i", $transformerId);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tx) {
        echo "<div class='table-empty'>Transformer not found.</div>";
        return;
    }

    $blank = "<span class='field-hint'>Not recorded</span>";

    $rows = array(
        'S/N'             => "<span class='code-cell'>"
                             . htmlspecialchars($tx['transformer_code']) . "</span>",
        'Substation Name' => (trim((string)$tx['substation_name']) !== '')
                             ? htmlspecialchars($tx['substation_name']) : $blank,
        'Capacity'        => $tx['capacity_kva']
                             ? $tx['capacity_kva'] . " kVA" : $blank,
        'Type'            => (trim((string)$tx['transformer_type']) !== '')
                             ? htmlspecialchars($tx['transformer_type']) : $blank,
        'No. of LT Feeders' => ($tx['lt_feeders'] !== null)
                             ? (int)$tx['lt_feeders'] : $blank,
        'Serial No'       => (trim((string)$tx['serial_no']) !== '')
                             ? "<span class='code-cell'>"
                               . htmlspecialchars($tx['serial_no']) . "</span>"
                             : $blank,
        'Proposal No'     => (trim((string)$tx['proposal_no']) !== '')
                             ? "<span class='code-cell'>"
                               . htmlspecialchars($tx['proposal_no']) . "</span>"
                             : $blank,
        'CSC'            => htmlspecialchars($tx['csc_name']
                             . " (" . $tx['csc_code'] . ")"),
        'Area'            => htmlspecialchars($tx['area_name']),
        'Province'        => htmlspecialchars($tx['province_name']),
        'Details'         => (trim((string)$tx['details']) !== '')
                             ? htmlspecialchars($tx['details']) : $blank
    );

    // Left is the unit itself, right is the proposal it came from, where it
    // stands and anything noted about it. Six and five since LT Feeders
    // joined the unit's own details.
    renderDetailColumns($rows, 6);
}


/**
 * One half of a detail panel: a plain label-and-value table.
 * The values are already built as HTML by the caller, so they go out as they
 * are; only the labels are escaped here.
 */
function renderDetailTable(array $rows) {

    if (!$rows) { return; }

    echo "<div class='table-wrap'><table class='data detail-table'><tbody>";
    foreach ($rows as $label => $value) {
        echo "<tr><th style='width:42%'>" . htmlspecialchars($label) . "</th>"
           . "<td>" . $value . "</td></tr>";
    }
    echo "</tbody></table></div>";
}


/**
 * A detail panel in two columns.
 *
 * A label-and-value table only ever fills the left half of a wide screen —
 * the labels are short and the values are shorter, so on a desktop the right
 * half of the panel was empty and the reader had to scroll for facts that
 * would have fitted on one screen.
 *
 * Splitting the same rows into two columns uses that space. It reuses the
 * .form-grid / .form-col pair the event and transformer forms already use,
 * so there is no new CSS and no new breakpoint: below 1100px the columns
 * stack by the rule that is already there, and the panel reads as one list
 * again on a phone.
 *
 *   $leftCount  how many rows go in the left column. Null splits evenly.
 *   $fullWidth  labels to pull out and lay across the bottom instead —
 *               for the long ones, like a description, that would make one
 *               column much taller than the other.
 */
function renderDetailColumns(array $rows, $leftCount = null, array $fullWidth = array()) {

    if (!$rows) { return; }

    $wide = array();
    foreach ($fullWidth as $key) {
        if (array_key_exists($key, $rows)) {
            $wide[$key] = $rows[$key];
            unset($rows[$key]);
        }
    }

    $total = count($rows);

    if ($leftCount === null || $leftCount < 1 || $leftCount >= $total) {
        $leftCount = (int)ceil($total / 2);
    }

    $left  = array_slice($rows, 0, $leftCount, true);
    $right = array_slice($rows, $leftCount, null, true);
    ?>
    <div class="panel-body">
        <div class="form-grid">
            <div class="form-col"><?php renderDetailTable($left); ?></div>
            <div class="form-col last"><?php renderDetailTable($right); ?></div>
        </div>
        <?php if ($wide) { renderDetailTable($wide); } ?>
    </div>
    <?php
}


/**
 * Print a transformer's event history — one row per event, newest first.
 * Used by transformer.php.
 *
 * Only the date, the Proposal No, the RID, the work type and who it is
 * assigned to. Everything else
 * about an event is on its own page, and the whole row opens it: data-href
 * is picked up by the handler in footer.php, and the date stays a real link
 * for the keyboard and "open in new tab".
 *
 * plain-table keeps ordinary rows on a wide screen. Without it the table
 * turns every row into a stacked tile.
 */
function renderTaskHistory(mysqli $conn, int $transformerId,
                           string $emptyText = 'No events recorded for this transformer.') {

    $stmt = $conn->prepare(
        "SELECT t.task_id, t.task_date, t.date_created, t.request_id, t.rid,
                ty.type_name, ty.description AS type_desc,
                u.full_name AS assigned_name
         FROM tasks t
         LEFT JOIN task_types ty ON t.type_id = ty.type_id
         LEFT JOIN users u       ON t.assigned_to = u.user_id
         WHERE (t.transformer_id = ?
                OR t.task_id IN (SELECT task_id FROM task_transformers
                                 WHERE transformer_id = ?))
           AND t.is_deleted = 0
         ORDER BY COALESCE(t.task_date, DATE(t.date_created)) DESC, t.task_id DESC"
    );
    // The events this transformer is T1 on, and those it is T2 to T5 on.
    $stmt->bind_param("ii", $transformerId, $transformerId);
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $dash = "<span class='field-hint'>&mdash;</span>";

    echo "<div class='table-wrap'>";
    echo "<table class='data plain-table'>";
    echo "<thead><tr>
            <th>Date</th>
            <th>Proposal No</th>
            <th>RID</th>
            <th>Work Type</th>
            <th>Assigned To</th>
          </tr></thead><tbody>";

    if (count($jobs) == 0) {
        echo "<tr><td colspan='5'><div class='table-empty'>"
           . htmlspecialchars($emptyText) . "</div></td></tr>";
    }

    foreach ($jobs as $j) {

        $tid = (int)$j['task_id'];

        // The date on the paper; older events carry only the day they were entered.
        $date = $j['task_date'] ? $j['task_date'] : $j['date_created'];

        echo "<tr data-href='event.php?id=$tid'>";

        echo "<td data-label='Date'><a href='event.php?id=$tid'>"
           . date('d M Y', strtotime($date)) . "</a></td>";

        echo "<td data-label='Proposal No'>" . (trim((string)$j['request_id']) !== ''
                ? "<span class='code-cell'>" . htmlspecialchars($j['request_id']) . "</span>"
                : $dash) . "</td>";

        echo "<td data-label='RID'>" . (trim((string)$j['rid']) !== ''
                ? htmlspecialchars($j['rid']) : $dash) . "</td>";

        echo "<td data-label='Work Type'"
           . ($j['type_desc'] ? " title='" . htmlspecialchars($j['type_desc']) . "'" : "")
           . ">" . (trim((string)$j['type_name']) !== ''
                    ? htmlspecialchars($j['type_name']) : $dash) . "</td>";

        echo "<td data-label='Assigned To'>" . ($j['assigned_name']
                ? htmlspecialchars($j['assigned_name'])
                : "<span class='unassigned-tag'>UNASSIGNED</span>") . "</td>";

        echo "</tr>";
    }

    echo "</tbody></table></div>";
}

/* ===========================================================================
   The home dashboard.

   One function, so the page and the live endpoint can never disagree about
   what the numbers are: index.php calls it to draw the first frame, and
   stats_api.php calls the same function every time the browser asks for a
   refresh.

   Everything is counted from the tables at the moment of the call. Nothing
   is cached and no total is stored, which is the same rule the ABC grid
   follows — a number on screen is always a number from the database.
   =========================================================================== */
function dashboardStats(mysqli $conn) {

    $out = array();

    /* Four headline counts lived here — transformers, events, pending
       events and CSCs — for the tiles across the top of the home page.
       The tiles were taken off, so the counts came off with them rather
       than running four subqueries every refresh for nobody. Putting
       them back is this one statement:

         SELECT (SELECT COUNT(*) FROM transformers t WHERE " . txActiveSql('t') . "),
                (SELECT COUNT(*) FROM tasks        WHERE is_deleted = 0),
                (SELECT COUNT(*) FROM csc),
                (SELECT COUNT(*) FROM tasks t
                   JOIN statuses s ON t.status_id = s.status_id
                  WHERE t.is_deleted = 0 AND s.status_name = 'Pending')  */

    // ---- events raised per month, the last twelve ------------------------
    /* The query only returns months that have events in them. A gap would
       make the line jump from March to June as though May never happened,
       so the twelve months are built here and the counts dropped into it —
       a month with no work shows as the zero it is. */
    $found = array();
    $res = $conn->query(
        "SELECT DATE_FORMAT(date_created, '%Y-%m') AS m, COUNT(*) AS n
         FROM tasks
         WHERE is_deleted = 0
           AND date_created >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         GROUP BY m"
    );
    while ($x = $res->fetch_assoc()) { $found[$x['m']] = (int)$x['n']; }

    $months = array();
    for ($i = 11; $i >= 0; $i--) {
        $t   = strtotime("-$i months");
        $key = date('Y-m', $t);
        $months[] = array(
            'key'   => $key,
            'label' => date('M', $t),
            'full'  => date('F Y', $t),
            'n'     => isset($found[$key]) ? $found[$key] : 0,
        );
    }
    $out['months'] = $months;

    // ---- transformers per CSC -------------------------------------------
    /* The map reads 'label' and 'n' from this and nothing else. The type
       buckets are carried alongside so the CSC breakdown panel can be
       drawn from the same rows — one query, and the map and the bars can
       never disagree about how many transformers a CSC holds.

       Ordered by csc_id, which is the order the office knows: Badulla
       down to Kandaketiya, the areas keeping their own CSCs together.
       Sorting by count instead would put the biggest first, which reads
       as a league table and moves a CSC up the page the week it gains a
       transformer. A register that stays where you left it is easier to
       find your own CSC in. The map does not care about the order. */
    $out['csc'] = array();
    $res = $conn->query(
        "SELECT c.csc_name AS label, COUNT(*) AS n,
                SUM(IFNULL(t.transformer_type,'') = 'Bulk')                AS bulk,
                SUM(IFNULL(t.transformer_type,'') = 'Distribution')        AS dist,
                SUM(IFNULL(t.transformer_type,'') = 'Bulk & Distribution') AS bd,
                SUM(TRIM(IFNULL(t.transformer_type,'')) <> ''
                    AND IFNULL(t.transformer_type,'') NOT IN
                        ('Bulk','Distribution','Bulk & Distribution'))     AS other,
                SUM(TRIM(IFNULL(t.transformer_type,'')) = '')              AS none,
                /* The same rows cut by capacity as well. Four sizes are
                   named because the office knows them by name and they are
                   90% of the register between them; everything from 630
                   upwards is one band because those are the big machines
                   and there are eighty of them across twelve sizes; the
                   odd sizes below it — 50, 150, 200, 300, 500, 600 — are
                   one more. A nought in capacity_kva means nobody wrote
                   it down, which is its own bucket, not a zero. */
                SUM(t.capacity_kva = 100)  AS k100,
                SUM(t.capacity_kva = 160)  AS k160,
                SUM(t.capacity_kva = 250)  AS k250,
                SUM(t.capacity_kva = 400)  AS k400,
                SUM(t.capacity_kva >= 630) AS kbig,
                SUM(t.capacity_kva > 0 AND t.capacity_kva < 630
                    AND t.capacity_kva NOT IN (100,160,250,400)) AS kodd,
                SUM(IFNULL(t.capacity_kva,0) = 0) AS knone
         FROM transformers t
         LEFT JOIN csc c ON t.csc_id = c.csc_id
         WHERE " . txActiveSql('t') . "
         GROUP BY c.csc_id, c.csc_name
         ORDER BY c.csc_id IS NULL, c.csc_id"
    );
    while ($x = $res->fetch_assoc()) {
        $out['csc'][] = array(
            'label' => ($x['label'] !== null && trim($x['label']) !== '')
                       ? $x['label'] : 'No CSC recorded',
            'n'     => (int)$x['n'],
            'bulk'  => (int)$x['bulk'],
            'dist'  => (int)$x['dist'],
            'bd'    => (int)$x['bd'],
            'other' => (int)$x['other'],
            'none'  => (int)$x['none'],
            'k100'  => (int)$x['k100'],
            'k160'  => (int)$x['k160'],
            'k250'  => (int)$x['k250'],
            'k400'  => (int)$x['k400'],
            'kbig'  => (int)$x['kbig'],
            'kodd'  => (int)$x['kodd'],
            'knone' => (int)$x['knone']);
    }

    // ---- transformers per area ------------------------------------------
    /* Five areas, so this is the one breakdown of the register that a pie
       can actually show: the shares run 26% down to 17%, which the eye can
       tell apart. The seventeen CSCs underneath cannot be drawn that way —
       they run 9.6% down to 3.3%, and eight of them sit between 6.7% and
       6.1%, which is a ring of identical slices. Those stay as bars. */
    $out['areas'] = array();
    $res = $conn->query(
        "SELECT a.area_name AS label, COUNT(*) AS n
         FROM transformers t
         LEFT JOIN csc   c ON t.csc_id  = c.csc_id
         LEFT JOIN areas a ON c.area_id = a.area_id
         WHERE " . txActiveSql('t') . "
         GROUP BY a.area_name
         ORDER BY n DESC"
    );
    while ($x = $res->fetch_assoc()) {
        $out['areas'][] = array(
            'label' => ($x['label'] !== null && trim($x['label']) !== '')
                       ? $x['label'] : 'No area recorded',
            'n'     => (int)$x['n']);
    }

    // ---- the work types, and how many events each one holds --------------
    /* The count is every event of that type, finished or not. Which of them
       are still outstanding is the Events register's question, and it can
       answer it properly with its status filter; repeating a half-version
       of that here only invited the two to disagree.

       COUNT(k.task_id) rather than COUNT(*): a LEFT JOIN makes one empty
       row for a type nothing is booked against, and COUNT(*) would count
       that row and report 1 job that does not exist.

       Ordered by name, the same order the Add Event form offers them. */
    $out['types'] = array();
    $res = $conn->query(
        "SELECT ty.type_name AS label,
                COUNT(k.task_id) AS n
         FROM task_types ty
         LEFT JOIN tasks k ON k.type_id = ty.type_id AND k.is_deleted = 0
         WHERE ty.is_active = 1
         GROUP BY ty.type_id, ty.type_name
         ORDER BY ty.type_name"
    );
    while ($x = $res->fetch_assoc()) {
        $out['types'][] = array(
            'label' => $x['label'],
            'n'     => (int)$x['n']);
    }

    $out['updated'] = date('d M Y, H:i:s');

    return $out;
}


/* ===========================================================================
   Extra transformers on one event — T2 to T5.

   Transformer 1 is tasks.transformer_id, as it always was. A job that covers
   more than one transformer adds up to four more, kept in task_transformers
   with their position. See sql/2026-09-28_task_transformers.sql.
   =========================================================================== */

/** How many transformers one event may carry, T1 included. */
function maxEventTransformers() { return 5; }


/**
 * The extra transformers on one event, in the order they were entered.
 * Each row: position, id, code, substation, csc_id, is_removed (1 or 0).
 */
function eventExtraTransformers(mysqli $conn, int $taskId) {

    $stmt = $conn->prepare(
        "SELECT x.position, tx.transformer_id AS id, tx.transformer_code AS code,
                tx.substation_name AS substation, tx.csc_id,
                (NOT " . txActiveSql('tx') . ") AS is_removed
         FROM task_transformers x
         JOIN transformers tx ON x.transformer_id = tx.transformer_id
         WHERE x.task_id = ?
         ORDER BY x.position"
    );
    $stmt->bind_param("i", $taskId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}


/**
 * Read Transformer 2 to 5 from a submitted form and check them.
 *
 * Blank boxes are skipped, so choosing T2 and T4 with T3 left empty simply
 * saves two extras. The same transformer twice — or T1 again — is refused
 * rather than quietly dropped, because the worker almost certainly meant a
 * different one and should be told.
 *
 * A transformer must still be in the register, except one this event
 * already carries: deleting a transformer later must not make its old
 * events impossible to save.
 *
 * Returns array(ids in order, error message or '').
 */
function extraTransformersFromPost(mysqli $conn, array $post, int $mainId,
                                   array $alreadyLinked = array()) {

    $posted = (isset($post['extra_tx']) && is_array($post['extra_tx']))
            ? $post['extra_tx'] : array();

    $ids = array();
    foreach ($posted as $v) {
        $v = (int)$v;
        if ($v > 0) { $ids[] = $v; }
    }
    $ids = array_slice($ids, 0, maxEventTransformers() - 1);

    if (!$ids) { return array(array(), ''); }

    $seen = array($mainId => true);
    foreach ($ids as $v) {
        if (isset($seen[$v])) {
            return array($ids, "The same transformer has been chosen more than once.");
        }
        $seen[$v] = true;
    }

    $check = $conn->prepare("SELECT (NOT " . txActiveSql('t') . ") AS is_removed
                             FROM transformers t WHERE t.transformer_id = ?");
    foreach ($ids as $v) {
        $check->bind_param("i", $v);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        if (!$row || ((int)$row['is_removed'] === 1 && !in_array($v, $alreadyLinked))) {
            $check->close();
            return array($ids, "One of the extra transformers no longer exists.");
        }
    }
    $check->close();

    return array($ids, '');
}


/**
 * Replace an event's extra transformers with this list. The first becomes
 * T2, the next T3, and so on — gaps left on the form are closed up.
 */
function saveExtraTransformers(mysqli $conn, int $taskId, array $ids) {

    $del = $conn->prepare("DELETE FROM task_transformers WHERE task_id = ?");
    $del->bind_param("i", $taskId);
    $del->execute();
    $del->close();

    if (!$ids) { return; }

    $ins = $conn->prepare("INSERT INTO task_transformers (task_id, position, transformer_id)
                           VALUES (?, ?, ?)");
    $pos = 2;
    foreach ($ids as $v) {
        $v = (int)$v;
        $ins->bind_param("iii", $taskId, $pos, $v);
        $ins->execute();
        $pos++;
    }
    $ins->close();
}


/**
 * The Transformer T2 to T5 boxes, for the Add and Edit Event forms.
 *
 * Every box is drawn; the script hides the empty ones and shows one more
 * each time the + beside Transformer T1 is pressed, so an ordinary one-
 * transformer event looks exactly as it did. They are printed inside the
 * same column as T1, so they line up underneath it. The options themselves
 * are filled in by the script, from the same list as Transformer T1.
 *
 * $chosen is the ids to show selected, in order (after a failed save, or
 * the ones an event already carries).
 */
function renderExtraTransformerRows(array $chosen) {

    $chosen = array_values($chosen);

    for ($i = 0; $i < maxEventTransformers() - 1; $i++) {
        $n   = $i + 2;
        $val = isset($chosen[$i]) ? (int)$chosen[$i] : 0;
        echo "<div class='form-row extra-tx-row'>"
           .   "<label for='tx" . $n . "'>Transformer T" . $n . "</label>"
           .   "<div class='tx-line'>"
           .     "<select id='tx" . $n . "' name='extra_tx[]' class='extra-tx-select'"
           .       " data-chosen='" . $val . "'>"
           .       "<option value=''>-- None --</option>"
           .     "</select>"
           .     "<button type='button' class='tx-icon-btn extra-tx-remove'"
           .       " title='Remove Transformer T" . $n . "' aria-label='Remove Transformer T" . $n . "'>"
           .       "<svg viewBox='0 0 24 24' width='16' height='16' fill='none' stroke='currentColor'"
           .       " stroke-width='2.2' stroke-linecap='round' aria-hidden='true'>"
           .       "<path d='M6 6l12 12'/><path d='M18 6 6 18'/></svg>"
           .     "</button>"
           .   "</div>"
           . "</div>";
    }
}


/**
 * The + that sits beside Transformer T1 and opens the next empty box.
 * Printed by the page right after T1's own box, inside a .tx-line.
 */
function renderExtraTransformerAddButton() {

    echo "<button type='button' id='extra-tx-add' class='tx-icon-btn tx-icon-add'"
       . " title='Add another transformer (up to " . maxEventTransformers() . ")'"
       . " aria-label='Add another transformer'>"
       . "<svg viewBox='0 0 24 24' width='18' height='18' fill='none' stroke='currentColor'"
       . " stroke-width='2.2' stroke-linecap='round' aria-hidden='true'>"
       . "<path d='M12 5v14'/><path d='M5 12h14'/></svg>"
       . "</button>";
}


/**
 * The script behind renderExtraTransformerRows(). Defines:
 *
 *   extraTxFill(list)  - refill every extra box from a list of
 *                        {id, code, kva}; keeps whatever each box had chosen.
 *   extraTxInit()      - hide the empty boxes and wire up Add and Remove.
 *
 * Printed inside a <script> block that the page already has open.
 */
function extraTransformerScript() { ?>
/* How a transformer reads in every dropdown: S/N, then the substation name
   the workers know it by, then the capacity — "UBP 004 — Passara (100 kVA)".
   A transformer with no name recorded simply has nothing in the middle. */
function txLabel(t) {
    var name = (t.name || '').trim();
    return t.code + (name !== '' ? ' — ' + name : '')
         + (t.kva ? ' (' + t.kva + ' kVA)' : '');
}

function extraTxFill(list) {
    var sels = document.querySelectorAll('.extra-tx-select');
    for (var s = 0; s < sels.length; s++) {
        var sel  = sels[s];
        // The first time, the choice comes from the page; after that, from
        // the box, so a transformer somebody removed does not come back.
        var keep = sel.getAttribute('data-filled')
                 ? sel.value : (sel.getAttribute('data-chosen') || '');
        sel.setAttribute('data-filled', '1');
        sel.innerHTML = '';
        sel.appendChild(new Option('-- None --', ''));
        for (var i = 0; i < list.length; i++) {
            var o = new Option(txLabel(list[i]), list[i].id);
            if (String(list[i].id) === String(keep)) { o.selected = true; }
            sel.appendChild(o);
        }
        // From here on the box itself holds the answer.
        sel.setAttribute('data-chosen', sel.value || '0');
    }
}

function extraTxInit() {
    var rows   = document.querySelectorAll('.extra-tx-row');
    var addBtn = document.getElementById('extra-tx-add');
    if (!addBtn) { return; }

    // With all five in use the + stays where it is but cannot be pressed,
    // so T1's box does not jump sideways.
    function refresh() {
        var hidden = 0;
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].style.display === 'none') { hidden++; }
        }
        addBtn.disabled = (hidden === 0);
    }

    for (var i = 0; i < rows.length; i++) {
        var sel = rows[i].querySelector('select');
        var has = (sel.getAttribute('data-chosen') || '0') !== '0';
        rows[i].style.display = has ? '' : 'none';

        rows[i].querySelector('.extra-tx-remove').addEventListener('click', function () {
            var row = this.closest('.extra-tx-row');
            row.querySelector('select').value = '';
            row.style.display = 'none';
            refresh();
        });
    }

    addBtn.addEventListener('click', function () {
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].style.display === 'none') {
                rows[i].style.display = '';
                rows[i].querySelector('select').focus();
                break;
            }
        }
        refresh();
    });

    refresh();
}
<?php }
