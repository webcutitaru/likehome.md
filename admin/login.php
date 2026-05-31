<?php
require_once __DIR__ . '/../config.php';

$error = '';

if (isset($_GET['reason'])) {
    if ($_GET['reason'] === 'disabled') {
        $error = 'Contul este dezactivat. Nu poți accesa panoul de administrare.';
    } elseif ($_GET['reason'] === 'session') {
        $error = 'Sesiune invalidă sau expirată. Autentifică-te din nou.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lh_csrf_verify_post()) {
        $error = 'Sesiune invalidă. Reîncarcă pagina și încearcă din nou.';
    } else {
    $email = trim((string) ($_POST['username'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');

    $tables = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    if (!$tables || mysqli_num_rows($tables) === 0) {
        $error = 'Tabela utilizatorilor lipsește. Rulează în MySQL scriptul sql/create_users_table.sql.';
    } elseif ($email === '' || $pass === '') {
        $error = 'Completează emailul și parola.';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $row = lh_mysqli_stmt_fetch_assoc($stmt);
            mysqli_stmt_close($stmt);

            if (!$row || !password_verify($pass, $row['password'])) {
                lh_admin_log_activity($conn, 'login_failed', null, null, ['email' => $email], null, true);
                $error = 'Date de acces incorecte!';
            } elseif (($row['status'] ?? '') !== 'active') {
                lh_admin_log_activity($conn, 'login_failed_disabled', null, null, ['email' => $email], null, true);
                $error = 'Contul este dezactivat. Nu poți accesa panoul.';
            } else {
                $loginUserId = (int) $row['id'];
                $ll = mysqli_prepare($conn, 'UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?');
                if ($ll) {
                    mysqli_stmt_bind_param($ll, 'i', $loginUserId);
                    mysqli_stmt_execute($ll);
                    mysqli_stmt_close($ll);
                }
                session_regenerate_id(true);
                lh_csrf_regenerate_token();
                $_SESSION['admin_logged_in']   = true;
                $_SESSION['admin_user_id']      = $loginUserId;
                $_SESSION['admin_user_name']   = $row['name'];
                $_SESSION['admin_user_email']  = $row['email'];
                $_SESSION['admin_user_role']   = $row['role'];
                lh_admin_log_activity(
                    $conn,
                    'login_success',
                    'user',
                    $loginUserId,
                    ['email' => $row['email'], 'name' => $row['name']]
                );
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Eroare la autentificare. Încearcă din nou.';
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login | LikeHome Admin</title>
    <link rel="stylesheet" href="../assets/css/tailwind.build.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-slate-900 via-slate-950 to-black text-ink">
    <div class="w-full max-w-md">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white/10 rounded-3xl mb-4 shadow-lg border border-white/10 backdrop-blur-sm">
                <i data-lucide="home" class="text-white w-8 h-8"></i>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tight">LikeHome <span class="text-white/50 text-sm align-top font-bold">ADMIN</span></h1>
            <p class="text-slate-400 mt-2">Introdu emailul și parola pentru a accesa panoul.</p>
        </div>

        <div class="bg-white/95 backdrop-blur-md p-10 rounded-3xl border border-white/10 shadow-2xl shadow-black/40">
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-100 text-red-800 p-4 rounded-2xl mb-6 text-sm font-bold flex items-center gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <?php lh_csrf_field(); ?>
                <div>
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Email</label>
                    <div class="relative mt-2">
                        <i data-lucide="mail" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                        <input type="email" name="username" required autocomplete="username" placeholder="ex: admin@likehome.local"
                        class="w-full bg-surface border border-black/8 rounded-2xl py-4 pl-12 pr-4 text-ink focus:ring-2 focus:ring-cta/25 focus:border-cta outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Parolă</label>
                    <div class="relative mt-2">
                        <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"
                        class="w-full bg-surface border border-black/8 rounded-2xl py-4 pl-12 pr-4 text-ink focus:ring-2 focus:ring-cta/25 focus:border-cta outline-none transition-all">
                    </div>
                </div>

                <button type="submit" class="w-full bg-cta text-white py-5 rounded-2xl font-black text-lg hover:brightness-110 transition-all shadow-lg shadow-black/15">
                    AUTENTIFICARE
                </button>
            </form>
        </div>

        <p class="text-center text-slate-500 mt-8 text-xs font-bold uppercase tracking-widest">
            &copy; <?php echo date('Y'); ?> LikeHome Property Management
        </p>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
