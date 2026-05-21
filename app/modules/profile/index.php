<?php

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';

$farm_id = farm_id();
$staff_id = $_SESSION['staff_id'] ?? 0;

$page_title = "My Profile";

$message = '';
$alert = 'success';

/**
 * =========================================================
 * CSRF TOKEN
 * =========================================================
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * =========================================================
 * LOAD USER PROFILE
 * =========================================================
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
 * =========================================================
 * HANDLE UPDATES
 * =========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']
    ) {
        die("Invalid CSRF token");
    }

    /**
     * =====================================================
     * PROFILE IMAGE UPLOAD (FIXED)
     * =====================================================
     */
    if (!empty($_FILES['profile_image']['name'])) {

        $file = $_FILES['profile_image'];

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($file['type'], $allowedTypes)) {

            $message = "Only JPG, PNG, WEBP allowed.";
            $alert = "danger";

        } elseif ($file['size'] > 2 * 1024 * 1024) {

            $message = "Image must not exceed 2MB.";
            $alert = "danger";

        } else {

            /**
             * =================================================
             * FIXED DIRECTORY HANDLING (MAIN FIX)
             * =================================================
             */
            $uploadDir = __DIR__ . '/../../uploads/profile/';

            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = 'profile_' . $staff_id . '_' . time() . '.' . $ext;

            $uploadPath = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {

                /**
                 * DELETE OLD IMAGE
                 */
                if (!empty($user['profile_image'])) {

                    $oldFile = $uploadDir . $user['profile_image'];

                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $stmt = $pdo->prepare("
                    UPDATE staff
                    SET profile_image = ?
                    WHERE id = ?
                ");

                $stmt->execute([$newName, $staff_id]);

                $message = "Profile image updated successfully.";
                $alert = "success";
            }
        }
    }

    /**
     * =====================================================
     * PASSWORD UPDATE
     * =====================================================
     */
    if (!empty($_POST['current_password'])) {

        $current = $_POST['current_password'];
        $new     = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if ($new !== $confirm) {

            $message = "Passwords do not match.";
            $alert = "danger";

        } else {

            $stmt = $pdo->prepare("SELECT password FROM staff WHERE id = ?");
            $stmt->execute([$staff_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!password_verify($current, $row['password'])) {

                $message = "Current password is incorrect.";
                $alert = "danger";

            } else {

                $hashed = password_hash($new, PASSWORD_BCRYPT);

                $stmt = $pdo->prepare("
                    UPDATE staff SET password = ? WHERE id = ?
                ");

                $stmt->execute([$hashed, $staff_id]);

                $message = "Password updated successfully.";
                $alert = "success";
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

/**
 * PROFILE IMAGE PATH
 */
$profileImage = !empty($user['profile_image'])
    ? '/uploads/profile/' . $user['profile_image']
    : '/assets/default-avatar.png';

?>

<style>
.profile-card{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 30px rgba(0,0,0,.06);
}

.avatar{
    width:120px;
    height:120px;
    border-radius:50%;
    object-fit:cover;
    border:3px solid #ddd;
}
</style>

<div class="row">

    <div class="col-md-5">

        <div class="card profile-card">
            <div class="card-body text-center">

                <img src="<?= $profileImage ?>" class="avatar mb-3">

                <h5><?= htmlspecialchars($user['full_name']) ?></h5>
                <p class="text-muted"><?= ucfirst($user['role']) ?></p>

                <hr>

                <p><b>Farm:</b> <?= htmlspecialchars($user['farm_name'] ?? 'N/A') ?></p>
                <p><b>Status:</b> <?= ucfirst($user['status']) ?></p>

            </div>
        </div>

    </div>

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
                        <input type="file" name="profile_image" class="form-control">
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>