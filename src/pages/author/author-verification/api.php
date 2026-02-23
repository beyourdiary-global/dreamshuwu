<?php
// Path: src/pages/author/author-verification/api.php

try {
    require_once dirname(__DIR__, 4) . '/common.php';
    
    // 2. Clear any stray output from common files
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // 3. Define Table Constants Safely
    $authorProfileTable = defined('AUTHOR_PROFILE') ? AUTHOR_PROFILE : 'author_profile';
    $usersTable = defined('USR_LOGIN') ? USR_LOGIN : 'users';

    // 4. Session & Authentication Check
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        throw new Exception('会话已过期，请重新登录。');
    }

    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0);
    $currentUrl = '/author/author-verification.php';
    
    $perm = hasPagePermission($conn, $currentUrl);
    if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
        $perm = hasPagePermission($conn, '/src/pages/author/author-verification/index.php');
    }
    if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
        $perm = hasPagePermission($conn, '/dashboard.php?view=author_verification');
    }
    
    $auditPage = 'Author Verification Management';

    $mode = strtolower(trim((string)($_REQUEST['mode'] ?? 'data')));

    // Helper Function: Bypassing "?" placeholders completely using strict integer casting
    if (!function_exists('authorVerificationFetchRow')) {
        function authorVerificationFetchRow($conn, $authorId, $authorProfileTable, $usersTable) {
            $safeId = (int)$authorId;
            $hasRejectReason = function_exists('columnExists') ? columnExists($conn, $authorProfileTable, 'reject_reason') : false;
            $hasEmailNotifiedAt = function_exists('columnExists') ? columnExists($conn, $authorProfileTable, 'email_notified_at') : false;
            $hasEmailNotifyCount = function_exists('columnExists') ? columnExists($conn, $authorProfileTable, 'email_notify_count') : false;

            $rejectExpr = $hasRejectReason ? 'ap.reject_reason' : "'' AS reject_reason";
            $emailNotifiedExpr = $hasEmailNotifiedAt ? 'ap.email_notified_at' : "NULL AS email_notified_at";
            $emailNotifyCountExpr = $hasEmailNotifyCount ? 'ap.email_notify_count' : "0 AS email_notify_count";

            $sql = "SELECT ap.id, ap.user_id, ap.real_name, ap.pen_name, ap.contact_email, ap.verification_status, {$rejectExpr}, {$emailNotifiedExpr}, {$emailNotifyCountExpr}, ap.updated_at, ap.status, u.name AS user_name "
                 . "FROM {$authorProfileTable} ap "
                 . "LEFT JOIN {$usersTable} u ON ap.user_id = u.id "
                 . "WHERE ap.id = {$safeId} LIMIT 1";
                 
            $res = $conn->query($sql);
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $res->free();
                return $row;
            }
            return null;
        }
    }

    // =========================================================================
    // MODE: DATA (DataTables) - Zero "?" Placeholders Used Here
    // =========================================================================
    if ($mode === 'data') {
        $viewError = checkPermissionError('view', $perm, '作者审核管理', false);
        if ($viewError) throw new Exception($viewError); 

        // Strictly cast to int for safe direct injection
        $draw = (int)($_REQUEST['draw'] ?? 1);
        $start = max(0, (int)($_REQUEST['start'] ?? 0));
        $length = max(1, min(100, (int)($_REQUEST['length'] ?? 10)));

        $statusFilterRaw = trim((string)($_REQUEST['status_filter'] ?? 'pending,rejected'));
        $searchData = $_REQUEST['search'] ?? [];
        $searchValue = trim((string)($searchData['value'] ?? ''));

        $baseWhere = " WHERE ap.status = 'A' ";
        
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
                $baseWhere .= " AND ap.verification_status IN (" . implode(',', $validStatuses) . ") ";
            }
        }

        // 2. Build Search Filter Safely
        $searchWhere = '';
        if ($searchValue !== '') {
            $escSearch = $conn->real_escape_string($searchValue);
            $searchWhere = " AND (ap.real_name LIKE '%{$escSearch}%' OR ap.pen_name LIKE '%{$escSearch}%' OR ap.contact_email LIKE '%{$escSearch}%' OR u.name LIKE '%{$escSearch}%') ";
        }

        // 3. Execute Total Count Query
        $countTotalSql = "SELECT COUNT(ap.id) AS total FROM {$authorProfileTable} ap LEFT JOIN {$usersTable} u ON ap.user_id = u.id " . $baseWhere;
        $resTotal = $conn->query($countTotalSql);
        if (!$resTotal) throw new Exception('Total count failed: ' . $conn->error);
        $recordsTotal = (int)($resTotal->fetch_assoc()['total'] ?? 0);
        $resTotal->free();

        // 4. Execute Filtered Count Query
        $countFilteredSql = "SELECT COUNT(ap.id) AS total FROM {$authorProfileTable} ap LEFT JOIN {$usersTable} u ON ap.user_id = u.id " . $baseWhere . $searchWhere;
        $resFiltered = $conn->query($countFilteredSql);
        if (!$resFiltered) throw new Exception('Filter count failed: ' . $conn->error);
        $recordsFiltered = (int)($resFiltered->fetch_assoc()['total'] ?? 0);
        $resFiltered->free();

        // 5. Execute Data Query
        $hasReject = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'reject_reason') ? 'ap.reject_reason' : "'' AS reject_reason";
        $hasNotify = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'email_notify_count') ? 'ap.email_notify_count' : "0 AS email_notify_count";

        $dataSql = "SELECT ap.id, ap.user_id, ap.real_name, ap.pen_name, ap.verification_status, {$hasReject}, {$hasNotify}, ap.updated_at, u.name AS user_name "
                 . "FROM {$authorProfileTable} ap "
                 . "LEFT JOIN {$usersTable} u ON ap.user_id = u.id "
                 . $baseWhere . $searchWhere
                 . " ORDER BY ap.updated_at DESC, ap.id DESC LIMIT {$start}, {$length}";

        $resData = $conn->query($dataSql);
        if (!$resData) throw new Exception('Data fetch failed: ' . $conn->error);

        $rows = [];
        while ($row = $resData->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'user_name' => (string)($row['user_name'] ?? ''),
                'real_name' => (string)($row['real_name'] ?? ''),
                'pen_name' => (string)($row['pen_name'] ?? ''),
                'verification_status' => (string)($row['verification_status'] ?? ''),
                'reject_reason' => (string)($row['reject_reason'] ?? ''),
                'email_notify_count' => (int)($row['email_notify_count'] ?? 0),
                'updated_at' => (string)($row['updated_at'] ?? '')
            ];
        }
        $resData->free();

        echo safeJsonEncode([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows
        ]);
        exit();
    }

    // =========================================================================
    // MODE: VERIFY & DELETE - Bypassing "?" for the core queries
    // =========================================================================
    if ($mode === 'verify') {
        $editError = checkPermissionError('edit', $perm, '作者审核管理', false);
        if ($editError) throw new Exception($editError);

        $id = (int)($_POST['id'] ?? 0);
        $actionType = strtolower(trim((string)($_POST['action_type'] ?? '')));
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

        if (isset($emailResult['success']) && !$emailResult['success']) {
            echo safeJsonEncode(['success' => true, 'message' => $messageText . '，但邮件发送失败：' . ($emailResult['message'] ?? '未知错误')]);
            exit();
        }

        echo safeJsonEncode(['success' => true, 'message' => $messageText . '成功']);
        exit();
    }

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
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    
    $modeStr = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'data';
    
    if ($modeStr === 'data') {
        echo json_encode([
            'draw' => intval($_REQUEST['draw'] ?? 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => '接口错误，请稍后重试' 
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '操作失败，请稍后重试'
        ]);
    }
    exit();
}