<?php
// Path: src/pages/author/author-verification/api.php

try {
    require_once dirname(__DIR__, 4) . '/common.php';
    
    // Session & Authentication Check
    requireLogin();
    
    // Clear any stray output from common files
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // Define Table Constants Safely
    $authorProfileTable = defined('AUTHOR_PROFILE') ? AUTHOR_PROFILE : 'author_profile';
    $usersTable = defined('USR_LOGIN') ? USR_LOGIN : 'users';

    $currentUserId = sessionInt('user_id');
    $currentUrl = '/author/author-verification.php';
    
    $perm = hasPagePermission($conn, $currentUrl);
    if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
        $legacyPath = defined('PATH_AUTHOR_VERIFICATION_INDEX') ? ('/' . ltrim(PATH_AUTHOR_VERIFICATION_INDEX, '/')) : '/src/pages/author/author-verification/index.php';
        $perm = hasPagePermission($conn, $legacyPath);
    }
    
    $auditPage = 'Author Verification Management';

    // [FIX] Use input() global function for mode detection
    $mode = strtolower(input('mode') ?: 'data');

    // Helper Function
    if (!function_exists('authorVerificationFetchRow')) {
        function authorVerificationFetchRow($conn, $authorId, $authorProfileTable, $usersTable) {
            $safeId = $authorId;
            $hasRejectReason = function_exists('columnExists') ? columnExists($conn, $authorProfileTable, 'reject_reason') : false;
            $hasEmailNotifiedAt = function_exists('columnExists') ? columnExists($conn, $authorProfileTable, 'email_notified_at') : false;
            $hasEmailNotifyCount = function_exists('columnExists') ? columnExists($conn, $authorProfileTable, 'email_notify_count') : false;

            $rejectExpr = $hasRejectReason ? 'reject_reason' : "'' AS reject_reason";
            $emailNotifiedExpr = $hasEmailNotifiedAt ? 'email_notified_at' : "NULL AS email_notified_at";
            $emailNotifyCountExpr = $hasEmailNotifyCount ? 'email_notify_count' : "0 AS email_notify_count";

            $sql = "SELECT id, user_id, real_name, pen_name, contact_email, verification_status, {$rejectExpr}, {$emailNotifiedExpr}, {$emailNotifyCountExpr}, updated_at, status "
                 . "FROM {$authorProfileTable} "
                 . "WHERE id = ? LIMIT 1"; 
                 
            $stmt = $conn->prepare($sql);
            if ($stmt === false) return null;
            
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
                    'id' => $r_id,
                    'user_id' => $r_uid,
                    'real_name' => (string)($r_realName ?? ''),
                    'pen_name' => (string)($r_penName ?? ''),
                    'contact_email' => (string)($r_email ?? ''),
                    'verification_status' => (string)($r_verificationStatus ?? ''),
                    'reject_reason' => (string)($r_rejectReason ?? ''),
                    'email_notified_at' => (string)($r_emailNotifiedAt ?? ''),
                    'email_notify_count' => ($r_emailNotifyCount ?? 0),
                    'updated_at' => (string)($r_updatedAt ?? ''),
                    'status' => (string)($r_status ?? ''),
                    'user_name' => '' 
                ];
                $stmt->close();

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
    // CSRF Protection
    // =========================================================================
    if (in_array($mode, ['verify', 'delete'])) {
        // [FIX] Use post() global function for CSRF token
        $clientToken = input('HTTP_X_CSRF_TOKEN') ?: post('csrf_token');

        if (empty(session('csrf_token')) || !hash_equals(session('csrf_token'), (string)$clientToken)) {
            http_response_code(403);
            throw new Exception('安全校验失败：非法的请求 (Invalid CSRF Token)');
        }
    }

    // =========================================================================
    // MODE: DATA (DataTables) 
    // =========================================================================
    if ($mode === 'data') {
        checkPermissionError('view', $perm);

        // [FIX] Use numberInput and getArray one-liners
        $draw   = (int)(numberInput('draw') ?: 1);
        $start  = max(0, (int)(numberInput('start') ?: 0));
        $length = max(1, min(100, (int)(numberInput('length') ?: 10)));

        $statusFilterRaw = postSpaceFilter('status_filter') ?: 'pending,rejected';
        $searchValue = getArray('search')['value'] ?? '';

        $baseWhere = " WHERE status = 'A' ";
        
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

        $searchWhere = '';
        if ($searchValue !== '') {
            $escSearch = $conn->real_escape_string($searchValue);
            $searchWhere = " AND (real_name LIKE '%{$escSearch}%' OR pen_name LIKE '%{$escSearch}%' OR contact_email LIKE '%{$escSearch}%' OR user_id IN (SELECT id FROM {$usersTable} WHERE name LIKE '%{$escSearch}%')) ";
        }

        $countTotalSql = "SELECT COUNT(id) AS total FROM {$authorProfileTable}" . $baseWhere;
        $resTotal = $conn->query($countTotalSql);
        $recordsTotal = ($resTotal) ? ($resTotal->fetch_assoc()['total'] ?? 0) : 0;

        $countFilteredSql = "SELECT COUNT(id) AS total FROM {$authorProfileTable}" . $baseWhere . $searchWhere;
        $resFiltered = $conn->query($countFilteredSql);
        $recordsFiltered = ($resFiltered) ? ($resFiltered->fetch_assoc()['total'] ?? 0) : 0;

        $hasReject = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'reject_reason') ? 'reject_reason' : "'' AS reject_reason";
        $hasNotify = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'email_notify_count') ? 'email_notify_count' : "0 AS email_notify_count";

        $dataSql = "SELECT id, user_id, real_name, pen_name, verification_status, {$hasReject}, {$hasNotify}, updated_at "
                 . "FROM {$authorProfileTable} "
                 . $baseWhere . $searchWhere
                 . " ORDER BY updated_at DESC, id DESC LIMIT ?, ?";

        $stmtData = $conn->prepare($dataSql);
        if (!$stmtData) throw new Exception('Data fetch prepare failed');
        
        $stmtData->bind_param('ii', $start, $length);
        $stmtData->execute();
        $stmtData->store_result();
        
        $d_id = $d_uid = $d_realName = $d_penName = $d_vStatus = $d_reject = $d_notify = $d_updated = null;
        $stmtData->bind_result($d_id, $d_uid, $d_realName, $d_penName, $d_vStatus, $d_reject, $d_notify, $d_updated);

        $rows = [];
        $userIds = [];
        while ($stmtData->fetch()) {
            $rows[] = [
                'id' => $d_id,
                'user_id' => $d_uid,
                'user_name' => '', 
                'real_name' => (string)($d_realName ?? ''),
                'pen_name' => (string)($d_penName ?? ''),
                'verification_status' => (string)($d_vStatus ?? ''),
                'reject_reason' => (string)($d_reject ?? ''),
                'email_notify_count' => ($d_notify ?? 0),
                'updated_at' => (string)($d_updated ?? '')
            ];
            if (!in_array($d_uid, $userIds)) $userIds[] = $d_uid;
        }
        $stmtData->close();

        if (!empty($userIds)) {
            $idList = implode(',', array_map('intval', $userIds)); 
            $userSql = "SELECT id, name FROM {$usersTable} WHERE id IN ({$idList})";
            if ($resUsers = $conn->query($userSql)) {
                $userMap = [];
                while ($uRow = $resUsers->fetch_assoc()) {
                    $userMap[$uRow['id']] = $uRow['name'];
                }
                foreach ($rows as &$r) {
                    $userKey = $r['user_id'] ?? '';
                    if ($userKey !== '' && isset($userMap[$userKey])) {
                        $r['user_name'] = $userMap[$userKey];
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
    // MODE: VERIFY
    // =========================================================================
    if ($mode === 'verify') {
        // [FIX] Use postSpaceFilter for strings and post() for simple checks
        $actionType = strtolower(postSpaceFilter('action_type'));
        
        if ($actionType === 'approve' && empty($perm->approve)) throw new Exception('权限不足');
        if ($actionType === 'reject' && empty($perm->reject)) throw new Exception('权限不足');
        if ($actionType === 'resend' && empty($perm->resend) && empty($perm->{'resend email'})) throw new Exception('权限不足');

        $id = (int)post('id');
        $rejectReason = postSpaceFilter('reject_reason');
        
        if ($id <= 0) throw new Exception('无效记录ID');
        if (!in_array($actionType, ['approve', 'reject', 'resend'], true)) throw new Exception('无效操作类型');
        if ($actionType === 'reject' && $rejectReason === '') throw new Exception('驳回原因不能为空');

        $oldRow = authorVerificationFetchRow($conn, $id, $authorProfileTable, $usersTable);
        if (!$oldRow || $oldRow['status'] !== 'A') throw new Exception('记录不存在或已删除');

        $authorUserIdInt = (int)$oldRow['user_id'];
        $conn->begin_transaction();
        
        $messageText = '';
        $emailResult = ['success' => true, 'message' => ''];
        $escId = $id;
        $escUser = $currentUserId;

        $hasUpdatedBy = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'updated_by');
        $hasReject = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'reject_reason');

        try {
            if ($actionType === 'approve') {
                $updateSql = "UPDATE {$authorProfileTable} SET verification_status = 'approved', updated_at = NOW()";
                if ($hasReject) $updateSql .= ", reject_reason = NULL";
                if ($hasUpdatedBy) $updateSql .= ", updated_by = {$escUser}";
                $updateSql .= " WHERE id = {$escId} AND status = 'A'";
                if (!$conn->query($updateSql)) throw new Exception('审核更新失败');
                
                if (function_exists('upgradeUserToAuthorRole')) {
                    if (!upgradeUserToAuthorRole($conn, $authorUserIdInt, $currentUserId)) throw new Exception('角色分配失败');
                }

                if (function_exists('processAuthorVerificationEmail')) {
                    $emailResult = processAuthorVerificationEmail($conn, $authorUserIdInt, 'approved', '');
                }
                $messageText = '作者审核通过';
            } elseif ($actionType === 'reject') {
                $escReason = $conn->real_escape_string($rejectReason);
                $updateSql = "UPDATE {$authorProfileTable} SET verification_status = 'rejected', updated_at = NOW()";
                if ($hasReject) $updateSql .= ", reject_reason = '{$escReason}'";
                if ($hasUpdatedBy) $updateSql .= ", updated_by = {$escUser}";
                $updateSql .= " WHERE id = {$escId} AND status = 'A'";
                if (!$conn->query($updateSql)) throw new Exception('审核更新失败');
                
                if (function_exists('processAuthorVerificationEmail')) {
                    $emailResult = processAuthorVerificationEmail($conn, $authorUserIdInt, 'rejected', $rejectReason);
                }
                $messageText = '作者审核驳回';
            } else {
                if (function_exists('processAuthorVerificationEmail')) {
                    $emailResult = processAuthorVerificationEmail($conn, $authorUserIdInt, 'resend', '');
                }
                $messageText = '重发作者审核通知';
            }
            
            $newRow = authorVerificationFetchRow($conn, $id, $authorProfileTable, $usersTable);
            if (function_exists('logAudit')) {
                logAudit([
                    'page' => $auditPage,
                    'action' => 'E',
                    'action_message' => $messageText,
                    'query' => $updateSql ?? 'Action: ' . $actionType,
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
            throw $transactionException;
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
        checkPermissionError('delete', $perm);

        $id = (int)post('id');
        if ($id <= 0) throw new Exception('无效记录ID');

        $oldRow = authorVerificationFetchRow($conn, $id, $authorProfileTable, $usersTable);
        if (!$oldRow || $oldRow['status'] !== 'A') throw new Exception('记录不存在或已删除');

        $escId = $id;
        $escUser = $currentUserId;
        $hasUpdatedBy = function_exists('columnExists') && columnExists($conn, $authorProfileTable, 'updated_by');

        $sqlDelete = "UPDATE {$authorProfileTable} SET status = 'D', updated_at = NOW()";
        if ($hasUpdatedBy) $sqlDelete .= ", updated_by = {$escUser}";
        $sqlDelete .= " WHERE id = {$escId} AND status = 'A'";

        if (!$conn->query($sqlDelete)) throw new Exception('删除失败');

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

    throw new Exception('不支持的请求模式');

} catch (Throwable $e) {
    error_log("Author Verification API Error: " . $e->getMessage());
    
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    
    // [FIX] Use input() for mode detection in error handler
    $modeStr = input('mode') ?: 'data';
    
    if ($modeStr === 'data') {
        echo safeJsonEncode([
            'draw' => (int)(input('draw') ?: 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => $e->getMessage()
        ]);
    } else {
        echo safeJsonEncode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit();
}