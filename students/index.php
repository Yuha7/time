<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Students'; $active = 'students';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO students (adm_number,name,stream_id,gender) VALUES (?,?,?,?)")
            ->execute([$_POST['adm_number'], $_POST['name'], $_POST['stream_id'], $_POST['gender']]);
        log_action($pdo, current_user_id(), 'ADD_STUDENT', $_POST['adm_number']);
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE students SET adm_number=?,name=?,stream_id=?,gender=? WHERE id=?")
            ->execute([$_POST['adm_number'], $_POST['name'], $_POST['stream_id'], $_POST['gender'], $_POST['id']]);
        log_action($pdo, current_user_id(), 'EDIT_STUDENT', $_POST['adm_number']);
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$_POST['id']]);
    } elseif ($action === 'import' && isset($_FILES['csv'])) {
        $file = fopen($_FILES['csv']['tmp_name'], 'r');
        fgetcsv($file); // skip header
        $stmt = $pdo->prepare("INSERT IGNORE INTO students (adm_number,name,stream_id,gender) VALUES (?,?,?,?)");
        while ($row = fgetcsv($file)) {
            if (count($row) >= 4) $stmt->execute([$row[0], $row[1], $row[2], $row[3]]);
        }
        fclose($file);
        log_action($pdo, current_user_id(), 'IMPORT_STUDENTS');
    }
    header('Location: index.php'); exit;
}



$stream_filter = $_GET['stream_id'] ?? '';
$where = $stream_filter ? "WHERE s.stream_id = " . (int)$stream_filter : ''; 
$students = $pdo->query("
    SELECT s.*, st.name AS stream, c.name AS class
    FROM students s
    JOIN streams st ON st.id = s.stream_id
    JOIN classes c ON c.id = st.class_id
    $where ORDER BY c.name, st.name, s.name
")->fetchAll();

$streams = $pdo->query("SELECT st.id, CONCAT(c.name,' - ',st.name) AS label FROM streams st JOIN classes c ON c.id=st.class_id ORDER BY c.name,st.name")->fetchAll();
require_once dirname(__DIR__) . '/layout_header.php';
?>
<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-bold mb-0">Students (<?= count($students) ?>)</h5>
    <div class="d-flex gap-2">
      <a href="export.php?type=csv<?= $stream_filter ? '&stream_id='.$stream_filter : '' ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> Export CSV</a>
      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload"></i> Import</button>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> Add Student</button>
    </div>
  </div>

  <div class="card mb-3 p-3">
    <form class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small">Filter by Stream</label>
        <select name="stream_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Streams</option>
          <?php foreach($streams as $st): ?>
          <option value="<?= $st['id'] ?>" <?= $stream_filter==$st['id']?'selected':'' ?>><?= htmlspecialchars($st['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>#</th><th>Adm No.</th><th>Name</th><th>Class</th><th>Stream</th><th>Gender</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($students as $i=>$s): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($s['adm_number']) ?></td>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><?= htmlspecialchars($s['class']) ?></td>
          <td><?= htmlspecialchars($s['stream']) ?></td>
          <td><?= $s['gender'] ?></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick="editStudent(<?= htmlspecialchars(json_encode($s)) ?>)"><i class="bi bi-pencil"></i></button>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this student?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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
  <div class="modal-header"><h5 class="modal-title">Add Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body row g-3">
    <div class="col-md-6"><label class="form-label">Adm Number</label><input type="text" name="adm_number" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Gender</label>
      <select name="gender" class="form-select"><option value="M">Male</option><option value="F">Female</option><option value="Other">Other</option></select></div>
    <div class="col-12"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
    <div class="col-12"><label class="form-label">Stream</label>
      <select name="stream_id" class="form-select" required>
        <option value="">Select stream</option>
        <?php foreach($streams as $st): ?><option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['label']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Edit Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="e_id">
  <div class="modal-body row g-3">
    <div class="col-md-6"><label class="form-label">Adm Number</label><input type="text" name="adm_number" id="e_adm" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Gender</label>
      <select name="gender" id="e_gender" class="form-select"><option value="M">Male</option><option value="F">Female</option><option value="Other">Other</option></select></div>
    <div class="col-12"><label class="form-label">Full Name</label><input type="text" name="name" id="e_name" class="form-control" required></div>
    <div class="col-12"><label class="form-label">Stream</label>
      <select name="stream_id" id="e_stream" class="form-select" required>
        <?php foreach($streams as $st): ?><option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['label']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Update</button></div>
  </form>
</div></div></div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Import Students (CSV)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="import">
  <div class="modal-body">
    <p class="text-muted small">CSV format: <code>adm_number, name, stream_id, gender</code> (first row = header)</p>
    <input type="file" name="csv" class="form-control" accept=".csv" required>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Import</button></div>
  </form>
</div></div></div>

<script>
function editStudent(s) {
  document.getElementById('e_id').value = s.id;
  document.getElementById('e_adm').value = s.adm_number;
  document.getElementById('e_name').value = s.name;
  document.getElementById('e_gender').value = s.gender;
  document.getElementById('e_stream').value = s.stream_id;
  new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
