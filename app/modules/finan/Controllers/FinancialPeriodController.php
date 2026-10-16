<?php
declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class FinancialPeriodController
{
    public function __construct(private readonly PDO $pdo, private readonly int $companyId) {}

    /** @return array{years:array,periods:array,summary:array} */
    public function index(): array
    {
        $years = $this->all(
            'SELECT fy.*, COUNT(fp.id) AS period_count,
                    SUM(CASE WHEN fp.status = \'OPEN\' THEN 1 ELSE 0 END) AS open_periods
             FROM financial_years fy
             LEFT JOIN financial_periods fp ON fp.financial_year_id = fy.id AND fp.company_id = fy.company_id
             WHERE fy.company_id = ?
             GROUP BY fy.id
             ORDER BY fy.start_date DESC, fy.id DESC',
            [$this->companyId]
        );
        $periods = $this->all(
            'SELECT fp.*, fy.year_code, fy.year_name
             FROM financial_periods fp
             INNER JOIN financial_years fy ON fy.id = fp.financial_year_id AND fy.company_id = fp.company_id
             WHERE fp.company_id = ?
             ORDER BY fy.start_date DESC, fp.period_no ASC',
            [$this->companyId]
        );
        $summary = [
            'years' => count($years),
            'open_years' => count(array_filter($years, static fn(array $y): bool => $y['status'] === 'OPEN')),
            'periods' => count($periods),
            'open_periods' => count(array_filter($periods, static fn(array $p): bool => $p['status'] === 'OPEN')),
        ];
        return compact('years', 'periods', 'summary');
    }

    /** Create a financial year and its 12 monthly periods atomically. */
    public function createYear(array $input, int $staffId): string
    {
        $startRaw = trim((string)($input['start_date'] ?? ''));
        $endRaw = trim((string)($input['end_date'] ?? ''));
        $name = trim((string)($input['year_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new RuntimeException('Enter a financial year name of no more than 100 characters.');
        }
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startRaw);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endRaw);
        if (!$start || !$end || $start->format('Y-m-d') !== $startRaw || $end->format('Y-m-d') !== $endRaw) {
            throw new RuntimeException('Enter valid start and end dates.');
        }
        if ($start->format('d') !== '01' || $end->format('d') !== $end->format('t')) {
            throw new RuntimeException('For monthly accounting periods, the start date must be the first day of a month and the end date must be the last day of a month.');
        }
        if ($end <= $start) throw new RuntimeException('The financial year end date must be after its start date.');
        $months = ((int)$end->format('Y') - (int)$start->format('Y')) * 12 + ((int)$end->format('n') - (int)$start->format('n')) + 1;
        if ($months !== 12) throw new RuntimeException('A financial year must cover exactly 12 calendar months.');

        $yearCode = $start->format('Y') === $end->format('Y')
            ? 'FY' . $start->format('Y')
            : 'FY' . $start->format('Y') . '-' . $end->format('Y');
        $today = new DateTimeImmutable('today');
        $isCurrentYear = $today >= $start && $today <= $end;
        $periodDates = [];
        $cursor = $start;
        for ($i = 1; $i <= 12; $i++) {
            $periodStart = $cursor->modify('first day of this month');
            $periodEnd = $periodStart->modify('last day of this month');
            $periodDates[] = [$i, $periodStart, $periodEnd];
            $cursor = $periodStart->modify('first day of next month');
        }

        try {
            $this->pdo->beginTransaction();
            $overlap = $this->one(
                'SELECT id FROM financial_years WHERE company_id = ? AND NOT (end_date < ? OR start_date > ?) LIMIT 1 FOR UPDATE',
                [$this->companyId, $startRaw, $endRaw]
            );
            if ($overlap) throw new RuntimeException('These dates overlap an existing financial year. Choose a non-overlapping 12-month period.');

            $insertYear = $this->pdo->prepare(
                'INSERT INTO financial_years (uuid, company_id, year_code, year_name, start_date, end_date, status, is_current, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, \'OPEN\', ?, ?, ?)'
            );
            $insertYear->execute([$this->uuid(), $this->companyId, $yearCode, $name, $startRaw, $endRaw, $isCurrentYear ? 1 : 0, $staffId, $staffId]);
            $yearId = (int)$this->pdo->lastInsertId();

            if ($isCurrentYear) {
                $stmt = $this->pdo->prepare('UPDATE financial_years SET is_current = 0, updated_by = ? WHERE company_id = ? AND id <> ?');
                $stmt->execute([$staffId, $this->companyId, $yearId]);
            }

            $insertPeriod = $this->pdo->prepare(
                'INSERT INTO financial_periods (uuid, company_id, financial_year_id, period_no, period_code, period_name, start_date, end_date, status, is_current, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'OPEN\', ?, ?, ?)'
            );
            foreach ($periodDates as [$number, $periodStart, $periodEnd]) {
                $periodCurrent = $today >= $periodStart && $today <= $periodEnd;
                $insertPeriod->execute([
                    $this->uuid(), $this->companyId, $yearId, $number,
                    $start->format('Y') . '-P' . str_pad((string)$number, 2, '0', STR_PAD_LEFT),
                    $periodStart->format('F Y'), $periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d'),
                    $periodCurrent ? 1 : 0, $staffId, $staffId,
                ]);
            }
            if ($isCurrentYear) {
                $stmt = $this->pdo->prepare('UPDATE financial_periods SET is_current = 0, updated_by = ? WHERE company_id = ? AND financial_year_id <> ?');
                $stmt->execute([$staffId, $this->companyId, $yearId]);
            }
            $this->audit($staffId, 'financial_years', (string)$yearId, 'CREATE', null, [
                'year_code' => $yearCode, 'year_name' => $name, 'start_date' => $startRaw,
                'end_date' => $endRaw, 'periods_generated' => 12,
            ]);
            $this->pdo->commit();
            return 'Financial year created successfully with 12 monthly accounting periods.';
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function setStatus(int $periodId, string $status, int $staffId): string
    {
        if (!in_array($status, ['OPEN', 'CLOSED', 'LOCKED'], true)) throw new RuntimeException('Invalid accounting period status.');
        $period = $this->one(
            'SELECT fp.*, fy.status AS year_status FROM financial_periods fp
             INNER JOIN financial_years fy ON fy.id = fp.financial_year_id AND fy.company_id = fp.company_id
             WHERE fp.id = ? AND fp.company_id = ? LIMIT 1',
            [$periodId, $this->companyId]
        );
        if (!$period) throw new RuntimeException('The selected accounting period was not found.');
        if ($period['year_status'] !== 'OPEN' && $status === 'OPEN') throw new RuntimeException('A period cannot be opened while its financial year is closed or locked.');
        if ($status === 'OPEN') {
            $hasPosted = $this->one('SELECT id FROM journal_entries WHERE company_id = ? AND financial_period_id = ? AND status = \'POSTED\' LIMIT 1', [$this->companyId, $periodId]);
            if ($period['status'] === 'LOCKED' && $hasPosted) throw new RuntimeException('This locked period contains posted journals. Use the approved period-reopening procedure instead of reopening it here.');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE financial_periods SET status = ?, closed_by = ?, closed_at = ?, updated_by = ? WHERE id = ? AND company_id = ?');
            $stmt->execute([$status, $status === 'OPEN' ? null : $staffId, $status === 'OPEN' ? null : date('Y-m-d H:i:s'), $staffId, $periodId, $this->companyId]);
            $this->audit($staffId, 'financial_periods', (string)$periodId, 'STATUS_CHANGE', ['status' => $period['status']], ['status' => $status]);
            $this->pdo->commit();
            return 'Accounting period status updated to ' . $status . '.';
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function audit(int $staffId, string $entity, string $entityId, string $action, ?array $old, array $new): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO finance_audit_log (uuid, company_id, farm_id, module, entity_name, entity_id, action, old_values, new_values, remarks, performed_by, ip_address, user_agent)
             VALUES (?, ?, ?, \'finance\', ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $farmId = isset($_SESSION['active_farm_id']) ? (int)$_SESSION['active_farm_id'] : (isset($_SESSION['farm_id']) ? (int)$_SESSION['farm_id'] : null);
        $stmt->execute([
            $this->uuid(), $this->companyId, $farmId, $entity, $entityId, $action,
            $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Financial period management', $staffId,
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }

    private function all(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: null;
    }
    private function uuid(): string
    {
        $b = random_bytes(16); $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
