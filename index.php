<?php
require __DIR__ . '/config.php';
if (current_user()) {
    redirect_to_role_dashboard();
}
header('Location: /login.php');
exit;