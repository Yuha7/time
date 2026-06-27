<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Exam Slots'; $active = 'slots';
$timetable_id = (int)($_GET['timetable_id'] ?? 0);

if (!$timetable_id) { header('Location: index.php'); exit; }

$timetable = $pdo->prepare("SELECT * FROM timetables WHERE id=?");
$timetable->execute([$timetable_id]);
$timetable = $timetable->fetch();
if (!$timetable) { header('Location: index.php'); exit; }

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        // Conflict detection: same stream, overlapping time on same date
        $conflict = $pdo->prepare("
            SELECT COUNT(*) FROM exam_slots
            WHERE timetable_id=? AND stream_id=? AND exam_date=?
            AND NOT (end_time <= ? OR start_time >= ?)
        ");
        $conflict->execute([$timetable_id, $_POST['stream_id'], $_POST['exam_date'], $_POST['start_time'], $_POST['end_time']]);
        if ($conflict->fetchColumn() > 0) {
            $error = 'Conflict detected: this stream already has an exam at the overlapping time on this date.';
        } else {
            $pdo->prepare("INSERT INTO exam_slots (timetable_id,subject_id,stream_id,exam_date,start_time,end_time) VALUES (?,?,?,?,?,?)")
                ->execute([$timetable_id, $_POST['subject_id'], $_POST['stream_id'], $_POST['exam_date'], $_POST['start_time'], $_POST['end_time']]);
            log_action($pdo, current_user_id(), 'ADD_EXAM_SLOT', "timetable:$timetable_id");
            $success = 'Exam slot added.';
        }
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM exam_slots WHERE id=? AND timetable_id=?")->execute([$_POST['id'], $timetable_id]);
    }
    if (!$error) { header("Location: slots.php?timetable_id=$timetable_id"); exit; }
}

$slots = $pdo->prepare("
    SELECT es.*, s.name AS subject, s.code, st.name AS stream, c.name AS class
    FROM exam_slots es
    JOIN subjects s ON s.id=es.subject_id
    JOIN streams st ON st.id=es.stream_id
    JOIN classes c ON c.id=st.class_id
    WHERE es.timetable_id=?
    ORDER BY es.exam_date, es.start_time
");
$slots->execute([$timetable_id]);
$slots = $slots->fetchAll();

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$streams  = $pdo->query("SELECT st.id, CONCAT(c.name,' - ',st.name) AS label FROM streams st JOIN classes c ON c.id=st.class_id ORDER BY c.name,st.name")->fetchAll();
require_once dirname(__DIR__) . '/layout_header.php';
?>
<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-1">
    <div>
      <a href="index.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Timetables</a>
      <h5 class="fw-bold mb-0"><?= htmlspecialchars($timetable['title']) ?> — Exam Slots</h5>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> Add Slot</button>
  </div>

  <?php if($error): ?><div class="alert alert-danger mt-2"><?= $error ?></div><?php endif; ?>
  <?php if($success): ?><div class="alert alert-success mt-2"><?= $success ?></div><?php endif; ?>

  <div class="card mt-3">
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Start</th><th>End</th><th>Class</th><th>Stream</th><th>Subject</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($slots)): ?>
          <tr><td colspan="7" class="text-center text-muted py-3">No exam slots added yet</td></tr>
        <?php else: foreach($slots as $s): ?>
        <tr>
          <td><?= date('D, d M Y', strtotime($s['exam_date'])) ?></td>
          <td><?= substr($s['start_time'],0,5) ?></td>
          <td><?= substr($s['end_time'],0,5) ?></td>
          <td><?= htmlspecialchars($s['class']) ?></td>
          <td><?= htmlspecialchars($s['stream']) ?></td>
          <td><?= htmlspecialchars($s['subject']) ?> <span class="badge bg-secondary"><?= $s['code'] ?></span></td>
          <td>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete slot?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3 d-flex gap-2">
    <a href="allocate_rooms.php?timetable_id=<?= $timetable_id ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-diagram-3"></i> Allocate Rooms</a>
    <a href="allocate_invigilators.php?timetable_id=<?= $timetable_id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-check"></i> Allocate Invigilators</a>
  </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add Exam Slot</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body row g-3">
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
    <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="exam_date" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label">Start Time</label><input type="time" name="start_time" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label">End Time</label><input type="time" name="end_time" class="form-control" required></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Add Slot</button></div>
  </form>
</div></div></div>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
