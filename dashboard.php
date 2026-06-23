<?php
require_once __DIR__ . '/config.php';
$page_title = 'Dashboard'; $active = 'dashboard';
require_once __DIR__ . '/layout_header.php';

$stats = [
    'students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'teachers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn(),
    'classes'  => $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
    'subjects' => $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn(),
    'rooms'    => $pdo->query("SELECT COUNT(*) FROM exam_rooms WHERE is_active=1")->fetchColumn(),
    'today'    => $pdo->query("SELECT COUNT(*) FROM exam_slots WHERE exam_date = CURDATE()")->fetchColumn(),
    'upcoming' => $pdo->query("SELECT COUNT(*) FROM exam_slots WHERE exam_date > CURDATE()")->fetchColumn(),
];
$recent_slots = $pdo->query("
    SELECT es.exam_date, es.start_time, s.name AS subject, st.name AS stream, cl.name AS class
    FROM exam_slots es
    JOIN subjects s ON s.id = es.subject_id
    JOIN streams st ON st.id = es.stream_id
    JOIN classes cl ON cl.id = st.class_id
    WHERE es.exam_date >= CURDATE()
    ORDER BY es.exam_date, es.start_time LIMIT 10
")->fetchAll();

$cards = [
    ['label'=>'Total Students','val'=>$stats['students'],'icon'=>'people-fill','color'=>'#1976d2'],
    ['label'=>'Total Teachers','val'=>$stats['teachers'],'icon'=>'person-badge-fill','color'=>'#388e3c'],
    ['label'=>'Total Classes','val'=>$stats['classes'],'icon'=>'building','color'=>'#f57c00'],
    ['label'=>'Total Subjects','val'=>$stats['subjects'],'icon'=>'book-fill','color'=>'#7b1fa2'],
    ['label'=>'Exam Rooms','val'=>$stats['rooms'],'icon'=>'door-open-fill','color'=>'#0097a7'],
    ['label'=>'Exams Today','val'=>$stats['today'],'icon'=>'calendar-check-fill','color'=>'#c62828'],
    ['label'=>'Upcoming Exams','val'=>$stats['upcoming'],'icon'=>'calendar-event-fill','color'=>'#5d4037'],
];
?>
<div class="main-content">
  <h5 class="mb-3 fw-bold">Dashboard</h5>
  <div class="row g-3 mb-4">
    <?php foreach($cards as $c): ?>
    <div class="col-6 col-md-4 col-xl-3">
      <div class="card stat-card h-100" style="border-color:<?= $c['color'] ?>">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:<?= $c['color'] ?>20">
            <i class="bi bi-<?= $c['icon'] ?> fs-4" style="color:<?= $c['color'] ?>"></i>
          </div>
          <div>
            <div class="fs-4 fw-bold"><?= $c['val'] ?></div>
            <div class="text-muted small"><?= $c['label'] ?></div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-header bg-white fw-semibold">Upcoming Exams</div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Time</th><th>Class</th><th>Stream</th><th>Subject</th></tr></thead>
        <tbody>
        <?php if(empty($recent_slots)): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">No upcoming exams</td></tr>
        <?php else: foreach($recent_slots as $r): ?>
          <tr>
            <td><?= date('D, d M Y', strtotime($r['exam_date'])) ?></td>
            <td><?= substr($r['start_time'],0,5) ?></td>
            <td><?= htmlspecialchars($r['class']) ?></td>
            <td><?= htmlspecialchars($r['stream']) ?></td>
            <td><?= htmlspecialchars($r['subject']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/layout_footer.php'; ?>
