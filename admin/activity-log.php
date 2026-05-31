<?php

declare(strict_types=1);

include '../config.php';
require_once __DIR__ . '/includes/auth.php';
dashboard_require_admin_user($conn);

$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'admin_activity_log'");
$tableOk = $tableCheck && mysqli_num_rows($tableCheck) > 0;

$perPage = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$filterUser = max(0, (int) ($_GET['user'] ?? 0));
$filterAction = trim((string) ($_GET['action'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$actionOptions = [];
if ($tableOk) {
    $ar = mysqli_query($conn, 'SELECT DISTINCT action FROM admin_activity_log ORDER BY action ASC');
    if ($ar) {
        while ($r = mysqli_fetch_assoc($ar)) {
            $actionOptions[] = (string) $r['action'];
        }
    }
}

$userOptions = [];
$ur = mysqli_query($conn, 'SELECT id, name, email FROM users ORDER BY name ASC');
if ($ur) {
    while ($row = mysqli_fetch_assoc($ur)) {
        $userOptions[] = $row;
    }
}

$totalRows = 0;
$rows = [];
$totalPages = 1;

if ($tableOk) {
    $where = ['1=1'];
    if ($filterUser > 0) {
        $where[] = 'l.user_id = ' . $filterUser;
    }
    if ($filterAction !== '') {
        $fa = mysqli_real_escape_string($conn, $filterAction);
        $where[] = "l.action = '{$fa}'";
    }
    if ($dateFrom !== '') {
        $df = mysqli_real_escape_string($conn, $dateFrom);
        $where[] = "l.created_at >= '{$df} 00:00:00'";
    }
    if ($dateTo !== '') {
        $dt = mysqli_real_escape_string($conn, $dateTo);
        $where[] = "l.created_at <= '{$dt} 23:59:59'";
    }
    $whereSql = implode(' AND ', $where);

    $cntRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM admin_activity_log l WHERE {$whereSql}");
    if ($cntRes) {
        $totalRows = (int) (mysqli_fetch_assoc($cntRes)['c'] ?? 0);
    }
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $listSql = "SELECT l.*, u.name AS user_name, u.email AS user_email
        FROM admin_activity_log l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE {$whereSql}
        ORDER BY l.id DESC
        LIMIT {$perPage} OFFSET {$offset}";
    $lr = mysqli_query($conn, $listSql);
    if ($lr) {
        while ($row = mysqli_fetch_assoc($lr)) {
            $rows[] = $row;
        }
    }
}

function lh_activity_action_label(string $action): string
{
    static $map = [
        'login_success' => 'Autentificare reușită',
        'login_failed' => 'Autentificare eșuată',
        'login_failed_disabled' => 'Autentificare refuzată (cont dezactivat)',
        'logout' => 'Deconectare',
        'property_create' => 'Proprietate nouă',
        'property_update' => 'Proprietate actualizată',
        'property_delete' => 'Proprietate ștearsă',
        'property_toggle_active' => 'Status activ/inactiv proprietate',
        'manual_block_add' => 'Blocare manuală adăugată',
        'manual_block_delete' => 'Blocare manuală ștearsă',
        'booking_confirm' => 'Rezervare confirmată',
        'booking_cancel' => 'Rezervare anulată',
        'booking_update' => 'Rezervare actualizată',
        'booking_hold' => 'Rezervare în așteptare',
        'user_create' => 'Utilizator creat',
        'user_update' => 'Utilizator actualizat',
        'user_toggle_status' => 'Status utilizator schimbat',
        'user_delete' => 'Utilizator șters',
    ];

    return $map[$action] ?? $action;
}

function lh_activity_build_query(array $overrides): string
{
    $q = array_merge($_GET, $overrides);
    $parts = [];
    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
    }

    return 'activity-log.php' . ($parts !== [] ? '?' . implode('&', $parts) : '');
}

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Jurnal activitate</h2>
            <p class="text-slate-500">Cine s-a autentificat, ce modificări s-au făcut în panou și de unde (IP).</p>
        </div>
        <a href="dashboard.php" class="bg-cta text-white px-6 py-3 rounded-2xl font-bold flex items-center gap-2 hover:brightness-110 transition-all shadow-lg shadow-black/10">
            <i data-lucide="arrow-left" class="w-5 h-5"></i> Înapoi la Dashboard
        </a>
    </div>

    <?php if (!$tableOk): ?>
        <div class="mb-8 p-5 rounded-2xl border border-amber-200 bg-amber-50 text-amber-900 font-bold shadow-sm">
            Tabela <code class="font-mono text-sm">admin_activity_log</code> nu există. Rulează o dată în MySQL scriptul
            <code class="font-mono text-sm">sql/admin_activity_log.sql</code>.
        </div>
    <?php else: ?>
        <form method="get" action="activity-log.php" class="bg-white border border-slate-100 shadow-sm p-6 rounded-2xl mb-8 flex flex-wrap gap-4 items-end">
            <div class="min-w-[200px]">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block mb-1">Utilizator</label>
                <select name="user" class="w-full p-3 bg-slate-50 border-none rounded-xl font-bold text-slate-800 outline-none focus:ring-2 focus:ring-cta/30">
                    <option value="0">Toți</option>
                    <?php foreach ($userOptions as $u): ?>
                        <option value="<?php echo (int) $u['id']; ?>" <?php echo $filterUser === (int) $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['name'] . ' · ' . $u['email']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="min-w-[220px]">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block mb-1">Acțiune</label>
                <select name="action" class="w-full p-3 bg-slate-50 border-none rounded-xl font-bold text-slate-800 outline-none focus:ring-2 focus:ring-cta/30">
                    <option value="">Toate</option>
                    <?php foreach ($actionOptions as $a): ?>
                        <option value="<?php echo htmlspecialchars($a, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filterAction === $a ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(lh_activity_action_label($a)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block mb-1">De la</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="p-3 bg-slate-50 border-none rounded-xl font-bold text-slate-800 outline-none focus:ring-2 focus:ring-cta/30">
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block mb-1">Până la</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="p-3 bg-slate-50 border-none rounded-xl font-bold text-slate-800 outline-none focus:ring-2 focus:ring-cta/30">
            </div>
            <button type="submit" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-bold hover:bg-slate-800 transition-all">Filtrează</button>
            <a href="activity-log.php" class="text-sm font-bold text-slate-500 hover:text-slate-900 py-3">Resetează</a>
        </form>

        <div class="flex items-center justify-between mb-4 px-1 text-sm text-slate-500 font-bold">
            <span><?php echo (int) $totalRows; ?> înregistrări</span>
            <span>Pagina <?php echo (int) $page; ?> / <?php echo (int) $totalPages; ?></span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-left min-w-[900px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 text-[10px] uppercase font-bold tracking-widest">
                        <th class="px-6 py-4">Data / ora</th>
                        <th class="px-6 py-4">Utilizator</th>
                        <th class="px-6 py-4">Acțiune</th>
                        <th class="px-6 py-4">Entitate</th>
                        <th class="px-6 py-4">Detalii</th>
                        <th class="px-6 py-4">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-sm">
                    <?php if ($rows !== []): ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $detailsRaw = (string) ($row['details'] ?? '');
                            $detailsShort = $detailsRaw;
                            if (strlen($detailsShort) > 120) {
                                $detailsShort = substr($detailsShort, 0, 117) . '…';
                            }
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors align-top">
                                <td class="px-6 py-4 whitespace-nowrap text-slate-600 font-medium">
                                    <?php echo htmlspecialchars((string) ($row['created_at'] ?? '')); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['user_id'])): ?>
                                        <div class="font-bold text-slate-900"><?php echo htmlspecialchars((string) ($row['user_name'] ?? '—')); ?></div>
                                        <div class="text-xs text-slate-500 break-all"><?php echo htmlspecialchars((string) ($row['user_email'] ?? '')); ?></div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">ID #<?php echo (int) $row['user_id']; ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-400 font-bold">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 font-bold text-slate-800">
                                    <?php echo htmlspecialchars(lh_activity_action_label((string) ($row['action'] ?? ''))); ?>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5"><?php echo htmlspecialchars((string) ($row['action'] ?? '')); ?></div>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <?php if (!empty($row['entity_type'])): ?>
                                        <span class="font-bold"><?php echo htmlspecialchars((string) $row['entity_type']); ?></span>
                                        <?php if (!empty($row['entity_id'])): ?>
                                            <span class="text-slate-400">#<?php echo (int) $row['entity_id']; ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-600 font-mono max-w-xs break-all" title="<?php echo htmlspecialchars($detailsRaw); ?>">
                                    <?php echo $detailsRaw !== '' ? htmlspecialchars($detailsShort) : '—'; ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500 font-mono whitespace-nowrap">
                                    <?php echo htmlspecialchars((string) ($row['ip_address'] ?? '')); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center text-slate-400 font-bold">Nicio înregistrare pentru filtrele selectate.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center gap-2 mt-8 flex-wrap">
                <?php if ($page > 1): ?>
                    <a href="<?php echo htmlspecialchars(lh_activity_build_query(['page' => $page - 1]), ENT_QUOTES, 'UTF-8'); ?>" class="px-4 py-2 rounded-xl bg-white border border-slate-200 font-bold text-slate-700 hover:bg-slate-50">← Anterior</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo htmlspecialchars(lh_activity_build_query(['page' => $page + 1]), ENT_QUOTES, 'UTF-8'); ?>" class="px-4 py-2 rounded-xl bg-white border border-slate-200 font-bold text-slate-700 hover:bg-slate-50">Următor →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
</script>

<?php include 'includes/footer.php'; ?>
