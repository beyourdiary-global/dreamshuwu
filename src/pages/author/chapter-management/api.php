<?php
// Path: src/pages/author/chapter-management/api.php
try {
    require_once dirname(__DIR__, 4) . '/common.php';

    // Session & Authentication Check
    requireLogin();
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $currentUserId = sessionInt('user_id');
    requireApprovedAuthor($conn, $currentUserId);

    // [CRITICAL FIX] Combine input() and post() for mode and novel_id detection
    $modeStr = input('mode') !== '' ? input('mode') : post('mode');
    $mode = strtolower($modeStr ?: 'data');
    
    $novelIdInput = input('novel_id') !== '' ? input('novel_id') : post('novel_id');
    $novelId = (int)$novelIdInput;
    
    $auditPage = 'Chapter Management';
    
    if ($novelId <= 0) throw new Exception('小说ID缺失');

    // Permission Logic
    if (defined('URL_AUTHOR_CHAPTER_MANAGEMENT')) {
        $menuUrl = str_replace('{id}/', '', parse_url(URL_AUTHOR_CHAPTER_MANAGEMENT, PHP_URL_PATH));
    } else {
        $menuUrl = '/author/novel/chapters/';
    }
    
    $perm = hasPagePermission($conn, $menuUrl);

    if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
        $systemPath = defined('PATH_AUTHOR_CHAPTER_MANAGEMENT') ? PATH_AUTHOR_CHAPTER_MANAGEMENT : '/src/pages/author/chapter-management/index.php';
        $perm = hasPagePermission($conn, $systemPath);
    }
    
    if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
        $legacyPath = defined('PATH_AUTHOR_NOVEL_MANAGEMENT') ? ('/' . ltrim(PATH_AUTHOR_NOVEL_MANAGEMENT, '/')) : '/src/pages/author/novel-management/index.php';
        $perm = hasPagePermission($conn, $legacyPath);
    }

    if (in_array($mode, ['data', 'get', 'get_sensitive_words', 'get_versions', 'get_version_detail'])) {
        if (empty($perm) || empty($perm->view)) throw new Exception('无查看权限 (No View Permission)');
    }
    if ($mode === 'delete') {
        if (empty($perm) || empty($perm->delete)) throw new Exception('无删除权限 (No Delete Permission)');
    }
    if (in_array($mode, ['save', 'auto_save'])) {
        if (empty($perm) || (empty($perm->add) && empty($perm->edit))) throw new Exception('无编辑权限 (No Edit Permission)');
    }

    $novelTable = defined('NOVEL') ? NOVEL : 'novel';
    $chapterTable = defined('CHAPTER') ? CHAPTER : 'chapter';
    $chapterVersionTable = defined('CHAPTER_VERSION') ? CHAPTER_VERSION : 'chapter_version';
    $sensitiveWordTable = defined('SENSITIVE_WORD') ? SENSITIVE_WORD : 'sensitive_word';
    $sensitiveLogTable = defined('SENSITIVE_WORD_LOG') ? SENSITIVE_WORD_LOG : 'sensitive_word_log';

    // Verify novel ownership
    $checkNovel = "SELECT id FROM {$novelTable} WHERE id = ? AND author_id = ? AND status = 'A' LIMIT 1";
    $stmt = $conn->prepare($checkNovel);
    if (!$stmt) throw new Exception("Database Error: " . $conn->error);
    $stmt->bind_param("ii", $novelId, $currentUserId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) throw new Exception('权限不足或小说不存在');
    $stmt->close();

    // Internal Functions
    if (!function_exists('processSensitiveWords')) {
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
                    $logSql = "INSERT INTO {$sensitiveLogTable} (author_id, chapter_id, detected_word, severity_level, created_at) VALUES (?, ?, ?, ?, NOW())";
                    $lStmt = $conn->prepare($logSql);
                    if ($lStmt) {
                        $lStmt->bind_param("iisi", $authorId, $chapterId, $word, $row['severity_level']);
                        $lStmt->execute();
                        $lStmt->close();
                    }

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
    }

    if (!function_exists('cleanAndCountWords')) {
        function cleanAndCountWords($content) {
            $clean = preg_replace('/[a-zA-Z]+:\/\/[^\s]+/', '', $content); 
            $clean = strip_tags($clean); 
            $count = mb_strlen(preg_replace('/\s+/', '', $clean));
            return ['clean' => $clean, 'count' => $count];
        }
    }

    // Get Sensitive Words
    if ($mode === 'get_sensitive_words') {
        $words = [];
        $res = $conn->query("SELECT word, replacement, severity_level FROM {$sensitiveWordTable} WHERE status = 'A'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $words[] = $row;
            }
        }
        echo safeJsonEncode(['success' => true, 'data' => $words]);
        exit();
    }

    // Data List
    if ($mode === 'data') {
        // [CRITICAL FIX] Ensure DataTables params pull from POST first
        $drawInput = post('draw') !== '' ? post('draw') : input('draw');
        $startInput = post('start') !== '' ? post('start') : input('start');
        $lengthInput = post('length') !== '' ? post('length') : input('length');
        
        $draw   = (int)($drawInput ?: 1);
        $start  = max(0, (int)($startInput ?: 0));
        $length = max(1, min(100, (int)($lengthInput ?: 10)));
        
        $countSql = "SELECT COUNT(id) FROM {$chapterTable} WHERE novel_id = ? AND status = 'A'";
        $stmt = $conn->prepare($countSql);
        if (!$stmt) throw new Exception("Database Error: " . $conn->error);
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
        if (!$stmt) throw new Exception("Database Error: " . $conn->error);
        $stmt->bind_param("iii", $novelId, $start, $length);
        $stmt->execute();
        $id=$cNum=$title=$wCount=$pStatus=$sTime=$uTime=$vCount=null;
        $stmt->bind_result($id, $cNum, $title, $wCount, $pStatus, $sTime, $uTime, $vCount);
        while ($stmt->fetch()) {
            $rows[] = [
                'id' => $id, 'chapter_number' => $cNum, 'title' => $title, 'word_count' => $wCount,
                'publish_status' => $pStatus, 'scheduled_publish_at' => $sTime,
                'updated_at' => formatDate($uTime, 'Y-m-d H:i:s'), 'version_count' => $vCount
            ];
        }
        $stmt->close();
        
        echo safeJsonEncode(['draw' => $draw, 'recordsTotal' => $totalRecords, 'recordsFiltered' => $totalRecords, 'data' => $rows]);
        exit();
    }

    // Save Logic
    if ($mode === 'save' || $mode === 'auto_save') {
        // Pre-define queries for execution and logging
        $insertSql = "INSERT INTO {$chapterTable} (novel_id, author_id, chapter_number, title, content, word_count, publish_status, scheduled_publish_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $updateSql = "UPDATE {$chapterTable} SET chapter_number=?, title=?, content=?, word_count=?, publish_status=?, scheduled_publish_at=?, updated_at=NOW() WHERE id=? AND novel_id=?";
        $insertVersionSql = "INSERT INTO {$chapterVersionTable} (chapter_id, version_number, title, content, word_count, created_by) VALUES (?, ?, ?, ?, ?, ?)";

        // [FIX] Safely pull from POST
        $chapterIdInput = post('chapter_id') !== '' ? post('chapter_id') : input('chapter_id');
        $chapterId = (int)$chapterIdInput;
        
        $title     = postSpaceFilter('title');
        $cNum      = (int)post('chapter_number') ?: 1;
        $content   = postSpaceFilter('content');
        $pStatus   = post('publish_status') ?: 'draft';
        $sTime     = post('scheduled_publish_at') ?: null;

        if ($mode === 'auto_save') $pStatus = 'draft';

        $cleanData = cleanAndCountWords($content);
        $content = $cleanData['clean'];
        $wCount = $cleanData['count'];

        if ($wCount > 50000) throw new Exception('字数超过50,000字，请拆分章节');
        if ($mode === 'save' && $wCount < 300 && $pStatus !== 'draft') throw new Exception('非草稿章节字数不能少于300字');

        $scan = processSensitiveWords($conn, $content, $currentUserId, $chapterId ?: null);
        if ($scan['status'] === 'blocked') throw new Exception($scan['message']);
        $content = $scan['content']; 

        if ($pStatus === 'scheduled' && strtotime($sTime) <= time()) {
            throw new Exception('定时时间必须在未来');
        }

        // Fetch Old Data using bind_result
        $oldChapterData = null;
        if ($chapterId > 0) {
            $osql = "SELECT id, title, chapter_number, content, word_count, publish_status, scheduled_publish_at FROM {$chapterTable} WHERE id = ?";
            $ostmt = $conn->prepare($osql);
            if ($ostmt) {
                $ostmt->bind_param("i", $chapterId);
                $ostmt->execute();
                $oId=$oTitle=$oNum=$oContent=$oWc=$oPub=$oSch=null;
                $ostmt->bind_result($oId, $oTitle, $oNum, $oContent, $oWc, $oPub, $oSch);
                if ($ostmt->fetch()) {
                    $oldChapterData = [
                        'id' => $oId, 
                        'title' => $oTitle, 
                        'chapter_number' => $oNum,
                        'content' => $oContent,
                        'word_count' => $oWc, 
                        'publish_status' => $oPub,
                        'scheduled_publish_at' => $oSch ? date('Y-m-d H:i:s', strtotime($oSch)) : null
                    ];
                }
                $ostmt->close();
            }

            // Global NO-CHANGE Check
            if ($mode === 'save' && $oldChapterData && function_exists('checkNoChangesAndRedirect')) {
                $newData = [
                    'title' => $title,
                    'chapter_number' => $cNum,
                    'content' => $content,
                    'word_count' => $wCount,
                    'publish_status' => $pStatus,
                    'scheduled_publish_at' => $sTime ? date('Y-m-d H:i:s', strtotime($sTime)) : null
                ];

                $noChanges = checkNoChangesAndRedirect($newData, $oldChapterData);
                
                if ($noChanges !== false) {
                    echo safeJsonEncode([
                        'success' => false, 
                        'is_no_change' => true, 
                        'message' => $noChanges['message']
                    ]);
                    exit();
                }
            }
        }

        $conn->begin_transaction();

        if ($chapterId == 0) {
            $stmt = $conn->prepare($insertSql);
            if (!$stmt) throw new Exception("Database Error: " . $conn->error);
            $stmt->bind_param("iiississ", $novelId, $currentUserId, $cNum, $title, $content, $wCount, $pStatus, $sTime);
            $stmt->execute();
            $chapterId = $conn->insert_id;
            $stmt->close();

            // Log Insert
            if (function_exists('logAudit') && $mode === 'save') {
                logAudit([
                    'page'           => $auditPage,
                    'action'         => 'A',
                    'action_message' => 'Author created new chapter',
                    'query'          => $insertSql,
                    'query_table'    => $chapterTable,
                    'user_id'        => $currentUserId,
                    'record_id'      => $chapterId,
                    'record_name'    => $title,
                    'new_value'      => [
                        'novel_id' => $novelId,
                        'title' => $title,
                        'chapter_number' => $cNum,
                        'word_count' => $wCount,
                        'publish_status' => $pStatus,
                        'scheduled_publish_at' => $sTime
                    ]
                ]);
            }
        } else {
            $stmt = $conn->prepare($updateSql);
            if (!$stmt) throw new Exception("Database Error: " . $conn->error);
            $stmt->bind_param("ississii", $cNum, $title, $content, $wCount, $pStatus, $sTime, $chapterId, $novelId);
            $stmt->execute();
            $stmt->close();

            // Log Update
            if (function_exists('logAudit') && $mode === 'save') {
                logAudit([
                    'page'           => $auditPage,
                    'action'         => 'E',
                    'action_message' => 'Author updated chapter',
                    'query'          => $updateSql,
                    'query_table'    => $chapterTable,
                    'user_id'        => $currentUserId,
                    'record_id'      => $chapterId,
                    'record_name'    => $title,
                    'old_value'      => $oldChapterData,
                    'new_value'      => [
                        'title' => $title,
                        'chapter_number' => $cNum,
                        'content' => $content,
                        'word_count' => $wCount,
                        'publish_status' => $pStatus,
                        'scheduled_publish_at' => $sTime
                    ]
                ]);
            }
        }

        if ($mode === 'save') {
            $vSql = "SELECT COALESCE(MAX(version_number), 0) + 1 FROM {$chapterVersionTable} WHERE chapter_id = ?";
            $stmt = $conn->prepare($vSql);
            if ($stmt) {
                $stmt->bind_param("i", $chapterId);
                $stmt->execute();
                $stmt->bind_result($nextV);
                $stmt->fetch();
                $stmt->close();

                $stmt2 = $conn->prepare($insertVersionSql);
                if ($stmt2) {
                    $stmt2->bind_param("iissii", $chapterId, $nextV, $title, $content, $wCount, $currentUserId);
                    $stmt2->execute();
                    $versionId = $conn->insert_id;
                    $stmt2->close();

                    if (function_exists('logAudit')) {
                        logAudit([
                            'page'           => $auditPage,
                            'action'         => 'A',
                            'action_message' => 'Author saved chapter version',
                            'query'          => $insertVersionSql,
                            'query_table'    => $chapterVersionTable,
                            'user_id'        => $currentUserId,
                            'record_id'      => $versionId,
                            'record_name'    => $title . ' (v' . $nextV . ')',
                            'new_value'      => [
                                'chapter_id' => $chapterId,
                                'version_number' => $nextV,
                                'title' => $title,
                                'word_count' => $wCount
                            ]
                        ]);
                    }
                }
            }
        }

        $conn->commit();
        $msg = '保存成功';
        if ($scan['status'] === 'warned') $msg .= ' (' . $scan['message'] . ')';

        echo safeJsonEncode(['success' => true, 'message' => $msg, 'chapter_id' => $chapterId]);
        exit();
    }

    // [CRITICAL FIX] Ensure chapter ID is caught for Delete and View actions
    $chapterIdInput2 = post('chapter_id') !== '' ? post('chapter_id') : input('chapter_id');
    $chapterId = (int)$chapterIdInput2;
    
    if ($chapterId > 0 && in_array($mode, ['get', 'delete'])) {
        $chk = "SELECT id FROM {$chapterTable} WHERE id = ? AND novel_id = ? AND status = 'A' LIMIT 1";
        $stmt=$conn->prepare($chk); 
        $stmt->bind_param("ii", $chapterId, $novelId); 
        $stmt->execute(); 
        $stmt->store_result();
        if ($stmt->num_rows===0) throw new Exception('章节不存在');
        $stmt->close();
        
        if ($mode === 'get') {
            $sql = "SELECT id, chapter_number, title, content, publish_status, scheduled_publish_at FROM {$chapterTable} WHERE id = ?";
            $stmt=$conn->prepare($sql); 
            if (!$stmt) throw new Exception("Database Error: " . $conn->error);
            $stmt->bind_param("i", $chapterId); 
            $stmt->execute();
            $rId=$rNum=$rTitle=$rCont=$rPub=$rSch=null;
            $stmt->bind_result($rId, $rNum, $rTitle, $rCont, $rPub, $rSch);
            $stmt->fetch();
            echo safeJsonEncode(['success'=>true, 'data'=>['id'=>$rId,'chapter_number'=>$rNum,'title'=>$rTitle,'content'=>$rCont,'publish_status'=>$rPub,'scheduled_publish_at'=>$rSch]]);
            exit();
        }
        
        if ($mode === 'delete') {
            $delSql = "UPDATE {$chapterTable} SET status = 'D', updated_at=NOW() WHERE id = ?";
            
            $oldRow = null;
            $osql = "SELECT id, title, chapter_number FROM {$chapterTable} WHERE id = ?";
            $ostmt = $conn->prepare($osql);
            if ($ostmt) {
                $ostmt->bind_param("i", $chapterId);
                $ostmt->execute();
                $oId=$oTitle=$oNum=null;
                $ostmt->bind_result($oId, $oTitle, $oNum);
                if ($ostmt->fetch()) {
                    $oldRow = ['id' => $oId, 'title' => $oTitle, 'chapter_number' => $oNum];
                }
                $ostmt->close();
            }

            $stmt=$conn->prepare($delSql); 
            if (!$stmt) throw new Exception("Database Error: " . $conn->error);
            $stmt->bind_param("i", $chapterId); 
            $stmt->execute();
            $stmt->close();

            if (function_exists('logAudit')) {
                logAudit([
                    'page'           => $auditPage,
                    'action'         => 'D',
                    'action_message' => 'Author soft deleted chapter',
                    'query'          => $delSql,
                    'query_table'    => $chapterTable,
                    'user_id'        => $currentUserId,
                    'record_id'      => $chapterId,
                    'record_name'    => $oldRow['title'] ?? 'Unknown',
                    'old_value'      => $oldRow
                ]);
            }

            echo safeJsonEncode(['success'=>true, 'message'=>'删除成功']);
            exit();
        }
    }

    // Versions
    if ($mode === 'get_versions') {
        $cIdInput = post('chapter_id') !== '' ? post('chapter_id') : input('chapter_id');
        $chapterId = (int)$cIdInput;
        
        $versions = [];
        $sql = "SELECT id, version_number, word_count, created_at FROM {$chapterVersionTable} WHERE chapter_id = ? ORDER BY version_number DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Database Error: " . $conn->error);
        $stmt->bind_param("i", $chapterId);
        $stmt->execute();
        $vId=$vNum=$vWc=$vDate=null;
        $stmt->bind_result($vId, $vNum, $vWc, $vDate);
        while ($stmt->fetch()) {
            $versions[] = [
                'id' => $vId,
                'version_number' => $vNum,
                'word_count' => $vWc,
                'created_at' => formatDate($vDate, 'Y-m-d H:i')
            ];
        }
        $stmt->close();
        echo safeJsonEncode(['success' => true, 'data' => $versions]);
        exit();
    }

    if ($mode === 'get_version_detail') {
        $vIdInput = post('version_id') !== '' ? post('version_id') : input('version_id');
        $versionId = (int)$vIdInput;
        
        $sql = "SELECT version_number, title, content FROM {$chapterVersionTable} WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Database Error: " . $conn->error);
        $stmt->bind_param("i", $versionId);
        $stmt->execute();
        $vNum=$vTitle=$vContent=null;
        $stmt->bind_result($vNum, $vTitle, $vContent);
        $stmt->fetch();
        $stmt->close();
        echo safeJsonEncode(['success' => true, 'data' => ['version_number' => $vNum, 'title' => $vTitle, 'content' => $vContent]]);
        exit();
    }

} catch (Throwable $e) {
    if (isset($conn) && $conn->ping() && $conn->thread_id) { $conn->rollback(); }
    
    error_log("Chapter Management API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    
    // [CRITICAL FIX] Ensure error handler correctly detects mode even from POST
    $modeStrErr = input('mode') !== '' ? input('mode') : post('mode');
    $modeErr = strtolower($modeStrErr ?: 'data');
    
    if ($modeErr === 'data') {
        $drawErr = post('draw') !== '' ? post('draw') : input('draw');
        echo safeJsonEncode(['draw' => (int)($drawErr ?: 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()]);
    } else {
        echo safeJsonEncode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}