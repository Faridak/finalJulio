<?php
/**
 * Generate 400px card thumbnails for product photos (originals are up to
 * 2000x2000). Writes uploads/thumbs/{product_id}.jpg and sets thumb_url.
 * Idempotent: skips products whose thumb already exists.
 *   php scripts/generate-thumbnails.php
 */
if (PHP_SAPI !== 'cli') exit('CLI only');
require_once __DIR__ . '/../config/database.php';

$rows = $pdo->query("
    SELECT id, image_url FROM products
    WHERE image_url LIKE 'uploads/legacy/%' AND (thumb_url IS NULL OR thumb_url = '')
")->fetchAll(PDO::FETCH_ASSOC);

$made = 0; $skip = 0; $upd = $pdo->prepare("UPDATE products SET thumb_url = ? WHERE id = ?");
foreach ($rows as $r) {
    $src = __DIR__ . '/../' . $r['image_url'];
    if (!is_file($src)) { $skip++; continue; }
    $dest = 'uploads/thumbs/' . $r['id'] . '.jpg';
    $destAbs = __DIR__ . '/../' . $dest;
    if (!is_file($destAbs)) {
        $info = @getimagesize($src);
        if (!$info) { $skip++; continue; }
        $im = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => @imagecreatefrompng($src),
            IMAGETYPE_GIF  => @imagecreatefromgif($src),
            IMAGETYPE_WEBP => @imagecreatefromwebp($src),
            default => null,
        };
        if (!$im) { $skip++; continue; }
        [$w, $h] = $info;
        $scale = min(1, 400 / max($w, $h));
        $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        $white = imagecolorallocate($out, 255, 255, 255);
        imagefill($out, 0, 0, $white);           // PNG transparency -> white
        imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($out, $destAbs, 82);
        imagedestroy($im); imagedestroy($out);
    }
    $upd->execute([$dest, $r['id']]);
    $made++;
    if ($made % 400 === 0) echo "  ...$made\n";
}
echo "thumbnails: $made generated/linked, $skip skipped (missing/unreadable source)\n";
