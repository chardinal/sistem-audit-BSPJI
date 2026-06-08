<?php // pegawai/_footer.php ?>
</main><!-- .pgw-main -->
</div><!-- .pgw-layout -->

<!-- Bottom Navigation (Mobile) -->
<nav class="pgw-bottom-nav">
  <a href="<?= BASE_URL ?>/pegawai/index.php" class="pgw-nav-item <?= ($activePage??'')==='jadwal'?'active':'' ?>">
    Jadwal
    <?php if ($jadwalCount > 0): ?>
    <span class="pgw-nav-badge aksi-badge-count" style="right:calc(50% - 24px)"><?= $jadwalCount ?></span>
    <?php endif; ?>
  </a>
  <a href="<?= BASE_URL ?>/pegawai/kalender.php" class="pgw-nav-item <?= ($activePage??'')==='kalender'?'active':'' ?>">
    Kalender
  </a>
  <a href="<?= BASE_URL ?>/pegawai/riwayat.php" class="pgw-nav-item <?= ($activePage??'')==='riwayat'?'active':'' ?>">
    Riwayat
  </a>
  <a href="<?= BASE_URL ?>/pegawai/notifikasi.php" class="pgw-nav-item <?= ($activePage??'')==='notifikasi'?'active':'' ?>">
    Notif
  </a>
  <a href="<?= BASE_URL ?>/pegawai/profil.php" class="pgw-nav-item <?= ($activePage??'')==='profil'?'active':'' ?>">
    Profil
  </a>
</nav>

<script src="<?= BASE_URL ?>/assets/js/pegawai.js"></script>
</body>
</html>
