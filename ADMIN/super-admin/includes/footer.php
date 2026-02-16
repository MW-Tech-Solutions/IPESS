            </main>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
const shell = document.getElementById('admin-shell');
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
const mobileToggle = document.getElementById('mobile-sidebar-toggle');

function toggleSidebar() {
    if (window.innerWidth <= 768) {
        shell.classList.toggle('sidebar-open');
    } else {
        shell.classList.toggle('sidebar-collapsed');
        sidebar.classList.toggle('collapsed');
    }
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
}

if (mobileToggle) {
    mobileToggle.addEventListener('click', toggleSidebar);
}

document.addEventListener('click', function(event) {
    if (!sidebar.contains(event.target) && window.innerWidth <= 768) {
        shell.classList.remove('sidebar-open');
    }
});
</script>

<div class="modal fade" id="idleWarningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Session Expiring</h5>
            </div>
            <div class="modal-body">
                <p class="mb-2">You are about to be logged out due to inactivity.</p>
                <p class="mb-0">Time remaining: <strong id="idleCountdown">120</strong> seconds.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="idleLogoutNowBtn">Logout Now</button>
                <button type="button" class="btn btn-primary" id="stayLoggedInBtn">Stay Logged In</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    if (window.self !== window.top) return;

    const sessionTimeoutSeconds = 900;
    const warnBeforeSeconds = 120;
    const logoutUrl = "<?php echo app_url('ADMIN/relogin.php?timeout=1'); ?>";
    const pingUrl = "<?php echo app_url('ADMIN/ping.php'); ?>";

    let lastActivity = Date.now();
    let warningShown = false;
    let countdownTimer = null;
    let idleModal = null;

    const modalEl = document.getElementById('idleWarningModal');
    const countdownEl = document.getElementById('idleCountdown');
    const stayBtn = document.getElementById('stayLoggedInBtn');

    const logoutBtn = document.getElementById('idleLogoutNowBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            window.location.href = logoutUrl;
        });
    }

    function ensureModal() {
        if (!idleModal && typeof bootstrap !== 'undefined') {
            idleModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
        }
    }

    function hideWarning() {
        if (idleModal) {
            idleModal.hide();
        }
        warningShown = false;
        if (countdownTimer) {
            clearInterval(countdownTimer);
            countdownTimer = null;
        }
    }

    function showWarning(secondsLeft) {
        ensureModal();
        warningShown = true;
        if (countdownEl) countdownEl.textContent = String(Math.max(0, Math.floor(secondsLeft)));
        if (idleModal) idleModal.show();

        if (!countdownTimer) {
            countdownTimer = setInterval(() => {
                const idleSeconds = (Date.now() - lastActivity) / 1000;
                const remaining = sessionTimeoutSeconds - idleSeconds;
                if (countdownEl) countdownEl.textContent = String(Math.max(0, Math.floor(remaining)));
                if (remaining <= 0) {
                    window.location.href = logoutUrl;
                }
            }, 1000);
        }
    }

    function resetActivity() {
        lastActivity = Date.now();
        if (warningShown) {
            hideWarning();
        }
    }

    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => {
        window.addEventListener(evt, resetActivity, { passive: true });
    });

    if (stayBtn) {
        stayBtn.addEventListener('click', async () => {
            try {
                await fetch(pingUrl, { credentials: 'same-origin' });
            } catch (e) {}
            resetActivity();
        });
    }
    setInterval(() => {
        const idleSeconds = (Date.now() - lastActivity) / 1000;
        if (!warningShown && idleSeconds >= (sessionTimeoutSeconds - warnBeforeSeconds)) {
            showWarning(sessionTimeoutSeconds - idleSeconds);
        }
        if (idleSeconds >= sessionTimeoutSeconds) {
            window.location.href = logoutUrl;
        }
    }, 1000);
})();
</script>
</body>
</html>
