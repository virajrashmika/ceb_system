</div><!-- /.content -->
</div><!-- /.main -->

<script>
/* ---- sliding nav pill ----------------------------------------------------

   One pill moves behind whichever link is active or hovered, instead of
   each link filling in on its own. The has-pill class tells the stylesheet
   to stand the old per-link fill down; it is added here and nowhere else,
   so with JavaScript off the sidebar keeps working the way it always did. */
(function () {
    var nav  = document.querySelector('.sidebar-nav');
    var pill = document.getElementById('nav-pill');
    if (!nav || !pill) { return; }

    var active = nav.querySelector('a.active');
    if (!active) { return; }

    nav.classList.add('has-pill');

    /* Links are NOT all the same size — the sub-items under Transformers and
       Events are smaller — so the pill is measured against whichever link it
       is moving to, every time, rather than once at the start. */
    function moveTo(el, smooth) {

        var navRect = nav.getBoundingClientRect();
        var r       = el.getBoundingClientRect();

        pill.style.transition = smooth ? '' : 'none';

        pill.style.width  = r.width + 'px';
        pill.style.height = r.height + 'px';
        pill.style.transform =
            'translate(' + (r.left - navRect.left) + 'px, ' +
                           (r.top  - navRect.top)  + 'px)';
        pill.style.opacity = '1';

        // Force the browser to apply the jump before animation is allowed
        // again, or the "no animation" move would animate after all.
        if (!smooth) { void pill.offsetHeight; pill.style.transition = ''; }
    }

    /* The pill starts on the open page's link and stays there. It used to
       remember the link you came from and fly in from it, which meant every
       page opened with the pill sliding across the menu before settling.
       Nothing is being said by that, so the page simply opens ready. */
    moveTo(active, false);

    var links = nav.querySelectorAll('a');
    for (var i = 0; i < links.length; i++) {
        links[i].addEventListener('mouseenter', function () { moveTo(this, true); });
        // Tabbing through the menu should move the pill too, or someone
        // using the keyboard cannot see where they are.
        links[i].addEventListener('focus', function () { moveTo(this, true); });
    }

    nav.addEventListener('mouseleave', function () { moveTo(active, true); });

    /* The sidebar scrolls, the window resizes, and switching to dark mode
       changes the text weight — all of which move the links. Re-measure
       without animating, so the pill never drifts off its link. */
    function settle() { moveTo(active, false); }

    window.addEventListener('resize', settle);

    // Anything still loading — the logo, a web font — can change the height
    // of the menu after this script has already measured it.
    window.addEventListener('load', settle);

    // And anything else at all that changes the menu's size. This is the
    // belt to the braces above: it catches causes nobody thought of.
    if (window.ResizeObserver) {
        new ResizeObserver(settle).observe(nav);
    }

    var sidebar = document.querySelector('.sidebar');
    if (sidebar) { sidebar.addEventListener('scroll', settle); }

    // Exposed so the theme toggle below can call it after the repaint.
    window.cebSettleNavPill = settle;
})();


/* ---- the glare that follows the mouse across the sidebar ---- */
(function () {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) { return; }

    // The glare is drawn by .sidebar::after, which is position: fixed, so
    // the pointer's viewport coordinates can be handed straight over.
    sidebar.addEventListener('mousemove', function (e) {
        var r = sidebar.getBoundingClientRect();
        sidebar.style.setProperty('--gx', (e.clientX - r.left) + 'px');
        sidebar.style.setProperty('--gy', (e.clientY - r.top)  + 'px');
    });
})();

/* ---- rows that open a record ----------------------------------------

   A table row cannot be a link in HTML, so any <tr data-href="..."> is
   made to behave like one here. The first cell still holds a real link,
   which is what the keyboard, middle-click and "open in new tab" use —
   this only saves the mouse from having to find it.

   A click that lands on something clickable already (a link inside the
   row) is left alone, and so is a click made while selecting text. */
(function () {

    /* One listener on the document rather than one per row.

       The rows used to be collected at load time and given a listener each.
       That works for a register printed by PHP, but the home page writes its
       search results after the page has loaded, and those rows were never in
       the list — they looked clickable and did nothing. Listening here and
       working out which row was hit covers both: it does not care when the
       row arrived, or how many there are. */
    document.addEventListener('click', function (e) {

        if (!e.target || !e.target.closest) { return; }

        var row = e.target.closest('tr[data-href]');
        if (!row) { return; }

        if (e.target.closest('a, button, input, label, select')) { return; }

        // Dragging across a row to copy a serial number is not a click.
        if (window.getSelection && String(window.getSelection()) !== '') { return; }

        // Ctrl/Cmd/middle click opens a new tab, the same as a link.
        if (e.ctrlKey || e.metaKey) {
            window.open(row.getAttribute('data-href'), '_blank');
        } else {
            window.location.href = row.getAttribute('data-href');
        }
    });
})();


/* ---- light / dark toggle ---- */
(function () {
    var btn = document.getElementById('theme-toggle');
    if (!btn) { return; }

    btn.addEventListener('click', function () {
        var root = document.documentElement;
        if (root.getAttribute('data-theme') === 'dark') {
            root.removeAttribute('data-theme');
            localStorage.setItem('ceb-theme', 'light');
        } else {
            root.setAttribute('data-theme', 'dark');
            localStorage.setItem('ceb-theme', 'dark');
        }

        // Dark mode changes the menu text weight, which shifts the links a
        // little. Re-measure once the new colours have been painted.
        if (window.cebSettleNavPill) {
            requestAnimationFrame(function () {
                requestAnimationFrame(window.cebSettleNavPill);
            });
        }
    });
})();

</script>

</body>
</html>