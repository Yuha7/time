<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Subjects'; $active = 'subjects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO subjects (name,code) VALUES (?,?)")->execute([$_POST['name'], strtoupper($_POST['code'])]);
        log_action($pdo, current_user_id(), 'ADD_SUBJECT', $_POST['code']);
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE subjects SET name=?,code=? WHERE id=?")->execute([$_POST['name'], strtoupper($_POST['code']), $_POST['id']]);
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM subjects WHERE id=?")->execute([$_POST['id']]);
    } elseif ($action === 'assign') {
        $pdo->prepare("INSERT IGNORE INTO teacher_subjects (teacher_id,subject_id,stream_id) VALUES (?,?,?)")
            ->execute([$_POST['teacher_id'], $_POST['subject_id'], $_POST['stream_id']]);
        log_action($pdo, current_user_id(), 'ASSIGN_SUBJECT', "teacher:{$_POST['teacher_id']} subject:{$_POST['subject_id']}");
    } elseif ($action === 'unassign') {
        $pdo->prepare("DELETE FROM teacher_subjects WHERE id=?")->execute([$_POST['id']]);
    }
    header('Location: index.php'); exit;
}

$subjects  = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$teachers  = $pdo->query("SELECT id, name FROM users WHERE role='teacher' AND is_active=1 ORDER BY name")->fetchAll();
$streams   = $pdo->query("SELECT st.id, CONCAT(c.name,' - ',st.name) AS label FROM streams st JOIN classes c ON c.id=st.class_id ORDER BY c.name,st.name")->fetchAll();
$assignments = $pdo->query("
    SELECT ts.id, u.name AS teacher, s.name AS subject, s.code, CONCAT(c.name,' ',st.name) AS stream
    FROM teacher_subjects ts
    JOIN users u ON u.id=ts.teacher_id
    JOIN subjects s ON s.id=ts.subject_id
    JOIN streams st ON st.id=ts.stream_id
    JOIN classes c ON c.id=st.class_id
    ORDER BY u.name, s.name
")->fetchAll();
require_once dirname(__DIR__) . '/layout_header.php';
?>
<div class="main-content">
<div class="row g-4">
  <div class="col-md-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">Subjects</h5>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> Add</button>
    </div>
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead class="table-light"><tr><th>Name</th><th>Code</th><th></th></tr></thead>
          <tbody>
          <?php foreach($subjects as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><span class="badge bg-secondary"><?= $s['code'] ?></span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="editSubject(<?= htmlspecialchars(json_encode($s)) ?>)"><i class="bi bi-pencil"></i></button>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
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

  <div class="col-md-7">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">Teacher-Subject Assignments</h5>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-plus-lg"></i> Assign</button>
    </div>
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead class="table-light"><tr><th>Teacher</th><th>Subject</th><th>Stream</th><th></th></tr></thead>
          <tbody>
          <?php foreach($assignments as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['teacher']) ?></td>
            <td><?= htmlspecialchars($a['subject']) ?> <span class="badge bg-secondary"><?= $a['code'] ?></span></td>
            <td><?= htmlspecialchars($a['stream']) ?></td>
            <td>
              <form method="POST" class="d-inline" onsubmit="return confirm('Remove assignment?')">
                <input type="hidden" name="action" value="unassign"><input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add Subject</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body">
    <label class="form-label">Name</label><input type="text" name="name" class="form-control mb-2" required>
    <label class="form-label">Code</label><input type="text" name="code" class="form-control" required>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Edit Subject</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="e_id">
  <div class="modal-body">
    <label class="form-label">Name</label><input type="text" name="name" id="e_name" class="form-control mb-2" required>
    <label class="form-label">Code</label><input type="text" name="code" id="e_code" class="form-control" required>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Update</button></div>
  </form>
</div></div></div>

<div class="modal fade" id="assignModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Assign Subject to Teacher</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="action" value="assign">
  <div class="modal-body row g-3">
    <div class="col-12"><label class="form-label">Teacher</label>
      <select name="teacher_id" class="form-select" required>
        <option value="">Select</option>
        <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-12"><label class="form-label">Subject</label>
      <select name="subject_id" class="form-select" required>
        <option value="">Select</option>
        <?php foreach($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-12"><label class="form-label">Stream</label>
      <select name="stream_id" class="form-select" required>
        <option value="">Select</option>
        <?php foreach($streams as $st): ?><option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['label']) ?></option><?php endforeach; ?>
      </select></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Assign</button></div>
  </form>
</div></div></div>

<script>
function editSubject(s) {
  document.getElementById('e_id').value = s.id;
  document.getElementById('e_name').value = s.name;
  document.getElementById('e_code').value = s.code;
  new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
