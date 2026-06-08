<?php
// api/validasi_penugasan.php — REMOVED
// Endpoint ini telah dihapus pada v7.2. Semua penugasan berstatus Aktif langsung.
http_response_code(410);
echo json_encode(['success' => false, 'message' => 'Endpoint ini tidak lagi tersedia.']);
