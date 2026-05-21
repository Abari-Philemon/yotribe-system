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
 * APP ROOT
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
        s.email,
        s.phone,
        s.email_verified,
        s.phone_verified,
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
 * ROLE PERMISSIONS
 */
$role = $user['role'];

$can_edit_email = in_array($role, ['super_admin','owner','manager','production']);
$can_edit_phone = in_array($role, ['super_admin','owner','manager','production']);
$can_edit_image = in_array($role, ['super_admin','owner','manager','production','storekeeper','hatchery']);
$can_edit_password = true;

/**
 * UPLOAD DIR
 */
$uploadDir = APP_ROOT . '/uploads/profile/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/**
 * =========================
 * HANDLE POST
 * =========================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    /**
     * =========================
     * PROFILE IMAGE
     * =========================
     */
    if (!empty($_FILES['profile_image']['name'])) {

        if ($can_edit_image) {

            $file = $_FILES['profile_image'];
            $allowed = ['image/jpeg','image/png','image/webp'];

            if ($file['error'] === 0 && in_array($file['type'], $allowed) && $file['size'] <= 2 * 1024 * 1024) {

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = 'profile_' . $staff_id . '_' . time() . '.' . $ext;

                $path = $uploadDir . $newName;

                if (move_uploaded_file($file['tmp_name'], $path)) {

                    if (!empty($user['profile_image'])) {
                        $old = $uploadDir . $user['profile_image'];
                        if (file_exists($old)) unlink($old);
                    }

                    $pdo->prepare("UPDATE staff SET profile_image=? WHERE id=?")
                        ->execute([$newName, $staff_id]);

                    $user['profile_image'] = $newName;

                    $message = "Profile image updated.";
                    $alert = "success";
                }

            } else {
                $message = "Invalid image.";
                $alert = "danger";
            }

        } else {
            $message = "Not allowed to change image.";
            $alert = "danger";
        }
    }

    /**
     * =========================
     * EMAIL + PHONE UPDATE
     * =========================
     */
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!empty($email) || !empty($phone)) {

        /**
         * EMAIL
         */
        if (!empty($email) && $email !== $user['email'] && $can_edit_email) {

            $check = $pdo->prepare("
                SELECT id FROM staff
                WHERE email=? AND farm_id=? AND id!=?
            ");
            $check->execute([$email, $farm_id, $staff_id]);

            if ($check->fetch()) {
                $message = "Email already exists.";
                $alert = "danger";
            } else {

                $pdo->prepare("UPDATE staff SET email=?, email_verified=0 WHERE id=?")
                    ->execute([$email, $staff_id]);

                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 day'));

                $pdo->prepare("
                    INSERT INTO staff_email_verifications (staff_id, token, expires_at)
                    VALUES (?, ?, ?)
                ")->execute([$staff_id, $token, $expires]);

                $link = "http://localhost/yotribe-system/app/auth/verify_email.php?token=$token";
                @mail($email, "Verify Email", "Click: $link");

                $message = "Email updated. Verification sent.";
                $alert = "success";
            }
        }

        /**
         * PHONE
         */
        if (!empty($phone) && $phone !== $user['phone'] && $can_edit_phone) {

            $check = $pdo->prepare("
                SELECT id FROM staff
                WHERE phone=? AND farm_id=? AND id!=?
            ");
            $check->execute([$phone, $farm_id, $staff_id]);

            if ($check->fetch()) {
                $message = "Phone already exists.";
                $alert = "danger";
            } else {

                $pdo->prepare("UPDATE staff SET phone=?, phone_verified=0 WHERE id=?")
                    ->execute([$phone, $staff_id]);

                $otp = rand(100000, 999999);
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                $pdo->prepare("
                    INSERT INTO staff_phone_otps (staff_id, phone, otp, expires_at)
                    VALUES (?, ?, ?, ?)
                ")->execute([$staff_id, $phone, $otp, $expires]);

                // sendSMS($phone, "OTP: $otp");

                $message = "Phone updated. OTP sent.";
                $alert = "success";
            }
        }
    }

    /**
     * =========================
     * OTP VERIFY
     * =========================
     */
    if (!empty($_POST['otp'])) {

        $otp = trim($_POST['otp']);

        $stmt = $pdo->prepare("
            SELECT * FROM staff_phone_otps
            WHERE staff_id=? AND otp=? AND verified=0 AND expires_at>NOW()
        ");
        $stmt->execute([$staff_id, $otp]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {

            $pdo->prepare("UPDATE staff SET phone_verified=1 WHERE id=?")
                ->execute([$staff_id]);

            $pdo->prepare("UPDATE staff_phone_otps SET verified=1 WHERE id=?")
                ->execute([$row['id']]);

            $message = "Phone verified.";
            $alert = "success";

        } else {
            $message = "Invalid OTP.";
            $alert = "danger";
        }
    }

    /**
     * =========================
     * PASSWORD
     * =========================
     */
    if (!empty($_POST['current_password'])) {

        $stmt = $pdo->prepare("SELECT password FROM staff WHERE id=?");
        $stmt->execute([$staff_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($_POST['current_password'], $row['password'])) {

            $message = "Wrong password.";
            $alert = "danger";

        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {

            $message = "Password mismatch.";
            $alert = "danger";

        } else {

            $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);

            $pdo->prepare("UPDATE staff SET password=? WHERE id=?")
                ->execute([$hash, $staff_id]);

            $message = "Password updated.";
            $alert = "success";
        }
    }
}

/**
 * PROFILE IMAGE
 */
$profileImage = !empty($user['profile_image'])
    ? '/uploads/profile/' . $user['profile_image']
    : '/assets/default-avatar.png';

/**
 * LAYOUT
 */
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<style>
.profile-card{border:none;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.06);}
.avatar{width:140px;height:140px;border-radius:50%;object-fit:cover;border:3px solid #ddd;}
</style>

<div class="row">

<div class="col-md-5">
<div class="card profile-card">
<div class="card-body text-center">

<img id="avatarPreview" src="<?= $profileImage ?>" class="avatar mb-3">

<h5><?= htmlspecialchars($user['full_name']) ?></h5>
<p class="text-muted"><?= ucfirst($user['role']) ?></p>

<hr>

<p>Email:
<?= htmlspecialchars($user['email']) ?>
<?= $user['email_verified'] ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning">Unverified</span>' ?>
</p>

<p>Phone:
<?= htmlspecialchars($user['phone']) ?>
<?= $user['phone_verified'] ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning">Unverified</span>' ?>
</p>

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
<div class="alert alert-<?= $alert ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<input type="file" name="profile_image" class="form-control mb-3" onchange="previewAvatar(event)">

<input type="email" name="email" class="form-control mb-3" value="<?= htmlspecialchars($user['email']) ?>">

<input type="text" name="phone" class="form-control mb-3" value="<?= htmlspecialchars($user['phone']) ?>">

<input type="text" name="otp" class="form-control mb-3" placeholder="Enter OTP if received">

<hr>

<input type="password" name="current_password" class="form-control mb-2" placeholder="Current password">
<input type="password" name="new_password" class="form-control mb-2" placeholder="New password">
<input type="password" name="confirm_password" class="form-control mb-3" placeholder="Confirm password">

<button class="btn btn-primary w-100">Update Profile</button>

</form>

</div>
</div>
</div>

</div>

<script>
function previewAvatar(e){
document.getElementById('avatarPreview').src = URL.createObjectURL(e.target.files[0]);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>