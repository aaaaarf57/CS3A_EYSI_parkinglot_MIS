</main> <!-- end of main container -->

<footer class="text-center py-3 mt-5" style="color:#555; font-size:14px;">
  © <?php echo date('Y'); ?> Eysi Parking Lot MIS. All rights reserved.
</footer>

<!-- Sidebar Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('toggleBtn');
  const overlay = document.getElementById('overlay');

  if (toggleBtn && sidebar && overlay) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.add('active');
      overlay.classList.add('active');
      toggleBtn.style.display = 'none'; // hide hamburger when sidebar opens
    });

    overlay.addEventListener('click', () => {
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
      toggleBtn.style.display = 'flex'; // show hamburger again when closed
    });
  }
});
</script>


<!-- Optional Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
