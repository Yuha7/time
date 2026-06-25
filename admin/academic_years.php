<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Years & Terms'; $active = 'academic';

$pdo->exec("CREATE TABLE IF NOT EXISTS year (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(20) NOT NULL,
    start_date DATE,
    end_date   DATE
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS term (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL,
    start_date DATE,
    end_date   DATE
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_year') {
        $pdo->prepare("INSERT INTO year (name, start_date, end_date) VALUES (?, ?, ?)")
            ->execute([trim($_POST['name']), $_POST['start_date'] ?: null, $_POST['end_date'] ?: null]);
        log_action($pdo, current_user_id(), 'ADD_YEAR', trim($_POST['name']));

    } elseif ($action === 'edit_year') {
        $pdo->prepare("UPDATE year SET name=?, start_date=?, end_date=? WHERE id=?")
            ->execute([trim($_POST['name']), $_POST['start_date'] ?: null, $_POST['end_date'] ?: null, $_POST['id']]);
        log_action($pdo, current_user_id(), 'EDIT_YEAR', $_POST['id']);

    } elseif ($action === 'delete_year') {
        $pdo->prepare("DELETE FROM year WHERE id=?")->execute([$_POST['id']]);
        log_action($pdo, current_user_id(), 'DELETE_YEAR', $_POST['id']);

    } elseif ($action === 'add_term') {
        $pdo->prepare("INSERT INTO term (name, start_date, end_date) VALUES (?, ?, ?)")
            ->execute([trim($_POST['name']), $_POST['start_date'] ?: null, $_POST['end_date'] ?: null]);
        log_action($pdo, current_user_id(), 'ADD_TERM', trim($_POST['name']));

    } elseif ($action === 'edit_term') {
        $pdo->prepare("UPDATE term SET name=?, start_date=?, end_date=? WHERE id=?")
            ->execute([trim($_POST['name']), $_POST['start_date'] ?: null, $_POST['end_date'] ?: null, $_POST['id']]);
        log_action($pdo, current_user_id(), 'EDIT_TERM', $_POST['id']);

    } elseif ($action === 'delete_term') {
        $pdo->prepare("DELETE FROM term WHERE id=?")->execute([$_POST['id']]);
        log_action($pdo, current_user_id(), 'DELETE_TERM', $_POST['id']);
    }

    header('Location: academic_years.php'); exit;
}

$years = $pdo->query("SELECT * FROM year ORDER BY start_date DESC, name ASC")->fetchAll();
$terms = $pdo->query("SELECT * FROM term ORDER BY start_date ASC, name ASC")->fetchAll();

require_once dirname(__DIR__) . '/layout_header.php';
?>
<div class="main-content">
  <h5 class="fw-bold mb-4"><i class="bi bi-calendar3"></i> Academic Years &amp; Terms</h5>

  <div class="row g-4">

    <!-- ══ YEARS ══ -->
    <div class="col-md-6">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Academic Years</h6>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addYearModal">
          <i class="bi bi-plus-lg"></i> Add Year
        </button>
      </div>
      <div class="card">
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr><th>Year</th><th>Start</th><th>End</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php if(empty($years)): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No years added yet</td></tr>
            <?php else: foreach($years as $y): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($y['name']) ?></td>
                <td><small><?= $y['start_date'] ? date('d M Y', strtotime($y['start_date'])) : '—' ?></small></td>
                <td><small><?= $y['end_date']   ? date('d M Y', strtotime($y['end_date']))   : '—' ?></small></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary"
                    onclick="openEditYear(<?= htmlspecialchars(json_encode($y)) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete year \'<?= htmlspecialchars($y['name']) ?>\'?')">
                    <input type="hidden" name="action" value="delete_year">
                    <input type="hidden" name="id" value="<?= $y['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ══ TERMS ══ -->
    <div class="col-md-6">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Terms</h6>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTermModal">
          <i class="bi bi-plus-lg"></i> Add Term
        </button>
      </div>
      <div class="card">
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr><th>Term</th><th>Start</th><th>End</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php if(empty($terms)): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No terms added yet</td></tr>
            <?php else: foreach($terms as $t): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($t['name']) ?></td>
                <td><small><?= $t['start_date'] ? date('d M Y', strtotime($t['start_date'])) : '—' ?></small></td>
                <td><small><?= $t['end_date']   ? date('d M Y', strtotime($t['end_date']))   : '—' ?></small></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary"
                    onclick="openEditTerm(<?= htmlspecialchars(json_encode($t)) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete term \'<?= htmlspecialchars($t['name']) ?>\'?')">
                    <input type="hidden" name="action" value="delete_term">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Add Year Modal -->
<div class="modal fade" id="addYearModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header">
    <h5 class="modal-title">Add Academic Year</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <form method="POST"><input type="hidden" name="action" value="add_year">
  <div class="modal-body row g-3">
    <div class="col-12">
      <label class="form-label">Year Name <small class="text-muted">(e.g. 2025/2026)</small></label>
      <input type="text" name="name" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Start Date</label>
      <input type="date" name="start_date" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">End Date</label>
      <input type="date" name="end_date" class="form-control">
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<!-- Edit Year Modal -->
<div class="modal fade" id="editYearModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header">
    <h5 class="modal-title">Edit Academic Year</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <form method="POST"><input type="hidden" name="action" value="edit_year">
  <input type="hidden" name="id" id="ey_id">
  <div class="modal-body row g-3">
    <div class="col-12">
      <label class="form-label">Year Name</label>
      <input type="text" name="name" id="ey_name" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Start Date</label>
      <input type="date" name="start_date" id="ey_start" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">End Date</label>
      <input type="date" name="end_date" id="ey_end" class="form-control">
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Update</button></div>
  </form>
</div></div></div>

<!-- Add Term Modal -->
<div class="modal fade" id="addTermModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header">
    <h5 class="modal-title">Add Term</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <form method="POST"><input type="hidden" name="action" value="add_term">
  <div class="modal-body row g-3">
    <div class="col-12">
      <label class="form-label">Term Name <small class="text-muted">(e.g. Term 1)</small></label>
      <input type="text" name="name" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Start Date</label>
      <input type="date" name="start_date" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">End Date</label>
      <input type="date" name="end_date" class="form-control">
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<!-- Edit Term Modal -->
<div class="modal fade" id="editTermModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header">
    <h5 class="modal-title">Edit Term</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <form method="POST"><input type="hidden" name="action" value="edit_term">
  <input type="hidden" name="id" id="et_id">
  <div class="modal-body row g-3">
    <div class="col-12">
      <label class="form-label">Term Name</label>
      <input type="text" name="name" id="et_name" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Start Date</label>
      <input type="date" name="start_date" id="et_start" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">End Date</label>
      <input type="date" name="end_date" id="et_end" class="form-control">
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Update</button></div>
  </form>
</div></div></div>

<script>
function openEditYear(y) {
    document.getElementById('ey_id').value    = y.id;
    document.getElementById('ey_name').value  = y.name;
    document.getElementById('ey_start').value = y.start_date ?? '';
    document.getElementById('ey_end').value   = y.end_date   ?? '';
    new bootstrap.Modal(document.getElementById('editYearModal')).show();
}
function openEditTerm(t) {
    document.getElementById('et_id').value    = t.id;
    document.getElementById('et_name').value  = t.name;
    document.getElementById('et_start').value = t.start_date ?? '';
    document.getElementById('et_end').value   = t.end_date   ?? '';
    new bootstrap.Modal(document.getElementById('editTermModal')).show();
}
</script>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
