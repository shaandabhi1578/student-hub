<?php
// Practical 12: Event Management CRUD with Poster Upload, File Validation, and Security
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_login(); // Protected page
$user = get_current_user_data();

$msg = "";
$msgType = "";
$uploadDir = __DIR__ . '/uploads/posters/';

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// -------------------------------------------------------------
// POST Actions: Add Event, Edit Event, Delete Event
// -------------------------------------------------------------
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST") {
    $action = $_POST['action'] ?? '';

    // Helper: Validate and upload poster image
    function handlePosterUpload($fileInputName, &$uploadError) {
        global $uploadDir;
        if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // No file uploaded
        }

        $file = $_FILES[$fileInputName];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadError = "File upload failed with error code: " . $file['error'];
            return false;
        }

        // 1. File Size Validation (Max 2 MB)
        $maxBytes = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $maxBytes) {
            $uploadError = "File too large: Poster image must not exceed 2 MB (uploaded " . round($file['size']/1024/1024, 2) . " MB).";
            return false;
        }

        // 2. File Extension Validation
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            $uploadError = "Invalid file extension: Only JPG, JPEG, PNG, and WEBP formats are allowed.";
            return false;
        }

        // 3. MIME Content Verification using getimagesize()
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $uploadError = "Security Validation Failed: The uploaded file is not a valid image.";
            return false;
        }
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($imageInfo['mime'], $allowedMimes)) {
            $uploadError = "Security Validation Failed: MIME type '{$imageInfo['mime']}' is not permitted.";
            return false;
        }

        // 4. Secure File Renaming (Practical 12 Advanced: Avoid collision and traversal)
        $uniqueFilename = "event_poster_" . date("Ymd_His") . "_" . bin2hex(random_bytes(6)) . "." . $ext;
        $targetPath = $uploadDir . $uniqueFilename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $uniqueFilename;
        } else {
            $uploadError = "Could not move uploaded poster to destination directory.";
            return false;
        }
    }

    // 1. CREATE EVENT
    if ($action === 'create') {
        $title       = trim(htmlspecialchars($_POST['title'] ?? ''));
        $category    = trim(htmlspecialchars($_POST['category'] ?? 'Technical'));
        $description = trim(htmlspecialchars($_POST['description'] ?? ''));
        $event_date  = trim($_POST['event_date'] ?? '');
        $location    = trim(htmlspecialchars($_POST['location'] ?? ''));
        $seats       = intval($_POST['seats'] ?? 60);
        $status      = $_POST['status'] ?? 'Open';

        if (empty($title) || empty($event_date) || empty($location)) {
            $msg = "Please provide event title, date, and location.";
            $msgType = "error";
        } else {
            $uploadError = "";
            $posterFilename = handlePosterUpload('poster_image', $uploadError);

            if ($posterFilename === false) {
                $msg = $uploadError;
                $msgType = "error";
            } else {
                if ($posterFilename === null) {
                    $posterFilename = "poster_tech_symposium.jpg"; // Default placeholder
                }

                $stmt = $conn->prepare("INSERT INTO events (title, category, description, event_date, location, seats, available_seats, status, poster_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssiiss", $title, $category, $description, $event_date, $location, $seats, $seats, $status, $posterFilename);
                if ($stmt->execute()) {
                    $newId = $conn->insert_id;
                    $msg = "Event '<strong>" . htmlspecialchars($title) . "</strong>' created successfully with poster validation! (Event ID: #$newId)";
                    $msgType = "success";
                    log_audit($conn, "EVENT_CREATE", "Created event #$newId: $title (Poster: $posterFilename)", $user['username'], $user['role']);
                } else {
                    $msg = "Failed to create event: " . $conn->error;
                    $msgType = "error";
                }
                $stmt->close();
            }
        }
    }

    // 2. UPDATE EVENT
    elseif ($action === 'update') {
        $id          = intval($_POST['event_id'] ?? 0);
        $title       = trim(htmlspecialchars($_POST['title'] ?? ''));
        $category    = trim(htmlspecialchars($_POST['category'] ?? 'Technical'));
        $description = trim(htmlspecialchars($_POST['description'] ?? ''));
        $event_date  = trim($_POST['event_date'] ?? '');
        $location    = trim(htmlspecialchars($_POST['location'] ?? ''));
        $seats       = intval($_POST['seats'] ?? 60);
        $status      = $_POST['status'] ?? 'Open';

        if ($id <= 0 || empty($title) || empty($event_date)) {
            $msg = "Invalid event details for update.";
            $msgType = "error";
        } else {
            $uploadError = "";
            $newPoster = handlePosterUpload('edit_poster_image', $uploadError);

            if ($newPoster === false) {
                $msg = $uploadError;
                $msgType = "error";
            } else {
                if ($newPoster !== null) {
                    // Update with new poster
                    $upd = $conn->prepare("UPDATE events SET title = ?, category = ?, description = ?, event_date = ?, location = ?, seats = ?, status = ?, poster_image = ? WHERE event_id = ?");
                    $upd->bind_param("sssssissi", $title, $category, $description, $event_date, $location, $seats, $status, $newPoster, $id);
                    log_audit($conn, "EVENT_POSTER_UPLOAD", "Replaced poster image for event #$id: $newPoster", $user['username'], $user['role']);
                } else {
                    // Keep existing poster
                    $upd = $conn->prepare("UPDATE events SET title = ?, category = ?, description = ?, event_date = ?, location = ?, seats = ?, status = ? WHERE event_id = ?");
                    $upd->bind_param("sssssisi", $title, $category, $description, $event_date, $location, $seats, $status, $id);
                }

                if ($upd->execute()) {
                    $msg = "Event #$id updated successfully.";
                    $msgType = "success";
                    log_audit($conn, "EVENT_UPDATE", "Updated event #$id: $title", $user['username'], $user['role']);
                } else {
                    $msg = "Failed to update event: " . $conn->error;
                    $msgType = "error";
                }
                $upd->close();
            }
        }
    }

    // 3. DELETE EVENT
    elseif ($action === 'delete') {
        $id = intval($_POST['event_id'] ?? 0);
        // Fetch poster filename to optionally unlink
        $fstmt = $conn->prepare("SELECT poster_image, title FROM events WHERE event_id = ?");
        $fstmt->bind_param("i", $id);
        $fstmt->execute();
        $ev = $fstmt->get_result()->fetch_assoc();
        $fstmt->close();

        $del = $conn->prepare("DELETE FROM events WHERE event_id = ?");
        $del->bind_param("i", $id);
        if ($del->execute()) {
            $msg = "Event record #$id deleted successfully.";
            $msgType = "success";
            log_audit($conn, "EVENT_DELETE", "Deleted event #$id (" . ($ev['title'] ?? '') . ")", $user['username'], $user['role']);
        } else {
            $msg = "Delete failed: " . $conn->error;
            $msgType = "error";
        }
        $del->close();
    }
}

// -------------------------------------------------------------
// GET Query: Filter by Category & Status
// -------------------------------------------------------------
$filterCategory = trim($_GET['category'] ?? 'All');
$filterStatus   = trim($_GET['status'] ?? 'All');
$searchTerm     = trim($_GET['search'] ?? '');

$sql = "SELECT event_id, title, category, description, event_date, location, seats, available_seats, status, poster_image FROM events WHERE 1=1";
$params = [];
$types = "";

if ($filterCategory !== 'All' && !empty($filterCategory)) {
    $sql .= " AND category = ?";
    $params[] = $filterCategory;
    $types .= "s";
}

if ($filterStatus !== 'All' && !empty($filterStatus)) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $sql .= " AND (title LIKE ? OR location LIKE ? OR description LIKE ?)";
    $like = "%" . $searchTerm . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$sql .= " ORDER BY event_date ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$eventsResult = $stmt->get_result();
$eventsCount = $eventsResult->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management CRUD &amp; Posters - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #fdf2f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        .main-container { max-width: 1200px; margin: 0 auto; }
        .nav-header {
            background: #933b55;
            color: white;
            padding: 18px 28px;
            border-radius: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            box-shadow: 0 6px 20px rgba(147, 59, 85, 0.2);
        }
        .nav-header h1 { margin: 0; font-size: 1.6rem; font-weight: 800; }
        .nav-header .sub { font-size: 0.85rem; opacity: 0.85; margin-top: 2px; }
        .nav-links { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .nav-links a {
            color: #fce7f3; text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 0.88rem; font-weight: 600;
        }
        .nav-links a:hover, .nav-links a.active { background: rgba(255, 255, 255, 0.2); color: white; }
        .btn-logout { background: #ef4444 !important; color: white !important; }

        .alert-box { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 0.95rem; }
        .alert-success { background: #dcfce7; border: 1px solid #22c55e; color: #15803d; }
        .alert-error { background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; }

        .toolbar-card {
            background: white; padding: 20px 24px; border-radius: 14px; margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #fce7f3;
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;
        }
        .filter-form { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .filter-form input, .filter-form select {
            padding: 9px 13px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.9rem;
        }
        .btn-primary {
            padding: 9px 18px; background: #933b55; color: white; border: none; border-radius: 8px;
            font-weight: 600; font-size: 0.9rem; cursor: pointer; text-decoration: none;
        }
        .btn-primary:hover { background: #782941; }
        .btn-secondary {
            padding: 9px 16px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db;
            border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none;
        }

        /* Events Grid View */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 22px;
            margin-bottom: 30px;
        }
        .event-card {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 1px solid #fce7f3;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .event-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(147, 59, 85, 0.15);
        }
        .poster-wrapper {
            position: relative;
            height: 180px;
            background: #1e1e2f;
            overflow: hidden;
        }
        .poster-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .category-tag {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(147, 59, 85, 0.9);
            color: white;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: bold;
            backdrop-filter: blur(4px);
        }
        .status-tag {
            position: absolute;
            top: 12px;
            right: 12px;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: bold;
        }
        .status-open { background: #dcfce7; color: #15803d; }
        .status-closed { background: #fee2e2; color: #b91c1c; }
        .status-cancelled { background: #f3f4f6; color: #4b5563; }

        .card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .card-body h3 {
            margin: 0 0 10px 0;
            color: #831843;
            font-size: 1.15rem;
        }
        .card-body p {
            color: #4b5563;
            font-size: 0.88rem;
            line-height: 1.5;
            margin: 0 0 15px 0;
            flex: 1;
        }
        .meta-list {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 16px;
        }
        .meta-item {
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card-actions {
            border-top: 1px solid #f3e8ff;
            padding-top: 14px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .btn-card {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-card-edit { background: #e0e7ff; color: #3730a3; }
        .btn-card-edit:hover { background: #c7d2fe; }
        .btn-card-delete { background: #fee2e2; color: #b91c1c; }
        .btn-card-delete:hover { background: #fecaca; }

        /* Modal */
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white; border-radius: 16px; padding: 28px; width: 95%; max-width: 580px;
            max-height: 90vh; overflow-y: auto; text-align: left;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 2px solid #fce7f3; padding-bottom: 12px; margin-bottom: 16px;
        }
        .form-row { margin-bottom: 14px; }
        .form-row label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 4px; color: #374151; }
        .form-row input, .form-row select, .form-row textarea {
            width: 100%; padding: 10px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;
        }
        .file-hint { font-size: 0.78rem; color: #6b7280; margin-top: 4px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Top Nav Header -->
        <header class="nav-header">
            <div>
                <h1>STUDENTHUB EVENTS</h1>
                <div class="sub">Practical 12: Event Management CRUD &amp; Poster Upload (Type/Size Validation)</div>
            </div>
            <nav class="nav-links">
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a href="profile.php">My Profile</a>
                <?php endif; ?>
                <a href="students.php">Students CRUD</a>
                <a href="manage_events.php" class="active">Events CRUD</a>
                <a href="students_ajax.php">Fetch API CRUD</a>
                <a href="events.html">Campus Events</a>
                <a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($user['username']); ?>)</a>
            </nav>
        </header>

        <!-- Alerts -->
        <?php if (!empty($msg)): ?>
            <div class="alert-box <?php echo $msgType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="toolbar-card">
            <form method="GET" action="manage_events.php" class="filter-form">
                <input type="text" name="search" placeholder="Search event title or location..." value="<?php echo htmlspecialchars($searchTerm); ?>">

                <select name="category">
                    <option value="All">All Categories</option>
                    <option value="Technical" <?php if ($filterCategory === 'Technical') echo 'selected'; ?>>Technical</option>
                    <option value="Cultural" <?php if ($filterCategory === 'Cultural') echo 'selected'; ?>>Cultural</option>
                    <option value="Sports" <?php if ($filterCategory === 'Sports') echo 'selected'; ?>>Sports</option>
                    <option value="Academic" <?php if ($filterCategory === 'Academic') echo 'selected'; ?>>Academic</option>
                </select>

                <select name="status">
                    <option value="All">All Statuses</option>
                    <option value="Open" <?php if ($filterStatus === 'Open') echo 'selected'; ?>>Open for Registration</option>
                    <option value="Closed" <?php if ($filterStatus === 'Closed') echo 'selected'; ?>>Closed</option>
                    <option value="Cancelled" <?php if ($filterStatus === 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                </select>

                <button type="submit" class="btn-primary">Filter</button>
                <?php if ($filterCategory !== 'All' || $filterStatus !== 'All' || !empty($searchTerm)): ?>
                    <a href="manage_events.php" class="btn-secondary">Clear</a>
                <?php endif; ?>
            </form>

            <button type="button" class="btn-primary" onclick="openAddEventModal()">+ Create New Event</button>
        </div>

        <!-- Events Grid -->
        <div class="events-grid">
            <?php if ($eventsCount === 0): ?>
                <div style="grid-column: 1 / -1; background: white; padding: 40px; border-radius: 12px; text-align: center; color: #6b7280;">
                    No events found matching your filter criteria.
                </div>
            <?php else: ?>
                <?php while ($ev = $eventsResult->fetch_assoc()): ?>
                    <?php 
                        $posterPath = "uploads/posters/" . ($ev['poster_image'] ?: 'poster_tech_symposium.jpg');
                        if (!file_exists(__DIR__ . '/' . $posterPath)) {
                            $posterPath = "uploads/posters/poster_tech_symposium.jpg";
                        }
                    ?>
                    <div class="event-card">
                        <div class="poster-wrapper">
                            <img src="<?php echo htmlspecialchars($posterPath); ?>" alt="<?php echo htmlspecialchars($ev['title']); ?> Poster">
                            <span class="category-tag"><?php echo htmlspecialchars($ev['category']); ?></span>
                            <span class="status-tag status-<?php echo strtolower($ev['status']); ?>">
                                ● <?php echo htmlspecialchars($ev['status']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h3><?php echo htmlspecialchars($ev['title']); ?></h3>
                            <p><?php echo htmlspecialchars($ev['description'] ?: 'No detailed description available.'); ?></p>
                            
                            <div class="meta-list">
                                <div class="meta-item">📅 <strong>Date:</strong> <?php echo date("D, d M Y", strtotime($ev['event_date'])); ?></div>
                                <div class="meta-item">📍 <strong>Venue:</strong> <?php echo htmlspecialchars($ev['location']); ?></div>
                                <div class="meta-item">👥 <strong>Seats:</strong> <?php echo $ev['available_seats']; ?> / <?php echo $ev['seats']; ?> available</div>
                            </div>

                            <div class="card-actions">
                                <button type="button" class="btn-card btn-card-edit" onclick='openEditEventModal(<?php echo json_encode($ev); ?>)'>Edit</button>
                                <form method="POST" action="manage_events.php" style="display:inline;" onsubmit="return confirm('Permanently delete event <?php echo htmlspecialchars($ev['title']); ?>?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="event_id" value="<?php echo $ev['event_id']; ?>">
                                    <button type="submit" class="btn-card btn-card-delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL 1: ADD EVENT -->
    <div id="addEventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; color:#933b55;">Create Campus Event</h3>
                <button type="button" style="border:none; background:none; font-size:1.4rem; cursor:pointer;" onclick="closeEventModal('addEventModal')">&times;</button>
            </div>
            <form method="POST" action="manage_events.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">

                <div class="form-row">
                    <label>Event Title *</label>
                    <input type="text" name="title" required placeholder="e.g. AI & Robotics Hackathon 2026">
                </div>

                <div style="display: flex; gap: 10px;">
                    <div class="form-row" style="flex: 1;">
                        <label>Category *</label>
                        <select name="category" required>
                            <option value="Technical">Technical</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Sports">Sports</option>
                            <option value="Academic">Academic</option>
                        </select>
                    </div>
                    <div class="form-row" style="flex: 1;">
                        <label>Status</label>
                        <select name="status">
                            <option value="Open">Open</option>
                            <option value="Closed">Closed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <div class="form-row" style="flex: 1;">
                        <label>Event Date *</label>
                        <input type="date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-row" style="flex: 1;">
                        <label>Total Seats</label>
                        <input type="number" name="seats" value="60" min="5" max="1000">
                    </div>
                </div>

                <div class="form-row">
                    <label>Venue / Location *</label>
                    <input type="text" name="location" required placeholder="e.g. Main Auditorium or Computer Lab 3">
                </div>

                <div class="form-row">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Brief synopsis of the campus event..."></textarea>
                </div>

                <div class="form-row">
                    <label>Event Poster Image (JPG, PNG, WEBP &lt; 2MB)</label>
                    <input type="file" name="poster_image" accept="image/jpeg,image/png,image/webp">
                    <div class="file-hint">Max file size: 2MB. Validated via MIME type and sanitized filename.</div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEventModal('addEventModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: EDIT EVENT -->
    <div id="editEventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; color:#933b55;">Edit Event Details</h3>
                <button type="button" style="border:none; background:none; font-size:1.4rem; cursor:pointer;" onclick="closeEventModal('editEventModal')">&times;</button>
            </div>
            <form method="POST" action="manage_events.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="edit_ev_id" name="event_id">

                <div class="form-row">
                    <label>Event Title *</label>
                    <input type="text" id="edit_ev_title" name="title" required>
                </div>

                <div style="display: flex; gap: 10px;">
                    <div class="form-row" style="flex: 1;">
                        <label>Category *</label>
                        <select id="edit_ev_cat" name="category" required>
                            <option value="Technical">Technical</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Sports">Sports</option>
                            <option value="Academic">Academic</option>
                        </select>
                    </div>
                    <div class="form-row" style="flex: 1;">
                        <label>Status</label>
                        <select id="edit_ev_status" name="status">
                            <option value="Open">Open</option>
                            <option value="Closed">Closed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <div class="form-row" style="flex: 1;">
                        <label>Event Date *</label>
                        <input type="date" id="edit_ev_date" name="event_date" required>
                    </div>
                    <div class="form-row" style="flex: 1;">
                        <label>Total Seats</label>
                        <input type="number" id="edit_ev_seats" name="seats" min="5" max="1000">
                    </div>
                </div>

                <div class="form-row">
                    <label>Venue / Location *</label>
                    <input type="text" id="edit_ev_location" name="location" required>
                </div>

                <div class="form-row">
                    <label>Description</label>
                    <textarea id="edit_ev_desc" name="description" rows="3"></textarea>
                </div>

                <div class="form-row">
                    <label>Replace Poster Image (Optional)</label>
                    <input type="file" name="edit_poster_image" accept="image/jpeg,image/png,image/webp">
                    <div class="file-hint">Leave blank to keep existing poster image. Max 2MB (JPG, PNG, WEBP).</div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEventModal('editEventModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Update Event</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddEventModal() {
            document.getElementById('addEventModal').classList.add('active');
        }

        function openEditEventModal(ev) {
            document.getElementById('edit_ev_id').value = ev.event_id;
            document.getElementById('edit_ev_title').value = ev.title;
            document.getElementById('edit_ev_cat').value = ev.category;
            document.getElementById('edit_ev_status').value = ev.status;
            document.getElementById('edit_ev_date').value = ev.event_date;
            document.getElementById('edit_ev_seats').value = ev.seats;
            document.getElementById('edit_ev_location').value = ev.location;
            document.getElementById('edit_ev_desc').value = ev.description;
            document.getElementById('editEventModal').classList.add('active');
        }

        function closeEventModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
</body>
</html>
