<?php
// Consolidated onto the live ledger engine. Original mock report preserved in
// admin/_legacy_backup/financial-reports.php.
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
requireRole('admin');
header('Location: financial-statements.php');
exit;
