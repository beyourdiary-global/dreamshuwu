<?php
// Path: src/pages/author/email-template/api.php

try {
    require_once dirname(__DIR__, 4) . '/common.php';
    
    // 2. Clear any stray output
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        throw new Exception('会话已过期，请重新登录。');
    }

    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0);
    $currentUrl = '/author/email-template.php';
    $perm = hasPagePermission($conn, $currentUrl);
    
    if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
        $legacyPath = defined('PATH_EMAIL_TEMPLATE_INDEX') ? ('/' . ltrim(PATH_EMAIL_TEMPLATE_INDEX, '/')) : '/src/pages/author/email-template/index.php';
        $perm = hasPagePermission($conn, $legacyPath);
    }
    $auditPage = 'Email Template Management';

    $mode = strtolower(trim((string)($_REQUEST['mode'] ?? 'data')));

    // --- [NEW] CSRF Token Protection ---
    // Validate CSRF token for all state-changing operations to prevent malicious forged requests
    if (in_array($mode, ['create', 'update', 'delete'])) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $clientToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $headers['X-CSRF-Token'] ?? '';
        
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $clientToken)) {
            http_response_code(403);
            throw new Exception('安全校验失败：非法的请求 (Invalid CSRF Token)');
        }
    }
    // -----------------------------------

    // Helper: Fetch Template Row
    if (!function_exists('fetchEmailTemplateRowById')) {
        function fetchEmailTemplateRowById($conn, $id) {
            $sql = "SELECT id, template_code, template_name, subject, content, status, created_at, updated_at, created_by, updated_by FROM " . EMAIL_TEMPLATE . " WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) return null;
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->store_result();

            $r_id = $r_code = $r_name = $r_subject = $r_content = $r_status = $r_created = $r_updated = $r_createdBy = $r_updatedBy = null;
            $stmt->bind_result($r_id, $r_code, $r_name, $r_subject, $r_content, $r_status, $r_created, $r_updated, $r_createdBy, $r_updatedBy);

            $row = null;
            if ($stmt->fetch()) {
                $row = [
                    'id' => (int)$r_id,
                    'template_code' => (string)$r_code,
                    'template_name' => (string)$r_name,
                    'subject' => (string)$r_subject,
                    'content' => (string)$r_content,
                    'status' => (string)$r_status,
                    'created_at' => (string)$r_created,
                    'updated_at' => (string)$r_updated,
                    'created_by' => (int)$r_createdBy,
                    'updated_by' => (int)$r_updatedBy
                ];
            }
            $stmt->close();
            return $row;
        }
    }

    // --- [NEW] DEFINE REQUIRED TEMPLATES LIST ---
    // These codes represent core system functions and cannot be deleted or renamed.
    $requiredTemplates = ['AUTHOR_APPROVED', 'AUTHOR_REJECTED'];

    // ==========================================
    // MODE: DATA (Fetch DataTables)
    // ==========================================
    if ($mode === 'data') {
        $viewError = checkPermissionError('view', $perm, '邮件模板管理', false);
        if ($viewError) throw new Exception($viewError);

        $draw = (int)($_REQUEST['draw'] ?? 1);
        $start = max(0, (int)($_REQUEST['start'] ?? 0));
        $length = max(1, min(100, (int)($_REQUEST['length'] ?? 10)));
        
        // Safely extract search value to prevent PHP 8 Array Warning
        $searchData = $_REQUEST['search'] ?? [];
        $searchValue = trim((string)($searchData['value'] ?? ''));

        $baseWhere = " WHERE status <> 'D' ";

        $sqlTotal = "SELECT COUNT(*) AS total FROM " . EMAIL_TEMPLATE . $baseWhere;
        $stmtTotal = $conn->prepare($sqlTotal);
        if (!$stmtTotal) throw new Exception('统计失败: ' . $conn->error);
        $stmtTotal->execute();
        $stmtTotal->bind_result($recordsTotal);
        $stmtTotal->fetch();
        $stmtTotal->close();

        $searchWhere = '';
        $types = '';
        $params = [];
        if ($searchValue !== '') {
            $searchWhere = " AND (template_code LIKE ? OR template_name LIKE ? OR subject LIKE ?) ";
            $like = '%' . $searchValue . '%';
            $types = 'sss';
            $params = [$like, $like, $like];
        }

        $sqlFiltered = "SELECT COUNT(*) AS total FROM " . EMAIL_TEMPLATE . $baseWhere . $searchWhere;
        $stmtFiltered = $conn->prepare($sqlFiltered);
        if (!$stmtFiltered) throw new Exception('筛选统计失败: ' . $conn->error);
        
        if (!empty($params)) {
            $bind = [$types];
            foreach ($params as $k => $v) $bind[] = &$params[$k];
            call_user_func_array([$stmtFiltered, 'bind_param'], $bind);
        }
        $stmtFiltered->execute();
        $stmtFiltered->bind_result($recordsFiltered);
        $stmtFiltered->fetch();
        $stmtFiltered->close();

        $sqlData = "SELECT id, template_code, template_name, subject, content, status, updated_at FROM " . EMAIL_TEMPLATE . $baseWhere . $searchWhere . " ORDER BY id DESC LIMIT ?, ?";
        $stmtData = $conn->prepare($sqlData);
        if (!$stmtData) throw new Exception('读取数据失败: ' . $conn->error);
        
        $typesData = (isset($types) ? $types : '') . 'ii';
        $paramsData = (isset($params) && is_array($params)) ? $params : [];
        $paramsData[] = $start;
        $paramsData[] = $length;
        if (!empty($paramsData)) {
            $bindData = [$typesData];
            foreach ($paramsData as $k => $v) $bindData[] = &$paramsData[$k];
            call_user_func_array([$stmtData, 'bind_param'], $bindData);
        }

        $stmtData->execute();
        $stmtData->store_result();

        $d_id = $d_code = $d_name = $d_subject = $d_content = $d_status = $d_updatedAt = null;
        $stmtData->bind_result($d_id, $d_code, $d_name, $d_subject, $d_content, $d_status, $d_updatedAt);

        $rows = [];
        while ($stmtData->fetch()) {
            $rows[] = [
                'id' => (int)$d_id,
                'template_code' => (string)$d_code,
                'template_name' => (string)$d_name,
                'subject' => (string)$d_subject,
                'content' => (string)$d_content,
                'status' => (string)$d_status,
                'updated_at' => (string)$d_updatedAt,
                // Pass a flag to frontend to optionally hide the delete button
                'is_required' => in_array((string)$d_code, $requiredTemplates, true)
            ];
        }
        $stmtData->close();

        echo safeJsonEncode([
            'draw' => $draw,
            'recordsTotal' => (int)$recordsTotal,
            'recordsFiltered' => (int)$recordsFiltered,
            'data' => $rows
        ]);
        exit();
    }

    // ==========================================
    // MODE: CREATE
    // ==========================================
    if ($mode === 'create') {
        $addError = checkPermissionError('add', $perm, '邮件模板管理', false);
        if ($addError) throw new Exception($addError);

        $templateCode = strtoupper(trim((string)($_POST['template_code'] ?? '')));
        $templateName = trim((string)($_POST['template_name'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'A'));

        if ($templateCode === '' || $templateName === '' || $subject === '' || $content === '') {
            throw new Exception('请完整填写必填字段');
        }

        // ADDED VALIDATION HERE
        if (mb_strlen($templateCode, 'UTF-8') > 50) {
            throw new Exception('模板代码长度不能超过50个字符');
        }
        if (mb_strlen($templateName, 'UTF-8') > 100) {
            throw new Exception('模板名称长度不能超过100个字符');
        }
        if (mb_strlen($subject, 'UTF-8') > 255) {
            throw new Exception('邮件主题长度不能超过255个字符');
        }
        
        if (!preg_match('/^[A-Z0-9_]+$/', $templateCode)) {
            throw new Exception('模板代码仅允许大写字母、数字、下划线');
        }

        if (!in_array($status, ['A', 'D'], true)) $status = 'A';

        $checkSql = "SELECT id FROM " . EMAIL_TEMPLATE . " WHERE template_code = ? LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) throw new Exception('校验失败: ' . $conn->error);
        $checkStmt->bind_param('s', $templateCode);
        $checkStmt->execute();
        $checkStmt->store_result();
        $exists = $checkStmt->num_rows > 0;
        $checkStmt->close();

        if ($exists) {
            throw new Exception('模板代码已存在，请使用其他代码');
        }

        $insertSql = "INSERT INTO " . EMAIL_TEMPLATE . " (template_code, template_name, subject, content, status, created_at, updated_at, created_by, updated_by) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)";
        $stmt = $conn->prepare($insertSql);
        if (!$stmt) throw new Exception('新增失败: ' . $conn->error);
        
        $stmt->bind_param('sssssii', $templateCode, $templateName, $subject, $content, $status, $currentUserId, $currentUserId);
        $ok = $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        if (!$ok) throw new Exception('新增执行失败');

        $newRow = fetchEmailTemplateRowById($conn, $newId);
        if (function_exists('logAudit')) {
            logAudit([
                'page' => $auditPage,
                'action' => 'A',
                'action_message' => 'Created email template: ' . $templateCode,
                'query' => $insertSql,
                'query_table' => EMAIL_TEMPLATE,
                'user_id' => $currentUserId,
                'record_id' => $newId,
                'record_name' => $templateCode,
                'new_value' => $newRow
            ]);
        }

        echo safeJsonEncode(['success' => true, 'message' => '新增成功']);
        exit();
    }

    // ==========================================
    // MODE: UPDATE
    // ==========================================
    if ($mode === 'update') {
        $editError = checkPermissionError('edit', $perm, '邮件模板管理', false);
        if ($editError) throw new Exception($editError);

        $id = (int)($_POST['id'] ?? 0);
        $templateCode = strtoupper(trim((string)($_POST['template_code'] ?? '')));
        $templateName = trim((string)($_POST['template_name'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'A'));

        if ($id <= 0 || $templateCode === '' || $templateName === '' || $subject === '' || $content === '') {
            throw new Exception('参数不完整');
        }
        if (!preg_match('/^[A-Z0-9_]+$/', $templateCode)) {
            throw new Exception('模板代码仅允许大写字母、数字、下划线');
        }
        if (!in_array($status, ['A', 'D'], true)) $status = 'A';

        $oldRow = fetchEmailTemplateRowById($conn, $id);
        if (!$oldRow || $oldRow['status'] === 'D') {
            throw new Exception('记录不存在');
        }

        // --- [CRITICAL REQUIREMENT FIX: PREVENT MODIFICATION OF CORE TEMPLATES] ---
        if (in_array($oldRow['template_code'], $requiredTemplates, true)) {
            // 1. Cannot rename template code
            if ($templateCode !== $oldRow['template_code']) {
                throw new Exception('系统必备模板的【模板代码】为核心参数，禁止修改！');
            }
            // 2. Cannot disable required templates
            if ($status === 'D') {
                throw new Exception('此模板为审核流程必备组件，禁止将其设为【禁用】状态！');
            }
        }
        // -------------------------------------------------------------------------

        $newData = [
            'template_code' => $templateCode,
            'template_name' => $templateName,
            'subject' => $subject,
            'content' => $content,
            'status' => $status,
        ];
        $changeResult = checkNoChangesAndRedirect($newData, $oldRow);
        if (is_array($changeResult)) {
            echo safeJsonEncode([
                'success' => false,
                'message' => $changeResult['message'],
                'type' => $changeResult['type']
            ]);
            exit();
        }

        $checkSql = "SELECT id FROM " . EMAIL_TEMPLATE . " WHERE template_code = ? AND id <> ? LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) throw new Exception('校验失败: ' . $conn->error);
        $checkStmt->bind_param('si', $templateCode, $id);
        $checkStmt->execute();
        $checkStmt->store_result();
        $exists = $checkStmt->num_rows > 0;
        $checkStmt->close();

        if ($exists) {
            throw new Exception('模板代码已存在，请使用其他代码');
        }

        $updateSql = "UPDATE " . EMAIL_TEMPLATE . " SET template_code = ?, template_name = ?, subject = ?, content = ?, status = ?, updated_at = NOW(), updated_by = ? WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        if (!$stmt) throw new Exception('更新失败: ' . $conn->error);
        
        $stmt->bind_param('sssssii', $templateCode, $templateName, $subject, $content, $status, $currentUserId, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) throw new Exception('更新执行失败');

        $newRow = fetchEmailTemplateRowById($conn, $id);
        if (function_exists('logAudit')) {
            logAudit([
                'page' => $auditPage,
                'action' => 'E',
                'action_message' => 'Updated email template: ' . $templateCode,
                'query' => $updateSql,
                'query_table' => EMAIL_TEMPLATE,
                'user_id' => $currentUserId,
                'record_id' => $id,
                'record_name' => $templateCode,
                'old_value' => $oldRow,
                'new_value' => $newRow
            ]);
        }

        echo safeJsonEncode(['success' => true, 'message' => '更新成功']);
        exit();
    }

    // ==========================================
    // MODE: DELETE
    // ==========================================
    if ($mode === 'delete') {
        $deleteError = checkPermissionError('delete', $perm, '邮件模板管理', false);
        if ($deleteError) throw new Exception($deleteError);

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('无效ID');

        $oldRow = fetchEmailTemplateRowById($conn, $id);
        if (!$oldRow || $oldRow['status'] === 'D') {
            throw new Exception('记录不存在');
        }

        // --- [CRITICAL REQUIREMENT FIX: PREVENT DELETION OF CORE TEMPLATES] ---
        if (in_array($oldRow['template_code'], $requiredTemplates, true)) {
            throw new Exception('【' . $oldRow['template_code'] . '】是审核流程必备模板，系统禁止删除！');
        }
        // -----------------------------------------------------------------------

        $deleteSql = "UPDATE " . EMAIL_TEMPLATE . " SET status = 'D', updated_at = NOW(), updated_by = ? WHERE id = ? AND status <> 'D'";
        $stmt = $conn->prepare($deleteSql);
        if (!$stmt) throw new Exception('删除失败: ' . $conn->error);
        
        $stmt->bind_param('ii', $currentUserId, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) throw new Exception('删除执行失败');

        $newRow = fetchEmailTemplateRowById($conn, $id);
        if (function_exists('logAudit')) {
            logAudit([
                'page' => $auditPage,
                'action' => 'D',
                'action_message' => 'Soft deleted email template: ' . ($oldRow['template_code'] ?? (string)$id),
                'query' => $deleteSql,
                'query_table' => EMAIL_TEMPLATE,
                'user_id' => $currentUserId,
                'record_id' => $id,
                'record_name' => $oldRow['template_code'] ?? null,
                'old_value' => $oldRow,
                'new_value' => $newRow
            ]);
        }

        echo safeJsonEncode(['success' => true, 'message' => '删除成功']);
        exit();
    }

    throw new Exception('不支持的请求模式: ' . htmlspecialchars($mode));

} catch (Throwable $e) {
    // 1. Log the full, unredacted error to the server logs for the admin
    $fullErrorMsg = trim((string)$e->getMessage());
    error_log("Email Template API Error: " . $fullErrorMsg . " in " . $e->getFile() . " on line " . $e->getLine());

    // 2. Clear any stray output to ensure a clean JSON response
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    
    $modeStr = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'data';
    
    if ($modeStr === 'data') {
        // Response format expected by DataTables
        echo safeJsonEncode([
            'draw' => intval($_REQUEST['draw'] ?? 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => '接口错误，请检查系统日志' 
        ]);
    } else {
        // [FIX] Security: Hide database details from the frontend user.
        // We split the string by ": " so "读取数据失败: SQL syntax..." becomes just "读取数据失败"
        $safeUserMsg = explode(': ', $fullErrorMsg)[0];
        
        if ($safeUserMsg === '') {
            $safeUserMsg = '操作失败，请稍后重试';
        }

        echo safeJsonEncode([
            'success' => false,
            'message' => $safeUserMsg
        ]);
    }
    exit();
}