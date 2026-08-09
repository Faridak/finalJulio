<?php
/**
 * Type-2 custom work: work order (BOM) → quote → A/R → payment.
 *
 * Money flow (double entry):
 *   consume material  ->  Dr 5000 COGS / Cr 1200 Inventory (at cost)  + stock out
 *   invoice quote     ->  Dr 1100 Accounts Receivable / Cr 4400 Engineering Revenue
 *   receive payment   ->  Dr 1000 Cash / Cr 1100 Accounts Receivable
 *
 * Labor cost = SUM(task actual_hours) × quotes.hourly_rate.
 * Final quote = (materials + labor + overhead) × (1 + markup %).
 */
require_once __DIR__ . '/AccountingService.php';

class WorkOrderService
{
    private $pdo;
    private $acc;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->acc = new AccountingService($pdo);
    }

    /** Consume material into the work order: stock out + COGS + BOM line. */
    public function addMaterial($quoteId, $productId, $qty, $userId = null)
    {
        $qty = max(1, (int) $qty);
        $p = $this->pdo->prepare("SELECT id, name, cost_price, inventory FROM products WHERE id = ?");
        $p->execute([(int) $productId]);
        $prod = $p->fetch(PDO::FETCH_ASSOC);
        if (!$prod) return ['success' => false, 'error' => 'Product not found'];
        if ((int) $prod['inventory'] < $qty) return ['success' => false, 'error' => "Only {$prod['inventory']} in stock"];

        $unitCost = (float) $prod['cost_price'] ?: round((float) $this->pdo->query("SELECT price FROM products WHERE id=" . (int) $productId)->fetchColumn() * 0.6, 2);
        $lineCost = round($unitCost * $qty, 2);

        $this->pdo->beginTransaction();
        try {
            // BOM line
            $this->pdo->prepare("INSERT INTO work_order_materials (quote_id, product_id, quantity, unit_cost, total_cost, added_by) VALUES (?,?,?,?,?,?)")
                ->execute([(int) $quoteId, (int) $productId, $qty, $unitCost, $lineCost, $userId]);

            // stock out (ledger-of-truth movement) + product counter
            $loc = $this->pdo->query("SELECT location_id FROM product_inventory WHERE product_id=" . (int) $productId . " ORDER BY quantity_on_hand DESC LIMIT 1")->fetchColumn();
            $this->pdo->prepare("
                INSERT INTO inventory_movements (product_id, location_id, movement_type, quantity, unit_cost, total_cost, reference_type, reference_id, reason, created_by, created_at)
                VALUES (?, ?, 'out', ?, ?, ?, 'work_order', ?, 'Consumed by work order', ?, NOW())
            ")->execute([(int) $productId, $loc ?: 1, $qty, $unitCost, $lineCost, (int) $quoteId, $userId]);
            $this->pdo->prepare("UPDATE products SET inventory = GREATEST(inventory - ?, 0) WHERE id = ?")->execute([$qty, (int) $productId]);
            if ($loc) {
                $this->pdo->prepare("UPDATE product_inventory SET quantity_on_hand = GREATEST(quantity_on_hand - ?, 0), quantity_available = GREATEST(quantity_available - ?, 0), last_movement_at = NOW() WHERE product_id=? AND location_id=?")
                    ->execute([$qty, $qty, (int) $productId, $loc]);
            }

            // COGS at consumption
            $this->acc->postJournal(
                [['code' => '5000', 'debit' => $lineCost], ['code' => '1200', 'credit' => $lineCost]],
                "Work order #$quoteId material: {$prod['name']} × $qty", 'work_order', (int) $quoteId);

            // roll the quote's material cost up
            $this->pdo->prepare("UPDATE quotes SET material_cost = COALESCE(material_cost,0) + ?, actual_material_cost = COALESCE(actual_material_cost,0) + ? WHERE id = ?")
                ->execute([$lineCost, $lineCost, (int) $quoteId]);

            $this->pdo->commit();
            return ['success' => true, 'line_cost' => $lineCost, 'unit_cost' => $unitCost];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Cost summary: materials (BOM), labor (task hours × rate), totals. */
    public function costing($quoteId)
    {
        $q = $this->pdo->prepare("SELECT * FROM quotes WHERE id = ?");
        $q->execute([(int) $quoteId]);
        $quote = $q->fetch(PDO::FETCH_ASSOC);
        if (!$quote) return null;

        $mat = $this->pdo->prepare("
            SELECT wm.*, p.name, p.sku FROM work_order_materials wm JOIN products p ON wm.product_id = p.id
            WHERE wm.quote_id = ? ORDER BY wm.id");
        $mat->execute([(int) $quoteId]);
        $materials = $mat->fetchAll(PDO::FETCH_ASSOC);
        $materialCost = round(array_sum(array_column($materials, 'total_cost')), 2);

        $hours = (float) $this->pdo->query("SELECT COALESCE(SUM(actual_hours),0) FROM engineering_tasks WHERE quote_id = " . (int) $quoteId)->fetchColumn();
        $rate = (float) ($quote['hourly_rate'] ?: 45);
        $laborCost = round($hours * $rate, 2);
        $overhead = (float) ($quote['overhead_cost'] ?: 0);
        $markup = (float) ($quote['markup_percentage'] ?: 25);
        $cost = $materialCost + $laborCost + $overhead;
        $final = round($cost * (1 + $markup / 100), 2);

        return [
            'quote' => $quote, 'materials' => $materials,
            'material_cost' => $materialCost, 'hours' => $hours, 'hourly_rate' => $rate,
            'labor_cost' => $laborCost, 'overhead' => $overhead, 'markup_pct' => $markup,
            'total_cost' => round($cost, 2), 'final_amount' => $final,
        ];
    }

    /** Freeze the price on the quote (labor+materials+overhead × markup). */
    public function finalizeQuote($quoteId, $rate = null, $markup = null, $overhead = null)
    {
        if ($rate !== null) $this->pdo->prepare("UPDATE quotes SET hourly_rate=? WHERE id=?")->execute([(float) $rate, (int) $quoteId]);
        if ($markup !== null) $this->pdo->prepare("UPDATE quotes SET markup_percentage=? WHERE id=?")->execute([(float) $markup, (int) $quoteId]);
        if ($overhead !== null) $this->pdo->prepare("UPDATE quotes SET overhead_cost=? WHERE id=?")->execute([(float) $overhead, (int) $quoteId]);

        $c = $this->costing($quoteId);
        $this->pdo->prepare("UPDATE quotes SET labor_cost=?, total_cost=?, final_quote_amount=?, actual_hours=? WHERE id=?")
            ->execute([$c['labor_cost'], $c['total_cost'], $c['final_amount'], $c['hours'], (int) $quoteId]);
        return $c;
    }

    /** Invoice the customer: Dr A/R / Cr Engineering Revenue; status -> invoiced. */
    public function invoice($quoteId)
    {
        $c = $this->finalizeQuote($quoteId);
        if ($c['final_amount'] <= 0) return ['success' => false, 'error' => 'Final amount is zero — add materials/labor first'];
        if ($c['quote']['status'] === 'invoiced') return ['success' => false, 'error' => 'Already invoiced'];
        if ($this->acc->isPosted('work_order_invoice', (int) $quoteId)) return ['success' => false, 'error' => 'Invoice journal already posted'];

        $this->acc->postJournal(
            [['code' => '1100', 'debit' => $c['final_amount']], ['code' => '4400', 'credit' => $c['final_amount']]],
            "Work order #$quoteId invoiced ({$c['quote']['product_type']})", 'work_order_invoice', (int) $quoteId);
        $this->pdo->prepare("UPDATE quotes SET status='invoiced', invoiced_at=NOW() WHERE id=?")->execute([(int) $quoteId]);
        return ['success' => true, 'amount' => $c['final_amount']];
    }

    /** Customer pays the invoice: Dr Cash / Cr A/R. */
    public function receivePayment($quoteId)
    {
        $q = $this->pdo->prepare("SELECT final_quote_amount, status FROM quotes WHERE id=?");
        $q->execute([(int) $quoteId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['status'] !== 'invoiced') return ['success' => false, 'error' => 'Quote is not invoiced'];
        if ($this->acc->isPosted('work_order_payment', (int) $quoteId)) return ['success' => false, 'error' => 'Payment already recorded'];

        $amt = (float) $row['final_quote_amount'];
        $this->acc->postJournal(
            [['code' => '1000', 'debit' => $amt], ['code' => '1100', 'credit' => $amt]],
            "Work order #$quoteId payment received", 'work_order_payment', (int) $quoteId);
        return ['success' => true, 'amount' => $amt];
    }
}
