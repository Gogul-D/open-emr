<?php
require_once('library/sql.inc.php');
require_once('library/sqlconf.php');

// Query medications for PID 1
$sql = "SELECT id, title, activity, outcome, enddate, begdate FROM lists WHERE pid = 1 AND type = 'medication'";
$result = sqlStatement($sql);
echo "Medications for PID 1:\n";
while ($row = sqlFetchArray($result)) {
    echo "ID: {$row['id']}, Title: {$row['title']}, Activity: {$row['activity']}, Outcome: {$row['outcome']}, Enddate: {$row['enddate']}, Begdate: {$row['begdate']}\n";
}
?>