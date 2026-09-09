</div><!-- /.content -->
</div><!-- /.main -->

<div class="footer-bar">
    Ceylon Electricity Board &mdash; Uva Province internal system.
    Official use only. &nbsp;|&nbsp; <?php echo date('d M Y, H:i'); ?>
</div>

<script>
/* ---- sliding nav pill ---- */
(function () {
    var nav  = document.querySelector('.sidebar-nav');
    var pill = document.getElementById('nav-pill');
    if (!nav || !pill) { return; }

    var active = nav.querySelector('a.active');
    if (!active) { return; }

    // Every link is the same size, so measure once and never resize again.
    var navRect = nav.getBoundingClientRect();
    var aRect   = active.getBoundingClientRect();

    pill.style.width  = aRect.width + 'px';
    pill.style.height = aRect.height + 'px';
    pill.style.left   = (aRect.left - navRect.left) + 'px';

    function moveTo(el, smooth) {
        var r = el.getBoundingClientRect();
        var y = r.top - nav.getBoundingClientRect().top;
        pill.style.transition = smooth ? '' : 'none';
        pill.style.transform  = 'translateY(' + y + 'px)';
        pill.style.opacity    = '1';
        if (!smooth) { pill.offsetHeight; pill.style.transition = ''; }
    }

    var prevHref = sessionStorage.getItem('ceb-nav-from');
    var prevEl   = prevHref ? nav.querySelector('a[href="' + prevHref + '"]') : null;

    if (prevEl && prevEl !== active) {
        moveTo(prevEl, false);
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { moveTo(active, true); });
        });
    } else {
        moveTo(active, false);
    }

    sessionStorage.setItem('ceb-nav-from', active.getAttribute('href'));

    var links = nav.querySelectorAll('a');
    for (var i = 0; i < links.length; i++) {
        links[i].addEventListener('mouseenter', function () { moveTo(this, true); });
    }
    nav.addEventListener('mouseleave', function () { moveTo(active, true); });
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
    });
})();
</script>

</body>
</html>