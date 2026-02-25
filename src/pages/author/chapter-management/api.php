<?php
// Path: src/pages/author/chapter-management/api.php
try {
    require_once dirname(__DIR__, 4) . '/common.php';
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) throw new Exception('会话过期');
    $currentUserId = (int)$_SESSION['user_id'];
    requireApprovedAuthor($conn, $currentUserId);

    $mode = strtolower(trim((string)($_REQUEST['mode'] ?? 'data')));
    $novelId = (int)($_REQUEST['novel_id'] ?? 0);
    
    if ($novelId <= 0) throw new Exception('小说ID缺失');

    // ==========================================
    // PERMISSION CHECKING (Inherited from Novel Management)
    // ==========================================
    if (defined('URL_AUTHOR_CHAPTER_MANAGEMENT')) {
    // This converts ".../author/novel/{id}/chapters/" into "/author/novel/chapters/"
    $menuUrl = str_replace('{id}/', '', parse_url(URL_AUTHOR_CHAPTER_MANAGEMENT, PHP_URL_PATH));
    } else {
    // Fallback if the constant is missing
    $menuUrl = '/author/novel/chapters/';
    }
    $perm = hasPagePermission($conn, $menuUrl);
    if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
        $legacyPath = defined('PATH_AUTHOR_NOVEL_MANAGEMENT') ? ('/' . ltrim(PATH_AUTHOR_NOVEL_MANAGEMENT, '/')) : '/src/pages/author/novel-management/index.php';
        $perm = hasPagePermission($conn, $legacyPath);
    }

    if (in_array($mode, ['data', 'get', 'get_sensitive_words'])) {
        $viewError = checkPermissionError('view', $perm, '章节管理', false);
        if ($viewError) throw new Exception($viewError);
    }
    if ($mode === 'delete') {
        $deleteError = checkPermissionError('delete', $perm, '章节管理', false);
        if ($deleteError) throw new Exception($deleteError);
    }

    $novelTable = defined('NOVEL') ? NOVEL : 'novel';
    $chapterTable = defined('CHAPTER') ? CHAPTER : 'chapter';
    $chapterVersionTable = defined('CHAPTER_VERSION') ? CHAPTER_VERSION : 'chapter_version';
    $sensitiveWordTable = defined('SENSITIVE_WORD') ? SENSITIVE_WORD : 'sensitive_word';
    $sensitiveLogTable = defined('SENSITIVE_WORD_LOG') ? SENSITIVE_WORD_LOG : 'sensitive_word_log';

    // 验证小说所有权
    $checkNovel = "SELECT id FROM {$novelTable} WHERE id = ? AND author_id = ? AND status = 'A' LIMIT 1";
    $stmt = $conn->prepare($checkNovel);
    $stmt->bind_param("ii", $novelId, $currentUserId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) throw new Exception('权限不足');
    $stmt->close();

    // 内部函数：敏感词扫描与处理
    function processSensitiveWords($conn, $content, $authorId, $chapterId = null) {
        global $sensitiveWordTable, $sensitiveLogTable;
        $result = ['status' => 'pass', 'content' => $content, 'message' => ''];
        
        $sql = "SELECT word, replacement, severity_level FROM {$sensitiveWordTable} WHERE status = 'A'";
        $res = $conn->query($sql);
        if (!$res) return $result;

        $blocked = false;
        $warned = false;

        while ($row = $res->fetch_assoc()) {
            $word = $row['word'];
            if (mb_stripos($result['content'], $word) !== false) {
                // Log violation
                $logSql = "INSERT INTO {$sensitiveLogTable} (author_id, chapter_id, detected_word, severity_level, created_at) VALUES (?, ?, ?, ?, NOW())";
                $lStmt = $conn->prepare($logSql);
                $lStmt->bind_param("iisi", $authorId, $chapterId, $word, $row['severity_level']);
                $lStmt->execute();
                $lStmt->close();

                if ($row['severity_level'] == 3) {
                    $blocked = true;
                    break;
                } else if ($row['severity_level'] == 2) {
                    $warned = true;
                    $result['content'] = str_ireplace($word, $row['replacement'], $result['content']);
                } else {
                    $result['content'] = str_ireplace($word, $row['replacement'], $result['content']);
                }
            }
        }

        if ($blocked) {
            $result['status'] = 'blocked';
            $result['message'] = '包含严重违规内容，已禁止提交。';
        } else if ($warned) {
            $result['status'] = 'warned';
            $result['message'] = '包含不当词汇，已被系统自动替换并记录。';
        }
        return $result;
    }

    // 内部函数：清理并计算字数
    function cleanAndCountWords($content) {
        $clean = preg_replace('/[a-zA-Z]+:\/\/[^\s]+/', '', $content); // Strip URLs
        $clean = strip_tags($clean); // Strip HTML
        $count = mb_strlen(preg_replace('/\s+/', '', $clean));
        return ['clean' => $clean, 'count' => $count];
    }

    // ==========================================
    // GET SENSITIVE WORDS
    // ==========================================
    if ($mode === 'get_sensitive_words') {
        $words = [];
        $res = $conn->query("SELECT word, replacement, severity_level FROM {$sensitiveWordTable} WHERE status = 'A'");
        while ($row = $res->fetch_assoc()) {
            $words[] = $row;
        }
        echo safeJsonEncode(['success' => true, 'data' => $words]);
        exit();
    }

    // ==========================================
    // DATA LIST
    // ==========================================
    if ($mode === 'data') {
        $start = max(0, (int)($_REQUEST['start'] ?? 0));
        $length = max(1, min(100, (int)($_REQUEST['length'] ?? 10)));
        
        $countSql = "SELECT COUNT(id) FROM {$chapterTable} WHERE novel_id = ? AND status = 'A'";
        $stmt = $conn->prepare($countSql);
        $stmt->bind_param("i", $novelId);
        $stmt->execute();
        $stmt->bind_result($totalRecords);
        $stmt->fetch();
        $stmt->close();

        $dataSql = "SELECT c.id, c.chapter_number, c.title, c.word_count, c.publish_status, c.scheduled_publish_at, c.updated_at, 
                    (SELECT COUNT(id) FROM {$chapterVersionTable} WHERE chapter_id = c.id) as version_count 
                    FROM {$chapterTable} c WHERE c.novel_id = ? AND c.status = 'A' 
                    ORDER BY c.chapter_number DESC, c.id DESC LIMIT ?, ?";
        
        $rows = [];
        $stmt = $conn->prepare($dataSql);
        $stmt->bind_param("iii", $novelId, $start, $length);
        $stmt->execute();
        $stmt->bind_result($id, $cNum, $title, $wCount, $pStatus, $sTime, $uTime, $vCount);
        while ($stmt->fetch()) {
            $rows[] = [
                'id' => $id, 'chapter_number' => $cNum, 'title' => $title, 'word_count' => $wCount,
                'publish_status' => $pStatus, 'scheduled_publish_at' => $sTime,
                'updated_at' => formatDate($uTime, 'Y-m-d H:i:s'), 'version_count' => $vCount
            ];
        }
        $stmt->close();
        
        echo safeJsonEncode(['draw' => (int)($_REQUEST['draw'] ?? 1), 'recordsTotal' => $totalRecords, 'recordsFiltered' => $totalRecords, 'data' => $rows]);
        exit();
    }

    // ==========================================
    // SAVE / AUTO_SAVE
    // ==========================================
    if ($mode === 'save' || $mode === 'auto_save') {
        $chapterId = (int)($_POST['chapter_id'] ?? 0);
        
        // Detailed Permission check for Add vs Edit
        if ($chapterId === 0) {
            $addError = checkPermissionError('add', $perm, '章节管理', false);
            if ($addError) throw new Exception($addError);
        } else {
            $editError = checkPermissionError('edit', $perm, '章节管理', false);
            if ($editError) throw new Exception($editError);
        }

        $title = trim($_POST['title'] ?? '');
        $cNum = (int)($_POST['chapter_number'] ?? 1);
        $content = trim($_POST['content'] ?? '');
        $pStatus = $_POST['publish_status'] ?? 'draft';
        $sTime = !empty($_POST['scheduled_publish_at']) ? $_POST['scheduled_publish_at'] : null;

        if ($mode === 'auto_save') $pStatus = 'draft';

        $cleanData = cleanAndCountWords($content);
        $content = $cleanData['clean'];
        $wCount = $cleanData['count'];

        if ($wCount > 50000) throw new Exception('字数超过50,000字，请拆分章节');
        if ($mode === 'save' && $wCount < 300 && $pStatus !== 'draft') throw new Exception('非草稿章节字数不能少于300字');

        // Sensitive Word Check
        $scan = processSensitiveWords($conn, $content, $currentUserId, $chapterId ?: null);
        if ($scan['status'] === 'blocked') throw new Exception($scan['message']);
        $content = $scan['content']; 

        if ($pStatus === 'scheduled' && strtotime($sTime) <= time()) {
            throw new Exception('定时时间必须在未来');
        }

        // Fetch old data for audit log if updating
        $oldChapterData = null;
        if ($chapterId > 0) {
            $oldChapterData = fetchAuditRow($conn, $chapterTable, $chapterId);
        }

        $conn->begin_transaction();

        if ($chapterId === 0) {
            // INSERT
            $sql = "INSERT INTO {$chapterTable} (novel_id, author_id, chapter_number, title, content, word_count, publish_status, scheduled_publish_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiississ", $novelId, $currentUserId, $cNum, $title, $content, $wCount, $pStatus, $sTime);
            $stmt->execute();
            $chapterId = $conn->insert_id;
            $stmt->close();

            // Audit Log for Chapter Creation
            if (function_exists('logAudit') && $mode === 'save') {
                $newChapterData = fetchAuditRow($conn, $chapterTable, $chapterId);
                logAudit([
                    'page' => 'Chapter Management',
                    'action' => 'A',
                    'action_message' => 'Author created new chapter',
                    'query' => $sql,
                    'query_table' => $chapterTable,
                    'user_id' => $currentUserId,
                    'record_id' => $chapterId,
                    'record_name' => $title,
                    'new_value' => $newChapterData
                ]);
            }
        } else {
            // UPDATE
            $sql = "UPDATE {$chapterTable} SET chapter_number=?, title=?, content=?, word_count=?, publish_status=?, scheduled_publish_at=?, updated_at=NOW() WHERE id=? AND novel_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ississii", $cNum, $title, $content, $wCount, $pStatus, $sTime, $chapterId, $novelId);
            $stmt->execute();
            $stmt->close();

            // Audit Log for Chapter Update
            if (function_exists('logAudit') && $mode === 'save') {
                $newChapterData = fetchAuditRow($conn, $chapterTable, $chapterId);
                logAudit([
                    'page' => 'Chapter Management',
                    'action' => 'E',
                    'action_message' => 'Author updated chapter',
                    'query' => $sql,
                    'query_table' => $chapterTable,
                    'user_id' => $currentUserId,
                    'record_id' => $chapterId,
                    'record_name' => $title,
                    'old_value' => $oldChapterData,
                    'new_value' => $newChapterData
                ]);
            }
        }

        // CREATE VERSION (Only if Manual Save)
        if ($mode === 'save') {
            $vSql = "SELECT COALESCE(MAX(version_number), 0) + 1 FROM {$chapterVersionTable} WHERE chapter_id = ?";
            $stmt = $conn->prepare($vSql);
            $stmt->bind_param("i", $chapterId);
            $stmt->execute();
            $stmt->bind_result($nextV);
            $stmt->fetch();
            $stmt->close();

            $iSql = "INSERT INTO {$chapterVersionTable} (chapter_id, version_number, title, content, word_count, created_by) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($iSql);
            $stmt->bind_param("iissii", $chapterId, $nextV, $title, $content, $wCount, $currentUserId);
            $stmt->execute();
            $versionId = $conn->insert_id;
            $stmt->close();
            
            if (function_exists('logAudit')) {
                $newVersionData = fetchAuditRow($conn, $chapterVersionTable, $versionId);
                logAudit([
                    'page' => 'Chapter Management',
                    'action' => 'A',
                    'action_message' => 'Author saved chapter version',
                    'query' => $iSql,
                    'query_table' => $chapterVersionTable,
                    'user_id' => $currentUserId,
                    'record_id' => $versionId,
                    'record_name' => $title . ' (v' . $nextV . ')',
                    'new_value' => $newVersionData
                ]);
            }
        }

        $conn->commit();
        
        $msg = '保存成功';
        if ($scan['status'] === 'warned') $msg .= ' (' . $scan['message'] . ')';

        echo safeJsonEncode(['success' => true, 'message' => $msg, 'chapter_id' => $chapterId]);
        exit();
    }

    // ==========================================
    // GET SINGLE / DELETE Logic
    // ==========================================
    $chapterId = (int)($_REQUEST['chapter_id'] ?? 0);
    if ($chapterId > 0 && in_array($mode, ['get', 'delete'])) {
        // Verify chapter belongs to novel
        $chk = "SELECT id FROM {$chapterTable} WHERE id = ? AND novel_id = ? AND status = 'A' LIMIT 1";
        $stmt=$conn->prepare($chk); $stmt->bind_param("ii", $chapterId, $novelId); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows===0) throw new Exception('章节不存在');
        $stmt->close();
        
        if ($mode === 'get') {
            $sql = "SELECT id, chapter_number, title, content, publish_status, scheduled_publish_at FROM {$chapterTable} WHERE id = ?";
            $stmt=$conn->prepare($sql); $stmt->bind_param("i", $chapterId); $stmt->execute();
            $rId=$rNum=$rTitle=$rCont=$rPub=$rSch=null;
            $stmt->bind_result($rId, $rNum, $rTitle, $rCont, $rPub, $rSch);
            $stmt->fetch();
            echo safeJsonEncode(['success'=>true, 'data'=>['id'=>$rId,'chapter_number'=>$rNum,'title'=>$rTitle,'content'=>$rCont,'publish_status'=>$rPub,'scheduled_publish_at'=>$rSch]]);
            exit();
        }
        
        if ($mode === 'delete') {
            // Pre-fetch old data
            $oldRow = fetchAuditRow($conn, $chapterTable, $chapterId);
            $chapterTitle = $oldRow ? $oldRow['title'] : 'Unknown Chapter';

            $sql = "UPDATE {$chapterTable} SET status = 'D', updated_at=NOW() WHERE id = ?";
            $stmt=$conn->prepare($sql); 
            $stmt->bind_param("i", $chapterId); 
            $stmt->execute();
            $stmt->close();

            // Fetch new data explicitly to capture the 'D' status change
            $newRow = fetchAuditRow($conn, $chapterTable, $chapterId);

            if (function_exists('logAudit')) {
                logAudit([
                    'page' => 'Chapter Management',
                    'action' => 'D',
                    'action_message' => 'Author soft deleted chapter',
                    'query' => $sql,
                    'query_table' => $chapterTable,
                    'user_id' => $currentUserId,
                    'record_id' => $chapterId,
                    'record_name' => $chapterTitle,
                    'old_value' => $oldRow,
                    'new_value' => $newRow
                ]);
            }
            echo safeJsonEncode(['success'=>true, 'message'=>'删除成功']);
            exit();
        }
    }

    // ==========================================
    // GET VERSIONS LIST
    // ==========================================
    if ($mode === 'get_versions') {
        $chapterId = (int)($_GET['chapter_id'] ?? 0);
        $versions = [];
        $sql = "SELECT id, version_number, word_count, created_at FROM {$chapterVersionTable} WHERE chapter_id = ? ORDER BY version_number DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $chapterId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['created_at'] = formatDate($row['created_at'], 'Y-m-d H:i');
            $versions[] = $row;
        }
        echo safeJsonEncode(['success' => true, 'data' => $versions]);
        exit();
    }

    // ==========================================
    // GET VERSION DETAIL
    // ==========================================
    if ($mode === 'get_version_detail') {
        $versionId = (int)($_GET['version_id'] ?? 0);
        $sql = "SELECT version_number, title, content FROM {$chapterVersionTable} WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $versionId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        echo safeJsonEncode(['success' => true, 'data' => $result]);
        exit();
    }

} catch (Throwable $e) {
    if (isset($conn) && $conn->ping() && $conn->thread_id) { $conn->rollback(); }
    
    // Detailed log for the backend server
    error_log("Chapter Management API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    
    // Set HTTP 400 Bad Request specifically for Level 3 Block
    if ($e->getMessage() === '包含严重违规内容，已禁止提交。') {
        http_response_code(400);
        echo safeJsonEncode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
    
    $modeStr = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'data';
    
    if ($modeStr === 'data') {
        echo safeJsonEncode([
            'draw' => intval($_REQUEST['draw'] ?? 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => '接口错误，请检查系统日志'
        ]);
    } else {
        echo safeJsonEncode([
            'success' => false, 
            'message' => '系统错误，请稍后再试或联系管理员' 
        ]);
    }
    exit();
}