<?php
?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Deployment Check</title></head>
<body style="font-family:Arial,sans-serif;background:#f8fafc;color:#111;padding:24px;">
  <h1>Deployment Check</h1>
  <p>This file is reachable from the local web server.</p>
  <ul>
    <li><a href="check_tables.php">Check database tables</a></li>
    <li><a href="tutorial.php">Open tutorial</a></li>
    <li><a href="student_dashboard.php">Student dashboard</a></li>
  </ul>
  <p>PHP version: <?php echo phpversion(); ?></p>
  <p>Server software: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'; ?></p>
</body>
</html>
