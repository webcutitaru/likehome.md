<?php
include('../config.php');
require_once __DIR__ . '/../includes/ical_importer.php';
include('includes/header.php');

$forbidden = $_GET['forbidden'] ?? '';

$status_filter = $_GET['status'] ?? 'all';
$allowed_status_filters = ['all', 'active', 'inactive'];
if (!in_array($status_filter, $allowed_status_filters, true)) {
    $status_filter = 'all';
}

const LH_DASHBOARD_SEARCH_MAX = 255;

$search_q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if (strlen($search_q) > LH_DASHBOARD_SEARCH_MAX) {
    $search_q = substr($search_q, 0, LH_DASHBOARD_SEARCH_MAX);
}
$has_search = ($search_q !== '');

$dash_href = static function (array $params) use ($search_q): string {
    if ($search_q !== '') {
        $params['q'] = $search_q;
    }
    $qs = http_build_query($params);

    return $qs === '' ? 'dashboard.php' : ('dashboard.php?' . $qs);
};

$href_clear_search = $status_filter === 'all'
    ? 'dashboard.php'
    : ('dashboard.php?' . http_build_query(['status' => $status_filter]));

$res_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM properties");
$data_total = mysqli_fetch_assoc($res_total);

$res_active = mysqli_query($conn, "SELECT COUNT(*) as total FROM properties WHERE is_active = 1");
$data_active = mysqli_fetch_assoc($res_active);

$res_inactive = mysqli_query($conn, "SELECT COUNT(*) as total FROM properties WHERE is_active = 0");
$data_inactive = mysqli_fetch_assoc($res_inactive);

$where_parts = [];
if ($status_filter === 'active') {
    $where_parts[] = 'is_active = 1';
} elseif ($status_filter === 'inactive') {
    $where_parts[] = 'is_active = 0';
}

if ($has_search) {
    $where_parts[] = '(title LIKE ? OR lot_id LIKE ? OR city LIKE ? OR address LIKE ? OR district LIKE ? OR slug LIKE ? OR location LIKE ? OR CAST(id AS CHAR) LIKE ?)';
}

$where_sql = $where_parts === [] ? '' : (' WHERE ' . implode(' AND ', $where_parts));
$query = "SELECT * FROM properties $where_sql ORDER BY id DESC";

$property_rows = [];
if ($has_search) {
    $like = '%' . $search_q . '%';
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt !== false) {
        $p1 = $p2 = $p3 = $p4 = $p5 = $p6 = $p7 = $p8 = $like;
        mysqli_stmt_bind_param($stmt, 'ssssssss', $p1, $p2, $p3, $p4, $p5, $p6, $p7, $p8);
        mysqli_stmt_execute($stmt);
        $stmt_result = function_exists('mysqli_stmt_get_result') ? mysqli_stmt_get_result($stmt) : false;
        if ($stmt_result instanceof mysqli_result) {
            while ($row = mysqli_fetch_assoc($stmt_result)) {
                $property_rows[] = $row;
            }
            mysqli_free_result($stmt_result);
            mysqli_stmt_close($stmt);
        } else {
            mysqli_stmt_close($stmt);
            $esc = mysqli_real_escape_string($conn, $like);
            $fb_where = [];
            if ($status_filter === 'active') {
                $fb_where[] = 'is_active = 1';
            } elseif ($status_filter === 'inactive') {
                $fb_where[] = 'is_active = 0';
            }
            $fb_where[] = "(title LIKE '{$esc}' OR lot_id LIKE '{$esc}' OR city LIKE '{$esc}' OR address LIKE '{$esc}' OR district LIKE '{$esc}' OR slug LIKE '{$esc}' OR location LIKE '{$esc}' OR CAST(id AS CHAR) LIKE '{$esc}')";
            $fb_sql = 'SELECT * FROM properties WHERE ' . implode(' AND ', $fb_where) . ' ORDER BY id DESC';
            $fb_res = mysqli_query($conn, $fb_sql);
            if ($fb_res) {
                while ($row = mysqli_fetch_assoc($fb_res)) {
                    $property_rows[] = $row;
                }
                mysqli_free_result($fb_res);
            }
        }
    }
} else {
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $property_rows[] = $row;
        }
        mysqli_free_result($result);
    }
}
?>

<div class="max-w-6xl">
    <?php if ($forbidden === '1' || $forbidden === 'users'): ?>
        <div class="mb-8 p-5 rounded-2xl border border-black/10 bg-brand-100 text-ink font-bold shadow-sm">
            Nu ai permisiunea să accesezi această zonă. Doar administratorii pot gestiona utilizatorii.
        </div>
    <?php elseif ($forbidden === 'property_delete'): ?>
        <div class="mb-8 p-5 rounded-2xl border border-black/10 bg-brand-100 text-ink font-bold shadow-sm">
            Doar administratorii pot șterge proprietăți.
        </div>
    <?php endif; ?>

    <?php
    $icalFb = lh_ical_consume_import_feedback();
    if ($icalFb !== null):
        if ($icalFb['success']): ?>
        <div class="mb-8 p-5 rounded-2xl border border-black/10 bg-brand-100 text-ink font-bold shadow-sm">
            Import iCal reușit: <?php echo (int) $icalFb['imported']; ?> eveniment(e) sincronizate în calendarul blocat.
        </div>
        <?php else: ?>
        <div class="mb-8 p-5 rounded-2xl border border-red-200 bg-red-50 text-red-800 font-bold shadow-sm">
            Import iCal eșuat: <?php echo htmlspecialchars($icalFb['error'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif;
    endif; ?>

    <div class="flex justify-between items-center mb-10">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                Salutare, <?php echo htmlspecialchars($_SESSION['admin_user_name'] ?? 'Admin'); ?>! 👋
            </h2>
            <p class="text-slate-500">
                Gestionează rapid proprietățile active și inactive
                <?php if (!empty($_SESSION['admin_user_role'])): ?>
                    <span class="text-slate-400">· rol: <strong class="text-slate-700"><?php echo htmlspecialchars($_SESSION['admin_user_role']); ?></strong></span>
                <?php endif; ?>
            </p>
        </div>
        <a href="add-property.php" class="bg-cta text-white px-6 py-3 rounded-2xl font-bold flex items-center gap-2 hover:brightness-110 transition-all shadow-lg shadow-black/10">
            <i data-lucide="plus" class="w-5 h-5"></i> Listing Nou
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <a href="<?php echo htmlspecialchars($dash_href(['status' => 'all']), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white p-6 rounded-2xl border <?php echo $status_filter === 'all' ? 'border-slate-300 ring-2 ring-slate-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all block">
            <div class="bg-slate-100 w-12 h-12 rounded-2xl flex items-center justify-center text-slate-700 mb-4">
                <i data-lucide="home"></i>
            </div>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Toate</p>
            <h4 class="text-3xl font-black text-slate-900 mt-1"><?php echo (int)($data_total['total'] ?? 0); ?></h4>
        </a>
        <a href="<?php echo htmlspecialchars($dash_href(['status' => 'active']), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white p-6 rounded-2xl border <?php echo $status_filter === 'active' ? 'border-slate-300 ring-2 ring-slate-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all block">
            <div class="bg-brand-100 w-12 h-12 rounded-2xl flex items-center justify-center text-cta mb-4">
                <i data-lucide="badge-check"></i>
            </div>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Active</p>
            <h4 class="text-3xl font-black text-slate-900 mt-1"><?php echo (int)($data_active['total'] ?? 0); ?></h4>
        </a>
        <a href="<?php echo htmlspecialchars($dash_href(['status' => 'inactive']), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white p-6 rounded-2xl border <?php echo $status_filter === 'inactive' ? 'border-slate-300 ring-2 ring-slate-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all block">
            <div class="bg-slate-100 w-12 h-12 rounded-2xl flex items-center justify-center text-slate-600 mb-4">
                <i data-lucide="pause-circle"></i>
            </div>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Inactive</p>
            <h4 class="text-3xl font-black text-slate-900 mt-1"><?php echo (int)($data_inactive['total'] ?? 0); ?></h4>
        </a>
    </div>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between mb-6 px-1">
        <div class="min-w-0">
            <p class="text-xs uppercase tracking-widest text-slate-400 font-bold">Filtru activ</p>
            <h3 class="text-lg font-extrabold text-slate-900 tracking-tight">
                <?php echo $status_filter === 'all' ? 'Toate proprietățile' : ($status_filter === 'active' ? 'Proprietăți active' : 'Proprietăți inactive'); ?>
            </h3>
        </div>
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3 w-full lg:w-auto lg:max-w-2xl">
            <form method="get" action="dashboard.php" class="flex flex-col sm:flex-row gap-2 flex-1 min-w-0">
                <?php if ($status_filter !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <input
                    type="search"
                    name="q"
                    value="<?php echo htmlspecialchars($search_q, ENT_QUOTES, 'UTF-8'); ?>"
                    maxlength="<?php echo (int) LH_DASHBOARD_SEARCH_MAX; ?>"
                    placeholder="Caută după titlu, LOT, oraș, adresă…"
                    class="w-full sm:flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200 focus:border-slate-300"
                    autocomplete="off"
                >
                <button type="submit" class="shrink-0 bg-slate-900 text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-slate-800 transition-colors">
                    Caută
                </button>
            </form>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm font-bold shrink-0">
                <?php if ($has_search): ?>
                    <a href="<?php echo htmlspecialchars($href_clear_search, ENT_QUOTES, 'UTF-8'); ?>" class="text-slate-500 hover:text-slate-900 transition-colors">Șterge căutarea</a>
                <?php endif; ?>
                <?php if ($status_filter !== 'all'): ?>
                    <a href="<?php echo htmlspecialchars($dash_href(['status' => 'all']), ENT_QUOTES, 'UTF-8'); ?>" class="text-slate-500 hover:text-slate-900 transition-colors">Resetează filtrul</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 text-[10px] uppercase font-bold tracking-widest">
                    <th class="px-8 py-5">Proprietate / LOT</th>
                    <th class="px-8 py-5">Locație</th>
                    <th class="px-8 py-5 text-center">Preț</th>
                    <th class="px-8 py-5 text-center">Status</th>
                    <th class="px-8 py-5 text-right">Acțiuni</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-sm">
                <?php if ($property_rows !== []): ?>
                    <?php foreach ($property_rows as $row): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-4">
                                <?php 
                                    $images = !empty($row['image_name']) ? explode(',', $row['image_name']) : [];
                                    $first_img = !empty($images[0]) ? trim($images[0]) : 'default.jpg';
                                ?>
                                <img src="<?php echo htmlspecialchars(lh_property_image_url((int) $row['id'], $first_img, 'thumb'), ENT_QUOTES, 'UTF-8'); ?>" class="w-14 h-14 object-cover rounded-2xl shadow-sm border border-slate-100" alt="">
                                <div>
                                    <div class="font-bold text-slate-900"><?php echo htmlspecialchars($row['title']); ?></div>
                                    <div class="text-[10px] text-cta font-extrabold uppercase mt-0.5 tracking-tighter">ID: #<?php echo htmlspecialchars($row['lot_id']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6 text-slate-500">
                            <div class="font-bold text-slate-800"><?php echo htmlspecialchars($row['city']); ?></div>
                            <div class="text-xs truncate max-w-[180px]"><?php echo htmlspecialchars($row['address']); ?></div>
                        </td>
                        <td class="px-8 py-6 text-center font-black text-slate-900">
                            <?php echo htmlspecialchars(lh_format_money((float) $row['price'], 0), ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td class="px-8 py-6 text-center">
                            <span class="inline-flex px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest <?php echo !empty($row['is_active']) ? 'bg-brand-100 text-ink' : 'bg-slate-100 text-slate-600'; ?>">
                                <?php echo !empty($row['is_active']) ? 'active' : 'inactive'; ?>
                            </span>
                        </td>
                        <td class="px-8 py-6">
                            <div class="flex justify-end gap-2 text-xs font-bold uppercase tracking-tighter">
                                <a href="edit-property.php?id=<?php echo (int)$row['id']; ?>" class="bg-slate-100 text-slate-600 px-4 py-2 rounded-xl hover:bg-cta hover:text-white transition-all">Editare</a>
                                <form method="POST" action="toggle-property-status.php" class="inline">
                                    <?php lh_csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                    <button type="submit" class="<?php echo !empty($row['is_active']) ? 'bg-slate-100 text-slate-700 hover:bg-slate-800 hover:text-white' : 'bg-brand-100 text-cta hover:bg-cta hover:text-white'; ?> px-4 py-2 rounded-xl transition-all">
                                        <?php echo !empty($row['is_active']) ? 'Dezactivează' : 'Activează'; ?>
                                    </button>
                                </form>
                                <?php if (dashboard_user_is_admin()): ?>
                                <button type="button" onclick="askDelete(<?php echo (int)$row['id']; ?>, '<?php echo addslashes($row['title']); ?>')" class="bg-red-50 text-red-500 px-4 py-2 rounded-xl hover:bg-red-500 hover:text-white transition-all">Șterge</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-8 py-16 text-center text-slate-400 font-bold">
                            <?php if ($has_search): ?>
                                Niciun rezultat pentru „<?php echo htmlspecialchars($search_q, ENT_QUOTES, 'UTF-8'); ?>”. Încearcă alt termen sau <?php if ($status_filter !== 'all'): ?><a href="<?php echo htmlspecialchars($dash_href(['status' => 'all']), ENT_QUOTES, 'UTF-8'); ?>" class="text-cta underline">vezi toate</a><?php else: ?><a href="<?php echo htmlspecialchars($href_clear_search, ENT_QUOTES, 'UTF-8'); ?>" class="text-cta underline">șterge căutarea</a><?php endif; ?>.
                            <?php else: ?>
                                Nu există proprietăți pentru filtrul selectat.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
    });
</script>

<?php include('includes/footer.php'); ?>