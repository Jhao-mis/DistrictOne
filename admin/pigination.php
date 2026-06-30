<!-- Pagination (Top) -->
<?php if ($totalPages > 1): ?>
  <nav aria-label="Announcements pagination" class="mb-3">
    <ul class="pagination justify-content-center">
      <!-- Previous button -->
      <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $page - 1 ?>" tabindex="-1">&lt;</a>
      </li>

      <!-- First page -->
      <li class="page-item <?= ($page == 1) ? 'active' : '' ?>">
        <a class="page-link" href="?page=1">1</a>
      </li>

      <!-- Dots before current -->
      <?php if ($page > 3): ?>
        <li class="page-item disabled"><span class="page-link">...</span></li>
      <?php endif; ?>

      <!-- Current / nearby pages -->
      <?php for ($i = max(2, $page - 1); $i <= min($totalPages - 1, $page + 1); $i++): ?>
        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>

      <!-- Dots after current -->
      <?php if ($page < $totalPages - 2): ?>
        <li class="page-item disabled"><span class="page-link">...</span></li>
      <?php endif; ?>

      <!-- Last page -->
      <?php if ($totalPages > 1): ?>
        <li class="page-item <?= ($page == $totalPages) ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $totalPages ?>"><?= $totalPages ?></a>
        </li>
      <?php endif; ?>

      <!-- Next button -->
      <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $page + 1 ?>">&gt;</a>
      </li>
    </ul>
  </nav>
<?php endif; ?>
