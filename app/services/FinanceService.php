<?php
class FinanceService {

    public static function recordSale(PDO $pdo, int $farm_id, int $sale_id, float $amount) {
        $stmt = $pdo->prepare("
            INSERT INTO finance_ledger (farm_id, ref_type, ref_id, credit, description)
            VALUES (?, 'sale', ?, ?, 'Fish sale')
        ");
        $stmt->execute([$farm_id, $sale_id, $amount]);
    }

    public static function recordExpense(PDO $pdo, int $farm_id, int $expense_id, float $amount, string $note) {
        $stmt = $pdo->prepare("
            INSERT INTO finance_ledger (farm_id, ref_type, ref_id, debit, description)
            VALUES (?, 'expense', ?, ?, ?)
        ");
        $stmt->execute([$farm_id, $expense_id, $amount, $note]);
    }

    public static function farmBalance(PDO $pdo, int $farm_id): float {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(credit - debit),0)
            FROM finance_ledger
            WHERE farm_id = ?
        ");
        $stmt->execute([$farm_id]);
        return (float) $stmt->fetchColumn();
    }
}
