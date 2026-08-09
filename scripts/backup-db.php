<?php
// Quick DB backup:  php scripts/backup-db.php   (keeps last 14)
if (PHP_SAPI !== 'cli') exit('CLI only');
$dir = __DIR__ . '/../backups';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$file = $dir . '/ventdepot-' . date('Y-m-d-Hi') . '.sql.gz';
passthru('"C:\xampp\mysql\bin\mysqldump.exe" -u root ventdepot | gzip > "' . $file . '"');
echo "Backup: $file (" . round(filesize($file)/1024) . " KB)\n";
foreach (array_slice(glob("$dir/ventdepot-*.sql.gz"), 0, -14) as $old) unlink($old);
