<?php
require_once __DIR__ . '/auth.php';
auth_required();
$page_title = $page_title ?? APP_NAME;
$active = $active ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($page_title) ?> | <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body{background:#f4f6fb}
  .sidebar{width:240px;height:100vh;background:#1a237e;position:fixed;top:0;left:0;z-index:100;display:flex;flex-direction:column;overflow:hidden}
  .sidebar .brand{padding:1.2rem 1rem;color:#fff;font-size:1.2rem;font-weight:700;border-bottom:1px solid rgba(255,255,255,.15);flex-shrink:0}
  .sidebar nav{flex:1 1 auto;overflow-y:auto;overflow-x:hidden}
  .sidebar nav::-webkit-scrollbar{width:4px}
  .sidebar nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:4px}
  .sidebar nav::-webkit-scrollbar-track{background:transparent}
  .sidebar .nav-link{color:rgba(255,255,255,.75);padding:.55rem 1rem;border-radius:6px;margin:2px 8px}
  .sidebar .nav-link:hover,.sidebar .nav-link.active{background:rgba(255,255,255,.15);color:#fff}
  .sidebar .nav-link i{width:22px;display:inline-block}
  .sidebar .section-label{color:rgba(255,255,255,.4);font-size:.7rem;text-transform:uppercase;padding:.5rem 1rem .1rem}
  .sidebar .sidebar-footer{flex-shrink:0;padding:.75rem 1rem;border-top:1px solid rgba(255,255,255,.15)}
  .main-content{margin-left:240px;padding:1.5rem}
  .topbar{background:#fff;border-bottom:1px solid #e0e0e0;padding:.75rem 1.5rem;margin-left:240px;position:sticky;top:0;z-index:99}
  .card{border:none;box-shadow:0 1px 4px rgba(0,0,0,.08)}
  .stat-card{border-left:4px solid}
  @media(max-width:768px){.sidebar{width:100%;height:auto;position:relative;flex-direction:column}.sidebar nav{max-height:60vh}.main-content,.topbar{margin-left:0}}
</style>
</head>
<body>
<div class="sidebar">
  <div class="brand"><i class="bi bi-calendar-check me-2"></i><?= APP_NAME ?></div>
  <nav class="mt-2">
    <div class="section-label">Main</div>
    <a href="/time/dashboard.php" class="nav-link <?= $active==='dashboard'?'active':'' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>

    <div class="section-label">Management</div>
    <a href="/time/students/index.php" class="nav-link <?= $active==='students'?'active':'' ?>"><i class="bi bi-people"></i> Students</a>
    <a href="/time/teachers/index.php" class="nav-link <?= $active==='teachers'?'active':'' ?>"><i class="bi bi-person-badge"></i> Teachers</a>
    <a href="/time/classes/index.php" class="nav-link <?= $active==='classes'?'active':'' ?>"><i class="bi bi-building"></i> Classes & Streams</a>
    <a href="/time/subjects/index.php" class="nav-link <?= $active==='subjects'?'active':'' ?>"><i class="bi bi-book"></i> Subjects</a>
    <a href="/time/rooms/index.php" class="nav-link <?= $active==='rooms'?'active':'' ?>"><i class="bi bi-door-open"></i> Exam Rooms</a>

    <div class="section-label">Examinations</div>
    <a href="/time/timetable/index.php" class="nav-link <?= $active==='timetable'?'active':'' ?>"><i class="bi bi-table"></i> Timetables</a>
    <a href="/time/timetable/slots.php" class="nav-link <?= $active==='slots'?'active':'' ?>"><i class="bi bi-clock"></i> Exam Slots</a>
    <a href="/time/timetable/allocate_rooms.php" class="nav-link <?= $active==='rooms_alloc'?'active':'' ?>"><i class="bi bi-diagram-3"></i> Room Allocation</a>
    <a href="/time/timetable/allocate_invigilators.php" class="nav-link <?= $active==='invig'?'active':'' ?>"><i class="bi bi-person-check"></i> Invigilators</a>
    <a href="/time/timetable/notice.php" class="nav-link <?= $active==='notice'?'active':'' ?>"><i class="bi bi-megaphone"></i> Notice Board</a>

    <div class="section-label">Reports</div>
    <a href="/time/reports/index.php" class="nav-link <?= $active==='reports'?'active':'' ?>"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>

    <?php if(is_admin()): ?>
    <div class="section-label">System</div>
    <a href="/time/admin/academic_years.php" class="nav-link <?= $active==='academic'?'active':'' ?>"><i class="bi bi-calendar3"></i> Years &amp; Terms</a>
    <a href="/time/admin/audit_logs.php" class="nav-link <?= $active==='audit'?'active':'' ?>"><i class="bi bi-shield-check"></i> Audit Logs</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <small class="text-white-50"><?= htmlspecialchars($_SESSION['name'] ?? '') ?> (<?= $_SESSION['role'] ?? '' ?>)</small><br>
    <a href="/time/logout.php" class="btn btn-sm btn-outline-light mt-1 w-100">Logout</a>
  </div>
</div>
