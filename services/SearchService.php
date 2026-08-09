<?php
/**
 * SearchService — faceted product search/discovery.
 *
 * Keyword + filters (category tree, seller, price, in-stock, on-sale, featured)
 * with facet COUNTS computed over the filtered set, effective pricing, and
 * sort/pagination. Built for the storefront browse experience.
 */
require_once __DIR__ . '/PricingService.php';

class SearchService
{
    private $pdo;
    private $pricing;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pricing = new PricingService($pdo);
    }

    /** @return array{products, total, page, pages, facets, params} */
    public function search(array $p)
    {
        $page = max(1, (int) ($p['page'] ?? 1));
        $per = min(48, max(12, (int) ($p['per'] ?? 24)));
        [$where, $args] = $this->buildWhere($p);

        $total = (int) $this->one("SELECT COUNT(*) FROM products pr WHERE $where", $args);

        // FULLTEXT misses mid-word substrings; if a keyword search finds
        // nothing, retry once with the (slower) LIKE scan for correctness.
        if ($total === 0 && trim($p['q'] ?? '') !== '') {
            [$where, $args] = $this->buildWhere($p, true);
            $total = (int) $this->one("SELECT COUNT(*) FROM products pr WHERE $where", $args);
        }
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $offset = ($page - 1) * $per;

        $order = $this->orderBy($p['sort'] ?? 'relevance');
        $sql = "SELECT pr.id, pr.sku, pr.name, pr.price, COALESCE(NULLIF(pr.thumb_url,''), pr.image_url) AS image_url, pr.merchant_id, pr.category_id,
                       (SELECT ROUND(AVG(rv.rating),1) FROM product_reviews rv WHERE rv.product_id = pr.id AND rv.is_approved = 1) AS avg_rating,
                       (SELECT COUNT(*) FROM product_reviews rv WHERE rv.product_id = pr.id AND rv.is_approved = 1) AS review_count
                FROM products pr WHERE $where $order LIMIT $per OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as &$row) {
            $ep = $this->pricing->effectivePrice($row['id']);
            $row['price'] = $ep['unit_effective'];
            $row['compare'] = $ep['discount'] > 0 ? $ep['base'] : null;
            $row['image_url'] = $row['image_url'] ?: 'assets/images/placeholder.jpg';
        }
        unset($row);

        return [
            'products' => $products, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'facets' => $this->facets($where, $args), 'params' => $p,
        ];
    }

    /** Facet counts over the current filtered set. */
    private function facets($where, $args)
    {
        // top-level category counts (map product's subcategory -> its parent)
        $cat = $this->pdo->prepare("
            SELECT COALESCE(pcat.id, c.id) AS top_id, COALESCE(pcat.name, c.name) AS top_name, COUNT(*) AS n
            FROM products pr
            JOIN categories c ON pr.category_id = c.id
            LEFT JOIN categories pcat ON c.parent_id = pcat.id
            WHERE $where
            GROUP BY top_id, top_name ORDER BY n DESC LIMIT 12
        ");
        $cat->execute($args);

        $sel = $this->pdo->prepare("
            SELECT pr.merchant_id, u.email, COUNT(*) n
            FROM products pr JOIN users u ON pr.merchant_id = u.id
            WHERE $where GROUP BY pr.merchant_id, u.email ORDER BY n DESC LIMIT 8
        ");
        $sel->execute($args);

        $pr = $this->pdo->prepare("SELECT MIN(price) mn, MAX(price) mx FROM products pr WHERE $where");
        $pr->execute($args);
        $range = $pr->fetch(PDO::FETCH_ASSOC);

        return [
            'categories' => $cat->fetchAll(PDO::FETCH_ASSOC),
            'sellers' => $sel->fetchAll(PDO::FETCH_ASSOC),
            'price' => ['min' => (float) ($range['mn'] ?? 0), 'max' => (float) ($range['mx'] ?? 0)],
        ];
    }

    private function buildWhere(array $p, $forceLike = false)
    {
        $w = ['1=1']; $a = [];

        $q = trim($p['q'] ?? '');
        if ($q !== '') {
            $bool = $this->booleanQuery($q);
            if ($bool !== '' && !$forceLike) {
                // Indexed keyword search (FULLTEXT, prefix-matched per word) —
                // ~26x faster than LIKE and scales with the catalog.
                $w[] = 'MATCH(pr.name, pr.description, pr.sku) AGAINST(? IN BOOLEAN MODE)';
                $a[] = $bool;
            } else {
                $like = "%$q%";
                $w[] = '(pr.name LIKE ? OR pr.description LIKE ? OR pr.sku LIKE ?)';
                array_push($a, $like, $like, $like);
            }
        }

        if (!empty($p['category_id'])) {
            $w[] = '(pr.category_id = ? OR pr.category_id IN (SELECT id FROM categories WHERE parent_id = ?))';
            $a[] = (int) $p['category_id']; $a[] = (int) $p['category_id'];
        }
        if (!empty($p['merchant_id'])) { $w[] = 'pr.merchant_id = ?'; $a[] = (int) $p['merchant_id']; }
        if (isset($p['min_price']) && $p['min_price'] !== '') { $w[] = 'pr.price >= ?'; $a[] = (float) $p['min_price']; }
        if (isset($p['max_price']) && $p['max_price'] !== '') { $w[] = 'pr.price <= ?'; $a[] = (float) $p['max_price']; }
        if (!empty($p['in_stock'])) { $w[] = 'pr.inventory > 0'; }
        if (!empty($p['featured'])) { $w[] = 'pr.is_featured = 1'; }
        if (!empty($p['on_sale'])) {
            $w[] = "EXISTS (SELECT 1 FROM product_discounts d WHERE d.product_id = pr.id AND d.is_active = 1
                     AND (d.start_date IS NULL OR d.start_date <= NOW()) AND (d.end_date IS NULL OR d.end_date >= NOW()))";
        }
        return [implode(' AND ', $w), $a];
    }

    private function orderBy($sort)
    {
        switch ($sort) {
            case 'price_asc':  return 'ORDER BY pr.price ASC';
            case 'price_desc': return 'ORDER BY pr.price DESC';
            case 'newest':     return 'ORDER BY pr.created_at DESC';
            case 'name':       return 'ORDER BY pr.name ASC';
            default:           return 'ORDER BY pr.is_featured DESC, pr.id DESC';
        }
    }

    /** "cable solar" -> "+cable* +solar*" (all words required, prefix match). */
    private function booleanQuery($q)
    {
        $terms = [];
        foreach (preg_split('/\s+/', $q) as $t) {
            $t = preg_replace('/[+\-<>()~*"@]/u', '', $t);   // strip boolean operators
            if (mb_strlen($t) >= 3) $terms[] = '+' . $t . '*'; // <3 chars is below the FT minimum
        }
        return implode(' ', $terms);
    }

    private function one($sql, $args) { $s = $this->pdo->prepare($sql); $s->execute($args); return $s->fetchColumn(); }
}
