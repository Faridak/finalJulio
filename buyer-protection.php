<?php
/**
 * Buyer protection now runs through the dispute center (DisputeService).
 * The old escrow dashboard here depended on EscrowSystem, whose escrow_* tables
 * and order statuses never existed, so the page could only error. Kept as a
 * redirect so old links and bookmarks still land somewhere useful.
 * (Old version: git history of this file.)
 */
require_once 'config/database.php';

$target = (getUserRole() === 'merchant') ? 'merchant/disputes.php' : 'disputes.php';
header('Location: ' . $target, true, 301);
exit;
