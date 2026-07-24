const fs = require('fs');

let php = fs.readFileSync('admin_dashboard.php', 'utf8');

// ── 1. Insert the Pending Teacher Registrations Panel ──
const targetRolesEnd = `            </form>
        </div>
    </div>
    <?php endif; ?>`;

const pendingPanelHTML = `
    <!-- Pending Teacher Registrations -->
    <div class="panel" style="margin-top: 24px;">
        <div class="panel-header"><h2><i class="fa-solid fa-user-clock" style="margin-right:8px;color:var(--accent);"></i>Pending Teacher Registrations</h2></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Teacher Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Selected Subjects</th>
                        <th>Suggested Subjects</th>
                        <th>Requested Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pending-teachers-tbody">
                    <tr><td colspan="7" class="empty-row">Loading pending registrations…</td></tr>
                </tbody>
            </table>
        </div>
    </div>`;

// Handle LF and CRLF variations
if (php.includes(targetRolesEnd)) {
    php = php.replace(targetRolesEnd, targetRolesEnd + '\n' + pendingPanelHTML);
} else {
    const targetRolesEndCRLF = targetRolesEnd.replace(/\n/g, '\r\n');
    php = php.replace(targetRolesEndCRLF, targetRolesEndCRLF + '\r\n' + pendingPanelHTML.replace(/\n/g, '\r\n'));
}


// ── 2. Add router inside switchTab(id) ──
const targetRouter = `    if (id === 'settings')       loadAllTermDates();`;
const newRouter = `    if (id === 'settings')       loadAllTermDates();\n    if (id === 'roles')          loadPendingTeachers();`;

if (php.includes(targetRouter)) {
    php = php.replace(targetRouter, newRouter);
} else {
    const targetRouterCRLF = targetRouter.replace(/\n/g, '\r\n');
    php = php.replace(targetRouterCRLF, newRouter.replace(/\n/g, '\r\n'));
}


// ── 3. Append the JavaScript helper functions before </script> ──
const helperJS = `
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function loadPendingTeachers() {
    const tbody = document.getElementById('pending-teachers-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="empty-row">Loading pending registrations…</td></tr>';
    
    fetch('api/api_approve_teacher.php?action=get_pending_teachers')
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                if (!d.pending || d.pending.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="empty-row">No pending teacher registration requests</td></tr>';
                    return;
                }
                
                let html = '';
                d.pending.forEach(p => {
                    html += \`
                    <tr>
                        <td><strong>\${escHtml(p.name)}</strong></td>
                        <td>\${escHtml(p.email)}</td>
                        <td>\${escHtml(p.phone)}</td>
                        <td><span style="font-size:0.85rem;color:var(--primary);font-weight:600;">\${escHtml(p.subject_names || 'None')}</span></td>
                        <td><span style="font-size:0.85rem;color:#047857;font-weight:600;">\${escHtml(p.custom_subjects || 'None')}</span></td>
                        <td>\${escHtml(p.created_at)}</td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-primary btn-sm" onclick="approveTeacher(\${p.id})"><i class="fa-solid fa-check"></i> Approve</button>
                                <button class="btn btn-sm btn-outline" style="border-color:var(--danger);color:var(--danger);" onclick="declineTeacher(\${p.id})"><i class="fa-solid fa-xmark"></i> Decline</button>
                            </div>
                        </td>
                    </tr>\`;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = \`<tr><td colspan="7" class="empty-row" style="color:red;">Error: \${escHtml(d.message)}</td></tr>\`;
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-row" style="color:red;">Network connection error loading requests</td></tr>';
        });
}

function approveTeacher(id) {
    const fd = new FormData();
    fd.append('action', 'approve_teacher');
    fd.append('id', id);
    
    fetch('api/api_approve_teacher.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.status, d.message);
            if (d.status === 'success') {
                loadPendingTeachers();
                if (typeof loadSystemData === 'function') loadSystemData();
            }
        })
        .catch(() => {
            showAlert('error', 'Failed to approve teacher. Please check connection.');
        });
}

function declineTeacher(id) {
    if (!confirm('Are you sure you want to decline this registration request?')) return;
    
    const fd = new FormData();
    fd.append('action', 'decline_teacher');
    fd.append('id', id);
    
    fetch('api/api_approve_teacher.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.status, d.message);
            if (d.status === 'success') {
                loadPendingTeachers();
            }
        })
        .catch(() => {
            showAlert('error', 'Failed to decline request.');
        });
}
`;

// Insert helpers before the closing script tag
htmlEnd = '</script>';
php = php.replace(htmlEnd, helperJS + '\n' + htmlEnd);

fs.writeFileSync('admin_dashboard.php', php, 'utf8');
console.log('✅ Injected Teacher Registration Approval UI and logic into admin_dashboard.php!');
