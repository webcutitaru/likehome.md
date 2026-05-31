<?php
include('../config.php');
require_once __DIR__ . '/includes/auth.php';
dashboard_require_admin_user($conn);

$message = '';
$message_type = 'success';

$status_filter      = $_GET['status'] ?? 'all';
$allowed_status     = ['all', 'active', 'disabled'];
if (!in_array($status_filter, $allowed_status, true)) {
    $status_filter = 'all';
}

$edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$edit_row = null;
if ($edit_id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT id, name, email, role, status, phone FROM users WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $edit_id);
    mysqli_stmt_execute($stmt);
    $edit_row = lh_mysqli_stmt_fetch_assoc($stmt);
    mysqli_stmt_close($stmt);
    if (!$edit_row) {
        $edit_id = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lh_csrf_verify_post()) {
        $message = 'Sesiune invalidă. Reîncarcă pagina și încearcă din nou.';
        $message_type = 'warning';
    } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name      = trim((string) ($_POST['name'] ?? ''));
        $email     = trim((string) ($_POST['email'] ?? ''));
        $phone     = trim((string) ($_POST['phone'] ?? ''));
        $role      = ($_POST['role'] ?? 'manager') === 'admin' ? 'admin' : 'manager';
        $status    = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';
        $password  = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password_confirm'] ?? '');

        if ($name === '' || $email === '') {
            $message = 'Numele și emailul sunt obligatorii.';
            $message_type = 'warning';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Email invalid.';
            $message_type = 'warning';
        } elseif ($password !== '' && $password !== $password2) {
            $message = 'Parolele nu coincid.';
            $message_type = 'warning';
        } elseif ($id === 0 && $password === '') {
            $message = 'Pentru utilizator nou, setează o parolă.';
            $message_type = 'warning';
        } else {
            // Unique email
            if ($id > 0) {
                $chk = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
                mysqli_stmt_bind_param($chk, 'si', $email, $id);
            } else {
                $chk = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
                mysqli_stmt_bind_param($chk, 's', $email);
            }
            mysqli_stmt_execute($chk);
            $exists = lh_mysqli_stmt_fetch_assoc($chk) !== null;
            mysqli_stmt_close($chk);

            if ($exists) {
                $message = 'Există deja un utilizator cu acest email.';
                $message_type = 'warning';
            } else {
                $current_user_id = (int) ($_SESSION['admin_user_id'] ?? 0);

                if ($id > 0) {
                    $cur = mysqli_prepare($conn, 'SELECT role, status FROM users WHERE id = ? LIMIT 1');
                    mysqli_stmt_bind_param($cur, 'i', $id);
                    mysqli_stmt_execute($cur);
                    $before = lh_mysqli_stmt_fetch_assoc($cur);
                    mysqli_stmt_close($cur);

                    if (!$before) {
                        $message = 'Utilizatorul nu există.';
                        $message_type = 'warning';
                    } else {
                        $was_admin = ($before['role'] ?? '') === 'admin';
                        $active_admins = dashboard_count_active_admins($conn);

                        if ($was_admin && $role !== 'admin' && $active_admins === 1 && ($before['status'] ?? '') === 'active') {
                            $message = 'Nu poți schimba rolul singurului administrator activ.';
                            $message_type = 'warning';
                        } elseif ($was_admin && $status === 'disabled' && $active_admins === 1 && ($before['status'] ?? '') === 'active') {
                            $message = 'Nu poți dezactiva singurul administrator activ.';
                            $message_type = 'warning';
                        } else {
                            if ($password !== '') {
                                $hash = password_hash($password, PASSWORD_DEFAULT);
                                $upd = mysqli_prepare(
                                    $conn,
                                    'UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ?, password = ? WHERE id = ?'
                                );
                                mysqli_stmt_bind_param($upd, 'ssssssi', $name, $email, $phone, $role, $status, $hash, $id);
                            } else {
                                $upd = mysqli_prepare(
                                    $conn,
                                    'UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?'
                                );
                                mysqli_stmt_bind_param($upd, 'sssssi', $name, $email, $phone, $role, $status, $id);
                            }
                            mysqli_stmt_execute($upd);
                            mysqli_stmt_close($upd);

                            if ($id === $current_user_id) {
                                $_SESSION['admin_user_name']  = $name;
                                $_SESSION['admin_user_email'] = $email;
                                $_SESSION['admin_user_role']  = $role;
                            }

                            lh_admin_log_activity($conn, 'user_update', 'user', $id, [
                                'name' => $name,
                                'email' => $email,
                                'role' => $role,
                                'status' => $status,
                                'password_changed' => $password !== '',
                            ]);

                            $message = 'Utilizatorul a fost actualizat.';
                            $message_type = 'success';
                            header('Location: users.php?status=' . urlencode($status_filter));
                            exit;
                        }
                    }
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = mysqli_prepare(
                        $conn,
                        'INSERT INTO users (name, email, password, role, status, phone) VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    mysqli_stmt_bind_param($ins, 'ssssss', $name, $email, $hash, $role, $status, $phone);
                    mysqli_stmt_execute($ins);
                    $newUserId = (int) mysqli_insert_id($conn);
                    mysqli_stmt_close($ins);

                    lh_admin_log_activity($conn, 'user_create', 'user', $newUserId > 0 ? $newUserId : null, [
                        'name' => $name,
                        'email' => $email,
                        'role' => $role,
                        'status' => $status,
                    ]);

                    $message = 'Utilizatorul a fost creat.';
                    $message_type = 'success';
                    header('Location: users.php?status=' . urlencode($status_filter));
                    exit;
                }
            }
        }
    }
    }
}

include('includes/header.php');

$res_total = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM users');
$data_total = mysqli_fetch_assoc($res_total);

$res_active = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE status = 'active'");
$data_active = mysqli_fetch_assoc($res_active);

$res_disabled = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE status = 'disabled'");
$data_disabled = mysqli_fetch_assoc($res_disabled);

$where_sql = '';
if ($status_filter === 'active') {
    $where_sql = " WHERE status = 'active' ";
} elseif ($status_filter === 'disabled') {
    $where_sql = " WHERE status = 'disabled' ";
}

$result = mysqli_query($conn, 'SELECT id, name, email, role, status, phone, created_at FROM users ' . $where_sql . ' ORDER BY id DESC');

$picker_result = mysqli_query($conn, 'SELECT id, name, email FROM users ORDER BY name ASC');
$picker_users = [];
if ($picker_result) {
    while ($pu = mysqli_fetch_assoc($picker_result)) {
        $picker_users[] = $pu;
    }
}

$add_mode = isset($_GET['add']) && $_GET['add'] === '1';
$form_expanded = ($edit_id > 0) || $add_mode;

function user_status_badge(string $status): string
{
    return $status === 'active' ? 'bg-brand-100 text-ink border border-black/8' : 'bg-slate-200 text-slate-600';
}

function user_role_badge(string $role): string
{
    return $role === 'admin' ? 'bg-slate-100 text-cta border border-black/10' : 'bg-slate-100 text-slate-600';
}
?>

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Utilizatori</h2>
            <p class="text-slate-500">Administratorii pot crea și gestiona conturile cu acces la panou.</p>
        </div>
        <a href="dashboard.php" class="bg-cta text-white px-6 py-3 rounded-2xl font-bold flex items-center gap-2 hover:brightness-110 transition-all shadow-lg shadow-black/10">
            <i data-lucide="arrow-left" class="w-5 h-5"></i> Înapoi la Dashboard
        </a>
    </div>

    <?php if ($message): ?>
        <div class="mb-8 p-5 rounded-2xl border border-black/10 <?php echo $message_type === 'success' ? 'bg-brand-100 text-ink' : 'bg-slate-100 text-slate-800'; ?> font-bold shadow-sm">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <a href="users.php?status=all" class="bg-white p-6 rounded-2xl border <?php echo $status_filter === 'all' ? 'border-slate-300 ring-2 ring-slate-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all block">
            <div class="bg-slate-100 w-12 h-12 rounded-2xl flex items-center justify-center text-slate-700 mb-4">
                <i data-lucide="users"></i>
            </div>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Total</p>
            <h4 class="text-3xl font-black text-slate-900 mt-1"><?php echo (int) ($data_total['total'] ?? 0); ?></h4>
        </a>
        <a href="users.php?status=active" class="bg-white p-6 rounded-2xl border <?php echo $status_filter === 'active' ? 'border-slate-300 ring-2 ring-slate-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all block">
            <div class="bg-brand-100 w-12 h-12 rounded-2xl flex items-center justify-center text-cta mb-4">
                <i data-lucide="badge-check"></i>
            </div>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Activi</p>
            <h4 class="text-3xl font-black text-slate-900 mt-1"><?php echo (int) ($data_active['total'] ?? 0); ?></h4>
        </a>
        <a href="users.php?status=disabled" class="bg-white p-6 rounded-2xl border <?php echo $status_filter === 'disabled' ? 'border-slate-300 ring-2 ring-slate-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all block">
            <div class="bg-slate-200 w-12 h-12 rounded-2xl flex items-center justify-center text-slate-600 mb-4">
                <i data-lucide="user-x"></i>
            </div>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Dezactivați</p>
            <h4 class="text-3xl font-black text-slate-900 mt-1"><?php echo (int) ($data_disabled['total'] ?? 0); ?></h4>
        </a>
    </div>

    <div class="bg-white border border-slate-100 shadow-sm p-6 md:p-8 mb-10 rounded-[2.5rem]">
        <label for="user-editor-select" class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block mb-2">Utilizator</label>
        <div class="relative">
            <select id="user-editor-select" class="w-full appearance-none mt-1 p-4 pr-12 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30 font-bold text-slate-800 cursor-pointer">
                <option value="" <?php echo !$form_expanded ? 'selected' : ''; ?> disabled>Selectează utilizator…</option>
                <option value="new" <?php echo ($form_expanded && $edit_id === 0) ? 'selected' : ''; ?>>Adaugă utilizator nou</option>
                <?php foreach ($picker_users as $pu): ?>
                    <option value="<?php echo (int) $pu['id']; ?>" <?php echo $edit_id === (int) $pu['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['email']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400"></i>
            </div>
        </div>
    </div>

    <?php if ($form_expanded): ?>
    <div class="bg-white border border-slate-100 shadow-sm p-8 md:p-10 mb-10 rounded-[2.5rem]">
        <h3 class="text-lg font-extrabold text-slate-900 tracking-tight mb-2">
            <?php echo $edit_id > 0 ? 'Editează utilizator' : 'Adaugă utilizator nou'; ?>
        </h3>
        <p class="text-slate-500 text-sm mb-8">
            <?php echo $edit_id > 0 ? 'Actualizează datele; lasă parola goală dacă nu o schimbi.' : 'Completează câmpurile pentru un cont nou (email unic).'; ?>
        </p>

        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php lh_csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo (int) $edit_id; ?>">

            <div class="md:col-span-2 md:grid md:grid-cols-2 md:gap-6 space-y-6 md:space-y-0">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nume</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($edit_row['name'] ?? ''); ?>"
                           class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Email (autentificare)</label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($edit_row['email'] ?? ''); ?>"
                           class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
                </div>
            </div>

            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Telefon</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($edit_row['phone'] ?? ''); ?>"
                       class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Rol</label>
                    <select name="role" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30 font-bold text-slate-800">
                        <option value="manager" <?php echo (isset($edit_row['role']) && $edit_row['role'] === 'manager') ? 'selected' : (!$edit_row ? 'selected' : ''); ?>>Manager</option>
                        <option value="admin" <?php echo (isset($edit_row['role']) && $edit_row['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Status</label>
                    <select name="status" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30 font-bold text-slate-800">
                        <option value="active" <?php echo (!isset($edit_row['status']) || $edit_row['status'] === 'active') ? 'selected' : ''; ?>>Activ</option>
                        <option value="disabled" <?php echo (isset($edit_row['status']) && $edit_row['status'] === 'disabled') ? 'selected' : ''; ?>>Dezactivat</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Parolă <?php echo $edit_id > 0 ? '(opțional)' : ''; ?></label>
                <input type="password" name="password" autocomplete="new-password" <?php echo $edit_id === 0 ? 'required' : ''; ?>
                       class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30"
                       placeholder="<?php echo $edit_id > 0 ? 'Lasă gol pentru a păstra parola' : ''; ?>">
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Confirmă parola</label>
                <input type="password" name="password_confirm" autocomplete="new-password" <?php echo $edit_id === 0 ? 'required' : ''; ?>
                       class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
            </div>

            <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                <button type="submit" class="bg-cta text-white px-8 py-4 rounded-2xl font-bold hover:brightness-110 transition-all shadow-lg shadow-black/10">
                    <?php echo $edit_id > 0 ? 'Salvează modificările' : 'Creează utilizator'; ?>
                </button>
                <a href="users.php?status=<?php echo htmlspecialchars(urlencode($status_filter)); ?>" class="inline-flex items-center px-8 py-4 rounded-2xl font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">
                    <?php echo $edit_id > 0 ? 'Anulează editarea' : 'Anulează'; ?>
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="flex items-center justify-between mb-6 px-1">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-400 font-bold">Filtru activ</p>
            <h3 class="text-lg font-extrabold text-slate-900 tracking-tight">
                <?php
                echo $status_filter === 'all' ? 'Toți utilizatorii' : ($status_filter === 'active' ? 'Utilizatori activi' : 'Utilizatori dezactivați');
                ?>
            </h3>
        </div>
        <?php if ($status_filter !== 'all'): ?>
            <a href="users.php?status=all" class="text-sm font-bold text-slate-500 hover:text-slate-900 transition-colors">Resetează filtrul</a>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-[2.5rem] border border-slate-100 overflow-hidden shadow-sm">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 text-[10px] uppercase font-bold tracking-widest">
                    <th class="px-8 py-5">Utilizator</th>
                    <th class="px-8 py-5">Contact</th>
                    <th class="px-8 py-5 text-center">Rol</th>
                    <th class="px-8 py-5 text-center">Status</th>
                    <th class="px-8 py-5 text-right">Acțiuni</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-sm">
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="hover:bg-slate-50 transition-colors align-top">
                            <td class="px-8 py-6">
                                <div class="font-bold text-slate-900"><?php echo htmlspecialchars($row['name']); ?></div>
                                <div class="text-[10px] text-cta font-extrabold uppercase mt-0.5 tracking-tighter">ID #<?php echo (int) $row['id']; ?></div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="text-xs text-slate-500 break-all"><?php echo htmlspecialchars($row['email']); ?></div>
                                <?php if (($row['phone'] ?? '') !== ''): ?>
                                    <div class="text-xs text-slate-400 mt-1"><?php echo htmlspecialchars($row['phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <span class="inline-flex px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest <?php echo user_role_badge($row['role']); ?>">
                                    <?php echo htmlspecialchars($row['role']); ?>
                                </span>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <span class="inline-flex px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest <?php echo user_status_badge($row['status']); ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex justify-end gap-2 text-xs font-bold uppercase tracking-tighter flex-wrap">
                                    <a href="users.php?edit=<?php echo (int) $row['id']; ?>&status=<?php echo htmlspecialchars(urlencode($status_filter)); ?>"
                                       class="bg-slate-100 text-slate-600 px-4 py-2 rounded-xl hover:bg-slate-900 hover:text-white transition-all">Editare</a>
                                    <?php if (($row['role'] ?? '') !== 'admin'): ?>
                                    <form method="POST" action="toggle-user-status.php" class="inline">
                                        <?php lh_csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                        <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit"
                                                class="<?php echo $row['status'] === 'active' ? 'bg-slate-100 text-slate-600 hover:bg-slate-800 hover:text-white' : 'bg-brand-100 text-cta hover:bg-cta hover:text-white'; ?> px-4 py-2 rounded-xl transition-all">
                                            <?php echo $row['status'] === 'active' ? 'Dezactivează' : 'Activează'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="delete-user.php" class="inline" onsubmit="return confirm('Ștergi definitiv acest utilizator?');">
                                        <?php lh_csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                        <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="bg-red-50 text-red-500 px-4 py-2 rounded-xl hover:bg-red-500 hover:text-white transition-all">Șterge</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-8 py-16 text-center text-slate-400 font-bold">
                            Nu există utilizatori pentru filtrul selectat.
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
        var sel = document.getElementById('user-editor-select');
        if (sel) {
            sel.addEventListener('change', function () {
                var v = this.value;
                if (v === '') {
                    return;
                }
                var st = <?php echo json_encode($status_filter, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                if (v === 'new') {
                    window.location.href = 'users.php?add=1&status=' + encodeURIComponent(st);
                    return;
                }
                window.location.href = 'users.php?edit=' + encodeURIComponent(v) + '&status=' + encodeURIComponent(st);
            });
        }
    });
</script>

<?php include('includes/footer.php'); ?>
