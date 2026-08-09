<?php
/**
 * Promo codes (marketing): sitewide, product-specific, or PERSONAL (locked to
 * one customer). Validated at checkout; the discount reduces the order
 * subtotal before tax.
 */
class PromoCodeService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array $cartItems [['product_id'=>, 'quantity'=>, 'price'=>], ...] (effective prices)
     * @return array{valid:bool, error?:string, discount?:float, row?:array}
     */
    public function validate($code, $userId, array $cartItems)
    {
        $code = strtoupper(trim($code));
        if ($code === '') return ['valid' => false, 'error' => 'No code'];

        $s = $this->pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1");
        $s->execute([$code]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['valid' => false, 'error' => 'Invalid promo code'];

        $today = $this->pdo->query('SELECT CURDATE()')->fetchColumn();
        if ($r['starts'] && $r['starts'] > $today) return ['valid' => false, 'error' => 'This code is not active yet'];
        if ($r['ends'] && $r['ends'] < $today) return ['valid' => false, 'error' => 'This code has expired'];
        if ((int) $r['max_uses'] > 0 && (int) $r['used_count'] >= (int) $r['max_uses']) return ['valid' => false, 'error' => 'This code has been fully redeemed'];
        if ($r['user_id'] !== null && (int) $r['user_id'] !== (int) $userId) return ['valid' => false, 'error' => 'This code is personal and not valid for your account'];

        // Eligible base: whole order, or only the named product's lines.
        $base = 0.0;
        foreach ($cartItems as $it) {
            if ($r['product_id'] === null || (int) $r['product_id'] === (int) $it['product_id']) {
                $base += (float) $it['price'] * (int) $it['quantity'];
            }
        }
        if ($base <= 0) return ['valid' => false, 'error' => 'This code does not apply to the items in your cart'];

        $discount = $r['discount_type'] === 'percentage'
            ? round($base * (float) $r['value'] / 100, 2)
            : min(round((float) $r['value'], 2), $base);

        return ['valid' => true, 'discount' => $discount, 'row' => $r];
    }

    public function redeem($code)
    {
        $this->pdo->prepare("UPDATE promo_codes SET used_count = used_count + 1 WHERE code = ?")->execute([strtoupper(trim($code))]);
    }
}
