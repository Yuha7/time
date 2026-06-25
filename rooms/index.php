<?php
require_once dirname(__DIR__) . '/config.php';
$page_title = 'Exam Rooms'; $active = 'rooms';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO exam_rooms (name,capacity,location) VALUES (?,?,?)")->execute([$_POST['name'], $_POST['capacity'], $_POST['location']]);
        log_action($pdo, current_user_id(), 'ADD_ROOM', $_POST['name']);
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE exam_rooms SET name=?,capacity=?,location=? WHERE id=?")->execute([$_POST['name'], $_POST['capacity'], $_POST['location'], $_POST['id']]);
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE exam_rooms SET is_active=1-is_active WHERE id=?")->execute([$_POST['id']]);
    }
    header('Location: index.php'); exit;
}

$rooms = $pdo->query("SELECT * FROM exam_rooms ORDER BY name")->fetchAll();
$total_cap = array_sum(array_column(array_filter($rooms, fn($r) => $r['is_active']), 'capacity'));
require_once dirname(__DIR__) . '/layout_header.php';
?>
<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="fw-bold mb-0">Exam Rooms</h5>
      <small class="text-muted">Total active capacity: <?= $total_cap ?> students</small>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> Add Room</button>
  </div>
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>#</th><th>Room Name</th><th>Capacity</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($rooms as $i=>$r): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= $r['capacity'] ?></td>
          <td><?= htmlspecialchars($r['location']) ?></td>
          <td><span class="badge <?= $r['is_active']?'bg-success':'bg-secondary' ?>"><?= $r['is_active']?'Active':'Inactive' ?></span></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick="editRoom(<?= htmlspecialchars(json_encode($r)) ?>)"><i class="bi bi-pencil"></i></button>
            <form method="POST" class="d-inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm <?= $r['is_active']?'btn-outline-warning':'btn-outline-success' ?>"><i class="bi bi-<?= $r['is_active']?'slash-circle':'check-circle' ?>"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add Exam Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body row g-3">
    <div class="col-md-6"><label class="form-label">Room Name</label><input type="text" name="name" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Capacity</label><input type="number" name="capacity" class="form-control" min="1" required></div>
    <div class="col-12"><label class="form-label">Location</label><input type="text" name="location" class="form-control"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Edit Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="e_id">
  <div class="modal-body row g-3">
    <div class="col-md-6"><label class="form-label">Room Name</label><input type="text" name="name" id="e_name" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Capacity</label><input type="number" name="capacity" id="e_cap" class="form-control" min="1" required></div>
    <div class="col-12"><label class="form-label">Location</label><input type="text" name="location" id="e_loc" class="form-control"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Update</button></div>
  </form>
</div></div></div>
<script>
function editRoom(r) {
  document.getElementById('e_id').value = r.id;
  document.getElementById('e_name').value = r.name;
  document.getElementById('e_cap').value = r.capacity;
  document.getElementById('e_loc').value = r.location || '';
  new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once dirname(__DIR__) . '/layout_footer.php'; ?>
