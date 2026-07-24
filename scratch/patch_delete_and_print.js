const fs = require('fs');

// ── PART 1: Patch api_profile.php ──
let profileApi = fs.readFileSync('api/api_profile.php', 'utf8');

const deleteActionBlock = `// ─────────────────────────────────────────────────
// POST: Admin Delete User (CRUD privilege)
// ─────────────────────────────────────────────────
if ($method === 'POST' && $action === 'delete_user') {
    // Validate CSRF token
    validate_csrf_token($_POST['csrf_token'] ?? '', true);

    if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'timetabler'])) {
        echo json_encode(['status' => 'error', 'message' => 'Administrative privilege required.']);
        exit;
    }

    $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $role         = trim($_POST['role'] ?? '');

    if (!$targetUserId || empty($role)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
        exit;
    }

    // Cannot delete yourself
    if ($targetUserId === (int)($_SESSION['user_id'] ?? 0) && $role === ($_SESSION['user_role'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'You cannot delete your own logged-in administrative account.']);
        exit;
    }

    $table_map = [
        'admin' => 'admins',
        'timetabler' => 'timetablers',
        'teacher' => 'teachers',
        'parent' => 'parents',
        'student' => 'students',
        'accounts' => 'accounts_officers'
    ];
    $table = $table_map[$role] ?? '';
    if (empty($table)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid role specified.']);
        exit;
    }

    try {
        // If the role is teacher, clean up teacher_subjects
        if ($role === 'teacher') {
            $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?")->execute([$targetUserId]);
        }
        // If the role is student, clean up student_profiles
        if ($role === 'student') {
            $pdo->prepare("DELETE FROM student_profiles WHERE user_id = ?")->execute([$targetUserId]);
        }

        // Delete from specific role table
        $stmt = $pdo->prepare("DELETE FROM \`$table\` WHERE id = ?");
        $stmt->execute([$targetUserId]);

        echo json_encode(['status' => 'success', 'message' => 'User account successfully deleted from the system.']);
    } catch (\\PDOException $e) {
        error_log('[SHTA API PROFILE DELETE ERROR] ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete user account: ' . $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// POST: Admin Override Update Any User`;

profileApi = profileApi.replace(
    '// ─────────────────────────────────────────────────\n// POST: Admin Override Update Any User',
    deleteActionBlock
);
if (!profileApi.includes('action === \'delete_user\'')) {
    profileApi = profileApi.replace(
        '// ─────────────────────────────────────────────────\r\n// POST: Admin Override Update Any User',
        deleteActionBlock.replace(/\n/g, '\r\n')
    );
}

fs.writeFileSync('api/api_profile.php', profileApi, 'utf8');
console.log('✅ Added delete_user to api/api_profile.php');


// ── PART 2: Patch admin_dashboard.php ──
let adminHtml = fs.readFileSync('admin_dashboard.php', 'utf8');

// A. Insert the Print Roster button in User Directory tab wrapper
const oldTabsBlock = `        <!-- Category Tabs -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px;">
            <button class="btn btn-primary btn-sm dir-tab active" onclick="filterUserDir('all', this)"><i class="fa-solid fa-users"></i> All</button>
            <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('parent', this)"><i class="fa-solid fa-house-user"></i> Parents</button>
            <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('teacher', this)"><i class="fa-solid fa-chalkboard-teacher"></i> Teachers</button>
            <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('student', this)"><i class="fa-solid fa-user-graduate"></i> Students</button>
            <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('timetabler', this)"><i class="fa-solid fa-calendar-days"></i> Timetablers</button>
            <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('accounts', this)"><i class="fa-solid fa-file-invoice-dollar"></i> Accounts</button>
            <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('admin', this)"><i class="fa-solid fa-user-shield"></i> Admins</button>
        </div>`;

const newTabsBlock = `        <!-- Category Tabs -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px;align-items:center;">
            <div style="display:flex;gap:8px;flex-wrap:wrap;flex:1;">
                <button class="btn btn-primary btn-sm dir-tab active" onclick="filterUserDir('all', this)"><i class="fa-solid fa-users"></i> All</button>
                <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('parent', this)"><i class="fa-solid fa-house-user"></i> Parents</button>
                <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('teacher', this)"><i class="fa-solid fa-chalkboard-teacher"></i> Teachers</button>
                <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('student', this)"><i class="fa-solid fa-user-graduate"></i> Students</button>
                <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('timetabler', this)"><i class="fa-solid fa-calendar-days"></i> Timetablers</button>
                <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('accounts', this)"><i class="fa-solid fa-file-invoice-dollar"></i> Accounts</button>
                <button class="btn btn-outline btn-sm dir-tab" onclick="filterUserDir('admin', this)"><i class="fa-solid fa-user-shield"></i> Admins</button>
            </div>
            <button class="btn btn-outline btn-sm" onclick="printRoster()" style="background-color:var(--primary);color:#fff;border:none;"><i class="fa-solid fa-print"></i> Print Roster</button>
        </div>`;

if (adminHtml.includes(oldTabsBlock)) {
    adminHtml = adminHtml.replace(oldTabsBlock, newTabsBlock);
} else {
    adminHtml = adminHtml.replace(oldTabsBlock.replace(/\n/g, '\r\n'), newTabsBlock.replace(/\n/g, '\r\n'));
}

// B. Track current role filter in filterUserDir, expose currentLoggedInUserId in JS, and append the delete button in table row
const oldFilterUserDir = `function filterUserDir(role, tabEl) {
    // Update active tab buttons
    if (tabEl) {
        document.querySelectorAll('.dir-tab').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline');
        });
        tabEl.classList.remove('btn-outline');
        tabEl.classList.add('btn-primary');
    }

    const filtered = role === 'all' ? allUsers : allUsers.filter(u => u.role === role);`;

const newFilterUserDir = `let currentFilteredRole = 'all';
const currentLoggedInUserId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
const currentLoggedInUserRole = '<?php echo htmlspecialchars($_SESSION['user_role'] ?? ""); ?>';

function filterUserDir(role, tabEl) {
    currentFilteredRole = role;
    // Update active tab buttons
    if (tabEl) {
        document.querySelectorAll('.dir-tab').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline');
        });
        tabEl.classList.remove('btn-outline');
        tabEl.classList.add('btn-primary');
    }

    const filtered = role === 'all' ? allUsers : allUsers.filter(u => u.role === role);`;

if (adminHtml.includes(oldFilterUserDir)) {
    adminHtml = adminHtml.replace(oldFilterUserDir, newFilterUserDir);
} else {
    adminHtml = adminHtml.replace(oldFilterUserDir.replace(/\n/g, '\r\n'), newFilterUserDir.replace(/\n/g, '\r\n'));
}

// C. Append the delete button in row HTML
const oldRowHtml = `            <td>
                <button class="btn btn-outline btn-sm" onclick="openAdminEditUserModal(\${u.id}, \\\`\${btoa(u.name || '')}\\\`, \\\`\${btoa(u.email || '')}\\\`, \\\`\${btoa(u.phone || '')}\\\`, '\${u.role}', '\${u.subject_ids || ''}')"><i class="fa-solid fa-user-pen"></i> Edit</button>
            </td>`;

const newRowHtml = `            <td>
                <div style="display:flex;gap:6px;align-items:center;">
                    <button class="btn btn-outline btn-sm" onclick="openAdminEditUserModal(\${u.id}, \\\`\${btoa(u.name || '')}\\\`, \\\`\${btoa(u.email || '')}\\\`, \\\`\${btoa(u.phone || '')}\\\`, '\${u.role}', '\${u.subject_ids || ''}')"><i class="fa-solid fa-user-pen"></i> Edit</button>
                    \${(u.id === currentLoggedInUserId && u.role === currentLoggedInUserRole) ? '' : \`<button class="btn btn-outline btn-sm" onclick="deleteUser(\${u.id}, '\${u.role}', \\\`\${btoa(u.name || '')}\\\`)" style="color:var(--danger);border-color:var(--danger);"><i class="fa-solid fa-trash"></i> Delete</button>\`}
                </div>
            </td>`;

if (adminHtml.includes(oldRowHtml)) {
    adminHtml = adminHtml.replace(oldRowHtml, newRowHtml);
} else {
    adminHtml = adminHtml.replace(oldRowHtml.replace(/\n/g, '\r\n'), newRowHtml.replace(/\n/g, '\r\n'));
}

// D. Add deleteUser and printRoster javascript functions
const targetForJsFunctions = `// ──────────────────────────────────────────────────────────────────────────
// LEAD DRAWER`;

const jsFunctions = `function deleteUser(id, role, nameB64) {
    const name = atob(nameB64);
    if (!confirm(\`Are you absolutely sure you want to permanently delete user "\${name}" (\${role.toUpperCase()}) from the system? This action is irreversible.\`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('action', 'delete_user');
    formData.append('user_id', id);
    formData.append('role', role);

    fetch('api/api_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showGlobalAlert(data.message, 'success');
            loadSystemData();
        } else {
            showGlobalAlert(data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showGlobalAlert('A server error occurred. Failed to delete user.', 'error');
    });
}

function printRoster() {
    const filtered = currentFilteredRole === 'all' ? allUsers : allUsers.filter(u => u.role === currentFilteredRole);
    if (!filtered.length) {
        showGlobalAlert('No users found in the current filter to print.', 'error');
        return;
    }
    
    const roleTitle = roleLabels[currentFilteredRole] || currentFilteredRole;
    
    let printContent = \`
    <html>
    <head>
        <title>S.H.T.A User Roster - \${roleTitle}</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; color: #333; }
            h1 { text-align: center; color: #4A0E17; font-size: 24px; margin-bottom: 5px; }
            p.subtitle { text-align: center; margin-top: 0; color: #666; font-size: 14px; margin-bottom: 30px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 12px 15px; text-align: left; }
            th { background-color: #4A0E17; color: white; font-weight: 600; text-transform: uppercase; font-size: 12px; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; background: #eee; }
            @media print {
                .no-print { display: none; }
                body { padding: 0; }
            }
        </style>
    </head>
    <body>
        <h1>SANITY HOME-BASED TUITION ACADEMY (S.H.T.A)</h1>
        <p class="subtitle">Active User Registry Roster — Category: <strong>\${roleTitle}</strong> (\${filtered.length} active records)</p>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>
    \`;
    
    filtered.forEach((u, i) => {
        let nameDetail = u.name || u.username || '–';
        if (u.role === 'student' && u.grade_level) {
            nameDetail += \` (\${u.grade_level})\`;
        }
        if (u.role === 'teacher' && u.subjects) {
            nameDetail += \` - Subjects: \${u.subjects}\`;
        }
        printContent += \`
            <tr>
                <td>\${i + 1}</td>
                <td><strong>\${nameDetail}</strong></td>
                <td>\${u.email}</td>
                <td>\${u.phone || '–'}</td>
                <td><span class="badge">\${u.role.toUpperCase()}</span></td>
            </tr>
        \`;
    });
    
    printContent += \`
            </tbody>
        </table>
        
        <script>
            window.onload = function() {
                window.print();
                setTimeout(function() { window.close(); }, 500);
            }
        </script>
    </body>
    </html>
    \`;
    
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    printWindow.document.write(printContent);
    printWindow.document.close();
}

// ──────────────────────────────────────────────────────────────────────────
// LEAD DRAWER`;

if (adminHtml.includes(targetForJsFunctions)) {
    adminHtml = adminHtml.replace(targetForJsFunctions, jsFunctions);
} else {
    adminHtml = adminHtml.replace(targetForJsFunctions.replace(/\n/g, '\r\n'), jsFunctions.replace(/\n/g, '\r\n'));
}

fs.writeFileSync('admin_dashboard.php', adminHtml, 'utf8');
console.log('✅ Patched admin_dashboard.php for print roster and user deletion');
