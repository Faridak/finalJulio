<?php
/**
 * Persistent cart for logged-in users.
 *
 * The SESSION cart ($_SESSION['cart'] = [product_id => qty]) stays the runtime
 * representation everywhere; this service mirrors it to the cart_items table
 * so it survives browser close / device switch, and merges the guest cart
 * into the saved cart at login.
 */
class CartService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * On login: guest session cart + saved DB cart -> union (qty = max of the
     * two, so refreshing/re-adding never doubles). Result goes to BOTH stores.
     */
    public function mergeOnLogin($userId)
    {
        $session = $_SESSION['cart'] ?? [];

        $saved = [];
        $stmt = $this->pdo->prepare("SELECT product_id, quantity FROM cart_items WHERE user_id = ?");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $saved[(int) $r['product_id']] = (int) $r['quantity'];
        }

        $merged = $saved;
        foreach ($session as $pid => $qty) {
            $pid = (int) $pid;
            $merged[$pid] = max($merged[$pid] ?? 0, (int) $qty);
        }

        // Drop products that no longer exist / are out of stock entirely.
        if ($merged) {
            $ids = array_keys($merged);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $ok = $this->pdo->prepare("SELECT id FROM products WHERE id IN ($in)");
            $ok->execute($ids);
            $valid = array_map('intval', $ok->fetchAll(PDO::FETCH_COLUMN));
            $merged = array_intersect_key($merged, array_flip($valid));
        }

        $_SESSION['cart'] = $merged;
        $this->persist($userId);
        return count($merged);
    }

    /** Mirror the current session cart to the DB (call after any mutation). */
    public function persist($userId)
    {
        $cart = $_SESSION['cart'] ?? [];
        $this->pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
        if (!$cart) return;
        $ins = $this->pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
        foreach ($cart as $pid => $qty) {
            if ((int) $qty > 0) $ins->execute([$userId, (int) $pid, (int) $qty]);
        }
    }

    /** Clear both stores (after successful order). */
    public function clear($userId)
    {
        unset($_SESSION['cart']);
        $this->pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
    }
}
