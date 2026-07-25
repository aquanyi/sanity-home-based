<?php
/**
 * Module 7: Centralized Learning Resource Library
 * api/api_resources.php
 * 
 * Handles: Admin file uploads, editing, deleting, dynamic subject area CRUD,
 *          and profile-matched filtering for parent/student portals.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Public actions: get_subjects for login.html and 'all' (or empty) for loading resources on index.html
if ($action === 'get_subjects' || $action === 'all' || empty($action)) {
    session_write_close();
} else {
    // Auth guard for all other actions
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated. Please log in.']);
        exit;
    }

    $role = $_SESSION['user_role'] ?? '';

    // Write actions require admin/teacher roles
    $write_actions = ['add_subject', 'edit_subject', 'delete_subject', 'upload_resource', 'edit_resource', 'delete_resource'];

    if (in_array($action, $write_actions)) {
        // Subject management is Admin only
        if (in_array($action, ['add_subject', 'edit_subject', 'delete_subject'])) {
            if ($role !== 'admin' && $role !== 'timetabler') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin role required.']);
                exit;
            }
        } else {
            // Resource management is Admin, Teacher, and Timetabler
            if (!in_array($role, ['admin', 'teacher', 'timetabler'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin or Teacher role required.']);
                exit;
            }
        }
    } else {
        // Read actions (viewing resources) are open to all authenticated users
        if (!in_array($role, ['admin', 'teacher', 'parent', 'student', 'timetabler', 'accounts'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access denied. Invalid portal role.']);
            exit;
        }
    }

    // Release session lock early since subsequent operations are database queries
    session_write_close();
}
$uploadDir = '../uploads/resources/';
$dbUploadDir = 'uploads/resources/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Ensure subject_areas table exists dynamically if not created yet
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subject_areas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
    // Seed defaults if table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM subject_areas")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO subject_areas (name) VALUES ('Mathematics'), ('Chemistry'), ('Biology'), ('Physics'), ('English'), ('Swahili');");
    }

    // Schema Check / Migration for images
    // Check and add image_path column to subject_areas
    $checkSubjImage = $pdo->query("SHOW COLUMNS FROM subject_areas LIKE 'image_path'")->fetch();
    if (!$checkSubjImage) {
        $pdo->exec("ALTER TABLE subject_areas ADD COLUMN image_path VARCHAR(255) NULL;");
    }

    // Check and add cover_image column to learning_resources
    $checkResImage = $pdo->query("SHOW COLUMNS FROM learning_resources LIKE 'cover_image'")->fetch();
    if (!$checkResImage) {
        $pdo->exec("ALTER TABLE learning_resources ADD COLUMN cover_image VARCHAR(255) NULL;");
    }

    $checkCat =$pdo->query("SHOW COLUMNS FROM learning_resources LIKE 'resource_category'")->fetch();
    if (!$checkCat)$pdo->exec("ALTER TABLE learning_resources ADD COLUMN resource_category VARCHAR(50) DEFAULT 'academic';");

    $checkAcc =$pdo->query("SHOW COLUMNS FROM learning_resources LIKE 'access_type'")->fetch();
    if (!$checkAcc)$pdo->exec("ALTER TABLE learning_resources ADD COLUMN access_type VARCHAR(50) DEFAULT 'free';");

    $pdo->exec("ALTER TABLE learning_resources MODIFY subject VARCHAR(100) NULL, MODIFY grade_level VARCHAR(50) NULL;");
} catch (\PDOException $e) {}


// ─────────────────────────────────────────────────
// SUBJECT AREAS CRUD (Fetch, Add, Edit, Delete)
// ─────────────────────────────────────────────────
if ($action === 'get_subjects') {
    try {
        $stmt = $pdo->query("SELECT * FROM subject_areas ORDER BY name ASC");
        echo json_encode(['status' => 'success', 'subjects' => $stmt->fetchAll()]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'add_subject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) { echo json_encode(['status' => 'error', 'message' => 'Subject name is required.']); exit; }
    
    $imagePath = null;
    $dbImagePath = null;
    if (isset($_FILES['subject_image']) && $_FILES['subject_image']['error'] === UPLOAD_ERR_OK) {
        $subjDir = '../uploads/subjects/';
        $dbSubjDir = 'uploads/subjects/';
        if (!is_dir($subjDir)) {
            mkdir($subjDir, 0755, true);
        }
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['subject_image']['tmp_name']);
        if (in_array($fileType, $allowedTypes)) {
            $ext = pathinfo($_FILES['subject_image']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('subj_') . '.' . $ext;
            if (move_uploaded_file($_FILES['subject_image']['tmp_name'], $subjDir . $fileName)) {
                $imagePath = $subjDir . $fileName;
                $dbImagePath = $dbSubjDir . $fileName;
            }
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO subject_areas (name, image_path) VALUES (?, ?)");
        $stmt->execute([$name, $dbImagePath]);
        echo json_encode(['status' => 'success', 'message' => "Subject area '{$name}' created successfully!"]);
    } catch (\PDOException $e) {
        if ($imagePath && file_exists($imagePath)) @unlink($imagePath);
        echo json_encode(['status' => 'error', 'message' => $e->getCode() == 23000 ? 'Subject already exists.' : $e->getMessage()]);
    }
    exit;
}

if ($action === 'edit_subject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    if (!$id || empty($name)) { echo json_encode(['status' => 'error', 'message' => 'Valid ID and name required.']); exit; }
    
    try {
        $sql = "UPDATE subject_areas SET name = ?";
        $params = [$name];

        if (isset($_FILES['subject_image']) && $_FILES['subject_image']['error'] === UPLOAD_ERR_OK) {
            $subjDir = '../uploads/subjects/';
            $dbSubjDir = 'uploads/subjects/';
            if (!is_dir($subjDir)) {
                mkdir($subjDir, 0755, true);
            }
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($_FILES['subject_image']['tmp_name']);
            if (in_array($fileType, $allowedTypes)) {
                $ext = pathinfo($_FILES['subject_image']['name'], PATHINFO_EXTENSION);
                $fileName = uniqid('subj_') . '.' . $ext;
                if (move_uploaded_file($_FILES['subject_image']['tmp_name'], $subjDir . $fileName)) {
                    // Fetch and delete old image
                    $old = $pdo->prepare("SELECT image_path FROM subject_areas WHERE id = ?");
                    $old->execute([$id]);
                    $oldImg = $old->fetchColumn();
                    if ($oldImg) {
                        $oldImgFs = '../' . $oldImg;
                        if (file_exists($oldImgFs)) @unlink($oldImgFs);
                    }

                    $sql .= ", image_path = ?";
                    $params[] = $dbSubjDir . $fileName;
                }
            }
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'message' => 'Subject updated successfully.']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_subject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { echo json_encode(['status' => 'error', 'message' => 'Subject ID required.']); exit; }
    try {
        // Delete image first
        $fetch = $pdo->prepare("SELECT image_path FROM subject_areas WHERE id = ?");
        $fetch->execute([$id]);
        $img = $fetch->fetchColumn();
        if ($img) {
            $imgFs = '../' . $img;
            if (file_exists($imgFs)) @unlink($imgFs);
        }

        $stmt = $pdo->prepare("DELETE FROM subject_areas WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Subject area removed.']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


// ─────────────────────────────────────────────────
// ADMIN: Upload a new resource
// ─────────────────────────────────────────────────
if ($action === 'upload_resource' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $subject       = trim($_POST['subject'] ?? '');
    $grade_level   = trim($_POST['grade_level'] ?? '');
    $material_type = trim($_POST['material_type'] ?? 'other');
    $uploaded_by   = filter_input(INPUT_POST, 'uploaded_by', FILTER_VALIDATE_INT) ?: 1;
    $resource_category = trim($_POST['resource_category'] ?? 'academic');
    $access_type = trim($_POST['access_type'] ?? 'free');

    if (!$title || !$description) {
        echo json_encode(['status' => 'error', 'message' => 'Title and description are required.']); exit;
    }
    if ($resource_category === 'academic' && (!$subject || !$grade_level)) {
        echo json_encode(['status' => 'error', 'message' => 'Subject and Grade Level are required for academic materials.']); exit;
    }

    if (!isset($_FILES['resource_file']) || $_FILES['resource_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'A valid file upload is required.']); exit;
    }

    $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                     'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                     'image/jpeg', 'image/png', 'application/zip', 'application/x-zip-compressed'];
    $fileType = mime_content_type($_FILES['resource_file']['tmp_name']);

    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'Unsupported file type. Allowed: PDF, DOCX, PPT, JPG, PNG, ZIP.']); exit;
    }

    $ext      = pathinfo($_FILES['resource_file']['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('res_') . '.' . $ext;
    $filePath = $uploadDir . $fileName;
    $dbFilePath = $dbUploadDir . $fileName;

    if (!move_uploaded_file($_FILES['resource_file']['tmp_name'], $filePath)) {
        echo json_encode(['status' => 'error', 'message' => 'File upload failed. Check server permissions.']); exit;
    }

    // Optional cover image upload
    $coverImagePath = null;
    $dbCoverImagePath = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $allowedImgTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $imgType = mime_content_type($_FILES['cover_image']['tmp_name']);
        if (in_array($imgType, $allowedImgTypes)) {
            $imgExt = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            $imgName = uniqid('cover_') . '.' . $imgExt;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadDir . $imgName)) {
                $coverImagePath = $uploadDir . $imgName;
                $dbCoverImagePath = $dbUploadDir . $imgName;
            }
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO learning_resources (title, description, file_path, file_type, subject, grade_level, material_type, uploaded_by, cover_image, resource_category, access_type)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([$title, $description, $dbFilePath, $ext, $subject, $grade_level, $material_type, $uploaded_by, $dbCoverImagePath, $resource_category, $access_type]);
        echo json_encode(['status' => 'success', 'message' => "Resource '{$title}' uploaded successfully.", 'resource_id' => $pdo->lastInsertId()]);
    } catch (\PDOException $e) {
        @unlink($filePath);
        if ($coverImagePath) @unlink($coverImagePath);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


// ─────────────────────────────────────────────────
// ADMIN: Edit an existing resource details
// ─────────────────────────────────────────────────
if ($action === 'edit_resource' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $resource_id   = filter_input(INPUT_POST, 'resource_id', FILTER_VALIDATE_INT);
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $subject       = trim($_POST['subject'] ?? '');
    $grade_level   = trim($_POST['grade_level'] ?? '');
    $material_type = trim($_POST['material_type'] ?? 'other');
    $resource_category = trim($_POST['resource_category'] ?? 'academic');
    $access_type = trim($_POST['access_type'] ?? 'free');

    if (!$resource_id || !$title || !$description) {
        echo json_encode(['status' => 'error', 'message' => 'ID, title and description are required.']); exit;
    }
    if ($resource_category === 'academic' && (!$subject || !$grade_level)) {
        echo json_encode(['status' => 'error', 'message' => 'Subject and Grade Level are required for academic materials.']); exit;
    }

    try {
        $sql = "UPDATE learning_resources SET title = ?, description = ?, subject = ?, grade_level = ?, material_type = ?, resource_category = ?, access_type = ?";
        $params = [$title, $description, $subject, $grade_level, $material_type, $resource_category, $access_type];

        // Optional new file upload
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
            $ext      = pathinfo($_FILES['resource_file']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('res_') . '.' . $ext;
            $filePath = $uploadDir . $fileName;
            $dbFilePath = $dbUploadDir . $fileName;
            if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $filePath)) {
                // Fetch and delete old file
                $old = $pdo->prepare("SELECT file_path FROM learning_resources WHERE id = ?");
                $old->execute([$resource_id]);
                $oldRes = $old->fetch();
                if ($oldRes && $oldRes['file_path']) {
                    $oldResFs = '../' . $oldRes['file_path'];
                    if (file_exists($oldResFs)) @unlink($oldResFs);
                }

                $sql .= ", file_path = ?, file_type = ?";
                $params[] = $dbFilePath;
                $params[] = $ext;
            }
        }

        // Optional new cover image upload
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $allowedImgTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $imgType = mime_content_type($_FILES['cover_image']['tmp_name']);
            if (in_array($imgType, $allowedImgTypes)) {
                $imgExt = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                $imgName = uniqid('cover_') . '.' . $imgExt;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadDir . $imgName)) {
                    // Fetch and delete old cover image
                    $old = $pdo->prepare("SELECT cover_image FROM learning_resources WHERE id = ?");
                    $old->execute([$resource_id]);
                    $oldCover = $old->fetchColumn();
                    if ($oldCover) {
                        $oldCoverFs = '../' . $oldCover;
                        if (file_exists($oldCoverFs)) @unlink($oldCoverFs);
                    }

                    $sql .= ", cover_image = ?";
                    $params[] = $dbUploadDir . $imgName;
                }
            }
        }

        $sql .= " WHERE id = ?";
        $params[] = $resource_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'message' => "Resource updated successfully."]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


// ─────────────────────────────────────────────────
// FETCH RESOURCES (With Subject Filter support)
// ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($action === 'all' || empty($action))) {
    $subject_filter = trim($_GET['subject'] ?? '');
    
    // ── ETag Caching Optimization ──
    try {
        // Dynamically detect if updated_at column exists
        $hasUpdatedAt = false;
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM learning_resources LIKE 'updated_at'")->fetch();
            if ($checkCol) {
                $hasUpdatedAt = true;
            }
        } catch (\Exception $eCol) {}

        $resTimeCol = $hasUpdatedAt ? 'updated_at' : 'created_at';

        // Check sum/max values to construct the ETag
        $resQuery = $pdo->query("SELECT COUNT(*) as count, CAST(COALESCE(MAX($resTimeCol), '1970-01-01 00:00:00') AS CHAR) as max_time FROM learning_resources")->fetch();
        $subjQuery = $pdo->query("SELECT COUNT(*) as count, CAST(COALESCE(MAX(created_at), '1970-01-01 00:00:00') AS CHAR) as max_time FROM subject_areas")->fetch();

        $etagRaw = ($resQuery['count'] ?? 0) . '_' . ($resQuery['max_time'] ?? '') . '_' . ($subjQuery['count'] ?? 0) . '_' . ($subjQuery['max_time'] ?? '') . '_' . $subject_filter;
        $etag = md5($etagRaw);

        // Tell the browser to cache but check with server every time
        header('Cache-Control: public, max-age=0, must-revalidate');
        header('ETag: "' . $etag . '"');

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if (trim($ifNoneMatch, '"') === $etag) {
            http_response_code(304);
            exit;
        }
    } catch (\PDOException $eCache) {
        // Fallback silently if tables do not exist yet (they will be created below)
    }

    try {
        $sql = "
            SELECT lr.*, u.name as uploaded_by_name, sa.image_path as subject_image
            FROM learning_resources lr
            LEFT JOIN admins u ON lr.uploaded_by = u.id
            LEFT JOIN subject_areas sa ON lr.subject = sa.name
        ";
        $params = [];
        if (!empty($subject_filter)) {
            $sql .= " WHERE lr.subject = ?";
            $params[] = $subject_filter;
        }
        $sql .= " ORDER BY lr.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'resources' => $stmt->fetchAll()]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


// ─────────────────────────────────────────────────
// DELETE RESOURCE
// ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_resource') {
    $resource_id = filter_input(INPUT_POST, 'resource_id', FILTER_VALIDATE_INT);
    if (!$resource_id) { echo json_encode(['status' => 'error', 'message' => 'resource_id required.']); exit; }

    try {
        $fetch = $pdo->prepare("SELECT file_path, cover_image FROM learning_resources WHERE id = ?");
        $fetch->execute([$resource_id]);
        $res = $fetch->fetch();
        if ($res) {
            if ($res['file_path']) {
                $filePathFs = '../' . $res['file_path'];
                if (file_exists($filePathFs)) @unlink($filePathFs);
            }
            if ($res['cover_image']) {
                $coverImageFs = '../' . $res['cover_image'];
                if (file_exists($coverImageFs)) @unlink($coverImageFs);
            }
        }
        $del = $pdo->prepare("DELETE FROM learning_resources WHERE id = ?");
        $del->execute([$resource_id]);
        echo json_encode(['status' => 'success', 'message' => 'Resource deleted.']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action or method.']);
?>
