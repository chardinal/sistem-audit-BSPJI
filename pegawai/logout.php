<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
logoutPegawai();
