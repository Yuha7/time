<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Invigilator Allocation'; $active = 'invig';
$timetable_id = (int)($_GET['timetable_id'] ?? 0);
if (!$timetable_id) { header('Location: index.php'); exit; }

$timetable = $pdo->prepare("SELECT * FROM timetables WHERE id=?");
$timetable->execute([$timetable_id]);
$timetable = $timetable->fetch();

$message = '';

define('MAX_DUTIES_PER_DAY', 2);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'auto_allocate') {
    // Clear existing
    $pdo->prepare("DELETE ia FROM invigilator_allocations ia JOIN exam_slots es ON es.id=ia.exam_slot_id WHERE es.timetable_id=?")->execute([$timetable_id]);

    $slots = $pdo->prepare("
        SELECT es.id, es.subject_id, es.stream_id, es.exam_date, es.start_time, es.end_time,
               COUNT(st.id) AS student_count
        FROM exam_slots es
        JOIN students st ON st.stream_id = es.stream_id
        WHERE es.timetable_id = ?
        GROUP BY es.id
        ORDER BY es.exam_date, es.start_time
    ");
    $slots->execute([$timetable_id]);
    $slots = $slots->fetchAll();

    $teachers = $pdo->query("SELECT id FROM users WHERE role='teacher' AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);

    // Per-teacher tracking: invigilation schedule and daily duty count
    $invig_schedule = [];  // tid => [[date,start,end], ...]
    $daily_duties   = [];  // tid => [date => count]
    foreach ($teachers as $tid) {
        $invig_schedule[$tid] = [];
        $daily_duties[$tid]   = [];
    }

    $errors       = [];
    $stmt_assign  = $pdo->prepare("INSERT INTO invigilator_allocations (exam_slot_id, teacher_id) VALUES (?,?)");

    foreach ($slots as $slot) {
        $needed = $slot['student_count'] > 120 ? 2 : 1;
        $date   = $slot['exam_date'];

        // Rule 1: exclude teachers who TEACH this subject to this stream
        $excl_subject = $pdo->prepare("SELECT teacher_id FROM teacher_subjects WHERE subject_id=? AND stream_id=?");
        $excl_subject->execute([$slot['subject_id'], $slot['stream_id']]);
        $excluded_ids = $excl_subject->fetchAll(PDO::FETCH_COLUMN);

        // Rule 2: exclude teachers who have ANY lesson (teach any subject) at an overlapping time on this date
        // We treat teacher_subjects as their teaching schedule; if they teach the same stream at overlapping time
        // across any slot in this timetable, they are busy.
        $busy_teaching = $pdo->prepare("
            SELECT DISTINCT ts.teacher_id
            FROM teacher_subjects ts
            JOIN exam_slots other ON other.subject_id = ts.subject_id
                                 AND other.stream_id  = ts.stream_id
            WHERE other.timetable_id = ?
              AND other.exam_date    = ?
              AND NOT (other.end_time <= ? OR other.start_time >= ?)
              AND other.id != ?
        ");
        $busy_teaching->execute([$timetable_id, $date, $slot['start_time'], $slot['end_time'], $slot['id']]);
        $teaching_now = $busy_teaching->fetchAll(PDO::FETCH_COLUMN);
        $excluded_ids = array_unique(array_merge($excluded_ids, $teaching_now));

        $candidates = [];
        foreach ($teachers as $tid) {
            if (in_array($tid, $excluded_ids)) continue;

            // Rule 3: not already invigilating at an overlapping time
            $clash = false;
            foreach ($invig_schedule[$tid] as $booked) {
                if ($booked['date'] === $date &&
                    !($slot['end_time'] <= $booked['start'] || $slot['start_time'] >= $booked['end'])) {
                    $clash = true; break;
                }
            }
            if ($clash) continue;

            // Rule 4: no more than MAX_DUTIES_PER_DAY invigilations per day
            $duties_today = $daily_duties[$tid][$date] ?? 0;
            if ($duties_today >= MAX_DUTIES_PER_DAY) continue;

            $candidates[$tid] = $duties_today; // sort by duties for fairness
        }

        // Sort by fewest duties today first (fair distribution)
        asort($candidates);

        $assigned = 0;
        foreach (array_keys($candidates) as $tid) {
            if ($assigned >= $needed) break;
            $stmt_assign->execute([$slot['id'], $tid]);
            $invig_schedule[$tid][]      = ['date' => $date, 'start' => $slot['start_time'], 'end' => $slot['end_time']];
            $daily_duties[$tid][$date]   = ($daily_duties[$tid][$date] ?? 0) + 1;
            $assigned++;
        }

        if ($assigned < $needed) {
            $errors[] = "Slot ID {$slot['id']} ({$date}): only {$assigned}/{$needed} invigilators could be assigned.";
        }
    }

    $message = empty($errors)
        ? 'success:Invigilators allocated successfully.'
        : 'error:' . implode('<br>', $errors);
    log_action($pdo, current_user_id(), 'AUTO_ALLOCATE_INVIGILATORS', "timetable:$timetable_id");
    header("Location: allocate_invigilators.php?timetable_id=$timetable_id&msg=" . urlencode($message)); exit;
}

$msg = $_GET['msg'] ?? '';
$allocations = $pdo->prepare("
    SELECT es.exam_date, es.start_time, es.end_time,
           s.name AS subject, st.name AS stream, c.name AS class,
           u.name AS invigilator
    FROM invigilator_allocations ia
    JOIN exam_slots es ON es.id=ia.exam_slot_id
    JOIN subjects s ON s.id=es.subject_id
    JOIN streams st ON st.id=es.stream_id
    JOIN classes c ON c.id=st.class_id
    JOIN users u ON u.id=ia.teacher_id
    WHERE es.timetable_id=?
    ORDER BY es.exam_date, es.start_time, u.name
");
$allocations->execute([$timetable_id]);
$allocations = $allocations->fetchAll();
require_once dirname(__DIR__) . '/layout_header.php';
?>
<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <a href="slots.php?timetable_id=<?= $timetable_id ?>" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Back to Slots</a>
      <h5 class="fw-bold mb-0">Invigilator Allocation — <?= htmlspecialchars($timetable['title']) ?></h5>
    </div>
    <form method="POST" onsubmit="return confirm('Re-run auto allocation? This will clear existing allocations.')">
      <input type="hidden" name="action" value="auto_allocate">
      <button class="btn btn-primary btn-sm"><i class="bi bi-magic"></i> Auto Allocate Invigilators</button>
    </form>
  </div>

  <div class="alert alert-info py-2 small">
    <strong>Rules applied:</strong>
    Teacher cannot invigilate a subject they teach &bull;
    Teacher with a lesson at the same time is excluded &bull;
    Max <?= MAX_DUTIES_PER_DAY ?> invigilation duties per teacher per day &bull;
    ≤120 students = 1 invigilator, &gt;120 = 2 invigilators &bull;
    No double-booking &bull; Duties distributed fairly.
  </div>

  <?php if($msg): $type=strpos($msg,'success')===0?'success':'danger'; ?>
  <div class="alert alert-<?= $type ?>"><?= htmlspecialchars_decode(substr($msg, strpos($msg,':')+1)) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Time</th><th>Class/Stream</th><th>Subject</th><th>Invigilator</th></tr></thead>
        <tbody>
        <?php if(empty($allocations)): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">No allocations yet. Click "Auto Allocate Invigilators".</td></tr>
        <?php else: foreach($allocations as $a): ?>
        <tr>
          <td><?= date('d M Y', strtotime($a['exam_date'])) ?></td>
          <td><?= substr($a['start_time'],0,5) ?>–<?= substr($a['end_time'],0,5) ?></td>
          <td><?= htmlspecialchars($a['class'].' '.$a['stream']) ?></td>
          <td><?= htmlspecialchars($a['subject']) ?></td>
          <td><?= htmlspecialchars($a['invigilator']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
