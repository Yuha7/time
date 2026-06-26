<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Notice Board'; $active = 'notice';

// Available published timetables for filter
$timetables_list = $pdo->query("SELECT id, title FROM timetables WHERE status='published' ORDER BY created_at DESC")->fetchAll();
$timetable_id    = (int)($_GET['timetable_id'] ?? ($timetables_list[0]['id'] ?? 0));

$timetable = null;
if ($timetable_id) {
    $stmt = $pdo->prepare("SELECT t.*, tm.name AS term_name, yr.name AS year_name FROM timetables t LEFT JOIN term tm ON tm.id=t.term_id LEFT JOIN year yr ON yr.id=t.year_id WHERE t.id=?");
    $stmt->execute([$timetable_id]);
    $timetable = $stmt->fetch();
}

// ── Teacher duties grouped by date ────────────────────────────────────────────
$teacher_duties = [];
if ($timetable_id) {
    $rows = $pdo->prepare("
        SELECT u.name AS teacher,
               es.exam_date, es.start_time, es.end_time,
               s.name AS subject,
               c.name AS class, st.name AS stream
        FROM invigilator_allocations ia
        JOIN exam_slots es ON es.id = ia.exam_slot_id
        JOIN users u       ON u.id  = ia.teacher_id
        JOIN subjects s    ON s.id  = es.subject_id
        JOIN streams st    ON st.id = es.stream_id
        JOIN classes c     ON c.id  = st.class_id
        WHERE es.timetable_id = ?
        ORDER BY u.name, es.exam_date, es.start_time
    ");
    $rows->execute([$timetable_id]);
    foreach ($rows->fetchAll() as $r) {
        $teacher_duties[$r['teacher']][] = $r;
    }
}

// ── Student room allocations grouped by class/stream ──────────────────────────
// room_allocations stores how many students per room per slot.
// We need to display: per slot, per room, the list of students assigned.
// Since allocation is random and we only store counts (not individual mappings),
// we re-derive individual student→room mapping deterministically using the
// stored students_assigned count and the shuffled student list (seeded by slot+room).
$student_allocations = []; // [class][stream][date] => [ [time, subject, room, students[]] ]
if ($timetable_id) {
    $slot_rooms = $pdo->prepare("
        SELECT ra.exam_slot_id, ra.room_id, ra.students_assigned,
               r.name AS room_name,
               es.exam_date, es.start_time, es.end_time,
               s.name AS subject,
               st.id AS stream_id, st.name AS stream_name,
               c.name  AS class_name
        FROM room_allocations ra
        JOIN exam_rooms r   ON r.id  = ra.room_id
        JOIN exam_slots es  ON es.id = ra.exam_slot_id
        JOIN subjects s     ON s.id  = es.subject_id
        JOIN streams st     ON st.id = es.stream_id
        JOIN classes c      ON c.id  = st.class_id
        WHERE es.timetable_id = ?
        ORDER BY es.exam_date, es.start_time, ra.room_id
    ");
    $slot_rooms->execute([$timetable_id]);
    $slot_rooms = $slot_rooms->fetchAll();

    // Pre-load students per stream
    $stream_students = [];
    $all_streams = $pdo->query("SELECT id FROM streams")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($all_streams as $sid) {
        $ss = $pdo->prepare("SELECT id, name, adm_number FROM students WHERE stream_id=? ORDER BY name");
        $ss->execute([$sid]);
        $stream_students[$sid] = $ss->fetchAll();
    }

    // Group slot_rooms by slot to reconstruct student→room mapping
    $slots_grouped = [];
    foreach ($slot_rooms as $row) {
        $slots_grouped[$row['exam_slot_id']][] = $row;
    }

    foreach ($slots_grouped as $slot_id => $rooms) {
        $first      = $rooms[0];
        $stream_id  = $first['stream_id'];
        $students   = $stream_students[$stream_id] ?? [];

        // Reproduce the same shuffle using slot_id as seed
        $keys = array_keys($students);
        srand($slot_id);
        shuffle($keys);

        $offset = 0;
        foreach ($rooms as $room) {
            $chunk        = array_slice($keys, $offset, $room['students_assigned']);
            $room_students = array_map(fn($k) => $students[$k], $chunk);
            $offset       += $room['students_assigned'];

            $student_allocations[$first['class_name']][$first['stream_name']][] = [
                'date'     => $first['exam_date'],
                'start'    => $first['start_time'],
                'end'      => $first['end_time'],
                'subject'  => $first['subject'],
                'room'     => $room['room_name'],
                'students' => $room_students,
            ];
        }
    }
}

require_once dirname(__DIR__) . '/layout_header.php';
?>
<style>
  .notice-section { page-break-inside: avoid; }
  .student-chip { display:inline-block; background:#e8eaf6; border-radius:4px; padding:1px 7px; margin:2px; font-size:.78rem; }
  @media print {
    .sidebar, .no-print { display:none !important; }
    .main-content { margin-left:0 !important; }
    .card { box-shadow:none !important; border:1px solid #ccc !important; }
  }
</style>

<div class="main-content">
  <!-- Controls -->
  <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2 no-print">
    <div>
      <h5 class="fw-bold mb-0"><i class="bi bi-megaphone"></i> Examination Notice Board</h5>
      <?php if($timetable): ?>
        <small class="text-muted"><?= htmlspecialchars($timetable['title']) ?> &nbsp;|&nbsp; <?= htmlspecialchars($timetable['year_name'] ?? '—') ?> &nbsp;|&nbsp; <?= htmlspecialchars($timetable['term_name'] ?? '—') ?></small>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <form class="d-flex gap-2">
        <select name="timetable_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:200px">
          <option value="">-- Select Timetable --</option>
          <?php foreach($timetables_list as $tl): ?>
            <option value="<?= $tl['id'] ?>" <?= $tl['id']==$timetable_id?'selected':'' ?>><?= htmlspecialchars($tl['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
  </div>

  <?php if(!$timetable_id || !$timetable): ?>
    <div class="alert alert-info">Select a published timetable above to view the notice board.</div>
  <?php elseif(empty($teacher_duties) && empty($student_allocations)): ?>
    <div class="alert alert-warning">No allocations found for this timetable. Run room and invigilator allocation first.</div>
  <?php else: ?>

  <!-- Print header (only shows when printing) -->
  <div style="display:none" class="print-only text-center mb-3">
    <h4><?= htmlspecialchars($timetable['title']) ?></h4>
    <p><?= htmlspecialchars($timetable['year_name'] ?? '') ?> — <?= htmlspecialchars($timetable['term_name'] ?? '') ?> &nbsp;|&nbsp; Generated: <?= date('d M Y H:i') ?></p>
    <hr>
  </div>
  <style>.print-only{display:none}@media print{.print-only{display:block !important}}</style>

  <!-- ══ SECTION 1: TEACHER INVIGILATION DUTIES ══ -->
  <?php if(!empty($teacher_duties)): ?>
  <div class="notice-section mb-4">
    <h6 class="fw-bold text-white bg-primary px-3 py-2 rounded-top mb-0">
      <i class="bi bi-person-badge"></i> Teacher Invigilation Duties
    </h6>
    <div class="card rounded-top-0">
      <div class="card-body p-0">
        <table class="table table-bordered table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>Teacher</th>
              <th>Date</th>
              <th>Time</th>
              <th>Subject</th>
              <th>Class / Stream</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($teacher_duties as $teacher => $duties): ?>
            <?php foreach($duties as $i => $d): ?>
            <tr>
              <?php if($i === 0): ?>
                <td rowspan="<?= count($duties) ?>" class="fw-semibold align-middle bg-light">
                  <?= htmlspecialchars($teacher) ?>
                </td>
              <?php endif; ?>
              <td><?= date('D, d M Y', strtotime($d['exam_date'])) ?></td>
              <td class="text-nowrap"><?= substr($d['start_time'],0,5) ?> – <?= substr($d['end_time'],0,5) ?></td>
              <td><?= htmlspecialchars($d['subject']) ?></td>
              <td><?= htmlspecialchars($d['class'].' '.$d['stream']) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ SECTION 2: STUDENT ROOM ALLOCATIONS ══ -->
  <?php if(!empty($student_allocations)): ?>
  <div class="notice-section">
    <h6 class="fw-bold text-white bg-success px-3 py-2 rounded-top mb-0">
      <i class="bi bi-people"></i> Student Exam Room Allocations
    </h6>
    <div class="card rounded-top-0">
      <div class="card-body p-0">
        <?php foreach($student_allocations as $class_name => $streams): ?>
          <div class="px-3 pt-3 pb-1">
            <strong class="text-primary"><i class="bi bi-building"></i> <?= htmlspecialchars($class_name) ?></strong>
          </div>
          <?php foreach($streams as $stream_name => $entries): ?>
            <div class="px-3 pb-2">
              <span class="badge bg-secondary mb-2"><i class="bi bi-bookmark"></i> Stream: <?= htmlspecialchars($stream_name) ?></span>
              <table class="table table-bordered table-sm mb-3">
                <thead class="table-light">
                  <tr>
                    <th style="width:110px">Date</th>
                    <th style="width:110px">Time</th>
                    <th>Subject</th>
                    <th style="width:120px">Room</th>
                    <th>Students Allocated</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach($entries as $e): ?>
                  <tr>
                    <td><?= date('D, d M Y', strtotime($e['date'])) ?></td>
                    <td class="text-nowrap"><?= substr($e['start'],0,5) ?> – <?= substr($e['end'],0,5) ?></td>
                    <td><?= htmlspecialchars($e['subject']) ?></td>
                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($e['room']) ?></span></td>
                    <td>
                      <?php foreach($e['students'] as $stu): ?>
                        <span class="student-chip" title="<?= htmlspecialchars($stu['adm_number']) ?>">
                          <?= htmlspecialchars($stu['name']) ?> <small class="text-muted">(<?= htmlspecialchars($stu['adm_number']) ?>)</small>
                        </span>
                      <?php endforeach; ?>
                      <span class="badge bg-secondary ms-1"><?= count($e['students']) ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; ?>
          <hr class="my-1">
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
