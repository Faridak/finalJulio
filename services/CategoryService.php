<?php
/**
 * CategoryService — hierarchical category tree, browsing, and product assignment.
 */
class CategoryService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Top-level categories (optionally only those with products). */
    public function topCategories($withCountsOnly = false)
    {
        $rows = $this->pdo->query("
            SELECT c.id, c.name, c.slug,
                   (SELECT COUNT(*) FROM categories x WHERE x.parent_id=c.id) AS subcats,
                   (SELECT COUNT(*) FROM products p WHERE p.category_id IN
                        (SELECT id FROM categories z WHERE z.id=c.id OR z.parent_id=c.id)) AS product_count
            FROM categories c WHERE c.level=1 AND c.is_active=1
            ORDER BY c.name
        ")->fetchAll(PDO::FETCH_ASSOC);
        return $withCountsOnly ? array_filter($rows, fn($r) => $r['product_count'] > 0) : $rows;
    }

    /** Direct children of a category. */
    public function children($parentId)
    {
        $s = $this->pdo->prepare("
            SELECT c.id, c.name, c.slug,
                   (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS product_count
            FROM categories c WHERE c.parent_id=? AND c.is_active=1 ORDER BY c.name
        ");
        $s->execute([$parentId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Breadcrumb path from root to the given category. */
    public function breadcrumb($categoryId)
    {
        $path = [];
        $cur = $categoryId;
        $stmt = $this->pdo->prepare("SELECT id, parent_id, name FROM categories WHERE id=?");
        while ($cur) {
            $stmt->execute([$cur]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) break;
            array_unshift($path, ['id' => (int) $row['id'], 'name' => $row['name']]);
            $cur = $row['parent_id'];
        }
        return $path;
    }

    /** Products in a category (and its subcategories). */
    public function products($categoryId, $limit = 100)
    {
        $s = $this->pdo->prepare("
            SELECT id, sku, name, price, image_url
            FROM products
            WHERE category_id = ? OR category_id IN (SELECT id FROM categories WHERE parent_id = ?)
            ORDER BY name LIMIT ?
        ");
        $s->bindValue(1, (int) $categoryId, PDO::PARAM_INT);
        $s->bindValue(2, (int) $categoryId, PDO::PARAM_INT);
        $s->bindValue(3, (int) $limit, PDO::PARAM_INT);
        $s->execute();
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assign($productId, $categoryId)
    {
        $this->pdo->prepare("UPDATE products SET category_id=? WHERE id=?")->execute([$categoryId, $productId]);
    }
}
