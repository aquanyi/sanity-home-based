<?php
/**
 * student_portal.php
 * Student Dashboard
 */
require_once 'security.php';
start_secure_session();

if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'student') {
    header('Location: login.html#student');
    exit;
}

$userName = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | Sanity Homebased Tuition Academy</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #4A0E17;
            --primary-mid: #7a1424;
            --accent: #E5A93B;
            --accent-light: #f5c76c;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --sidebar-width: 260px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary) 0%, #2A080D 100%);
            color: white;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
        }

        .brand-area {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 8px;
            padding: 4px;
        }

        .brand-text h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.1rem;
            color: var(--accent);
            line-height: 1.2;
        }

        .nav-links {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 24px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }

        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.05);
            border-right: 3px solid var(--accent);
        }

        .nav-link i { width: 20px; font-size: 1.1rem; }

        .logout-btn {
            margin: 20px;
            padding: 12px;
            background: rgba(220, 38, 38, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(220, 38, 38, 0.2);
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(220, 38, 38, 0.2);
            color: white;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .top-header {
            background: var(--card-bg);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .header-title h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            color: var(--primary);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--accent);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .content-scroll {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
            background: var(--bg-color);
        }

        /* VIEWS */
        .view-section { display: none; animation: fadeIn 0.3s ease; }
        .view-section.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* CARDS */
        .info-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h3 { color: var(--primary); font-family: 'Outfit', sans-serif; }
        .card-header i { color: var(--accent); font-size: 1.2rem; }

        /* PROFILE GRID */
        .profile-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .detail-item label { display: block; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 4px; }
        .detail-item div { font-size: 1.05rem; font-weight: 500; color: var(--text-dark); }

        /* DATA TABLE */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .data-table th { background: rgba(74,14,23,0.03); color: var(--primary); font-weight: 600; font-size: 0.9rem; }
        .data-table tr:hover td { background: #f8fafc; }

        /* BADGES */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge-subject { background: #e0e7ff; color: #4f46e5; }
        .badge-grade { background: #dcfce7; color: #166534; }

        .loading-spinner { text-align: center; padding: 40px; color: var(--text-muted); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="brand-area">
            <img src="logo.png" alt="Logo" class="brand-logo">
            <div class="brand-text">
                <h2>Student Portal</h2>
            </div>
        </div>

        <div class="nav-links">
            <a class="nav-link active" onclick="switchView('profile', this)">
                <i class="fa-regular fa-id-badge"></i> My Profile
            </a>
            <a class="nav-link" onclick="switchView('subjects', this)">
                <i class="fa-solid fa-book"></i> My Subjects
            </a>
            <a class="nav-link" onclick="switchView('timetable', this)">
                <i class="fa-regular fa-calendar-days"></i> Timetable
            </a>
            <a class="nav-link" onclick="switchView('results', this)">
                <i class="fa-solid fa-square-poll-vertical"></i> Exam Results
            </a>
            <a class="nav-link" onclick="switchView('assignments', this)">
                <i class="fa-solid fa-list-check"></i> Assignments
            </a>
            <a class="nav-link" onclick="switchView('reports', this)">
                <i class="fa-regular fa-file-lines"></i> Reports
            </a>
        </div>

        <a href="logout.php" class="logout-btn">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="header-title">
                <h1 id="view-title">My Profile</h1>
            </div>
            <div class="user-profile">
                <div style="text-align:right;">
                    <div style="font-weight:600; color:var(--primary);"><?php echo htmlspecialchars($userName); ?></div>
                    <div style="font-size:0.8rem; color:var(--text-muted);">Student</div>
                </div>
                <div class="avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
            </div>
        </header>

        <div class="content-scroll">
            
            <!-- PROFILE -->
            <div id="view-profile" class="view-section active">
                <div class="info-card">
                    <div class="card-header"><i class="fa-solid fa-user"></i><h3>Student Information</h3></div>
                    <div class="profile-grid" id="profile-data">
                        <div class="loading-spinner"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading...</div>
                    </div>
                </div>
                <div class="info-card" style="margin-top:20px;">
                    <div class="card-header"><i class="fa-solid fa-users"></i><h3>Parent/Guardian Details</h3></div>
                    <div class="profile-grid" id="parent-data">
                        <div class="loading-spinner"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading...</div>
                    </div>
                </div>
            </div>

            <!-- SUBJECTS -->
            <div id="view-subjects" class="view-section">
                <div class="info-card">
                    <div class="card-header"><i class="fa-solid fa-book"></i><h3>Enrolled Subjects</h3></div>
                    <table class="data-table">
                        <thead><tr><th>Subject Name</th><th>Category</th></tr></thead>
                        <tbody id="subjects-table"></tbody>
                    </table>
                </div>
            </div>

            <!-- TIMETABLE -->
            <div id="view-timetable" class="view-section">
                <div class="info-card">
                    <div class="card-header"><i class="fa-solid fa-calendar-days"></i><h3>Weekly Schedule</h3></div>
                    <table class="data-table">
                        <thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Teacher</th><th>Action</th></tr></thead>
                        <tbody id="timetable-table"></tbody>
                    </table>
                </div>
            </div>

            <!-- RESULTS -->
            <div id="view-results" class="view-section">
                <div class="info-card">
                    <div class="card-header"><i class="fa-solid fa-square-poll-vertical"></i><h3>Exam Results</h3></div>
                    <table class="data-table">
                        <thead><tr><th>Exam</th><th>Date</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Remarks</th></tr></thead>
                        <tbody id="results-table"></tbody>
                    </table>
                </div>
            </div>

            <!-- ASSIGNMENTS -->
            <div id="view-assignments" class="view-section">
                <div class="info-card">
                    <div class="card-header"><i class="fa-solid fa-list-check"></i><h3>Homework & Assignments</h3></div>
                    <table class="data-table">
                        <thead><tr><th>Title</th><th>Subject</th><th>Due Date</th><th>Status</th></tr></thead>
                        <tbody id="assignments-table"></tbody>
                    </table>
                </div>
            </div>

            <!-- REPORTS -->
            <div id="view-reports" class="view-section">
                <div class="info-card">
                    <div class="card-header"><i class="fa-regular fa-file-lines"></i><h3>Academic Progress Reports</h3></div>
                    <table class="data-table">
                        <thead><tr><th>Report Title</th><th>Term/Year</th><th>Date Uploaded</th><th>Download</th></tr></thead>
                        <tbody id="reports-table"></tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        // UI Navigation
        function switchView(viewId, element) {
            document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
            
            document.getElementById('view-' + viewId).classList.add('active');
            element.classList.add('active');
            
            document.getElementById('view-title').innerText = element.innerText.trim();

            loadData(viewId);
        }

        // Data Fetching
        const apiBase = 'api/api_student_portal.php?action=';

        async function loadData(action) {
            try {
                const res = await fetch(apiBase + action);
                const json = await res.json();
                
                if (json.status === 'error') {
                    alert('Error: ' + json.message);
                    return;
                }

                const data = json.data;

                if (action === 'profile') {
                    if(!data) return;
                    document.getElementById('profile-data').innerHTML = `
                        <div class="detail-item"><label>Full Name</label><div>${data.name || 'N/A'}</div></div>
                        <div class="detail-item"><label>Admission No</label><div>${data.admission_no || 'N/A'}</div></div>
                        <div class="detail-item"><label>Grade Level</label><div><span class="badge badge-grade">${data.grade_level || 'N/A'}</span></div></div>
                        <div class="detail-item"><label>Student Email</label><div>${data.email || 'N/A'}</div></div>
                        <div class="detail-item"><label>Date of Birth</label><div>${data.dob || 'N/A'}</div></div>
                        <div class="detail-item"><label>Nationality</label><div>${data.nationality || 'N/A'}</div></div>
                    `;
                    document.getElementById('parent-data').innerHTML = `
                        <div class="detail-item"><label>Parent Name</label><div>${data.parent_name || 'N/A'}</div></div>
                        <div class="detail-item"><label>Contact Phone</label><div>${data.parent_phone || 'N/A'}</div></div>
                        <div class="detail-item"><label>Email Address</label><div>${data.parent_email || 'N/A'}</div></div>
                    `;
                }

                else if (action === 'subjects') {
                    const tbody = document.getElementById('subjects-table');
                    if (!data || data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="2">No subjects assigned yet.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = data.map(s => `
                        <tr>
                            <td><strong>${s.name}</strong></td>
                            <td><span class="badge badge-subject">${s.category || 'General'}</span></td>
                        </tr>
                    `).join('');
                }

                else if (action === 'timetable') {
                    const tbody = document.getElementById('timetable-table');
                    if (!data || data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5">No timetable slots available.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = data.map(s => `
                        <tr>
                            <td><strong>${s.day_of_week}</strong></td>
                            <td>${s.start_time} - ${s.end_time}</td>
                            <td>${s.subject_name}</td>
                            <td>${s.teacher_name}</td>
                            <td>
                                ${s.zoom_link ? `<a href="${s.zoom_link}" target="_blank" style="color:var(--accent); text-decoration:none; font-weight:600;"><i class="fa-solid fa-video"></i> Join Zoom</a>` : 'Offline'}
                            </td>
                        </tr>
                    `).join('');
                }

                else if (action === 'results') {
                    const tbody = document.getElementById('results-table');
                    if (!data || data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6">No exam results available.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = data.map(r => `
                        <tr>
                            <td><strong>${r.exam_name}</strong></td>
                            <td>${r.exam_date}</td>
                            <td>${r.subject_name}</td>
                            <td><strong>${r.marks}%</strong></td>
                            <td><span class="badge badge-grade">${r.grade}</span></td>
                            <td>${r.remarks || '-'}</td>
                        </tr>
                    `).join('');
                }

                else if (action === 'assignments') {
                    const tbody = document.getElementById('assignments-table');
                    if (!data || data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4">No assignments available.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = data.map(a => `
                        <tr>
                            <td><strong>${a.title}</strong></td>
                            <td>${a.subject_name}</td>
                            <td>${a.due_date}</td>
                            <td><span class="badge" style="background:#f1f5f9;color:#334155;">${a.status || 'Pending'}</span></td>
                        </tr>
                    `).join('');
                }

                else if (action === 'reports') {
                    const tbody = document.getElementById('reports-table');
                    if (!data || data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4">No reports available.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = data.map(r => `
                        <tr>
                            <td><strong>${r.report_title}</strong></td>
                            <td>Term ${r.term} - ${r.year}</td>
                            <td>${r.created_at.substring(0,10)}</td>
                            <td><a href="uploads/${r.file_path}" target="_blank" style="color:var(--primary);"><i class="fa-solid fa-download"></i> Download</a></td>
                        </tr>
                    `).join('');
                }

            } catch (err) {
                console.error(err);
            }
        }

        // Initialize first view
        loadData('profile');
    

// Loading state utility
function setButtonLoading(btn, isLoading, loadingText = 'Processing...') {
    if (!btn || !(btn instanceof HTMLElement)) return;
    if (isLoading) {
        if (!btn.hasAttribute('data-original-html')) {
            btn.setAttribute('data-original-html', btn.innerHTML);
        }
        btn.disabled = True;
        btn.innerHTML = <i class="fa-solid fa-spinner fa-spin"></i> ;
    } else {
        btn.disabled = False;
        if (btn.hasAttribute('data-original-html')) {
            btn.innerHTML = btn.getAttribute('data-original-html');
        }
    }
}

// Missing functions implementation
function dispatchAllOverdueAlerts(btn) {
    if (!confirm('Dispatch nudge emails to all overdue teachers?')) return;
    setButtonLoading(btn, true, 'Dispatching...');
    fetch('api/api_manage_reports.php?action=nudge_all_overdue', { method: 'POST' })
        .then(r => r.json())
        .then(d => { showAlert(d.status || 'success', d.message || 'Nudges dispatched'); })
        .catch(() => showAlert('error', 'Failed to dispatch nudges.'))
        .finally(() => setButtonLoading(btn, false));
}

function openAddStudentModal() {
    showAlert('info', 'Add student feature is under development.');
}

function piDoPrint() {
    window.print();
}
</script>
</body>
</html>
