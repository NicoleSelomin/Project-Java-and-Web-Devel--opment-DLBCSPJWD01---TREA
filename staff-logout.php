<?php
/**
 * ----------------------------------------------------------------------
 * staff-logout.php
 * ----------------------------------------------------------------------
 * Destroys the staff session and redirects to the staff login page.
 * ----------------------------------------------------------------------
 */

session_start();
session_destroy();
header("Location: staff-login.php");
exit();
