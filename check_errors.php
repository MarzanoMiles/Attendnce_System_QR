<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h3>Testing students/index.php includes...</h3>";
try {
    $db = getDB();
    $sections = getAllowedSections();
    echo "✅ getAllowedSections() works — " . count($sections) . " sections<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>Testing gradeLevelOrderSQL()...</h3>";
try {
    $sql = gradeLevelOrderSQL('sec.grade_level');
    echo "✅ gradeLevelOrderSQL() works: {$sql}<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>Testing attendanceTypeBadge()...</h3>";
try {
    echo attendanceTypeBadge('full_day') . "<br>";
    echo "✅ attendanceTypeBadge() works<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>Testing entryColor()...</h3>";
try {
    echo entryColor('holiday') . "<br>";
    echo "✅ entryColor() works<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>Testing canAccessSection()...</h3>";
try {
    startSession();
    $_SESSION['user_id'] = 1;
    $_SESSION['role']    = 'admin';
    $result = canAccessSection(1);
    echo "✅ canAccessSection() works: " . ($result ? 'true' : 'false') . "<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>DB Tables check...</h3>";
$tables = ['users','sections','students','attendance','school_calendar','system_settings','sms_logs'];
foreach ($tables as $t) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        echo "✅ {$t}: {$count} rows<br>";
    } catch (Exception $e) {
        echo "❌ {$t}: " . $e->getMessage() . "<br>";
    }
}
?>