<?php
// Path: src/pages/author/author-verification/api.php

try {
    require_once dirname(__DIR__, 4) . '/common.php';
    
    // Clear any stray output from common files
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // Define Table Constants Safely
    $authorProfileTable = defined('AUTHOR_PROFILE') ? AUTHOR_PROFILE : 'author_profile';
    $usersTable = defined('USR_LOGIN') ? USR_LOGIN : 'users';

    // Session & Authentication Check
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        throw new Exception('会话已过期，请重新登录。');
    }

    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0);
    $currentUrl = '/author/author-verification.php';
    
    $perm = hasPagePermission($conn, $currentUrl);
    if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
        $legacyPath = defined('PATH_AUTHOR_VERIFICATION_INDEX') ? ('/' . ltrim(PATH_AUTHOR_VERIFICATION_INDEX, '/')) : '/src/pages/author/author-verification/index.php';
        $perm = hasPagePermission($conn, $legacyPath);
    }
    
    $auditPage = 'Author Verification Management';

    $mode = strtolower(trim((string)($_REQUEST['mode'] ?? 'data')));

    // Helper Function
    if (!function_exists('authorVerificationFetchRow')) {
        function authorVerificationFetchRow($conn, $authorId, $authorProfileTable, $usersTable) {
            $safeId = (int)$authorId;
            $hasRejectReason = function_exists('columnExists') ? columnExists($conn, $authorProfileTable, 'reject_reason') : false;
            $hasEmailNotifiedAt = function_exists('columnExists') ? columnExists($conn, $authorProfileTable, 'email_notified_at') : false;
            $hasEmailNotifyCount = function_exists('columnExists') ? columnExists($conn, $authorProfileTable, 'email_notify_count') : false;

            // Removed 'ap.' prefixes since we are no longer using a JOIN
            $rejectExpr = $hasRejectReason ? 'reject_reason' : "'' AS reject_reason";
            $emailNotifiedExpr = $hasEmailNotifiedAt ? 'email_notified_at' : "NULL AS email_notified_at";
            $emailNotifyCountExpr = $hasEmailNotifyCount ? 'email_notify_count' : "0 AS email_notify_count";

            // 1. First Query: Fetch Author Profile Data
            $sql = "SELECT id, user_id, real_name, pen_name, contact_email, verification_status, {$rejectExpr}, {$emailNotifiedExpr}, {$emailNotifyCountExpr}, updated_at, status "
                 . "FROM {$authorProfileTable} "
                 . "WHERE id = ? LIMIT 1"; 
                 
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                return null;
            }
            $stmt->bind_param('i', $safeId);
            if (!$stmt->execute()) {
                $stmt->close();
                return null;
            }
            $stmt->store_result();

            $r_id = $r_uid = $r_realName = $r_penName = $r_email = $r_verificationStatus = $r_rejectReason = $r_emailNotifiedAt = $r_emailNotifyCount = $r_updatedAt = $r_status = null;
            $stmt->bind_result(
                $r_id, $r_uid, $r_realName, $r_penName, $r_email, 
                $r_verificationStatus, $r_rejectReason, $r_emailNotifiedAt, 
                $r_emailNotifyCount, $r_updatedAt, $r_status
            );

            if ($stmt->fetch()) {
                $row = [
                    'id' => (int)$r_id,
                    'user_id' => (int)$r_uid,
                    'real_name' => (string)($r_realName ?? ''),
                    'pen_name' => (string)($r_penName ?? ''),
                    'contact_email' => (string)($r_email ?? ''),
                    'verification_status' => (string)($r_verificationStatus ?? ''),
                    'reject_reason' => (string)($r_rejectReason ?? ''),
                    'email_notified_at' => (string)($r_emailNotifiedAt ?? ''),
                    'email_notify_count' => (int)($r_emailNotifyCount ?? 0),
                    'updated_at' => (string)($r_updatedAt ?? ''),
                    'status' => (string)($r_status ?? ''),
                    'user_name' => '' // Default to empty, will populate below
                ];
                $stmt->close();

                // 2. Second Query: Fetch User Name Separately
                if ($row['user_id'] > 0) {
                    $uSql = "SELECT name FROM {$usersTable} WHERE id = ? LIMIT 1";
                    $uStmt = $conn->prepare($uSql);
                    if ($uStmt) {
                        $uStmt->bind_param('i', $row['user_id']);
                        $uStmt->execute();
                        $uName = null;
                        $uStmt->bind_result($uName);
                        if ($uStmt->fetch()) {
                            $row['user_name'] = (string)($uName ?? '');
                        }
                        $uStmt->close();
                    }
                }

                return $row;
            }
            $stmt->close();

            return null;
        }
    }

    // =========================================================================
    // CSRF Protection (Required for state-changing operations)
    // =========================================================================
    if (in_array($mode, ['verify', 'delete'])) {
        // 1. Prioritize HTTP Header (standard for AJAX)
        $clientToken = '';
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            // Check for case-insensitive header variations
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'x-csrf-token') {
                    $clientToken = $value;
                    break;
                }
            }
        }
        
        // 2. Fallback to POST body if header is completely missing
        if (empty($clientToken) && !empty($_POST['csrf_token'])) {
            $clientToken = $_POST['csrf_token'];
        }

        // 3. Validate
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$clientToken)) {
            http_response_code(403);
            throw new Exception('安全校验失败：非法的请求 (Invalid CSRF Token)');
        }
    }

    // =========================================================================
    // MODE: DATA (DataTables) 
    // =========================================================================
    if ($mode === 'data') {
        $viewError = checkPermissionError('view', $perm, '作者审核管理', false);
        if ($viewError) throw new Exception($viewError); 

        $draw = (int)($_REQUEST['draw'] ?? 1);
        $start = max(0, (int)($_REQUEST['start'] ?? 0));
        $length = max(1, min(100, (int)($_REQUEST['length'] ?? 10)));

        $statusFilterRaw = trim((string)($_REQUEST['status_filter'] ?? 'pending,rejected'));
        $searchData = $_REQUEST['search'] ?? [];
        $searchValue = trim((string)($searchData['value'] ?? ''));

        // Removed 'ap.' and 'u.' aliases for the separated query approach
        $baseWhere = " WHERE status = 'A' ";
        
        // 1. Build Status Filter Safely
        if ($statusFilterRaw !== 'all' && $statusFilterRaw !== '') {
            $parts = explode(',', $statusFilterRaw);
            $validStatuses = [];
            foreach ($parts as $p) {
                $p = strtolower(trim($p));
                if (in_array($p, ['pending', 'approved', 'rejected'])) {
                    $validStatuses[] = "'" . $conn->real_escape_string($p) . "'";
                }
            }
            if (!empty($validStatuses)) {
                $baseWhere .= " AND verification_status IN (" . implode(',', $validStatuses) . ") ";
            }
        }

        // 2. Build Search Filter Safely (Using Subquery instead of JOIN)
        $searchWhere = '';
        if ($searchValue !== '') {
            $escSearch = $conn->real_escape_string($searchValue);
            $searchWhere = " AND (real_name LIKE '%{$escSearch}%' OR pen_name LIKE '%{$escSearch}%' OR contact_email LIKE '%{$escSearch}%' OR user_id IN (SELECT id FROM {$usersTable} WHERE name LIKE '%{$escSearch}%')) ";
        }

        // 3. Execute Total Count Query (No JOIN needed)
        $countTotalSql = "SELECT COUNT(id) AS total FROM {$authorProfileTable}" . $baseWhere;
        $resTotal = $conn->query($countTotalSql);
        if (!$resTotal) throw new Exception('Total count failed: ' . $conn->error);
        $recordsTotal = (int)($resTotal->fetch_assoc()['total'] ?? 0);
        $resTotal->free();

        // 4. Execute Filtered Count Query (No JOIN needed)
        $countFilteredSql = "SELECT COUNT(id) AS total FROM {$authorProfileTable}" . $baseWhere . $searchWhere;
        $resFiltered = $conn->query($countFilteredSql);
        if (!$resFiltered) throw new Exception('Filter count failed: ' . $conn->error);
        $recordsFiltered = (int)($resFiltered->fetch_assoc()['total'] ?? 0);
        $resFiltered->free();

        // 5. Execute Data Query (Prepared Statement for LIMIT, No JOIN)
        $hasReject = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'reject_reason') ? 'reject_reason' : "'' AS reject_reason";
        $hasNotify = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'email_notify_count') ? 'email_notify_count' : "0 AS email_notify_count";

        $dataSql = "SELECT id, user_id, real_name, pen_name, verification_status, {$hasReject}, {$hasNotify}, updated_at "
                 . "FROM {$authorProfileTable} "
                 . $baseWhere . $searchWhere
                 . " ORDER BY updated_at DESC, id DESC LIMIT ?, ?";

        $stmtData = $conn->prepare($dataSql);
        if (!$stmtData) throw new Exception('Data fetch prepare failed: ' . $conn->error);
        
        // Bind the LIMIT values properly to prevent SQL Injection violations
        $stmtData->bind_param('ii', $start, $length);
        if (!$stmtData->execute()) throw new Exception('Data fetch execute failed: ' . $stmtData->error);
        
        $stmtData->store_result();
        $d_id = $d_uid = $d_realName = $d_penName = $d_vStatus = $d_reject = $d_notify = $d_updated = null;
        $stmtData->bind_result($d_id, $d_uid, $d_realName, $d_penName, $d_vStatus, $d_reject, $d_notify, $d_updated);

        $rows = [];
        $userIds = [];
        while ($stmtData->fetch()) {
            $rows[] = [
                'id' => (int)$d_id,
                'user_id' => (int)$d_uid,
                'user_name' => '', // Will populate in the separated query below
                'real_name' => (string)($d_realName ?? ''),
                'pen_name' => (string)($d_penName ?? ''),
                'verification_status' => (string)($d_vStatus ?? ''),
                'reject_reason' => (string)($d_reject ?? ''),
                'email_notify_count' => (int)($d_notify ?? 0),
                'updated_at' => (string)($d_updated ?? '')
            ];
            
            // Collect unique user_ids
            if (!in_array((int)$d_uid, $userIds)) {
                $userIds[] = (int)$d_uid;
            }
        }
        $stmtData->close();

        // 6. Second Query to Fetch User Names (Separated Logic)
        if (!empty($userIds)) {
            $idList = implode(',', $userIds); // Safe because array only contains strictly casted integers
            $userSql = "SELECT id, name FROM {$usersTable} WHERE id IN ({$idList})";
            if ($resUsers = $conn->query($userSql)) {
                $userMap = [];
                while ($uRow = $resUsers->fetch_assoc()) {
                    $userMap[$uRow['id']] = $uRow['name'];
                }
                $resUsers->free();
                
                // Map the names back into the dataset using reference (&)
                foreach ($rows as &$r) {
                    if (isset($userMap[$r['user_id']])) {
                        $r['user_name'] = $userMap[$r['user_id']];
                    }
                }
            }
        }

        echo safeJsonEncode([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows
        ]);
        exit();
    }

    // =========================================================================
    // MODE: VERIFY - Bypassing "?" for the core queries
    // =========================================================================
    if ($mode === 'verify') {
        $actionType = strtolower(trim((string)($_POST['action_type'] ?? '')));
        
        // [FIX] Strictly verify custom permissions without falling back to "edit"
        if ($actionType === 'approve' && empty($perm->approve)) {
            throw new Exception('权限不足：您没有执行【通过】操作的权限。');
        }
        if ($actionType === 'reject' && empty($perm->reject)) {
            throw new Exception('权限不足：您没有执行【驳回】操作的权限。');
        }
        if ($actionType === 'resend' && empty($perm->resend) && empty($perm->{'resend email'})) {
            throw new Exception('权限不足：您没有执行【重发】操作的权限。');
        }

        $id = (int)($_POST['id'] ?? 0);
        $rejectReason = trim((string)($_POST['reject_reason'] ?? ''));
        
        if ($id <= 0) throw new Exception('无效记录ID');
        if (!in_array($actionType, ['approve', 'reject', 'resend'], true)) throw new Exception('无效操作类型');
        if ($actionType === 'reject' && $rejectReason === '') throw new Exception('驳回原因不能为空');

        $oldRow = authorVerificationFetchRow($conn, $id, $authorProfileTable, $usersTable);
        if (!$oldRow || $oldRow['status'] !== 'A') throw new Exception('记录不存在或已删除');

        $authorUserId = (int)$oldRow['user_id'];
        $conn->begin_transaction();
        
        $messageText = '';
        $emailResult = ['success' => true, 'message' => ''];
        $escId = (int)$id;
        $escUser = (int)$currentUserId;

        $hasUpdatedBy = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'updated_by');
        $hasReject = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'reject_reason');

        try {
            if ($actionType === 'approve') {
                $updateSql = "UPDATE {$authorProfileTable} SET verification_status = 'approved', updated_at = NOW()";
                if ($hasReject) $updateSql .= ", reject_reason = NULL";
                if ($hasUpdatedBy) $updateSql .= ", updated_by = {$escUser}";
                $updateSql .= " WHERE id = {$escId} AND status = 'A'";
                if (!$conn->query($updateSql)) throw new Exception('审核更新失败: ' . $conn->error);
                
                if (function_exists('processAuthorVerificationEmail')) {
                    $emailResult = processAuthorVerificationEmail($conn, $authorUserId, 'approved', '');
                }
                $messageText = '作者审核通过';
            } elseif ($actionType === 'reject') {
                $escReason = $conn->real_escape_string($rejectReason);
                $updateSql = "UPDATE {$authorProfileTable} SET verification_status = 'rejected', updated_at = NOW()";
                if ($hasReject) $updateSql .= ", reject_reason = '{$escReason}'";
                if ($hasUpdatedBy) $updateSql .= ", updated_by = {$escUser}";
                $updateSql .= " WHERE id = {$escId} AND status = 'A'";
                if (!$conn->query($updateSql)) throw new Exception('审核更新失败: ' . $conn->error);
                
                if (function_exists('processAuthorVerificationEmail')) {
                    $emailResult = processAuthorVerificationEmail($conn, $authorUserId, 'rejected', $rejectReason);
                }
                $messageText = '作者审核驳回';
            } else {
                if (function_exists('processAuthorVerificationEmail')) {
                    $emailResult = processAuthorVerificationEmail($conn, $authorUserId, 'resend', '');
                }
                $messageText = '重发作者审核通知';
            }
            
            $newRow = authorVerificationFetchRow($conn, $id, $authorProfileTable, $usersTable);
            if (function_exists('logAudit')) {
                logAudit([
                    'page' => $auditPage,
                    'action' => 'E',
                    'action_message' => $messageText,
                    'query' => 'Author verification action via API',
                    'query_table' => $authorProfileTable,
                    'user_id' => $currentUserId,
                    'record_id' => $id,
                    'record_name' => $oldRow['real_name'] ?? null,
                    'old_value' => $oldRow,
                    'new_value' => $newRow
                ]);
            }
            $conn->commit();
        } catch (Throwable $transactionException) {
            $conn->rollback();
            throw clone $transactionException; // Re-throw to hit the main catch block and log it
        }

        if (isset($emailResult['success']) && !$emailResult['success']) {
            echo safeJsonEncode(['success' => true, 'message' => $messageText . '，但邮件发送失败：' . ($emailResult['message'] ?? '未知错误')]);
            exit();
        }

        echo safeJsonEncode(['success' => true, 'message' => $messageText . '成功']);
        exit();
    }

    // =========================================================================
    // MODE: DELETE
    // =========================================================================
    if ($mode === 'delete') {
        $deleteError = checkPermissionError('delete', $perm, '作者审核管理', false);
        if ($deleteError) throw new Exception($deleteError);

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('无效记录ID');

        $oldRow = authorVerificationFetchRow($conn, $id, $authorProfileTable, $usersTable);
        if (!$oldRow || $oldRow['status'] !== 'A') throw new Exception('记录不存在或已删除');

        $escId = (int)$id;
        $escUser = (int)$currentUserId;
        $hasUpdatedBy = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'updated_by');

        $sqlDelete = "UPDATE {$authorProfileTable} SET status = 'D', updated_at = NOW()";
        if ($hasUpdatedBy) $sqlDelete .= ", updated_by = {$escUser}";
        $sqlDelete .= " WHERE id = {$escId} AND status = 'A'";

        if (!$conn->query($sqlDelete)) throw new Exception('删除失败: ' . $conn->error);

        $newRow = authorVerificationFetchRow($conn, $id, $authorProfileTable, $usersTable);
        if (function_exists('logAudit')) {
            logAudit([
                'page' => $auditPage,
                'action' => 'D',
                'action_message' => 'Soft deleted author verification record',
                'query' => $sqlDelete,
                'query_table' => $authorProfileTable,
                'user_id' => $currentUserId,
                'record_id' => $id,
                'record_name' => $oldRow['real_name'] ?? null,
                'old_value' => $oldRow,
                'new_value' => $newRow
            ]);
        }

        echo safeJsonEncode(['success' => true, 'message' => '删除成功']);
        exit();
    }

    throw new Exception('不支持的请求模式: ' . htmlspecialchars($mode));

} catch (Throwable $e) {
    error_log("Author Verification API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    
    $modeStr = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'data';
    
    if ($modeStr === 'data') {
        echo json_encode([
            'draw' => intval($_REQUEST['draw'] ?? 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => $e->getMessage() // [FIX] Show actual error in DataTables
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage() // [FIX] Show actual error in SweetAlert Popup
        ]);
    }
    exit();
}