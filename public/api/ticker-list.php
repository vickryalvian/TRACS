<?php require '_bootstrap.php';
$conn->query("CREATE TABLE IF NOT EXISTS tracs_ticker_messages (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  text VARCHAR(500) NOT NULL, class ENUM('normal','info','urgent','critical') DEFAULT 'normal',
  enabled TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT NOW(), INDEX(user_id))");
// Ticker announcements are a shared public feed: every user must see the
// same rows in the same order, so this is intentionally not scoped by user_id.
$res = $conn->query("SELECT * FROM tracs_ticker_messages WHERE enabled=1 ORDER BY created_at ASC, id ASC");
if (!$res) fail('Database error', 500);
$rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
ok($rows);
