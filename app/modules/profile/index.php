<?php

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/config.php';

$farm_id = farm_id();
$staff_id = $_SESSION['staff_id'] ?? 0;

$page_title = "My Profile";

$message = '';
$alert = 'success';

/**
 * SAFETY CHECK
 */
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../../'));
}

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * LOAD USER
 */
$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.full_name,
        s.username,
        s.role,
        s.status,
        s.active,
        s.approval_status,
        s.last_login,
        s.created_at,
        s.profile_image,
        f.name AS farm_name
    FROM staff s
    LEFT JOIN farms f ON f.id = s.farm_id
    WHERE s.id = ?
");

$stmt->execute([$staff_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

/**
 * UPLOAD DIRECTORY
 */
$uploadDir = APP_ROOT . '/uploads/profile/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/**
 * HANDLE POST
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    /**
     * PROFILE IMAGE
    */
    
        if (!empty($_FILES['profile_image']['name'])) {

            $file = $_FILES['profile_image'];

            $allowed = ['image/jpeg', 'image/png', 'image/webp'];

            if (in_array($file['type'], $allowed) && $file['size'] <= 2 * 1024 * 1024) {

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

                $newName = 'profile_' . $staff_id . '_' . time() . '.' . $ext;

                $uploadPath = $uploadDir . $newName;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {

                    if (!empty($user['profile_image'])) {
                        $oldFile = $uploadDir . $user['profile_image'];
                        if (file_exists($oldFile)) unlink($oldFile);
                    }

                    $pdo->prepare("UPDATE staff SET profile_image = ? WHERE id = ?")
                        ->execute([$newName, $staff_id]);

                    $user['profile_image'] = $newName;

                    $message = "Profile image updated.";
                    $alert = "success";
                }

            } else {
                $message = "Invalid image or file too large.";
                $alert = "danger";
            }
        } 
    
  /**
     * PASSWORD UPDATE
    if (!empty($_FILES['profile_image']['name'])) {

    $file = $_FILES['profile_image'];

    echo "<pre>";
    var_dump($file);
    echo "</pre>";

    $uploadDir = APP_ROOT . '/uploads/profile/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newName = 'profile_' . $staff_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);

    $uploadPath = $uploadDir . $newName;

    echo "UPLOAD PATH: " . $uploadPath;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo "<br>UPLOAD SUCCESS";
    } else {
        echo "<br>UPLOAD FAILED";
        print_r(error_get_last());
    }

    exit;
    }  
*/

    /**
     * PASSWORD UPDATE
     */
    if (!empty($_POST['current_password'])) {

        $stmt = $pdo->prepare("SELECT password FROM staff WHERE id = ?");
        $stmt->execute([$staff_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($_POST['current_password'], $row['password'])) {

            $message = "Current password incorrect.";
            $alert = "danger";

        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {

            $message = "Passwords do not match.";
            $alert = "danger";

        } else {

            $hashed = password_hash($_POST['new_password'], PASSWORD_BCRYPT);

            $pdo->prepare("UPDATE staff SET password = ? WHERE id = ?")
                ->execute([$hashed, $staff_id]);

            $message = "Password updated.";
            $alert = "success";
        }
    }
}
$stmt = $pdo->prepare("
    SELECT profile_image
    FROM staff
    WHERE id = ?
");

$stmt->execute([$staff_id]);
$user['profile_image'] = $stmt->fetchColumn();
/**
 * PROFILE IMAGE DISPLAY
 */
$profileImage = !empty($user['profile_image'])
    ? '/app/uploads/profile/' . $user['profile_image']
    : '/assets/default-avatar.png';

/**
 * LAYOUT INCLUDE (IMPORTANT)
 */
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<style>
.profile-card{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 30px rgba(0,0,0,.06);
}

.avatar{
    width:140px;
    height:140px;
    border-radius:50%;
    object-fit:cover;
    border:3px solid #ddd;
}
</style>

<div class="row">

    <!-- LEFT PANEL -->
    <div class="col-md-5">

        <div class="card profile-card">
            <div class="card-body text-center">

                <img id="avatarPreview" src="<?= $profileImage ?>" class="avatar mb-3" alt="Profile Image">
                

                <h5><?= htmlspecialchars($user['full_name']) ?></h5>
                <p class="text-muted"><?= ucfirst($user['role']) ?></p>

                <hr>

                <p><b>Farm:</b> <?= htmlspecialchars($user['farm_name'] ?? 'N/A') ?></p>
                <p><b>Status:</b> <?= ucfirst($user['status']) ?></p>

            </div>
        </div>

    </div>

    <!-- RIGHT PANEL -->
    <div class="col-md-7">

        <div class="card profile-card">
            <div class="card-body">

                <h5>Update Profile</h5>

                <?php if($message): ?>
                    <div class="alert alert-<?= $alert ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="mb-3">
                        <label>Profile Image</label>
                        <input type="file"
                               name="profile_image"
                               class="form-control"
                               accept="image/*"
                               onchange="previewAvatar(event)">
                    </div>

                    <hr>

                    <h6>Change Password</h6>

                    <div class="mb-3">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control">
                    </div>

                    <button class="btn btn-primary w-100">
                        Update Profile
                    </button>

                </form>

            </div>
        </div>

    </div>

</div>

<script>
function previewAvatar(event) {
    const reader = new FileReader();
    reader.onload = function () {
        document.getElementById('avatarPreview').src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>