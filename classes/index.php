<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Classes & Streams'; $active = 'classes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_class') {
        //$ay = $pdo->query("SELECT id FROM term WHERE is_current=1 LIMIT 1")->fetchColumn();
        $pdo->prepare("INSERT INTO classes (name, academic_year_id) VALUES (?,?)")->execute([$_POST['name'], $ay]);
        log_action($pdo, current_user_id(), 'ADD_CLASS', $_POST['name']);
    } elseif ($action === 'add_stream') {
        $pdo->prepare("INSERT INTO streams (class_id, name) VALUES (?,?)")->execute([$_POST['class_id'], $_POST['name']]);
        log_action($pdo, current_user_id(), 'ADD_STREAM', $_POST['name']);
    } elseif ($action === 'delete_class') {
        $pdo->prepare("DELETE FROM classes WHERE id=?")->execute([$_POST['id']]);
    } elseif ($action === 'delete_stream') {
        $pdo->prepare("DELETE FROM streams WHERE id=?")->execute([$_POST['id']]);
    }
    header('Location: index.php'); exit;
}

$classes = $pdo->query("SELECT c.*, COUNT(st.id) AS stream_count FROM classes c LEFT JOIN streams st ON st.class_id=c.id GROUP BY c.id ORDER BY c.name")->fetchAll();
$streams = $pdo->query("SELECT st.*, c.name AS class_name, (SELECT COUNT(*) FROM students WHERE stream_id=st.id) AS student_count FROM streams st JOIN classes c ON c.id=st.class_id ORDER BY c.name,st.name")->fetchAll();
require_once dirname(__DIR__) . '/layout_header.php';
?>
<div class="main-content">
  <div class="row g-4">
    <!-- Classes -->
    <div class="col-md-5">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Classes</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addClassModal"><i class="bi bi-plus-lg"></i> Add Class</button>
      </div>
      <div class="card">
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Class</th><th>Streams</th><th></th></tr></thead>
            <tbody>
            <?php foreach($classes as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['name']) ?></td>
              <td><span class="badge bg-primary"><?= $c['stream_count'] ?></span></td>
              <td>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete class and all its streams/students?')">
                  <input type="hidden" name="action" value="delete_class"><input type="hidden" name="id" value="<?= $c['id'] ?>">
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
    <!-- Streams -->
    <div class="col-md-7">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Streams</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStreamModal"><i class="bi bi-plus-lg"></i> Add Stream</button>
      </div>
      <div class="card">
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Class</th><th>Stream</th><th>Students</th><th></th></tr></thead>
            <tbody>
            <?php foreach($streams as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['class_name']) ?></td>
              <td><?= htmlspecialchars($s['name']) ?></td>
              <td><span class="badge bg-secondary"><?= $s['student_count'] ?></span></td>
              <td>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete stream?')">
                  <input type="hidden" name="action" value="delete_stream"><input type="hidden" name="id" value="<?= $s['id'] ?>">
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
  </div>
</div>

<div class="modal fade" id="addClassModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add Class</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="action" value="add_class">
  <div class="modal-body"><label class="form-label">Class Name</label><input type="text" name="name" class="form-control" placeholder="e.g. Form 1" required></div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<div class="modal fade" id="addStreamModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add Stream</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="action" value="add_stream">
  <div class="modal-body">
    <label class="form-label">Class</label>
    <select name="class_id" class="form-select mb-3" required>
      <option value="">Select class</option>
      <?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
    </select>
    <label class="form-label">Stream Name</label>
    <input type="text" name="name" class="form-control" placeholder="e.g. East" required>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
