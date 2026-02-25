<?php
// Path: src/pages/author/novel-management/api.php

try {
    require_once dirname(__DIR__, 4) . '/common.php';
    
    // Clear any accidental output before sending JSON
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // Basic Auth Check
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        throw new Exception('会话已过期，请重新登录。');
    }

    $currentUserId = (int)$_SESSION['user_id'];
    
    // Strict Author Profile Check (Must be Verified Author)
    $authorSql = "SELECT verification_status FROM " . AUTHOR_PROFILE . " WHERE user_id = ? AND status = 'A' LIMIT 1";
    $stmt = $conn->prepare($authorSql);
    if ($stmt) {
        $stmt->bind_param("i", $currentUserId);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) throw new Exception('您还不是作者。');
        
        $vStatus = null;
        $stmt->bind_result($vStatus);
        $stmt->fetch();
        $stmt->close();
        
        if ($vStatus !== 'approved') throw new Exception('仅审核通过的作者可以操作。');
    }

    // Strict backend permission check
    $currentUrl = defined('URL_AUTHOR_NOVEL_MANAGEMENT') ? parse_url(URL_AUTHOR_NOVEL_MANAGEMENT, PHP_URL_PATH) : '/author/novel-management.php';
    $perm = hasPagePermission($conn, $currentUrl);
    if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
        $legacyPath = defined('PATH_AUTHOR_NOVEL_MANAGEMENT') ? ('/' . ltrim(PATH_AUTHOR_NOVEL_MANAGEMENT, '/')) : '/src/pages/author/novel-management/index.php';
        $perm = hasPagePermission($conn, $legacyPath);
    }

    $mode = strtolower(trim((string)($_REQUEST['mode'] ?? 'data')));
    
    // Check specific operation permissions based on the current request mode
    if (in_array($mode, ['data', 'stats', 'get_tags', 'get_novel'])) {
        $viewError = checkPermissionError('view', $perm, '我的小说', false);
        if ($viewError) throw new Exception($viewError);
    }
    if ($mode === 'create') {
        $addError = checkPermissionError('add', $perm, '我的小说', false);
        if ($addError) throw new Exception($addError);
    }
    if ($mode === 'update') {
        $editError = checkPermissionError('edit', $perm, '我的小说', false);
        if ($editError) throw new Exception($editError);
    }
    if ($mode === 'delete') {
        $deleteError = checkPermissionError('delete', $perm, '我的小说', false);
        if ($deleteError) throw new Exception($deleteError);
    }

    $novelTable = defined('NOVEL') ? NOVEL : 'novel';
    $categoryTable = defined('NOVEL_CATEGORY') ? NOVEL_CATEGORY : 'novel_category';
    $tagTable = defined('NOVEL_TAG') ? NOVEL_TAG : 'novel_tag';
    $catTagTable = defined('CATEGORY_TAG') ? CATEGORY_TAG : 'category_tag';

    // ==========================================
    // GET SINGLE NOVEL (For Modal Populating)
    // ==========================================
    if ($mode === 'get_novel') {
        $novelId = (int)($_GET['id'] ?? 0);
        if ($novelId <= 0) throw new Exception('无效的小说ID');

        $sql = "SELECT id, title, category_id, tags, introduction, cover_image, completion_status FROM {$novelTable} WHERE id = ? AND author_id = ? AND status = 'A' LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('数据库准备失败');
        
        $stmt->bind_param("ii", $novelId, $currentUserId);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) throw new Exception('小说不存在或您无权访问');
        
        $rId = $rTitle = $rCatId = $rTags = $rIntro = $rCover = $rStatus = null;
        $stmt->bind_result($rId, $rTitle, $rCatId, $rTags, $rIntro, $rCover, $rStatus);
        $stmt->fetch();
        $stmt->close();

        $tagsArray = array_map('trim', explode(',', $rTags));

        echo safeJsonEncode([
            'success' => true,
            'data' => [
                'id' => $rId,
                'title' => $rTitle,
                'category_id' => $rCatId,
                'tags' => $tagsArray,
                'introduction' => $rIntro,
                'cover_image' => $rCover ? (URL_ASSETS . '/uploads/novel_covers/' . $rCover) : '',
                'completion_status' => $rStatus
            ]
        ]);
        exit();
    }

    // ==========================================
    // DYNAMIC TAGS FETCHING (No JOINs)
    // ==========================================
    if ($mode === 'get_tags') {
        $catId = (int)($_GET['category_id'] ?? 0);
        $tags = [];
        $tagIds = [];
        
        $sqlCt = "SELECT tag_id FROM {$catTagTable} WHERE category_id = ?";
        $stmt = $conn->prepare($sqlCt);
        if ($stmt) {
            $stmt->bind_param("i", $catId);
            $stmt->execute();
            $stmt->bind_result($tId);
            while ($stmt->fetch()) {
                $tagIds[] = $tId;
            }
            $stmt->close();
        }

        if (!empty($tagIds)) {
            $inClause = implode(',', array_fill(0, count($tagIds), '?'));
            $sqlT = "SELECT id, name FROM {$tagTable} WHERE id IN ($inClause) ORDER BY name ASC";
            $stmt = $conn->prepare($sqlT);
            if ($stmt) {
                $types = str_repeat('i', count($tagIds));
                $stmt->bind_param($types, ...$tagIds);
                $stmt->execute();
                $stmt->bind_result($id, $name);
                while ($stmt->fetch()) {
                    $tags[] = ['id' => $id, 'name' => $name];
                }
                $stmt->close();
            }
        }
        
        echo safeJsonEncode(['success' => true, 'data' => $tags]);
        exit();
    }

    // ==========================================
    // STATS
    // ==========================================
    if ($mode === 'stats') {
        $stats = ['total' => 0, 'ongoing' => 0, 'completed' => 0];
        $statSql = "SELECT completion_status, COUNT(id) as count FROM {$novelTable} WHERE author_id = ? AND status = 'A' GROUP BY completion_status";
        $stmt = $conn->prepare($statSql);
        if ($stmt) {
            $stmt->bind_param('i', $currentUserId);
            $stmt->execute();
            $cStatus = $cCount = null;
            $stmt->bind_result($cStatus, $cCount);
            while ($stmt->fetch()) {
                $stats['total'] += $cCount;
                if ($cStatus === 'ongoing') $stats['ongoing'] += $cCount;
                if ($cStatus === 'completed') $stats['completed'] += $cCount;
            }
            $stmt->close();
        }
        echo safeJsonEncode(['success' => true, 'data' => $stats]);
        exit();
    }

    // ==========================================
    // DATATABLES LIST (No JOINs)
    // ==========================================
    if ($mode === 'data') {
        $draw = (int)($_REQUEST['draw'] ?? 1);
        $start = max(0, (int)($_REQUEST['start'] ?? 0));
        $length = max(1, min(100, (int)($_REQUEST['length'] ?? 10)));
        $searchValue = trim((string)($_REQUEST['search']['value'] ?? ''));

        $baseWhere = " author_id = ? AND status = 'A' ";
        $params = [$currentUserId];
        $types = "i";

        if ($searchValue !== '') {
            $searchWildcard = "%{$searchValue}%";
            
            $matchedCatIds = [];
            $catSearchSql = "SELECT id FROM {$categoryTable} WHERE name LIKE ?";
            $stmtCat = $conn->prepare($catSearchSql);
            if ($stmtCat) {
                $stmtCat->bind_param("s", $searchWildcard);
                $stmtCat->execute();
                $stmtCat->bind_result($mcId);
                while ($stmtCat->fetch()) {
                    $matchedCatIds[] = $mcId;
                }
                $stmtCat->close();
            }

            $baseWhere .= " AND (title LIKE ? OR tags LIKE ?";
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $types .= "ss";

            if (!empty($matchedCatIds)) {
                $safeCatIds = array_map('intval', $matchedCatIds);
                $baseWhere .= " OR category_id IN (" . implode(',', $safeCatIds) . ")";
            }
            $baseWhere .= ") ";
        }

        $countFiltered = 0;
        $countSql = "SELECT COUNT(id) FROM {$novelTable} WHERE {$baseWhere}";
        $stmt = $conn->prepare($countSql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->bind_result($countFiltered);
            $stmt->fetch();
            $stmt->close();
        }

        $dataSql = "SELECT id, title, cover_image, tags, completion_status, created_at, category_id 
                    FROM {$novelTable} 
                    WHERE {$baseWhere} ORDER BY created_at DESC LIMIT ?, ?";
        
        $rows = [];
        $catIdsToFetch = [];
        $stmt = $conn->prepare($dataSql);
        if ($stmt) {
            $params[] = $start;
            $params[] = $length;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->store_result();
            
            $rId = $rTitle = $rCover = $rTags = $rStatus = $rCreated = $rCatId = null;
            $stmt->bind_result($rId, $rTitle, $rCover, $rTags, $rStatus, $rCreated, $rCatId);
            
            while ($stmt->fetch()) {
                $rows[] = [
                    'id' => $rId,
                    'cover_image' => $rCover ? (URL_ASSETS . '/uploads/novel_covers/' . $rCover) : (URL_ASSETS . '/images/no-cover.png'),
                    'title' => $rTitle,
                    'category_id' => $rCatId, 
                    'category_name' => '未分类', 
                    'tags' => $rTags,
                    'completion_status' => $rStatus,
                    'created_at' => formatDate($rCreated, 'Y-m-d H:i')
                ];
                if ($rCatId > 0 && !in_array($rCatId, $catIdsToFetch)) {
                    $catIdsToFetch[] = $rCatId;
                }
            }
            $stmt->close();
        }

        if (!empty($catIdsToFetch) && !empty($rows)) {
            $catMap = [];
            $inClause = implode(',', array_fill(0, count($catIdsToFetch), '?'));
            $catSql = "SELECT id, name FROM {$categoryTable} WHERE id IN ($inClause)";
            
            $stmtCat = $conn->prepare($catSql);
            if ($stmtCat) {
                $cTypes = str_repeat('i', count($catIdsToFetch));
                $stmtCat->bind_param($cTypes, ...$catIdsToFetch);
                $stmtCat->execute();
                $stmtCat->bind_result($cId, $cName);
                while ($stmtCat->fetch()) {
                    $catMap[$cId] = $cName;
                }
                $stmtCat->close();
            }

            foreach ($rows as &$row) {
                if (isset($catMap[$row['category_id']])) {
                    $row['category_name'] = $catMap[$row['category_id']];
                }
                unset($row['category_id']); 
            }
        }

        echo safeJsonEncode([
            'draw' => $draw,
            'recordsTotal' => $countFiltered,
            'recordsFiltered' => $countFiltered,
            'data' => $rows
        ]);
        exit();
    }

    // CSRF Protection
    $clientToken = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$clientToken)) {
        throw new Exception('安全校验失败 (Invalid CSRF Token)');
    }

    // ==========================================
    // CREATE NOVEL
    // ==========================================
    if ($mode === 'create') {
        $title = trim($_POST['title'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $tagsArray = $_POST['tags'] ?? [];
        $intro = trim($_POST['introduction'] ?? '');
        $completionStatus = trim($_POST['completion_status'] ?? 'ongoing');
        $copyright = (int)($_POST['copyright_declaration'] ?? 0);

        if ($title === '') throw new Exception('书名不能为空');
        if ($categoryId <= 0) throw new Exception('请选择分类');
        if (empty($tagsArray)) throw new Exception('请至少选择一个标签');
        if (count($tagsArray) > 10) throw new Exception('最多只能选择10个标签');
        if ($intro === '') throw new Exception('简介不能为空');
        if ($copyright !== 1) throw new Exception('必须勾选版权声明');
        if (!isset($_FILES['cover_image']) || $_FILES['cover_image']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception('请上传封面图片');
        }

        $tagsString = implode(', ', array_map('trim', $tagsArray));

        $checkSql = "SELECT id FROM {$novelTable} WHERE author_id = ? AND title = ? AND status = 'A' LIMIT 1";
        $stmt = $conn->prepare($checkSql);
        if ($stmt) {
            $stmt->bind_param("is", $currentUserId, $title);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) throw new Exception('您已创建过同名小说，书名不可重复。');
            $stmt->close();
        }

        $uploadResult = uploadImage($_FILES['cover_image'], BASE_PATH . 'assets/uploads/novel_covers/');
        if (!$uploadResult['success']) throw new Exception('封面上传失败: ' . $uploadResult['message']);
        $coverFileName = $uploadResult['filename'];

        $insertSql = "INSERT INTO {$novelTable} (author_id, title, category_id, tags, introduction, cover_image, completion_status, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'A', NOW(), NOW())";
        $stmt = $conn->prepare($insertSql);
        if (!$stmt) throw new Exception('数据库准备失败: ' . $conn->error);

        $stmt->bind_param("isissss", $currentUserId, $title, $categoryId, $tagsString, $intro, $coverFileName, $completionStatus);
        
        if (!$stmt->execute()) {
            @unlink(BASE_PATH . 'assets/uploads/novel_covers/' . $coverFileName);
            throw new Exception('保存小说失败: ' . $stmt->error);
        }
        $newNovelId = $conn->insert_id;
        $stmt->close();

        if (function_exists('logAudit')) {
            logAudit([
                'page' => 'Author Novel Management',
                'action' => 'A',
                'action_message' => 'Author created a new novel',
                'user_id' => $currentUserId,
                'record_id' => $newNovelId,
                'record_name' => $title
            ]);
        }
        echo safeJsonEncode(['success' => true, 'message' => '小说创建成功！']);
        exit();
    }

    // ==========================================
    // UPDATE NOVEL (Via Modal)
    // ==========================================
    if ($mode === 'update') {
        $novelId = (int)($_POST['novel_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $tagsArray = $_POST['tags'] ?? [];
        $intro = trim($_POST['introduction'] ?? '');
        $completionStatus = trim($_POST['completion_status'] ?? 'ongoing');
        
        if ($novelId <= 0) throw new Exception('无效的小说ID');
        if ($title === '') throw new Exception('书名不能为空');
        if ($categoryId <= 0) throw new Exception('请选择分类');
        if (empty($tagsArray)) throw new Exception('请至少选择一个标签');
        if (count($tagsArray) > 10) throw new Exception('最多只能选择10个标签');
        if ($intro === '') throw new Exception('简介不能为空');

        $tagsString = implode(', ', array_map('trim', $tagsArray));

        // 1. Fetch all old fields for change detection & ownership check
        $oldRow = [];
        $checkSql = "SELECT title, category_id, tags, introduction, cover_image, completion_status FROM {$novelTable} WHERE id = ? AND author_id = ? AND status = 'A' LIMIT 1";
        $stmt = $conn->prepare($checkSql);
        if ($stmt) {
            $stmt->bind_param("ii", $novelId, $currentUserId);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) throw new Exception('小说不存在或您无权修改。');
            
            $oldTitle = $oldCat = $oldTags = $oldIntro = $oldCover = $oldStatus = null;
            $stmt->bind_result($oldTitle, $oldCat, $oldTags, $oldIntro, $oldCover, $oldStatus);
            $stmt->fetch();
            $stmt->close();
            
            $oldRow = [
                'title' => $oldTitle,
                'category_id' => $oldCat,
                'tags' => $oldTags,
                'introduction' => $oldIntro,
                'cover_image' => $oldCover,
                'completion_status' => $oldStatus
            ];
        }

        // 2. BACKEND CHANGE DETECTION (Mimics checkNoChangesAndRedirect)
        $newData = [
            'title' => $title,
            'category_id' => $categoryId,
            'tags' => $tagsString,
            'introduction' => $intro,
            'completion_status' => $completionStatus
        ];
        
        $hasChanges = false;
        // Check if a new file was uploaded
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['size'] > 0) {
            $hasChanges = true;
        } else {
            // Check if any text field is different
            foreach ($newData as $key => $newVal) {
                if ((string)$newVal !== (string)$oldRow[$key]) {
                    $hasChanges = true;
                    break;
                }
            }
        }

        // If no changes exist, return early with warning flag
        if (!$hasChanges) {
            echo safeJsonEncode([
                'success' => false,
                'is_warning' => true,
                'message' => '无需保存'
            ]);
            exit();
        }

        // 3. Uniqueness Check (Exclude current novel ID)
        $uniqSql = "SELECT id FROM {$novelTable} WHERE author_id = ? AND title = ? AND id != ? AND status = 'A' LIMIT 1";
        $stmt = $conn->prepare($uniqSql);
        if ($stmt) {
            $stmt->bind_param("isi", $currentUserId, $title, $novelId);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) throw new Exception('您已创建过同名小说，书名不可重复。');
            $stmt->close();
        }

        $finalCoverName = $oldRow['cover_image'];

        // 4. Process New Image if uploaded
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = uploadImage($_FILES['cover_image'], BASE_PATH . 'assets/uploads/novel_covers/');
            if (!$uploadResult['success']) throw new Exception('封面上传失败: ' . $uploadResult['message']);
            $finalCoverName = $uploadResult['filename'];
            
            // Delete old cover to save disk space
            if (!empty($oldRow['cover_image']) && file_exists(BASE_PATH . 'assets/uploads/novel_covers/' . $oldRow['cover_image'])) {
                @unlink(BASE_PATH . 'assets/uploads/novel_covers/' . $oldRow['cover_image']);
            }
        }

        // 5. Update Database
        $updateSql = "UPDATE {$novelTable} SET title=?, category_id=?, tags=?, introduction=?, cover_image=?, completion_status=?, updated_at=NOW() WHERE id=?";
        $stmt = $conn->prepare($updateSql);
        if (!$stmt) throw new Exception('数据库准备失败: ' . $conn->error);

        $stmt->bind_param("sissssi", $title, $categoryId, $tagsString, $intro, $finalCoverName, $completionStatus, $novelId);
        
        if (!$stmt->execute()) {
            throw new Exception('更新小说失败: ' . $stmt->error);
        }
        $stmt->close();

        if (function_exists('logAudit')) {
            logAudit([
                'page' => 'Author Novel Management',
                'action' => 'E',
                'action_message' => 'Author updated novel',
                'user_id' => $currentUserId,
                'record_id' => $novelId,
                'record_name' => $title
            ]);
        }
        echo safeJsonEncode(['success' => true, 'message' => '小说更新成功！']);
        exit();
    }

    // ==========================================
    // DELETE NOVEL (Soft Delete)
    // ==========================================
    if ($mode === 'delete') {
        $novelId = (int)($_POST['id'] ?? 0);
        if ($novelId <= 0) throw new Exception('无效的ID');

        $checkSql = "SELECT title FROM {$novelTable} WHERE id = ? AND author_id = ? AND status = 'A' LIMIT 1";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("ii", $novelId, $currentUserId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) throw new Exception('小说不存在或您无权删除。');
        $stmt->bind_result($novelTitle);
        $stmt->fetch();
        $stmt->close();

        $delSql = "UPDATE {$novelTable} SET status = 'D', updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($delSql);
        $stmt->bind_param("i", $novelId);
        $stmt->execute();
        $stmt->close();

        if (function_exists('logAudit')) {
            logAudit([
                'page' => 'Author Novel Management',
                'action' => 'D',
                'action_message' => 'Author soft deleted novel',
                'user_id' => $currentUserId,
                'record_id' => $novelId,
                'record_name' => $novelTitle
            ]);
        }
        echo safeJsonEncode(['success' => true, 'message' => '小说已删除。']);
        exit();
    }

} catch (Throwable $e) {
    // 1. Log the full, unredacted error to the server logs for the developer/admin
    // This records the message, file path, and line number securely on the server
    error_log("Novel Management API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

    // 2. Clear any stray output to ensure a clean JSON response
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    
    $modeStr = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'data';
    
    if ($modeStr === 'data') {
        // Standard response format expected by DataTables when an error occurs
        echo safeJsonEncode([
            'draw' => intval($_REQUEST['draw'] ?? 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => '接口错误，请检查系统日志' // Generic message for DataTables
        ]);
    } else {
        echo safeJsonEncode([
            'success' => false, 
            'message' => '系统错误，请稍后再试或联系管理员' 
        ]);
    }
    exit();
}