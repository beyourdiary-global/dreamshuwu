<?php
// File: debug-audit-db.php
// Purpose: Show ALL raw database values to verify if data is being saved.

require_once 'init.php';

echo "<h1>Audit Log - Raw Database Dump (ALL RECORDS)</h1>";
echo "<style>table { border-collapse: collapse; width: 100%; font-family: sans-serif; } th, td { border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; font-size: 13px; } pre { margin: 0; white-space: pre-wrap; background: #f4f4f4; padding: 5px; border: 1px solid #ddd; }</style>";

$sql = "SELECT id, page, action, action_message, old_value, new_value, created_at FROM " . AUDIT_LOG . " ORDER BY id DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table>";
    echo "<thead style='background: #eee;'><tr><th style='width:50px;'>ID</th><th style='width:50px;'>Action</th><th>Message</th><th>Old Value (Raw)</th><th>New Value (Raw)</th><th style='width:150px;'>Date</th></tr></thead>";
    echo "<tbody>";
    while($row = $result->fetch_assoc()) {
        $bg = $row['action'] == 'D' ? '#fff0f0' : ($row['action'] == 'A' ? '#f0fff0' : '#fff');
        
        echo "<tr style='background: $bg;'>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['action'] . "</td>";
        echo "<td>" . $row['action_message'] . "</td>";
        
        // DUMP OLD VALUE
        echo "<td>";
        if ($row['old_value'] === null) {
            echo "<span style='color:red; font-weight:bold;'>NULL</span>";
        } else {
            // Check if it's JSON or String
            $isJson = is_string($row['old_value']) && (str_starts_with(trim($row['old_value']), '{') || str_starts_with(trim($row['old_value']), '['));
            echo $isJson ? "<strong>JSON:</strong>" : "<strong>String:</strong>";
            echo "<pre>" . htmlspecialchars($row['old_value']) . "</pre>";
        }
        echo "</td>";

        // DUMP NEW VALUE
        echo "<td>";
        if ($row['new_value'] === null) {
            echo "<span style='color:red; font-weight:bold;'>NULL</span>";
        } else {
             // Check if it's JSON or String
            $isJson = is_string($row['new_value']) && (str_starts_with(trim($row['new_value']), '{') || str_starts_with(trim($row['new_value']), '['));
            echo $isJson ? "<strong>JSON:</strong>" : "<strong>String:</strong>";
            echo "<pre>" . htmlspecialchars($row['new_value']) . "</pre>";
        }
        echo "</td>";
        
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "No audit logs found in database.";
}
?>