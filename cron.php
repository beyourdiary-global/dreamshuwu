<?php
// Path: /cron.php
// Description: Background cron job to publish scheduled chapters.
// Recommended Cron Setup: * * * * * php /path/to/your/project/cron.php

// 1. Initialize System
require_once __DIR__ . '/common.php';

// Security: Prevent unauthorized execution via web browser. 
// Comment this out if you want to test it by visiting http://localhost:8000/cron.php
if (php_sapi_name() !== 'cli' && empty($_GET['test_run'])) {
    die("Forbidden: This script can only be run from the command line or with a test_run parameter.");
}

echo "Starting Scheduled Publish Cron Job...\n";

// 2. Fetch all active sensitive words into memory for the scan
$sensitiveWords = [];
$swSql = "SELECT word, replacement, severity_level FROM " . SENSITIVE_WORD . " WHERE status = 'A'";
if ($result = $conn->query($swSql)) {
    while ($row = $result->fetch_assoc()) {
        $sensitiveWords[] = $row;
    }
    $result->free();
}

// 3. Find all chapters ready to be published
// Conditions: publish_status is 'scheduled', time is past or present, and record is active
$sql = "SELECT id, novel_id, author_id, title, content 
        FROM " . CHAPTER . " 
        WHERE publish_status = 'scheduled' 
        AND scheduled_publish_at <= NOW() 
        AND status = 'A'";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "No scheduled chapters to publish at this time.\n";
    exit();
}

$chaptersToProcess = [];
$uniqueAuthorIds = [];

// Collect chapters and unique author IDs
while ($row = $result->fetch_assoc()) {
    $chaptersToProcess[] = $row;
    $uniqueAuthorIds[(int)$row['author_id']] = true;
}
$result->free();

// 4. Fetch Author Details Separately (Batch Query for Performance)
$authorsMap = [];
if (!empty($uniqueAuthorIds)) {
    // Sanitize IDs just in case
    $safeIds = implode(',', array_map('intval', array_keys($uniqueAuthorIds)));
    
    $userSql = "SELECT id, email, name FROM " . USR_LOGIN . " WHERE id IN ($safeIds)";
    if ($uResult = $conn->query($userSql)) {
        while ($uRow = $uResult->fetch_assoc()) {
            $authorsMap[(int)$uRow['id']] = [
                'email' => $uRow['email'],
                'name'  => $uRow['name']
            ];
        }
        $uResult->free();
    }
}

$processedCount = 0;
$failedCount = 0;

// 5. Process each scheduled chapter using the mapped data
foreach ($chaptersToProcess as $chapter) {
    $chapterId = (int)$chapter['id'];
    $authorId = (int)$chapter['author_id'];
    $content = $chapter['content'];
    $title = $chapter['title'];
    
    // Retrieve author details from the mapped array
    $authorEmail = $authorsMap[$authorId]['email'] ?? '';
    $authorName = $authorsMap[$authorId]['name'] ?? 'Unknown Author';
    
    $isBlocked = false;
    $hasModifications = false;

    // A. Re-scan content against sensitive words
    foreach ($sensitiveWords as $rule) {
        $word = $rule['word'];
        
        // Case-insensitive search
        if (mb_stripos($content, $word) !== false) {
            
            // Log the violation
            $logSql = "INSERT INTO " . SENSITIVE_WORD_LOG . " (author_id, chapter_id, detected_word, severity_level, created_at) VALUES (?, ?, ?, ?, NOW())";
            $lStmt = $conn->prepare($logSql);
            $lStmt->bind_param("iisi", $authorId, $chapterId, $word, $rule['severity_level']);
            $lStmt->execute();
            $lStmt->close();

            // Handle Severity Levels
            if ($rule['severity_level'] == 3) {
                $isBlocked = true;
                break; // Stop scanning, it's already blocked
            } else {
                // Level 1 & 2: Replace word
                $content = str_ireplace($word, $rule['replacement'], $content);
                $hasModifications = true;
            }
        }
    }

    $conn->begin_transaction();

    try {
        if ($isBlocked) {
            // B. Failed Validation: Revert to draft
            $updSql = "UPDATE " . CHAPTER . " SET publish_status = 'draft', scheduled_publish_at = NULL, updated_at = NOW() WHERE id = ?";
            $updStmt = $conn->prepare($updSql);
            $updStmt->bind_param("i", $chapterId);
            $updStmt->execute();
            $updStmt->close();

            // Notify Author
            if (isValidEmail($authorEmail)) {
                $subject = "【系统通知】您的定时发布章节被拦截";
                $message = "尊敬的作者 {$authorName}，\n\n您定时发布的章节《{$title}》由于包含严重违规词汇（Level 3），已被系统自动拦截并退回草稿箱。\n\n请前往作者专区修改后重新发布。";
                $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">";
                @mail($authorEmail, $subject, $message, $headers);
            }

            echo "Chapter ID {$chapterId} BLOCKED (Level 3 violation). Reverted to draft.\n";
            $failedCount++;

        } else {
            // C. Passed Validation: Publish the chapter
            // Update the main chapter record
            $updSql = "UPDATE " . CHAPTER . " SET content = ?, publish_status = 'published', updated_at = NOW() WHERE id = ?";
            $updStmt = $conn->prepare($updSql);
            $updStmt->bind_param("si", $content, $chapterId);
            $updStmt->execute();
            $updStmt->close();

            // Word Count (In case replacements changed length slightly)
            $clean = strip_tags(preg_replace('/[a-zA-Z]+:\/\/[^\s]+/', '', $content));
            $wCount = mb_strlen(preg_replace('/\s+/', '', $clean));

            // Create a new version history record for the Published state
            $vSql = "SELECT COALESCE(MAX(version_number), 0) + 1 FROM " . CHAPTER_VERSION . " WHERE chapter_id = ?";
            $stmt = $conn->prepare($vSql);
            $stmt->bind_param("i", $chapterId);
            $stmt->execute();
            $stmt->bind_result($nextV);
            $stmt->fetch();
            $stmt->close();

            $iSql = "INSERT INTO " . CHAPTER_VERSION . " (chapter_id, version_number, title, content, word_count, created_by) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($iSql);
            $stmt->bind_param("iissii", $chapterId, $nextV, $title, $content, $wCount, $authorId);
            $stmt->execute();
            $stmt->close();

            // Audit Log
            if (function_exists('logAudit')) {
                logAudit([
                    'page'           => 'System Cron',
                    'action'         => 'E',
                    'action_message' => 'System automatically published scheduled chapter',
                    'user_id'        => 0, // 0 represents System
                    'record_id'      => $chapterId,
                    'record_name'    => $title
                ]);
            }

            echo "Chapter ID {$chapterId} PUBLISHED successfully.\n";
            $processedCount++;
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "Error processing Chapter ID {$chapterId}: " . $e->getMessage() . "\n";
    }
}

echo "Cron Job Complete. Published: {$processedCount} | Failed/Blocked: {$failedCount}\n";
?>