<?php // pegawai/_footer.php ?>
</main><!-- .pgw-main -->
</div><!-- .pgw-layout -->

<!-- Bottom Navigation (Mobile) -->
<nav class="pgw-bottom-nav">
  <a href="<?= BASE_URL ?>/pegawai/index.php?tab=jadwal" class="pgw-nav-item <?= ($tab??'')==='jadwal'?'active':'' ?>">
    Jadwal
    <?php if ($jadwalCount > 0): ?>
    <span class="pgw-nav-badge aksi-badge-count" style="right:calc(50% - 24px)"><?= $jadwalCount ?></span>
    <?php endif; ?>
  </a>
  <a href="<?= BASE_URL ?>/pegawai/index.php?tab=kalender" class="pgw-nav-item <?= ($tab??'')==='kalender'?'active':'' ?>">
    Kalender
  </a>
  <a href="<?= BASE_URL ?>/pegawai/index.php?tab=riwayat" class="pgw-nav-item <?= ($tab??'')==='riwayat'?'active':'' ?>">
    Riwayat
  </a>
  <a href="<?= BASE_URL ?>/pegawai/index.php?tab=notifikasi" class="pgw-nav-item <?= ($tab??'')==='notifikasi'?'active':'' ?>">
    Notif
  </a>
  <a href="<?= BASE_URL ?>/pegawai/index.php?tab=profil" class="pgw-nav-item <?= ($tab??'')==='profil'?'active':'' ?>">
    Profil
  </a>
</nav>

<script src="<?= BASE_URL ?>/assets/js/pegawai.js"></script>
</body>
</html>
