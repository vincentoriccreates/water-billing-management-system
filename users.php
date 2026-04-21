<?php
require_once 'includes/functions.php';
requireLogin();
if (!isAdmin()) { header('Location: dashboard.php'); exit; }
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: users.php'); exit; }

$pdo = getDB();

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $name   = post('name');
    $email  = post('email');
    $role   = post('role');
    $pass   = post('password');
    $id     = (int)post('id');

    if ($action === 'add') {
        if (!$name || !$email || !$pass) { setFlash('Fill all required fields.','error'); }
        else {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $dup->execute([$email]);
            if ($dup->fetch()) { setFlash('Email already in use.','error'); }
            else {
                $hash   = password_hash($pass, PASSWORD_DEFAULT);
                $avatar = strtoupper(substr($name, 0, 1));
                $stmt   = $pdo->prepare("INSERT INTO users (name,email,password,role,avatar) VALUES (?,?,?,?,?)");
                $stmt->execute([$name, $email, $hash, $role, $avatar]);
                setFlash('User added successfully!');
            }
        }
    } elseif ($action === 'edit') {
        if (!$name || !$email) { setFlash('Fill all required fields.','error'); }
        else {
            $avatar = strtoupper(substr($name, 0, 1));
            if ($pass) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET name=?,email=?,password=?,role=?,avatar=? WHERE id=?")->execute([$name,$email,$hash,$role,$avatar,$id]);
            } else {
                $pdo->prepare("UPDATE users SET name=?,email=?,role=?,avatar=? WHERE id=?")->execute([$name,$email,$role,$avatar,$id]);
            }
            // Update session if editing self
            if ($id == currentUser()['id']) {
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role']  = $role;
                $_SESSION['user_avatar']= $avatar;
            }
            setFlash('User updated!');
        }
    }
    header('Location: users.php');
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY id")->fetchAll();
$editUser = null;
if (get('edit')) {
    $s = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $s->execute([get('edit')]);
    $editUser = $s->fetch();
}

require_once 'includes/header.php';
renderHeader('User Accounts', 'users');
?>

<div class="toolbar">
  <div></div>
  <button class="btn btn-primary" onclick="document.getElementById('add-user-modal').style.display='flex'">+ Add User</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Avatar</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="width:36px;height:36px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800">
              <?= h($u['avatar']) ?>
            </div>
          </td>
          <td class="fw-bold">
            <?= h($u['name']) ?>
            <?php if ($u['id'] == currentUser()['id']): ?>
            <span style="font-size:10px;color:var(--success);margin-left:6px">(You)</span>
            <?php endif; ?>
          </td>
          <td class="fs-sm text-muted"><?= h($u['email']) ?></td>
          <td><span class="badge badge-<?= strtolower(h($u['role'])) ?>"><?= h($u['role']) ?></span></td>
          <td>
            <a href="users.php?edit=<?= $u['id'] ?>" class="btn btn-sm btn-info-soft">Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="add-user-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add User</span>
      <button class="modal-close" onclick="document.getElementById('add-user-modal').style.display='none'">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group"><label class="form-label">Full Name *</label><input name="name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Role</label>
        <select name="role" class="form-control"><option>Admin</option><option selected>Staff</option></select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('add-user-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Save User</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<?php if ($editUser): ?>
<div class="modal-overlay" id="edit-user-modal" style="display:flex" onclick="if(event.target===this)window.location='users.php'">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit User</span>
      <a href="users.php" class="modal-close">×</a>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
      <div class="form-group"><label class="form-label">Full Name *</label><input name="name" class="form-control" value="<?= h($editUser['name']) ?>" required></div>
      <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?= h($editUser['email']) ?>" required></div>
      <div class="form-group"><label class="form-label">New Password <span class="fs-xs text-muted">(leave blank to keep current)</span></label><input type="password" name="password" class="form-control"></div>
      <div class="form-group"><label class="form-label">Role</label>
        <select name="role" class="form-control">
          <option <?= $editUser['role']==='Admin'?'selected':'' ?>>Admin</option>
          <option <?= $editUser['role']==='Staff'?'selected':'' ?>>Staff</option>
        </select>
      </div>
      <div class="modal-footer">
        <a href="users.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Update User</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
