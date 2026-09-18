    </div><!-- /.main-content -->
</div><!-- /#page-content-wrapper -->
</div><!-- /#wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Sidebar Toggle
    document.getElementById('menu-toggle')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('wrapper').classList.toggle('toggled');
    });

    // Close sidebar when clicking outside (mobile)
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('wrapper');
        const sidebar = document.getElementById('sidebar-wrapper');
        const toggle = document.getElementById('menu-toggle');

        if (window.innerWidth <= 991.98 && 
            wrapper.classList.contains('toggled') && 
            !sidebar.contains(e.target) && 
            !toggle.contains(e.target)) {
            wrapper.classList.remove('toggled');
        }
    });

    // DataTables default
    $(document).ready(function() {
        $('.datatable').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries"
            }
        });
    });
</script>

<?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>