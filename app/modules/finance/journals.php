<?php
declare(strict_types=1);

use App\Core\Database;
use App\Modules\Finance\Repositories\JournalEntryRepository;
use App\Modules\Finance\Services\CompanyContext;

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_permission('finance');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Journal Register | Finance';

$e = static fn ($value): string => htmlspecialchars(
    (string) ($value ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$journals = [];
$farms = [];
$errorMessage = '';
$companyId = 0;
$pageSize = 25;
$currentPage = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 1000000]]
);
$currentPage = $currentPage === false ? 1 : $currentPage;

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
$farmIdInput = trim((string) ($_GET['farm_id'] ?? ''));

$allowedStatuses = ['DRAFT', 'POSTED', 'REVERSED', 'CANCELLED'];

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('The database connection is unavailable.');
    }

    $db = new Database($pdo);
    $companyContext = new CompanyContext($db);
    $companyId = $companyContext->resolve($_SESSION);

    $farmQuery = $db->fetchAll(
        'SELECT f.id, f.name, f.location
         FROM company_farms cf
         INNER JOIN farms f ON f.id = cf.farm_id
         WHERE cf.company_id = :company_id
         ORDER BY f.name ASC, f.id ASC',
        ['company_id' => $companyId]
    );

    $farms = $farmQuery;

    $filters = [];

    if ($farmIdInput !== '') {
        $validatedFarmId = filter_var(
            $farmIdInput,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($validatedFarmId === false) {
            throw new InvalidArgumentException('Select a valid farm.');
        }

        $farmBelongsToCompany = false;
        foreach ($farms as $farm) {
            if ((int) $farm['id'] === $validatedFarmId) {
                $farmBelongsToCompany = true;
                break;
            }
        }

        if (!$farmBelongsToCompany) {
            throw new InvalidArgumentException(
                'The selected farm does not belong to the active company.'
            );
        }

        $filters['farm_id'] = $validatedFarmId;
    }

    if ($dateFrom !== '') {
        $filters['date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $filters['date_to'] = $dateTo;
    }

    if ($status !== '') {
        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException('Select a valid journal status.');
        }

        $filters['status'] = $status;
    }

    $repository = new JournalEntryRepository($db);
    $offset = ($currentPage - 1) * $pageSize;

    // One extra row indicates whether another page exists.
    $resultRows = $repository->findAllByCompany(
        $companyId,
        $filters,
        $pageSize + 1,
        $offset
    );

    $hasNextPage = count($resultRows) > $pageSize;
    $journals = array_slice($resultRows, 0, $pageSize);
} catch (Throwable $exception) {
    if ($exception instanceof InvalidArgumentException) {
        $errorMessage = $exception->getMessage();
    } else {
        error_log('Finance journal register error: ' . $exception->getMessage());
        $errorMessage = 'The journal register could not be loaded. Please try again.';
    }

    $hasNextPage = false;
}

$queryParams = [];
if ($dateFrom !== '') {
    $queryParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $queryParams['date_to'] = $dateTo;
}
if ($status !== '') {
    $queryParams['status'] = $status;
}
if ($farmIdInput !== '') {
    $queryParams['farm_id'] = $farmIdInput;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .journal-page {
        padding: 24px;
        max-width: 1600px;
        margin: 0 auto;
    }
    .journal-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 22px;
    }
    .journal-heading h1 {
        font-size: 1.65rem;
        margin: 0 0 6px;
        font-weight: 700;
    }
    .journal-muted {
        color: #64748b;
        font-size: .92rem;
    }
    .journal-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
    }
    .journal-filters {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 14px;
        align-items: end;
    }
    .journal-filters label {
        display: block;
        font-size: .85rem;
        font-weight: 600;
        margin-bottom: 6px;
    }
    .journal-filters input,
    .journal-filters select {
        width: 100%;
        min-height: 42px;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        padding: 8px 10px;
        background: #fff;
    }
    .journal-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .journal-table-wrap {
        overflow-x: auto;
    }
    .journal-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 900px;
    }
    .journal-table th,
    .journal-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        vertical-align: top;
    }
    .journal-table th {
        background: #f8fafc;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .035em;
        color: #475569;
        white-space: nowrap;
    }
    .journal-table td.numeric,
    .journal-table th.numeric {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .journal-number {
        font-weight: 700;
        color: #0f172a;
    }
    .journal-narration {
        min-width: 200px;
        max-width: 360px;
        overflow-wrap: anywhere;
    }
    .journal-status {
        display: inline-block;
        border-radius: 999px;
        padding: 4px 9px;
        font-size: .75rem;
        font-weight: 700;
        background: #f1f5f9;
        color: #334155;
        white-space: nowrap;
    }
    .journal-status-posted {
        background: #dcfce7;
        color: #166534;
    }
    .journal-status-reversed,
    .journal-status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    .journal-status-draft {
        background: #fef3c7;
        color: #92400e;
    }
    .journal-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 18px;
    }
    @media (max-width: 900px) {
        .journal-filters {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 560px) {
        .journal-page {
            padding: 14px;
        }
        .journal-panel {
            padding: 14px;
        }
        .journal-filters {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="journal-page">
    <div class="journal-heading">
        <div>
            <h1>Journal Register</h1>
            <div class="journal-muted">
                View company journals across all associated farms.
            </div>
        </div>
        <div class="journal-actions">
            <a class="btn btn-outline-secondary"
               href="/yotribe-system/app/modules/finance/index.php">
                Finance Overview
            </a>
        </div>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?= $e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <section class="journal-panel">
        <form method="get" action="journals.php">
            <div class="journal-filters">
                <div>
                    <label for="farm_id">Farm</label>
                    <select name="farm_id" id="farm_id">
                        <option value="">All Farms</option>
                        <?php foreach ($farms as $farm): ?>
                            <option
                                value="<?= (int) $farm['id'] ?>"
                                <?= $farmIdInput === (string) $farm['id'] ? 'selected' : '' ?>>
                                <?= $e($farm['name']) ?>
                                <?= !empty($farm['location']) ? ' — ' . $e($farm['location']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="date_from">Date from</label>
                    <input type="date" name="date_from" id="date_from"
                           value="<?= $e($dateFrom) ?>">
                </div>

                <div>
                    <label for="date_to">Date to</label>
                    <input type="date" name="date_to" id="date_to"
                           value="<?= $e($dateTo) ?>">
                </div>

                <div>
                    <label for="status">Journal status</label>
                    <select name="status" id="status">
                        <option value="">All statuses</option>
                        <?php foreach ($allowedStatuses as $allowedStatus): ?>
                            <option value="<?= $e($allowedStatus) ?>"
                                <?= $status === $allowedStatus ? 'selected' : '' ?>>
                                <?= $e(ucfirst(strtolower($allowedStatus))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="journal-actions mt-3">
                <button type="submit" class="btn btn-primary">Apply filters</button>
                <a href="journals.php" class="btn btn-outline-secondary">Clear filters</a>
            </div>
        </form>
    </section>

    <section class="journal-panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <strong>Journal entries</strong>
                <div class="journal-muted">
                    <?= count($journals) ?> entr<?= count($journals) === 1 ? 'y' : 'ies' ?>
                    on page <?= (int) $currentPage ?>
                </div>
            </div>
            <div class="journal-muted">Amounts are displayed in NGN.</div>
        </div>

        <div class="journal-table-wrap">
            <table class="journal-table">
                <thead>
                    <tr>
                        <th>Journal no.</th>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Narration</th>
                        <th class="numeric">Debit (₦)</th>
                        <th class="numeric">Credit (₦)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($journals === []): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            No journal entries match the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($journals as $journal): ?>
                        <?php
                        $journalStatus = strtoupper((string) ($journal['status'] ?? ''));
                        $statusClass = strtolower($journalStatus);
                        ?>
                        <tr>
                            <td>
                                <span class="journal-number">
                                    <?= $e($journal['journal_no'] ?? '') ?>
                                </span>
                            </td>
                            <td><?= $e($journal['journal_date'] ?? '') ?></td>
                            <td><?= $e($journal['reference_no'] ?? '—') ?: '—' ?></td>
                            <td class="journal-narration">
                                <?= $e($journal['narration'] ?? '') ?>
                            </td>
                            <td class="numeric">
                                <?= number_format((float) ($journal['total_debit'] ?? 0), 2) ?>
                            </td>
                            <td class="numeric">
                                <?= number_format((float) ($journal['total_credit'] ?? 0), 2) ?>
                            </td>
                            <td>
                                <span class="journal-status journal-status-<?= $e($statusClass) ?>">
                                    <?= $e($journalStatus !== '' ? ucfirst(strtolower($journalStatus)) : 'Unknown') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="journal-pagination">
            <div class="journal-muted">
                Page <?= (int) $currentPage ?>
            </div>
            <div class="journal-actions">
                <?php if ($currentPage > 1): ?>
                    <?php
                    $previousParams = $queryParams;
                    $previousParams['page'] = $currentPage - 1;
                    ?>
                    <a class="btn btn-outline-secondary"
                       href="?<?= $e(http_build_query($previousParams)) ?>">
                        Previous
                    </a>
                <?php endif; ?>

                <?php if ($hasNextPage): ?>
                    <?php
                    $nextParams = $queryParams;
                    $nextParams['page'] = $currentPage + 1;
                    ?>
                    <a class="btn btn-outline-primary"
                       href="?<?= $e(http_build_query($nextParams)) ?>">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
