<?php
// admin.php
// Simple password-gated page for organizers to see who has registered.
// Not linked from the public site — keep the URL and password to yourselves.

declare(strict_types=1);
session_start();

require_once __DIR__ . '/config.php';

$error = '';

if (isset($_POST['admin_password'])) {
    if (hash_equals(ADMIN_PASSWORD, $_POST['admin_password'])) {
        $_SESSION['admin_authed'] = true;
    } else {
        $error = 'Wrong password.';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$authed = !empty($_SESSION['admin_authed']);

if ($authed && ($_GET['export'] ?? '') === 'csv') {
    try {
        $pdo = get_db_connection();
        $regs = $pdo->query('SELECT * FROM registrations ORDER BY created_at DESC')->fetchAll();
        $memberStmt = $pdo->prepare('SELECT member_name, member_class FROM team_members WHERE registration_id = :id ORDER BY position ASC');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="hackthon-registrations.csv"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel reads names correctly.
        $output = fopen('php://output', 'w');
        $safeForSpreadsheet = static function ($value): string {
            $value = (string) $value;
            return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
        };
        fputcsv($output, ['Team Name', 'Leader Name', 'Problem Statement ID', 'Member Names', 'Member Classes', 'Registered At']);

        foreach ($regs as $r) {
            $memberStmt->execute(['id' => $r['id']]);
            $members = $memberStmt->fetchAll();
            fputcsv($output, [
                $safeForSpreadsheet($r['team_name']),
                $safeForSpreadsheet($r['leader_name']),
                $safeForSpreadsheet($r['problem_statement_id']),
                $safeForSpreadsheet(implode(' | ', array_column($members, 'member_name'))),
                $safeForSpreadsheet(implode(' | ', array_column($members, 'member_class'))),
                $safeForSpreadsheet($r['created_at']),
            ]);
        }
        fclose($output);
        exit;
    } catch (PDOException $e) {
        error_log('Admin export DB error: ' . $e->getMessage());
        http_response_code(500);
        exit('Could not export registrations.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — HACKTHON</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@800;900&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{ --cream:#F7F2EB; --ink:#0c0c0c; --red:#E63B2E; --line:rgba(12,12,12,0.1); }
  *{box-sizing:border-box;}
  body{ margin:0; background:var(--cream); color:var(--ink); font-family:'Inter', sans-serif; padding:40px 20px; }
  h1{ font-family:'Plus Jakarta Sans', sans-serif; font-weight:900; font-size:26px; margin-bottom:24px; }
  .login{ max-width:360px; margin:80px auto; background:#fff; border:1px solid var(--line); padding:32px; }
  .login input{ width:100%; padding:12px; margin:14px 0; border:1px solid rgba(12,12,12,.2); background:var(--cream); font-family:'Inter',sans-serif; }
  .login button{ width:100%; padding:13px; background:var(--ink); color:#fff; border:none; font-family:'Space Mono',monospace; cursor:pointer; }
  .back-home{ display:block; margin-top:12px; text-align:center; font-family:'Space Mono',monospace; font-size:12px; color:var(--ink); text-decoration:none; opacity:.65; }
  .back-home:hover{ color:var(--red); opacity:1; }
  .err{ color:var(--red); font-size:13px; }
  .topbar{ display:flex; justify-content:space-between; align-items:center; max-width:1000px; margin:0 auto 20px; }
  .topbar-actions{display:flex;align-items:center;gap:16px}.logout,.export{ font-family:'Space Mono', monospace; font-size:12px; color:var(--ink); opacity:.6; text-decoration:none; }.export{color:#fff;background:var(--red);opacity:1;padding:9px 12px}.export:hover,.logout:hover{opacity:1;}
  table{ width:100%; max-width:1000px; margin:0 auto; border-collapse:collapse; background:#fff; }
  th, td{ text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); font-size:13px; vertical-align:top; }
  th{ font-family:'Space Mono', monospace; font-size:11px; letter-spacing:.05em; opacity:.6; }
  .leader-tag{ color:var(--red); font-family:'Space Mono', monospace; font-size:10px; }
  .empty{ text-align:center; padding:40px; opacity:.5; font-family:'Space Mono', monospace; }
</style>
</head>
<body>

<?php if (!$authed): ?>

  <form class="login" method="post">
    <h1 style="margin-top:0;">Admin access</h1>
    <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <input type="password" name="admin_password" placeholder="Admin password" autofocus required>
    <button type="submit">Enter</button>
    <a class="back-home" href="index.html">← Back to Home</a>
  </form>

<?php else: ?>

  <div class="topbar">
    <h1 style="margin:0;">Registered teams</h1>
    <div class="topbar-actions">
      <a class="export" href="?export=csv">Export Excel</a>
      <a class="logout" href="?logout=1">log out</a>
    </div>
  </div>

  <?php
  try {
      $pdo = get_db_connection();
      $regs = $pdo->query('SELECT * FROM registrations ORDER BY created_at DESC')->fetchAll();

      $memberStmt = $pdo->prepare('SELECT member_name, member_class FROM team_members WHERE registration_id = :id ORDER BY position ASC');
  ?>

  <?php if (empty($regs)): ?>
    <p class="empty">No registrations yet.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Team</th>
          <th>Leader</th>
          <th>Problem ID</th>
          <th>Members</th>
          <th>Registered</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($regs as $r): ?>
          <?php
            $memberStmt->execute(['id' => $r['id']]);
            $members = $memberStmt->fetchAll();
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['team_name']) ?></strong></td>
            <td><?= htmlspecialchars($r['leader_name']) ?> <span class="leader-tag">LEADER</span></td>
            <td><?= htmlspecialchars($r['problem_statement_id']) ?></td>
            <td>
              <?php foreach ($members as $m): ?>
                <?= htmlspecialchars($m['member_name']) ?> (<?= htmlspecialchars($m['member_class']) ?>)<br>
              <?php endforeach; ?>
            </td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php
  } catch (PDOException $e) {
      error_log('Admin view DB error: ' . $e->getMessage());
      echo '<p class="empty">Could not load registrations — check DB connection.</p>';
  }
  ?>

<?php endif; ?>

</body>
</html>
