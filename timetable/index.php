<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Timetables'; $active = 'timetable';

// Ensure timetables table has term_id and year_id columns
$pdo->exec("ALTER TABLE timetables 
    ADD COLUMN IF NOT EXISTS term_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS year_id INT DEFAULT NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO timetables (title, term_id, year_id, created_by) VALUES (?,?,?,?)")
            ->execute([
                $_POST['title'],
                $_POST['term_id'] ?: null,
                $_POST['year_id'] ?: null,
                $_SESSION['user_id']
            ]);
        log_action($pdo, $_SESSION['user_id'], 'ADD_TIMETABLE', $_POST['title']);
    } elseif ($action === 'publish') {
        $pdo->prepare("UPDATE timetables SET status='published' WHERE id=?")->execute([$_POST['id']]);
        log_action($pdo, $_SESSION['user_id'], 'PUBLISH_TIMETABLE', $_POST['id']);
    } elseif ($action === 'draft') {
        $pdo->prepare("UPDATE timetables SET status='draft' WHERE id=?")->execute([$_POST['id']]);
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM timetables WHERE id=?")->execute([$_POST['id']]);
    }
    header('Location: index.php'); exit;
}

$timetables = $pdo->query("
    SELECT t.*, 
           tm.name AS term_name, 
           yr.name AS year_name, 
           u.name  AS created_by_name,
           COUNT(es.id) AS slot_count
    FROM timetables t
    LEFT JOIN term tm         ON tm.id = t.term_id
    LEFT JOIN year yr         ON yr.id = t.year_id
    LEFT JOIN users u         ON u.id  = t.created_by
    LEFT JOIN exam_slots es   ON es.timetable_id = t.id
    GROUP BY t.id
    ORDER BY t.created_at DESC
")->fetchAll();

$terms = $pdo->query("SELECT id, name FROM term ORDER BY name ASC")->fetchAll();
$years = $pdo->query("SELECT id, name FROM year ORDER BY name DESC")->fetchAll();

require_once dirname(__DIR__) . '/layout_header.php';
?>
<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">Timetables</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="bi bi-plus-lg"></i> New Timetable
    </button>
  </div>

  <div class="row g-3">
    <?php foreach($timetables as $t): ?>
    <div class="col-md-6 col-xl-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <h6 class="fw-bold mb-1"><?= htmlspecialchars($t['title']) ?></h6>
            <span class="badge <?= $t['status']==='published' ? 'bg-success' : 'bg-warning text-dark' ?>">
              <?= ucfirst($t['status']) ?>
            </span>
          </div>
          <p class="text-muted small mb-1">
            <i class="bi bi-calendar3"></i> <?= htmlspecialchars($t['year_name'] ?? '—') ?>
            &nbsp;|&nbsp;
            <i class="bi bi-bookmark"></i> <?= htmlspecialchars($t['term_name'] ?? '—') ?>
          </p>
          <p class="text-muted small mb-2">
            Exam Slots: <strong><?= $t['slot_count'] ?></strong>
            &nbsp;|&nbsp; By: <?= htmlspecialchars($t['created_by_name'] ?? '—') ?>
          </p>
          <div class="d-flex gap-2 flex-wrap">
            <a href="slots.php?timetable_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-table"></i> Manage Slots
            </a>
            <?php if($t['status'] === 'draft'): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="publish">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button class="btn btn-sm btn-success"><i class="bi bi-send"></i> Publish</button>
              </form>
            <?php else: ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="draft">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button class="btn btn-sm btn-warning"><i class="bi bi-arrow-counterclockwise"></i> Draft</button>
              </form>
            <?php endif; ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this timetable?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($timetables)): ?>
      <div class="col-12">
        <p class="text-muted text-center py-4">No timetables yet. Click "New Timetable" to create one.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Timetable Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Timetable</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Year</label>
            <select name="year_id" class="form-select" required>
              <option value="">-- Select Year --</option>
              <?php foreach($years as $yr): ?>
                <option value="<?= $yr['id'] ?>"><?= htmlspecialchars($yr['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Term</label>
            <select name="term_id" class="form-select" required>
              <option value="">-- Select Term --</option>
              <?php foreach($terms as $tm): ?>
                <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
