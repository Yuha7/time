<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Teachers'; $active = 'teachers';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role,phone) VALUES (?,?,?,'teacher',?)");
        $stmt->execute([$_POST['name'], $_POST['email'], $hash, $_POST['phone']]);
        log_action($pdo, current_user_id(), 'ADD_TEACHER', $_POST['email']);
    } elseif ($action === 'edit') {
        $sql = "UPDATE users SET name=?,email=?,phone=? WHERE id=? AND role='teacher'";
        $params = [$_POST['name'], $_POST['email'], $_POST['phone'], $_POST['id']];
        if (!empty($_POST['password'])) {
            $sql = "UPDATE users SET name=?,email=?,phone=?,password=? WHERE id=? AND role='teacher'";
            $params = [$_POST['name'], $_POST['email'], $_POST['phone'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['id']];
        }
        $pdo->prepare($sql)->execute($params);
        log_action($pdo, current_user_id(), 'EDIT_TEACHER', $_POST['email']);
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='teacher'")->execute([$_POST['id']]);
    } elseif ($action === 'import' && isset($_FILES['csv'])) {
        // Allow email to be NULL in the users table
        $pdo->exec("ALTER TABLE users MODIFY COLUMN email VARCHAR(100) NULL DEFAULT NULL");

        $file     = fopen($_FILES['csv']['tmp_name'], 'r');
        fgetcsv($file); // skip header row
        $stmt     = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'teacher')");
        $imported = 0;
        $skipped  = 0;

        while ($row = fgetcsv($file)) {
            // Only requirement: name must exist
            $name  = trim($row[0] ?? '');
            if (empty($name)) { $skipped++; continue; }

            $email = trim($row[1] ?? '');
            $phone = trim($row[2] ?? '');

            // If email is missing, use NULL; if duplicate, make it unique with a suffix
            if (empty($email)) {
                $email = null;
            } else {
                // Check for duplicate email and skip only that duplicate
                $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $dup->execute([$email]);
                if ($dup->fetchColumn() > 0) { $skipped++; continue; }
            }

            $default_pass = password_hash('teacher123', PASSWORD_DEFAULT);
            $stmt->execute([$name, $email, $phone ?: null, $default_pass]);
            $imported++;
        }
        fclose($file);
        log_action($pdo, current_user_id(), 'IMPORT_TEACHERS', "imported:{$imported} skipped:{$skipped}");
        $_SESSION['import_msg'] = "Import complete: <strong>{$imported}</strong> teachers added, <strong>{$skipped}</strong> skipped (blank name or duplicate email).";
    }
    header('Location: index.php'); exit;
}

$teachers = $pdo->query("SELECT * FROM users WHERE role='teacher' ORDER BY name")->fetchAll();
$import_msg = $_SESSION['import_msg'] ?? '';
unset($_SESSION['import_msg']);
require_once dirname(__DIR__) . '/layout_header.php';
?>
<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">Teachers (<?= count($teachers) ?>)</h5>
    <div class="d-flex gap-2">
      <a href="export.php" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> Export CSV</a>
      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload"></i> Import</button>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> Add Teacher</button>
    </div>
  </div>
  <?php if($import_msg): ?>
  <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <?= $import_msg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($teachers as $i=>$t): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($t['name']) ?></td>
          <td><?= htmlspecialchars($t['email'] ?: '—') ?></td>
          <td><?= htmlspecialchars($t['phone'] ?: '—') ?></td>
          <td><span class="badge <?= $t['is_active']?'bg-success':'bg-secondary' ?>"><?= $t['is_active']?'Active':'Inactive' ?></span></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick="editTeacher(<?= htmlspecialchars(json_encode($t)) ?>)"><i class="bi bi-pencil"></i></button>
            <form method="POST" class="d-inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="btn btn-sm <?= $t['is_active']?'btn-outline-warning':'btn-outline-success' ?>" title="<?= $t['is_active']?'Deactivate':'Activate' ?>"><i class="bi bi-<?= $t['is_active']?'slash-circle':'check-circle' ?>"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add Teacher</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST">
  <input type="hidden" name="action" value="add">
  <div class="modal-body row g-3">
    <div class="col-12"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
    <div class="col-12"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Edit Teacher</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST">
  <input type="hidden" name="action" value="edit">
  <input type="hidden" name="id" id="edit_id">
  <div class="modal-body row g-3">
    <div class="col-12"><label class="form-label">Full Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
    <div class="col-12"><label class="form-label">Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">New Password <small class="text-muted">(leave blank to keep)</small></label><input type="password" name="password" class="form-control"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Update</button></div>
  </form>
</div></div></div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Import Teachers (CSV)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="import">
  <div class="modal-body">
    <div class="alert alert-info py-2 small mb-3">
      <strong>CSV format:</strong> <code>name, email, phone</code> (first row = header)<br>
      Only <strong>name</strong> is required — email and phone are optional.<br>
      Missing emails are stored as blank. Duplicate emails are skipped.<br>
      Default password for all imported teachers: <code>teacher123</code>
    </div>
    <label class="form-label">Select CSV File</label>
    <input type="file" name="csv" class="form-control" accept=".csv,.xlsx" required>
  </div>
  <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-upload"></i> Import</button></div>
  </form>
</div></div></div>

<script>
function editTeacher(t) {
  document.getElementById('edit_id').value = t.id;
  document.getElementById('edit_name').value = t.name;
  document.getElementById('edit_email').value = t.email;
  document.getElementById('edit_phone').value = t.phone || '';
  new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
