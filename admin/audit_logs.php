<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Audit Logs'; $active = 'audit';
require_once dirname(__DIR__) . '/layout_header.php';

$logs = $pdo->query("
    SELECT al.*, u.name AS user_name
    FROM audit_logs al
    LEFT JOIN users u ON u.id=al.user_id
    ORDER BY al.created_at DESC
    LIMIT 500
")->fetchAll();
?>
<div class="main-content">
  <h5 class="fw-bold mb-3">Audit Logs <small class="text-muted fs-6">(last 500)</small></h5>
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-hover table-sm mb-0">
        <thead class="table-light"><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach($logs as $l): ?>
        <tr>
          <td><small><?= $l['created_at'] ?></small></td>
          <td><?= htmlspecialchars($l['user_name'] ?? 'System') ?></td>
          <td><span class="badge bg-secondary"><?= htmlspecialchars($l['action']) ?></span></td>
          <td><small class="text-muted"><?= htmlspecialchars($l['details']) ?></small></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
