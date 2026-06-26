<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Room Allocation'; $active = 'rooms_alloc';
$timetable_id = (int)($_GET['timetable_id'] ?? 0);
if (!$timetable_id) { header('Location: index.php'); exit; }

$timetable = $pdo->prepare("SELECT * FROM timetables WHERE id=?");
$timetable->execute([$timetable_id]);
$timetable = $timetable->fetch();

$message = '';

define('MAX_STUDENTS_PER_ROOM', 30);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'auto_allocate') {
    // Clear existing allocations for this timetable
    $pdo->prepare("DELETE ra FROM room_allocations ra JOIN exam_slots es ON es.id=ra.exam_slot_id WHERE es.timetable_id=?")->execute([$timetable_id]);

    // Fetch each slot with the list of student IDs (for random allocation)
    $slots = $pdo->prepare("
        SELECT es.id AS slot_id, es.stream_id
        FROM exam_slots es
        WHERE es.timetable_id=?
    ");
    $slots->execute([$timetable_id]);
    $slots = $slots->fetchAll();

    // All active rooms, each capped at MAX_STUDENTS_PER_ROOM regardless of physical capacity
    $rooms = $pdo->query("SELECT * FROM exam_rooms WHERE is_active=1")->fetchAll();
    $errors = [];
    $stmt_insert = $pdo->prepare("INSERT INTO room_allocations (exam_slot_id, room_id, students_assigned) VALUES (?,?,?)");

    foreach ($slots as $slot) {
        // Get students for this stream and shuffle for random allocation
        $stu_stmt = $pdo->prepare("SELECT id FROM students WHERE stream_id = ?");
        $stu_stmt->execute([$slot['stream_id']]);
        $students = $stu_stmt->fetchAll(PDO::FETCH_COLUMN);
        shuffle($students); // random allocation

        $total     = count($students);
        if ($total === 0) continue;

        // Shuffle rooms so room assignment is also randomised each run
        $avail_rooms = $rooms;
        shuffle($avail_rooms);

        $offset  = 0;
        $room_idx = 0;

        while ($offset < $total) {
            if ($room_idx >= count($avail_rooms)) {
                $errors[] = "Slot ID {$slot['slot_id']}: ran out of rooms — " . ($total - $offset) . " students unallocated.";
                break;
            }
            $room      = $avail_rooms[$room_idx];
            $cap       = min($room['capacity'], MAX_STUDENTS_PER_ROOM); // enforce 30-student cap
            $chunk     = min($cap, $total - $offset);
            $stmt_insert->execute([$slot['slot_id'], $room['id'], $chunk]);
            $offset   += $chunk;
            $room_idx++;
        }
    }
    $message = empty($errors) ? 'success:Rooms allocated successfully (max ' . MAX_STUDENTS_PER_ROOM . ' per room).' : 'error:' . implode('<br>', $errors);
    log_action($pdo, current_user_id(), 'AUTO_ALLOCATE_ROOMS', "timetable:$timetable_id");
    header("Location: allocate_rooms.php?timetable_id=$timetable_id&msg=" . urlencode($message)); exit;
}

$msg = $_GET['msg'] ?? '';
$allocations = $pdo->prepare("
    SELECT es.exam_date, es.start_time, es.end_time,
           s.name AS subject, st.name AS stream, c.name AS class,
           r.name AS room, ra.students_assigned, r.capacity
    FROM room_allocations ra
    JOIN exam_slots es ON es.id=ra.exam_slot_id
    JOIN subjects s ON s.id=es.subject_id
    JOIN streams st ON st.id=es.stream_id
    JOIN classes c ON c.id=st.class_id
    JOIN exam_rooms r ON r.id=ra.room_id
    WHERE es.timetable_id=?
    ORDER BY es.exam_date, es.start_time, es.id
");
$allocations->execute([$timetable_id]);
$allocations = $allocations->fetchAll();
require_once dirname(__DIR__) . '/layout_header.php';
?>
<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <a href="slots.php?timetable_id=<?= $timetable_id ?>" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Back to Slots</a>
      <h5 class="fw-bold mb-0">Room Allocation — <?= htmlspecialchars($timetable['title']) ?></h5>
    </div>
    <form method="POST" onsubmit="return confirm('Re-run auto allocation? This will clear existing allocations.')">
      <input type="hidden" name="action" value="auto_allocate">
      <button class="btn btn-primary btn-sm"><i class="bi bi-magic"></i> Auto Allocate Rooms</button>
    </form>
  </div>

  <?php if($msg): $type=strpos($msg,'success')===0?'success':'danger'; ?>
  <div class="alert alert-<?= $type ?>"><?= htmlspecialchars_decode(substr($msg, strpos($msg,':')+1)) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Time</th><th>Class/Stream</th><th>Subject</th><th>Room</th><th>Assigned</th><th>Max/Room</th></tr></thead>
        <tbody>
        <?php if(empty($allocations)): ?>
          <tr><td colspan="7" class="text-center text-muted py-3">No allocations yet. Click "Auto Allocate Rooms".</td></tr>
        <?php else: foreach($allocations as $a): ?>
        <tr>
          <td><?= date('d M Y', strtotime($a['exam_date'])) ?></td>
          <td><?= substr($a['start_time'],0,5) ?>–<?= substr($a['end_time'],0,5) ?></td>
          <td><?= htmlspecialchars($a['class'].' '.$a['stream']) ?></td>
          <td><?= htmlspecialchars($a['subject']) ?></td>
          <td><?= htmlspecialchars($a['room']) ?></td>
          <td><?= $a['students_assigned'] ?></td>
          <td><?= $a['capacity'] ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
