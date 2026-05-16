<?php require '_bootstrap.php';
$text=trim($body['text']??''); if(!$text) fail('Text required');
$cls=in_array($body['class']??'',['normal','info','urgent','critical'])?$body['class']:'normal';
// Store in tracs_ticker_messages (create if not exists)
$conn->query("CREATE TABLE IF NOT EXISTS tracs_ticker_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  text VARCHAR(500) NOT NULL,
  class ENUM('normal','info','urgent','critical') DEFAULT 'normal',
  enabled TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT NOW(),
  INDEX(user_id)
)");
tracs_ensure_creator_columns($conn, 'tracs_ticker_messages', 'user_id');
$stmt=$conn->prepare("INSERT INTO tracs_ticker_messages (user_id,text,class,created_by,created_by_name) VALUES (?,?,?,?,?)");
$stmt->bind_param('issis',$uid,$text,$cls,$uid,$creator_name);
if(!$stmt->execute()) fail('Database error');
$id=$stmt->insert_id; $stmt->close();
ok(['id'=>$id],'Message added');
