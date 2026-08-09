<?php
// Consolidated onto the live ledger engine. Original mock page preserved in
// admin/_legacy_backup/budget-vs-actual.php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
requireRole('admin');
header('Location: accounting-hub.php');
exit;
