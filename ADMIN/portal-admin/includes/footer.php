    </main>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
const shell = document.getElementById('admin-shell');
const sidebar = document.getElementById('sidebar');
const mobileToggle = document.getElementById('mobile-sidebar-toggle');

function toggleSidebar() {
    if (window.innerWidth <= 768) {
        shell.classList.toggle('sidebar-open');
    } else {
        shell.classList.toggle('sidebar-collapsed');
        sidebar.classList.toggle('collapsed');
    }
}

if (mobileToggle) {
    mobileToggle.addEventListener('click', toggleSidebar);
}

document.addEventListener('click', function(event) {
    if (sidebar && !sidebar.contains(event.target) && window.innerWidth <= 768) {
        shell.classList.remove('sidebar-open');
    }
});
</script>
</body>
</html>
