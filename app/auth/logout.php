<?php
session_start();
session_unset();
session_destroy();

// Delete Remember Me cookies
setcookie('staff_id', '', time() - 3600, "/");
setcookie('role', '', time() - 3600, "/");

header("Location: login.php");
exit;
?>
