<?php
/**
 * Breadcrumb helpers.
 *
 *   echo renderBreadcrumbs([['Home','index.php'], ['Ventilación','browse.php?category_id=4'], ['Extractores', null]]);
 *
 * categoryTrail($pdo, $categoryId) returns the [label,url] pairs for a
 * category and its parent, ready to splice into renderBreadcrumbs.
 */
function renderBreadcrumbs(array $items)
{
    $html = '<nav class="text-sm text-gray-500 mb-4" aria-label="Breadcrumb"><ol class="flex flex-wrap items-center gap-1">';
    $last = count($items) - 1;
    foreach ($items as $i => [$label, $url]) {
        if ($i > 0) $html .= '<li class="text-gray-300 px-1">/</li>';
        $label = htmlspecialchars($label);
        $html .= ($url !== null && $i !== $last)
            ? '<li><a href="' . htmlspecialchars($url) . '" class="hover:text-blue-600">' . $label . '</a></li>'
            : '<li class="text-gray-800 font-medium truncate max-w-[16rem]" aria-current="page">' . $label . '</li>';
    }
    return $html . '</ol></nav>';
}

function categoryTrail(PDO $pdo, $categoryId)
{
    $trail = [];
    $stmt = $pdo->prepare("SELECT c.id, c.name, p.id pid, p.name pname
                           FROM categories c LEFT JOIN categories p ON c.parent_id = p.id
                           WHERE c.id = ?");
    $stmt->execute([(int) $categoryId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['pid']) $trail[] = [$row['pname'], 'browse.php?category_id=' . (int) $row['pid']];
        $trail[] = [$row['name'], 'browse.php?category_id=' . (int) $row['id']];
    }
    return $trail;
}
