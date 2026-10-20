<?php
// Practical 13: AJAX/Fetch-Based CRUD Interface without Page Reload
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_login();
$user = get_current_user_data();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fetch API Asynchronous CRUD - StudentHub</title>
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

        /* Toast Container */
        #toastContainer {
            position: fixed;
            top: 25px;
            right: 25px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            padding: 14px 20px;
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            color: white;
            font-weight: 600;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 280px;
            animation: slideIn 0.3s ease-out;
        }
        .toast-success { background: #059669; }
        .toast-error { background: #dc2626; }
        .toast-info { background: #2563eb; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Toolbar */
        .toolbar-card {
            background: white; padding: 20px 24px; border-radius: 14px; margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #fce7f3;
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;
        }
        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }
        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
            box-sizing: border-box;
            outline: none;
        }
        .search-box input:focus { border-color: #933b55; }
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        .debounce-pill {
            display: inline-block;
            background: #ede9fe;
            color: #6d28d9;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 10px;
            font-weight: 600;
            margin-left: 8px;
        }
        .btn-primary {
            padding: 10px 18px; background: #933b55; color: white; border: none; border-radius: 8px;
            font-weight: 600; font-size: 0.9rem; cursor: pointer; text-decoration: none;
        }
        .btn-primary:hover { background: #782941; }

        /* Table Card */
        .table-card {
            background: white; border-radius: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #fce7f3; overflow-x: auto; position: relative; min-height: 250px;
        }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.92rem; }
        thead th {
            background: #fdf2f8; color: #831843; font-weight: 700; padding: 14px 16px;
            border-bottom: 2px solid #fce7f3; white-space: nowrap;
        }
        tbody td { padding: 12px 16px; border-bottom: 1px solid #f3e8ff; vertical-align: middle; }
        tbody tr:hover { background: #fff5f8; }

        /* Loading Spinner Overlay */
        .loading-overlay {
            display: none;
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .loading-overlay.active { display: flex; }
        .spinner {
            width: 38px; height: 38px;
            border: 4px solid #fce7f3;
            border-top: 4px solid #933b55;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .btn-action {
            padding: 5px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;
            border: none; cursor: pointer; margin-right: 4px;
        }
        .btn-edit { background: #e0e7ff; color: #3730a3; }
        .btn-delete { background: #fee2e2; color: #991b1b; }

        /* Modal */
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white; border-radius: 16px; padding: 28px; width: 95%; max-width: 520px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: left;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 2px solid #fce7f3; padding-bottom: 12px; margin-bottom: 16px;
        }
        .form-row { margin-bottom: 14px; }
        .form-row label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 4px; color: #374151; }
        .form-row input, .form-row select {
            width: 100%; padding: 10px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;
        }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    </style>
</head>
<body>
    <div id="toastContainer"></div>

    <div class="main-container">
        <!-- Top Nav Header -->
        <header class="nav-header">
            <div>
                <h1>STUDENTHUB FETCH API</h1>
                <div class="sub">Practical 13: Asynchronous AJAX/Fetch CRUD (Zero Page Reload + Debounced Search)</div>
            </div>
            <nav class="nav-links">
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a href="profile.php">My Profile</a>
                <?php endif; ?>
                <a href="students.php">Students CRUD</a>
                <a href="manage_events.php">Events CRUD</a>
                <a href="students_ajax.php" class="active">Fetch API CRUD</a>
                <a href="events.html">Campus Events</a>
                <a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($user['username']); ?>)</a>
            </nav>
        </header>

        <!-- Live Toolbar -->
        <div class="toolbar-card">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="ajaxSearchInput" placeholder="Live search by name, email, or enrollment...">
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="debounce-pill">⚡ 300ms Debounce Active</span>
                <button type="button" class="btn-primary" onclick="openCreateModal()">+ Add Student (AJAX)</button>
            </div>
        </div>

        <!-- Table Card with Live Spinner -->
        <div class="table-card">
            <div id="loadingOverlay" class="loading-overlay">
                <div style="text-align: center;">
                    <div class="spinner"></div>
                    <div style="margin-top: 10px; color: #933b55; font-weight: 600; font-size: 0.88rem;">Fetching JSON from API...</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Enrollment No.</th>
                        <th>Student Name</th>
                        <th>Email &amp; Mobile</th>
                        <th>Course &amp; Sem</th>
                        <th>Status</th>
                        <th>Async Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <!-- Populated dynamically via Fetch API -->
                </tbody>
            </table>
        </div>
        <p id="tableStats" style="margin-top: 15px; font-size: 0.88rem; color: #6b7280;">Loading records...</p>
    </div>

    <!-- CREATE STUDENT MODAL (AJAX) -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; color:#933b55;">Add Student via Fetch API</h3>
                <button type="button" style="border:none; background:none; font-size:1.4rem; cursor:pointer;" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form id="createForm" onsubmit="handleCreateStudent(event)">
                <div class="form-row">
                    <label>Full Name *</label>
                    <input type="text" id="create_name" required placeholder="e.g. Diya Sharma">
                </div>
                <div class="form-row">
                    <label>Email Address *</label>
                    <input type="email" id="create_email" required placeholder="diya@studenthub.edu">
                </div>
                <div class="form-row">
                    <label>Mobile Number *</label>
                    <input type="tel" id="create_phone" pattern="[6-9][0-9]{9}" required placeholder="9876543210">
                </div>
                <div class="form-row">
                    <label>Academic Course *</label>
                    <select id="create_course" required>
                        <option value="B.Tech Computer Engineering">B.Tech Computer Engineering</option>
                        <option value="B.Tech Information Technology">B.Tech Information Technology</option>
                        <option value="B.Tech AI & Data Science">B.Tech AI & Data Science</option>
                        <option value="B.Tech Electronics & Comm.">B.Tech Electronics & Comm.</option>
                        <option value="B.Tech Mechanical Engineering">B.Tech Mechanical Engineering</option>
                        <option value="B.Tech Civil Engineering">B.Tech Civil Engineering</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="form-row" style="flex:1;">
                        <label>Semester</label>
                        <input type="number" id="create_semester" value="1" min="1" max="8">
                    </div>
                    <div class="form-row" style="flex:1;">
                        <label>Gender</label>
                        <select id="create_gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action" style="background:#f3f4f6;" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Create Record (AJAX)</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT STUDENT MODAL (AJAX) -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; color:#933b55;">Edit Student via Fetch API</h3>
                <button type="button" style="border:none; background:none; font-size:1.4rem; cursor:pointer;" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form id="editForm" onsubmit="handleUpdateStudent(event)">
                <input type="hidden" id="edit_student_id">

                <div class="form-row">
                    <label>Full Name *</label>
                    <input type="text" id="edit_name" required>
                </div>
                <div class="form-row">
                    <label>Email Address *</label>
                    <input type="email" id="edit_email" required>
                </div>
                <div class="form-row">
                    <label>Mobile Number *</label>
                    <input type="tel" id="edit_phone" required>
                </div>
                <div class="form-row">
                    <label>Course *</label>
                    <select id="edit_course" required>
                        <option value="B.Tech Computer Engineering">B.Tech Computer Engineering</option>
                        <option value="B.Tech Information Technology">B.Tech Information Technology</option>
                        <option value="B.Tech AI & Data Science">B.Tech AI & Data Science</option>
                        <option value="B.Tech Electronics & Comm.">B.Tech Electronics & Comm.</option>
                        <option value="B.Tech Mechanical Engineering">B.Tech Mechanical Engineering</option>
                        <option value="B.Tech Civil Engineering">B.Tech Civil Engineering</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="form-row" style="flex:1;">
                        <label>Semester</label>
                        <input type="number" id="edit_semester" min="1" max="8">
                    </div>
                    <div class="form-row" style="flex:1;">
                        <label>Status</label>
                        <select id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action" style="background:#f3f4f6;" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Update Record (AJAX)</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript Fetch API Engine with Debounce -->
    <script>
        const API_URL = 'api/students.php';
        let debounceTimer = null;

        // Show Toast Notification
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = (type === 'success' ? '✓ ' : '✕ ') + message;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.transition = 'opacity 0.4s';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 400);
            }, 3500);
        }

        // Show / Hide Loading Spinner
        function setLoading(isLoading) {
            const overlay = document.getElementById('loadingOverlay');
            if (isLoading) overlay.classList.add('active');
            else overlay.classList.remove('active');
        }

        // 1. Fetch Students List (GET)
        async function loadStudents(search = '') {
            setLoading(true);
            try {
                const url = search ? `${API_URL}?search=${encodeURIComponent(search)}` : API_URL;
                const response = await fetch(url);
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Error fetching records.');
                }

                renderTable(result.data);
                document.getElementById('tableStats').textContent = `Displaying ${result.count} student records (Asynchronously fetched via GET ${url})`;
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                setLoading(false);
            }
        }

        // Render Table Rows
        function renderTable(students) {
            const tbody = document.getElementById('studentsTableBody');
            tbody.innerHTML = '';

            if (students.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 30px; color: #6b7280;">No students found matching your query.</td></tr>`;
                return;
            }

            students.forEach(s => {
                const tr = document.createElement('tr');
                tr.id = `row-${s.student_id}`;
                tr.innerHTML = `
                    <td>#${s.student_id}</td>
                    <td><strong>${escapeHtml(s.enrollment_no)}</strong></td>
                    <td>${escapeHtml(s.name)}</td>
                    <td>${escapeHtml(s.email)}<br><small style="color:#6b7280;">+91 ${escapeHtml(s.phone)}</small></td>
                    <td>${escapeHtml(s.course)}<br><small style="color:#6b7280;">Sem ${s.semester}</small></td>
                    <td><span style="font-weight:bold; color:${s.status === 'active' ? '#15803d' : '#991b1b'};">${s.status.toUpperCase()}</span></td>
                    <td>
                        <button type="button" class="btn-action btn-edit" onclick='openEditModal(${JSON.stringify(s)})'>Edit</button>
                        <button type="button" class="btn-action btn-delete" onclick="handleDeleteStudent(${s.student_id}, '${escapeHtml(s.name)}')">Delete</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // 2. Add Student (POST)
        async function handleCreateStudent(e) {
            e.preventDefault();
            const payload = {
                name: document.getElementById('create_name').value.trim(),
                email: document.getElementById('create_email').value.trim(),
                phone: document.getElementById('create_phone').value.trim(),
                course: document.getElementById('create_course').value,
                semester: document.getElementById('create_semester').value,
                gender: document.getElementById('create_gender').value
            };

            setLoading(true);
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || (result.errors ? result.errors.join(', ') : 'Failed to create student.'));
                }

                showToast(`Student ${result.data.name} added successfully!`, 'success');
                closeModal('createModal');
                document.getElementById('createForm').reset();
                loadStudents();
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                setLoading(false);
            }
        }

        // 3. Update Student (PUT)
        async function handleUpdateStudent(e) {
            e.preventDefault();
            const studentId = document.getElementById('edit_student_id').value;
            const payload = {
                student_id: studentId,
                name: document.getElementById('edit_name').value.trim(),
                email: document.getElementById('edit_email').value.trim(),
                phone: document.getElementById('edit_phone').value.trim(),
                course: document.getElementById('edit_course').value,
                semester: document.getElementById('edit_semester').value,
                status: document.getElementById('edit_status').value
            };

            setLoading(true);
            try {
                const response = await fetch(API_URL, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to update record.');
                }

                showToast(`Student #${studentId} updated successfully!`, 'success');
                closeModal('editModal');
                loadStudents();
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                setLoading(false);
            }
        }

        // 4. Delete Student (DELETE)
        async function handleDeleteStudent(id, name) {
            if (!confirm(`Are you sure you want to permanently delete student '${name}' (ID: #${id}) via AJAX?`)) {
                return;
            }

            setLoading(true);
            try {
                const response = await fetch(API_URL, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ student_id: id })
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to delete record.');
                }

                showToast(`Student #${id} deleted without page reload.`, 'success');
                // Remove row dynamically
                const row = document.getElementById(`row-${id}`);
                if (row) row.remove();
                loadStudents();
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                setLoading(false);
            }
        }

        // 5. Intermediate Extension: Debounced Live Search (300ms)
        document.getElementById('ajaxSearchInput').addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                loadStudents(query);
            }, 300);
        });

        // Modal Helpers
        function openCreateModal() { document.getElementById('createModal').classList.add('active'); }
        function openEditModal(student) {
            document.getElementById('edit_student_id').value = student.student_id;
            document.getElementById('edit_name').value = student.name;
            document.getElementById('edit_email').value = student.email;
            document.getElementById('edit_phone').value = student.phone;
            document.getElementById('edit_course').value = student.course;
            document.getElementById('edit_semester').value = student.semester;
            document.getElementById('edit_status').value = student.status;
            document.getElementById('editModal').classList.add('active');
        }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initial Load on Page Mount
        document.addEventListener('DOMContentLoaded', () => {
            loadStudents();
        });
    </script>
</body>
</html>
