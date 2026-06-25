<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Reports'; $active = 'reports';

// Handle exports
$type = $_GET['type'] ?? '';
$report = $_GET['report'] ?? '';

if ($type && $report) {
    $data = []; $headers = []; $title = '';

    if ($report === 'students_class') {
        $title = 'Students per Class';
        $headers = ['Class','Stream','Students'];
        $data = $pdo->query("SELECT c.name, st.name, COUNT(s.id) FROM students s JOIN streams st ON st.id=s.stream_id JOIN classes c ON c.id=st.class_id GROUP BY st.id ORDER BY c.name,st.name")->fetchAll(PDO::FETCH_NUM);
    } elseif ($report === 'room_allocations') {
        $title = 'Room Allocations';
        $headers = ['Date','Time','Class','Stream','Subject','Room','Students','Capacity'];
        $data = $pdo->query("SELECT es.exam_date, CONCAT(TIME_FORMAT(es.start_time,'%H:%i'),'-',TIME_FORMAT(es.end_time,'%H:%i')), c.name, st.name, su.name, r.name, ra.students_assigned, r.capacity FROM room_allocations ra JOIN exam_slots es ON es.id=ra.exam_slot_id JOIN streams st ON st.id=es.stream_id JOIN classes c ON c.id=st.class_id JOIN subjects su ON su.id=es.subject_id JOIN exam_rooms r ON r.id=ra.room_id ORDER BY es.exam_date,es.start_time")->fetchAll(PDO::FETCH_NUM);
    } elseif ($report === 'invigilators') {
        $title = 'Invigilator Assignments';
        $headers = ['Date','Time','Class','Stream','Subject','Invigilator'];
        $data = $pdo->query("SELECT es.exam_date, CONCAT(TIME_FORMAT(es.start_time,'%H:%i'),'-',TIME_FORMAT(es.end_time,'%H:%i')), c.name, st.name, su.name, u.name FROM invigilator_allocations ia JOIN exam_slots es ON es.id=ia.exam_slot_id JOIN streams st ON st.id=es.stream_id JOIN classes c ON c.id=st.class_id JOIN subjects su ON su.id=es.subject_id JOIN users u ON u.id=ia.teacher_id ORDER BY es.exam_date,es.start_time,u.name")->fetchAll(PDO::FETCH_NUM);
    } elseif ($report === 'exam_schedule') {
        $title = 'Examination Schedule';
        $headers = ['Date','Start','End','Class','Stream','Subject'];
        $data = $pdo->query("SELECT es.exam_date, TIME_FORMAT(es.start_time,'%H:%i'), TIME_FORMAT(es.end_time,'%H:%i'), c.name, st.name, su.name FROM exam_slots es JOIN streams st ON st.id=es.stream_id JOIN classes c ON c.id=st.class_id JOIN subjects su ON su.id=es.subject_id JOIN timetables t ON t.id=es.timetable_id WHERE t.status='published' ORDER BY es.exam_date,es.start_time")->fetchAll(PDO::FETCH_NUM);
    } elseif ($report === 'teacher_assignments') {
        $title = 'Teacher Subject Assignments';
        $headers = ['Teacher','Subject','Code','Class','Stream'];
        $data = $pdo->query("SELECT u.name, su.name, su.code, c.name, st.name FROM teacher_subjects ts JOIN users u ON u.id=ts.teacher_id JOIN subjects su ON su.id=ts.subject_id JOIN streams st ON st.id=ts.stream_id JOIN classes c ON c.id=st.class_id ORDER BY u.name,su.name")->fetchAll(PDO::FETCH_NUM);
    }

    if ($type === 'csv') {
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"{$report}_".date('Ymd').".csv\"");
        $f = fopen('php://output','w');
        fputcsv($f, $headers);
        foreach ($data as $row) fputcsv($f, $row);
        fclose($f); exit;
    } elseif ($type === 'html_print') {
        // Simple printable HTML (works as "PDF" via browser print)
        echo "<!DOCTYPE html><html><head><title>$title</title>";
        echo "<style>body{font-family:Arial,sans-serif;font-size:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:5px}th{background:#1a237e;color:#fff}@media print{button{display:none}}</style></head><body>";
        echo "<h3>$title — Generated: ".date('d M Y H:i')."</h3>";
        echo "<button onclick='window.print()' style='margin-bottom:10px'>Print / Save as PDF</button>";
        echo "<table><thead><tr>";
        foreach ($headers as $h) echo "<th>$h</th>";
        echo "</tr></thead><tbody>";
        foreach ($data as $row) {
            echo "<tr>"; foreach ($row as $cell) echo "<td>".htmlspecialchars($cell)."</td>"; echo "</tr>";
        }
        echo "</tbody></table></body></html>"; exit;
    }
}

require_once dirname(__DIR__) . '/layout_header.php';
$reports = [
    ['id'=>'students_class','label'=>'Students per Class/Stream','icon'=>'people'],
    ['id'=>'exam_schedule','label'=>'Examination Schedule','icon'=>'calendar-week'],
    ['id'=>'room_allocations','label'=>'Room Allocations','icon'=>'building'],
    ['id'=>'invigilators','label'=>'Invigilator Assignments','icon'=>'person-check'],
    ['id'=>'teacher_assignments','label'=>'Teacher Assignments','icon'=>'person-badge'],
];
?>
<div class="main-content">
  <h5 class="fw-bold mb-3">Reports</h5>
  <div class="row g-3">
  <?php foreach($reports as $r): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px">
            <i class="bi bi-<?= $r['icon'] ?> text-primary fs-5"></i>
          </div>
          <h6 class="mb-0 fw-semibold"><?= $r['label'] ?></h6>
        </div>
        <div class="d-flex gap-2">
          <a href="?report=<?= $r['id'] ?>&type=csv" class="btn btn-sm btn-outline-success"><i class="bi bi-filetype-csv"></i> CSV</a>
          <a href="?report=<?= $r['id'] ?>&type=html_print" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> PDF/Print</a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
