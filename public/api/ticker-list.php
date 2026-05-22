<?php require '_bootstrap.php';
$conn->query("CREATE TABLE IF NOT EXISTS tracs_ticker_messages (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  text VARCHAR(500) NOT NULL, class ENUM('normal','info','urgent','critical') DEFAULT 'normal',
  enabled TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT NOW(), INDEX(user_id))");
$stmt = $conn->prepare("SELECT * FROM tracs_ticker_messages WHERE user_id=? AND enabled=1 ORDER BY created_at DESC");
if (!$stmt) fail('Database error', 500);
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
$rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
$stmt->close();
ok($rows);
