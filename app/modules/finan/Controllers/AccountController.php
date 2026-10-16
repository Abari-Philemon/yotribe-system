<?php
declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use PDO;
use RuntimeException;
use Throwable;
use App\Modules\Finance\Requests\AccountRequest;

final class AccountController
{
    public function __construct(private readonly PDO $pdo, private readonly int $companyId)
    {
    }

    /** @return array<string,mixed> */
    public function index(): array
    {
        $this->ensureReferenceData();
        $search = trim((string)($_GET['q'] ?? ''));
        $sql = 'SELECT a.*, t.type_name, t.type_code, c.class_name, p.account_name AS parent_name
                FROM accounts a
                INNER JOIN account_types t ON t.id = a.account_type_id AND t.company_id = a.company_id
                INNER JOIN account_classes c ON c.id = a.account_class_id AND c.company_id = a.company_id
                LEFT JOIN accounts p ON p.id = a.parent_account_id AND p.company_id = a.company_id
                WHERE a.company_id = :company_id';
        $params = ['company_id' => $this->companyId];
        if ($search !== '') {
            $sql .= ' AND (a.account_code LIKE :q_code OR a.account_name LIKE :q_name OR a.short_name LIKE :q_short)';
            $like = '%' . $search . '%';
            $params['q_code'] = $like;
            $params['q_name'] = $like;
            $params['q_short'] = $like;
        }
        $sql .= ' ORDER BY a.account_code ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $types = $this->fetchAll('SELECT id, type_code, type_name, normal_balance FROM account_types WHERE company_id = ? AND is_active = 1 ORDER BY display_order, type_name', [$this->companyId]);
        $classes = $this->fetchAll('SELECT id, account_type_id, class_code, class_name FROM account_classes WHERE company_id = ? AND is_active = 1 ORDER BY display_order, class_name', [$this->companyId]);
        $parents = $this->fetchAll('SELECT id, account_code, account_name FROM accounts WHERE company_id = ? AND is_active = 1 ORDER BY account_code', [$this->companyId]);

        return compact('accounts', 'types', 'classes', 'parents', 'search');
    }

    /** @return array{ok:bool,message:string,errors:array<string,string>} */
    public function create(array $input, int $staffId): array
    {
        $validated = AccountRequest::validate($input);
        if ($validated['errors'] !== []) {
            return ['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $validated['errors']];
        }
        $data = $validated['data'];

        $type = $this->fetchOne('SELECT id, normal_balance FROM account_types WHERE id = ? AND company_id = ? AND is_active = 1', [$data['account_type_id'], $this->companyId]);
        $class = $this->fetchOne('SELECT id FROM account_classes WHERE id = ? AND account_type_id = ? AND company_id = ? AND is_active = 1', [$data['account_class_id'], $data['account_type_id'], $this->companyId]);
        if (!$type || !$class) {
            return ['ok' => false, 'message' => 'The selected account type and class do not match. Refresh the page and try again.', 'errors' => []];
        }
        if ($data['parent_account_id'] !== null) {
            $parent = $this->fetchOne('SELECT id, account_type_id, account_level, account_path, allow_posting FROM accounts WHERE id = ? AND company_id = ? AND is_active = 1', [$data['parent_account_id'], $this->companyId]);
            if (!$parent) {
                return ['ok' => false, 'message' => 'The selected parent account is not available.', 'errors' => []];
            }
            if ((int)$parent['account_type_id'] !== $data['account_type_id']) {
                return ['ok' => false, 'message' => 'A parent account must have the same account type.', 'errors' => []];
            }
            if ((int)$parent['allow_posting'] === 1) {
                return ['ok' => false, 'message' => 'Choose a non-posting parent account. Posting accounts cannot contain child accounts.', 'errors' => []];
            }
            $level = (int)$parent['account_level'] + 1;
            $path = trim((string)($parent['account_path'] ?? ''), '/');
            $path = ($path === '' ? (string)$parent['id'] : $path . '/' . $parent['id']);
        } else {
            $level = 1;
            $path = null;
        }
        if ($data['is_cash_account'] && $data['is_bank_account']) {
            return ['ok' => false, 'message' => 'An account cannot be marked as both cash and bank.', 'errors' => []];
        }
        if (($data['is_cash_account'] || $data['is_bank_account']) && (string)$type['normal_balance'] !== 'debit') {
            return ['ok' => false, 'message' => 'Cash and bank accounts must use a debit-normal account type.', 'errors' => []];
        }
        $exists = $this->fetchOne('SELECT id FROM accounts WHERE company_id = ? AND (account_code = ? OR system_key = ?)', [$this->companyId, $data['account_code'], 'USER_' . $data['account_code']]);
        if ($exists) {
            return ['ok' => false, 'message' => 'That account code is already in use.', 'errors' => ['account_code' => 'Choose a unique account code.']];
        }

        try {
            $this->pdo->beginTransaction();
            $sql = 'INSERT INTO accounts
                (uuid, company_id, account_type_id, account_class_id, parent_account_id, account_code, system_key, account_name, short_name, description, normal_balance, account_level, account_path, opening_balance, current_balance, allow_posting, is_control_account, is_cash_account, is_bank_account, is_system, is_active, created_by, updated_by)
                VALUES (:uuid, :company_id, :account_type_id, :account_class_id, :parent_account_id, :account_code, :system_key, :account_name, :short_name, :description, :normal_balance, :account_level, :account_path, :opening_balance, :current_balance, :allow_posting, 0, :is_cash_account, :is_bank_account, 0, 1, :created_by, :updated_by)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'uuid' => $this->uuid(), 'company_id' => $this->companyId,
                'account_type_id' => $data['account_type_id'], 'account_class_id' => $data['account_class_id'],
                'parent_account_id' => $data['parent_account_id'], 'account_code' => $data['account_code'],
                'system_key' => 'USER_' . $data['account_code'], 'account_name' => $data['account_name'],
                'short_name' => $data['short_name'], 'description' => $data['description'],
                'normal_balance' => $type['normal_balance'], 'account_level' => $level, 'account_path' => $path,
                'opening_balance' => $data['opening_balance'], 'current_balance' => $data['opening_balance'],
                'allow_posting' => $data['allow_posting'], 'is_cash_account' => $data['is_cash_account'],
                'is_bank_account' => $data['is_bank_account'], 'created_by' => $staffId, 'updated_by' => $staffId,
            ]);
            $id = (int)$this->pdo->lastInsertId();
            $this->audit($id, 'CREATE', null, $data, $staffId);
            $this->pdo->commit();
            return ['ok' => true, 'message' => 'Account created successfully.', 'errors' => []];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ((string)$e->getCode() === '23000') {
                return ['ok' => false, 'message' => 'The account could not be saved because its code or system key already exists.', 'errors' => ['account_code' => 'Use a unique account code.']];
            }
            throw $e;
        }
    }

    public function toggle(int $id, int $staffId): string
    {
        $account = $this->fetchOne('SELECT id, is_active, is_system FROM accounts WHERE id = ? AND company_id = ?', [$id, $this->companyId]);
        if (!$account) return 'Account not found.';
        if ((int)$account['is_system'] === 1) return 'System accounts cannot be deactivated here.';
        $newStatus = (int)$account['is_active'] === 1 ? 0 : 1;
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE accounts SET is_active = ?, updated_by = ? WHERE id = ? AND company_id = ?');
            $stmt->execute([$newStatus, $staffId, $id, $this->companyId]);
            $this->audit($id, $newStatus ? 'ACTIVATE' : 'DEACTIVATE', ['is_active' => (int)$account['is_active']], ['is_active' => $newStatus], $staffId);
            $this->pdo->commit();
            return $newStatus ? 'Account activated.' : 'Account deactivated.';
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function ensureReferenceData(): void
    {
        $types = [
            ['ASSET', 'Assets', 'debit', 10], ['LIABILITY', 'Liabilities', 'credit', 20],
            ['EQUITY', 'Equity', 'credit', 30], ['INCOME', 'Income', 'credit', 40], ['EXPENSE', 'Expenses', 'debit', 50],
        ];
        $classes = [
            ['CA','Current Assets','ASSET',10], ['NCA','Non-current Assets','ASSET',20],
            ['CL','Current Liabilities','LIABILITY',30], ['NCL','Non-current Liabilities','LIABILITY',40],
            ['EQ','Equity','EQUITY',50], ['REV','Operating Revenue','INCOME',60], ['OINC','Other Income','INCOME',70],
            ['COS','Cost of Sales','EXPENSE',80], ['OPEX','Operating Expenses','EXPENSE',90], ['OEXP','Other Expenses','EXPENSE',100],
        ];
        $this->pdo->beginTransaction();
        try {
            $getType = $this->pdo->prepare('SELECT id FROM account_types WHERE company_id = ? AND type_code = ? LIMIT 1');
            $insertType = $this->pdo->prepare('INSERT INTO account_types (uuid, company_id, type_code, type_name, normal_balance, display_order, description, is_system, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)');
            foreach ($types as [$code,$name,$normal,$order]) {
                $getType->execute([$this->companyId,$code]);
                if (!$getType->fetchColumn()) $insertType->execute([$this->uuid(),$this->companyId,$code,$name,$normal,$order,'Standard chart-of-accounts type']);
            }
            $getType->execute([$this->companyId,'ASSET']);
            $typeIds = [];
            $stmtType = $this->pdo->prepare('SELECT id, type_code FROM account_types WHERE company_id = ?');
            $stmtType->execute([$this->companyId]);
            foreach ($stmtType->fetchAll(PDO::FETCH_ASSOC) as $row) $typeIds[$row['type_code']] = (int)$row['id'];
            $getClass = $this->pdo->prepare('SELECT id FROM account_classes WHERE company_id = ? AND class_code = ? LIMIT 1');
            $insertClass = $this->pdo->prepare('INSERT INTO account_classes (uuid, company_id, account_type_id, class_code, class_name, description, display_order, is_system, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)');
            foreach ($classes as [$code,$name,$typeCode,$order]) {
                if (!isset($typeIds[$typeCode])) continue;
                $getClass->execute([$this->companyId,$code]);
                if (!$getClass->fetchColumn()) $insertClass->execute([$this->uuid(),$this->companyId,$typeIds[$typeCode],$code,$name,'Standard chart-of-accounts class',$order]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function audit(int $id, string $action, ?array $old, array $new, int $staffId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO finance_audit_log (uuid, company_id, farm_id, module, entity_name, entity_id, action, old_values, new_values, remarks, performed_by, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $farmId = function_exists('farm_id') ? farm_id() : null;
        $stmt->execute([$this->uuid(), $this->companyId, $farmId ?: null, 'finance', 'accounts', $id, $action,
            $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR), json_encode($new, JSON_THROW_ON_ERROR),
            'Chart of Accounts maintenance', $staffId, $_SERVER['REMOTE_ADDR'] ?? null, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    }

    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: null;
    }
    private function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes); return sprintf('%s-%s-%s-%s-%s', substr($hex,0,8),substr($hex,8,4),substr($hex,12,4),substr($hex,16,4),substr($hex,20,12));
    }
}
