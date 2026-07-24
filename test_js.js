
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

const CSRF_TOKEN = '<?= $csrf_token ?>';
function getCsrfToken() {
    return typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '';
}
const originalFetch = window.fetch;
window.fetch = function(url, options) {
    if (options && options.method && options.method.toUpperCase() === 'POST' && options.body instanceof FormData) {
        options.body.append('csrf_token', CSRF_TOKEN);
    }
    return originalFetch(url, options);
};

// ————————————————————————————————————————————————
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// STATE
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let currentLeads = [];
let currentExams = [];
let currentExamSessions = [];
let currentAnalStudents = [];
let currentAnalSessionId = null;
let allUsers     = [];
let allSlots     = [];
let allTeachers  = [];
let allStudentsForTT = [];

const TAB_TITLES = {
    dashboard:     ['Dashboard', 'School overview & key metrics'],
    leads:         ['New Applications', 'Review and process enrollment requests'],
    'users-dir':   ['User Directory', 'Manage all staff, teachers and parents'],
    roles:         ['Role Management', 'Assign and update user roles'],
    timetable:     ['Academic Operations Coordinator', 'Schedule and manage weekly lesson slots'],
    attendance:    ['Attendance', 'Track lesson check-ins and session activity'],
    exams:         ['Exams', 'Create exam series, enter marks, and run analysis'],
    reports:       ['Reports', 'Review and approve teacher progress reports'],
    library:       ['Resources', 'Upload and manage learning materials'],
    notifications: ['Notifications', 'Send and receive school-wide announcements'],
    settings:      ['Settings', 'Configure academic calendar and grading scales'],
    profile:       ['My Profile', 'Update your account details and password'],
    curriculums:   ['Curriculum Registry', 'Configure curriculums and review parent proposals'],
};

function switchTab(id) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));

    const target = document.getElementById('section-' + id);
    if (target) target.classList.add('active');

    // Manage active state of sub-menu items and auto-open category
    document.querySelectorAll('.submenu-item').forEach(item => {
        item.classList.remove('active');
        const onClickAttr = item.getAttribute('onclick') || '';
        if (onClickAttr.includes(`'${id}'`) || onClickAttr.includes(`"${id}"`)) {
            item.classList.add('active');
            
            // Expand the parent category
            const wrap = item.closest('.nav-category-wrap');
            if (wrap) {
                // Collapse other categories first
                document.querySelectorAll('.nav-category-wrap').forEach(w => w.classList.remove('active'));
                wrap.classList.add('active');
            }
        }
    });

    // Update topbar title
    const titles = TAB_TITLES[id] || [id, ''];
    const titleEl = document.getElementById('topbar-title');
    const subEl   = document.getElementById('topbar-sub');
    if (titleEl) titleEl.textContent = titles[0];
    if (subEl)   subEl.textContent   = titles[1] || 'Sanity Home Based Tuition Academy';

    closeNotifDropdown();

    localStorage.setItem('admin_dashboard_active_tab', id);

    if (['dashboard','leads','users-dir'].includes(id)) loadSystemData();
    if (id === 'profile')        loadProfile();
    if (id === 'timetable')      loadTimetable();
    if (id === 'attendance')     loadAttendance();
    if (id === 'exams')          loadAcademic();
    if (id === 'reports')        loadReports();
    if (id === 'library')        loadLibrary();
    if (id === 'settings')       loadAllTermDates();
    if (id === 'roles')          { loadPendingTeachers(); loadRoleParentCurriculums(); }
    if (id === 'notifications')  loadNotifications();
    if (id === 'curriculums')    loadCurriculums();
    if (id === 'curriculums')    loadCurriculums();

    // Close mobile drawer on selection
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const icon = document.getElementById('hamburgerIcon');
    if (sidebar && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        if (icon) icon.className = 'fa-solid fa-bars';
    }
}

function toggleCategoryMenu(headerEl) {
    const wrap = headerEl.parentElement;
    const isAlreadyActive = wrap.classList.contains('active');
    
    // Collapse other category menus first (accordion style)
    document.querySelectorAll('.nav-category-wrap').forEach(w => w.classList.remove('active'));
    
    if (!isAlreadyActive) {
        wrap.classList.add('active');
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const icon = document.getElementById('hamburgerIcon');
    if (sidebar) {
        sidebar.classList.toggle('active');
        const isActive = sidebar.classList.contains('active');
        if (overlay) {
            if (isActive) overlay.classList.add('active');
            else overlay.classList.remove('active');
        }
        if (icon) {
            if (isActive) icon.className = 'fa-solid fa-xmark';
            else icon.className = 'fa-solid fa-bars';
        }
    }
}

// ————————————————————————————————————————————————
// GLOBAL: Dashboard & Leads & Users
// ————————————————————————————————————————————————
function loadSystemData() {
    fetch('api/api_fetch_leads.php')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') return;
            currentLeads = data.leads || [];

            const mLeads = document.getElementById('m-leads');
            if (mLeads) mLeads.textContent = data.metrics.pending_leads ?? '–';
            
            const mUsers = document.getElementById('m-users');
            if (mUsers) mUsers.textContent = data.metrics.total_users ?? '–';
            
            const mStudents = document.getElementById('m-students');
            if (mStudents) mStudents.textContent = data.metrics.total_students ?? '–';
            
            const mSlots = document.getElementById('m-slots');
            if (mSlots) mSlots.textContent = data.metrics.total_slots ?? data.metrics.total_students ?? '–';

            const badge = data.metrics.pending_leads || 0;
            const badgeLeads = document.getElementById('badge-leads');
            if (badgeLeads) badgeLeads.textContent = badge;

            // Mobile & Dashboard Message Center updates
            const badgeLeadsMobile = document.getElementById('badge-leads-mobile');
            if (badgeLeadsMobile) badgeLeadsMobile.textContent = badge;
            const mcLeads = document.getElementById('mc-leads-count');
            if (mcLeads) mcLeads.textContent = badge;

            // Leads table
            const tbody = document.getElementById('leads-tbody');
            if (tbody) {
                tbody.innerHTML = '';
                if (!currentLeads.length) {
                    tbody.innerHTML = `<tr><td colspan="7" class="empty-row">No pending leads.</td></tr>`;
                } else {
                    currentLeads.forEach(l => {
                        const vBadge = l.venue_preference === 'home_visit' ? 'badge-home' : 'badge-school';
                        const vLabel = l.venue_preference === 'home_visit' ? 'Home Visit' : 'On-Site';
                        tbody.innerHTML += `<tr>
                            <td><strong>${l.parent_name}</strong><br><small style="color:var(--gray-600);">${l.parent_email}</small></td>
                            <td>${l.parent_phone}</td>
                            <td>${l.student_name}</td>
                            <td>${l.student_grade}</td>
                            <td><span class="badge ${vBadge}">${vLabel}</span></td>
                            <td><small>${l.created_at?.split(' ')[0] || ''}</small></td>
                            <td class="btn-group">
                                <button class="btn btn-primary btn-sm" onclick="openDrawer(${l.id})"><i class="fa-solid fa-eye"></i> Review</button>
                                <button class="btn btn-danger btn-sm" onclick="rejectLead(${l.id})"><i class="fa-solid fa-xmark"></i></button>
                            </td>
                        </tr>`;
                    });
                }
            }

            // Users table — store globally and render
            allUsers = data.users || [];
            filterUserDir('all');
        })
        .catch(err => console.error(err));

    // Fetch reports statistics for dashboard message center
    fetch('api/api_manage_reports.php')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') return;
            const pendingReports = (data.reports || []).filter(r => r.status === 'pending').length;
            const badgeReports = document.getElementById('badge-reports');
            const badgeReportsMobile = document.getElementById('badge-reports-mobile');
            const mcReports = document.getElementById('mc-reports-count');
            if (badgeReports) badgeReports.textContent = pendingReports;
            if (badgeReportsMobile) badgeReportsMobile.textContent = pendingReports;
            if (mcReports) mcReports.textContent = pendingReports;
        }).catch(() => {});

    // Fetch notifications statistics for dashboard message center (unread only for badge)
    fetch('api/api_notifications.php?action=get_notifications&unread=1')
        .then(r => r.json())
        .then(data => {
            const notifsCount = (data.notifications || []).length;
            const badgeNotifs = document.getElementById('badge-notifs');
            const badgeNotifsMobile = document.getElementById('badge-notifs-mobile');
            const mcNotifs = document.getElementById('mc-notifs-count');
            
            if (badgeNotifs) {
                badgeNotifs.textContent = notifsCount;
                badgeNotifs.style.display = notifsCount > 0 ? 'inline-block' : 'none';
            }
            if (badgeNotifsMobile) badgeNotifsMobile.textContent = notifsCount;
            if (mcNotifs) mcNotifs.textContent = notifsCount;
        }).catch(() => {});
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// USER DIRECTORY: Category Filtering
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const roleLabels = {
    parent: 'Parents', teacher: 'Teachers', student: 'Students',
    timetabler: 'Academic Operations Coordinators', accounts: 'Accounts Officers', admin: 'Admins', all: 'All Users'
};

let currentFilteredRole = 'all';
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

    const filtered = role === 'all' ? allUsers : allUsers.filter(u => u.role === role);
    const label    = document.getElementById('dir-category-label');
    const count    = document.getElementById('dir-count');
    const utbody   = document.getElementById('users-tbody');
    if (label)  label.textContent = roleLabels[role] || role;
    if (count)  count.textContent = `${filtered.length} record${filtered.length !== 1 ? 's' : ''}`;
    if (!utbody) return;
    utbody.innerHTML = '';
    if (!filtered.length) {
        utbody.innerHTML = `<tr><td colspan="6" class="empty-row">No ${roleLabels[role] || role} found.</td></tr>`;
        return;
    }
    filtered.forEach((u, i) => {
        let nameHTML = `<strong>${u.name || u.username || '–'}</strong>`;
        if (u.role === 'student') {
            const admNo = u.admission_no || u.staff_id || 'A000S';
            nameHTML += `<br><span class="badge" style="background:rgba(229,169,59,0.2);color:#B45309;padding:2px 8px;border-radius:10px;font-size:0.78rem;font-weight:700;display:inline-block;margin-top:3px;"><i class="fa-solid fa-id-card"></i> Adm No: ${admNo}</span>`;
            if (u.grade_level) {
                nameHTML += ` <small style="color:var(--gray-600);"><i class="fa-solid fa-graduation-cap"></i> ${u.grade_level}</small>`;
            }
            if (u.subjects) {
                nameHTML += `<br><small style="color:var(--primary); font-weight: 600;"><i class="fa-solid fa-book-bookmark"></i> Subjects: ${u.subjects}</small>`;
            }
        }
        if (u.role === 'teacher' && u.subjects) {
            nameHTML += `<br><small style="color:var(--accent); font-weight: 500;"><i class="fa-solid fa-book"></i> Subjects: ${u.subjects}</small>`;
        }

        let editSubjBtn = '';
        if (u.role === 'student' && u.profile_id) {
            editSubjBtn = `<button class="btn btn-outline btn-sm" style="border-color:var(--accent);color:var(--primary);" onclick="openEditStudentSubjectsModal(${u.profile_id}, \`${btoa(u.name || '')}\`)"><i class="fa-solid fa-book-open-reader"></i> Subjects</button>`;
        }

        utbody.innerHTML += `<tr>
            <td>${i + 1}</td>
            <td>${nameHTML}</td>
            <td>${u.email}</td>
            <td>${u.phone || '–'}</td>
            <td><span class="badge badge-approved" style="background:rgba(74,14,23,0.08);color:var(--primary);">${u.role.toUpperCase()}</span></td>
            <td>
                <div style="display:flex;gap:6px;align-items:center;">
                    <button class="btn btn-outline btn-sm" onclick="openAdminEditUserModal(${u.id}, \`${btoa(u.name || '')}\`, \`${btoa(u.email || '')}\`, \`${btoa(u.phone || '')}\`, '${u.role}', '${u.subject_ids || ''}', \`${btoa(u.admission_no || u.staff_id || '')}\`)"><i class="fa-solid fa-user-pen"></i> Edit</button>
                    ${editSubjBtn}
                    ${(u.id === currentLoggedInUserId && u.role === currentLoggedInUserRole) ? '' : `<button class="btn btn-outline btn-sm" onclick="deleteUser(${u.id}, '${u.role}')" style="color:var(--danger);border-color:var(--danger);"><i class="fa-solid fa-trash"></i> Delete</button>`}
                </div>
            </td>
        </tr>`;
    });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// LEAD DRAWER
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function openDrawer(id) {
    const l = currentLeads.find(x => x.id == id);
    if (!l) return;

    let loc = '';
    if (l.venue_preference === 'home_visit' && l.loc_place) {
        loc = `<div style="background:#FFF8F2;border:1px dashed var(--accent);padding:15px;border-radius:8px;margin-top:15px;">
            <h5 style="color:var(--primary);margin-bottom:10px;"><i class="fa-solid fa-map-location-dot"></i> Home-Visit Coordinates</h5>
            <p><strong>Area:</strong> ${l.loc_place || '–'}</p>
            <p><strong>Estate:</strong> ${l.loc_estate || '–'}</p>
            ${l.loc_link ? `<p><a href="${l.loc_link}" target="_blank" style="color:var(--primary);">ðŸ“ Open Google Maps</a></p>` : ''}
        </div>`;
    }

    document.getElementById('drawer-content').innerHTML = `
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div><div style="font-size:0.78rem;color:var(--gray-600);font-weight:700;text-transform:uppercase;margin-bottom:3px;">Parent</div><div style="font-size:1rem;font-weight:600;">${l.parent_name}</div></div>
            <div><div style="font-size:0.78rem;color:var(--gray-600);font-weight:700;text-transform:uppercase;margin-bottom:3px;">Phone</div><div><strong>${l.parent_phone}</strong></div></div>
            <div><div style="font-size:0.78rem;color:var(--gray-600);font-weight:700;text-transform:uppercase;margin-bottom:3px;">Email</div><div>${l.parent_email}</div></div>
            <div><div style="font-size:0.78rem;color:var(--gray-600);font-weight:700;text-transform:uppercase;margin-bottom:3px;">Student</div><div>${l.student_name} &bull; ${l.student_grade}</div></div>
            
            <div style="display:flex;gap:20px;flex-wrap:wrap;background:#F9F9F9;padding:12px;border-radius:8px;border:1px solid #ECECEC;">
                <div>
                    <div style="font-size:0.75rem;color:var(--gray-600);font-weight:700;text-transform:uppercase;margin-bottom:3px;">Study Option</div>
                    <div><span class="badge ${l.study_type === 'homeschooling' ? 'badge-home' : 'badge-school'}">${l.study_type === 'homeschooling' ? 'Homeschooling' : 'Tuition'}</span></div>
                </div>
                <div style="flex:1;">
                    <div style="font-size:0.75rem;color:var(--gray-600);font-weight:700;text-transform:uppercase;margin-bottom:3px;">Curriculum</div>
                    <div>
                        ${l.curriculum_name ? (
                            l.curriculum_approved == 0 
                            ? `<span style="color:var(--danger);font-weight:700;">${l.curriculum_name}</span> 
                               <span style="font-size:0.72rem;background:#FEE2E2;color:#991B1B;padding:2px 6px;border-radius:4px;margin-left:5px;font-weight:600;display:inline-block;vertical-align:middle;">Pending Approval</span>
                               <button class="btn btn-accent btn-sm" style="display:inline-flex;padding:3px 8px;font-size:0.75rem;margin-left:10px;vertical-align:middle;height:auto;" onclick="quickApproveCurriculum(${l.curriculum_id}, ${l.id})"><i class="fa-solid fa-circle-check"></i> Approve</button>`
                            : `<span style="font-weight:600;color:var(--primary);">${l.curriculum_name}</span>`
                        ) : `<span style="color:var(--gray-500);font-style:italic;">None Selected</span>`}
                    </div>
                </div>
            </div>

            <div><div style="font-size:0.78rem;color:var(--gray-600);font-weight:700;text-transform:uppercase;margin-bottom:3px;">Learning Needs</div>
                <div style="background:var(--cream);padding:10px;border-radius:6px;font-size:0.88rem;">${l.learning_needs || '–'}</div>
            </div>
            ${loc}
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:15px;">
                <a href="tel:${l.parent_phone}" class="btn btn-accent" style="justify-content:center;"><i class="fa-solid fa-phone"></i> Call Parent</a>
                <button class="btn btn-primary" style="justify-content:center;" onclick="openEmailComposer(${l.id})"><i class="fa-solid fa-circle-check"></i> Approve & Provision Account</button>
            </div>
        </div>
    `;
    document.getElementById('drawerOverlay').classList.add('open');
    document.getElementById('detailDrawer').classList.add('open');
}
function closeDrawer() {
    document.getElementById('drawerOverlay').classList.remove('open');
    document.getElementById('detailDrawer').classList.remove('open');
}

function openEmailComposer(id) {
    const l = currentLeads.find(x => x.id == id);
    if (!l) return;
    document.getElementById('approve-lead-id').value = l.id;
    document.getElementById('approve-recipient-email').value = l.parent_email;
    document.getElementById('approve-email-body').value =
`Dear {parent_name},

We are delighted to welcome {student_name} to Sanity Homebased Tuition Academy!

Your enrollment application has been reviewed and approved. A formal profile has been provisioned for you in our system.

ðŸ” Your Secure Login Credentials:
   Username:  {username}
   Password:  {password}

Portal Access: http://localhost/sanity%20home%20based/login.html#parent

ðŸ“‹ Custom Notes / Payment Terms:
   [Admin: add any custom payment schedule or notes here]

Please log in and change your password upon first access.

Warm regards,
Admissions Director
Sanity Homebased Tuition Academy`;
    document.getElementById('emailModal').classList.add('open');
}

function executeEnrollment(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('lead_id', document.getElementById('approve-lead-id').value);
    fd.append('email_body', document.getElementById('approve-email-body').value);
    fetch('api/api_approve_lead.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            closeModal('emailModal');
            closeDrawer();
            showAlert(data.status, data.status === 'success'
                ? `âœ… Account provisioned! Username: <strong>${data.generated_username}</strong> | Email dispatched: ${data.email_sent ? 'Yes' : 'Pending mail server'}`
                : data.message);
            loadSystemData();
        });
}

function rejectLead(id) {
    if (!confirm('Reject this lead? This will update their status in the staging table.')) return;
    const fd = new FormData();
    fd.append('action', 'reject');
    fd.append('lead_id', id);
    fetch('api/api_approve_lead.php', { method: 'POST', body: fd })
        .then(() => { showAlert('success', 'Lead rejected.'); loadSystemData(); });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// ROLE DELEGATION
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function toggleRoleSubjects() {
    const roleEl = document.getElementById('r-role');
    const group  = document.getElementById('teacher-subjects-group');
    const box    = document.getElementById('teacher-subjects-checkboxes');
    if (!roleEl || !group || !box) return;

    if (roleEl.value === 'teacher') {
        group.style.display = 'block';
        if (availableSubjects.length === 0) {
            loadSubjects().then(() => renderRoleSubjectsCheckboxes(box, 'r-subject-item'));
        } else {
            renderRoleSubjectsCheckboxes(box, 'r-subject-item');
        }
    } else {
        group.style.display = 'none';
        box.innerHTML = '<span style="color:var(--gray-400);font-style:italic;font-size:0.85rem;">Loading\u2026</span>';
    }
}

function renderRoleSubjectsCheckboxes(container, namePrefix, checkedIds = []) {
    container.innerHTML = '';
    if (availableSubjects.length === 0) {
        container.innerHTML = `<span style="color:var(--gray-500);font-size:0.85rem;">No subjects in system yet. Add one below.</span>`;
        return;
    }
    availableSubjects.forEach(s => {
        const isChecked = checkedIds.map(String).includes(String(s.id)) ? 'checked' : '';
        container.innerHTML += `
            <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:0.88rem;font-weight:500;color:var(--gray-700);background:white;border:1.5px solid ${isChecked ? 'var(--primary)' : '#e2d6c2'};border-radius:7px;padding:7px 13px;transition:border-color 0.2s;">
                <input type="checkbox" name="${namePrefix}" value="${s.id}" ${isChecked}
                    style="width:15px;height:15px;accent-color:var(--primary);cursor:pointer;"
                    onchange="this.closest('label').style.borderColor = this.checked ? 'var(--primary)' : '#e2d6c2'">
                ${s.name}
            </label>
        `;
    });
}

// Add a brand-new subject inline from the Role Delegation form
function addSubjectInline() {
    const input = document.getElementById('r-new-subject-input');
    const name  = (input?.value || '').trim();
    if (!name) { showAlert('error', 'Please enter a subject name.'); return; }
    const fd = new FormData();
    fd.append('action', 'add_subject');
    fd.append('name', name);
    fetch('api/api_manage_academic.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.status, d.message);
            if (d.status === 'success') {
                input.value = '';
                // Reload subjects and re-render, preserving current checked state
                const container = document.getElementById('teacher-subjects-checkboxes');
                const currentlyChecked = [...document.querySelectorAll('input[name="r-subject-item"]:checked')].map(c => c.value);
                loadSubjects().then(() => {
                    renderRoleSubjectsCheckboxes(container, 'r-subject-item', currentlyChecked);
                });
            }
        });
}

function createRole(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('name',     document.getElementById('r-name').value);
    fd.append('email',    document.getElementById('r-email').value);
    fd.append('phone',    document.getElementById('r-phone').value);
    fd.append('password', document.getElementById('r-pass').value);
    const role = document.getElementById('r-role').value;
    fd.append('role',     role);
    
    if (role === 'teacher') {
        const checked = document.querySelectorAll('input[name="r-subject-item"]:checked');
        checked.forEach(cb => {
            fd.append('subject_ids[]', cb.value);
        });
    }

    fetch('api/api_create_role.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') {
                e.target.reset();
                toggleRoleSubjects();
            }
        });
}

function switchRoleFormMode(mode) {
    const staffForm = document.getElementById('form-create-staff');
    const parentForm = document.getElementById('form-create-parent');
    const btnStaff = document.getElementById('btn-role-tab-staff');
    const btnParent = document.getElementById('btn-role-tab-parent');

    if (!staffForm || !parentForm) return;

    if (mode === 'parent') {
        staffForm.style.display = 'none';
        parentForm.style.display = 'block';
        if (btnStaff && btnParent) {
            btnStaff.className = 'btn btn-outline btn-sm';
            btnParent.className = 'btn btn-primary btn-sm';
        }
        loadRoleParentCurriculums();
        // Pre-build nationality/language option strings for dynamic student cards
        if (!window.adminNationalityOptions) {
            const natSel = document.getElementById('r-parent-nationality');
            if (natSel) {
                window.adminNationalityOptions = Array.from(natSel.options).slice(1).map(o => `<option value="${o.value}">${o.text}</option>`).join('');
            }
        }
        if (!window.adminLanguageOptions) {
            const langs = ['English','Swahili','French','Arabic','Spanish','Mandarin Chinese','German','Portuguese','Russian','Japanese','Hindi','Italian','Dutch','Korean','Turkish','Persian (Farsi)','Urdu','Bengali','Afrikaans','Amharic','Somali','Swedish','Polish','Greek','Hebrew','Vietnamese','Thai','Indonesian','Malay','Tagalog/Filipino','Other'];
            window.adminLanguageOptions = langs.map(l => `<option value="${l}">${l}</option>`).join('');
        }
        renderAdminStudentCards();
    } else {
        parentForm.style.display = 'none';
        staffForm.style.display = 'block';
        if (btnStaff && btnParent) {
            btnStaff.className = 'btn btn-primary btn-sm';
            btnParent.className = 'btn btn-outline btn-sm';
        }
    }
}

function handleRoleDropdownChange() {
    const roleEl = document.getElementById('r-role');
    if (!roleEl) return;
    if (roleEl.value === 'parent') {
        switchRoleFormMode('parent');
        roleEl.value = 'timetabler';
    } else {
        toggleRoleSubjects();
    }
}

function loadRoleParentCurriculums() {
    const select = document.getElementById('r-curriculum-id');
    if (!select) return;

    fetch('api/api_curriculums.php?action=get_all')
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.curriculums) {
                const currentVal = select.value;
                select.innerHTML = '<option value="" disabled selected>Select Curriculum...</option>';
                data.curriculums.forEach(c => {
                    const option = document.createElement('option');
                    option.value = c.id;
                    option.textContent = c.name;
                    option.setAttribute('data-level-type', c.level_type || 'custom');
                    select.appendChild(option);
                });
                const customOption = document.createElement('option');
                customOption.value = 'custom';
                customOption.textContent = 'Other / Custom Curriculum...';
                customOption.setAttribute('data-level-type', 'custom');
                select.appendChild(customOption);

                if (currentVal) select.value = currentVal;

                // Sync the grade selections
                handleAdminCurriculumChange();
            }
        })
        .catch(err => console.error('Error loading curriculums for parent form:', err));
}

function handleAdminCurriculumChange() {
    const curriculumSelect = document.getElementById('r-curriculum-id');
    const gradeSelect = document.getElementById('r-student-grade-select');
    const gradeInput = document.getElementById('r-student-grade');
    
    if (!curriculumSelect || !gradeSelect || !gradeInput) return;
    
    const selectedOption = curriculumSelect.options[curriculumSelect.selectedIndex];
    const levelType = selectedOption?.getAttribute('data-level-type') || 'custom';
    const selectedText = selectedOption?.text || '';
    const textLower = selectedText.toLowerCase();
    
    let grades = [];
    if (levelType === 'grades_1_12' || textLower.includes('cbc') || textLower.includes('american')) {
        for (let i = 1; i <= 12; i++) {
            grades.push(`Grade ${i}`);
        }
    } else if (levelType === 'years_1_13' || textLower.includes('cambridge') || textLower.includes('pearson') || textLower.includes('edexcel')) {
        for (let i = 1; i <= 13; i++) {
            grades.push(`Year ${i}`);
        }
    } else if (levelType === 'classes_1_8' || textLower.includes('8-4-4') || textLower.includes('844')) {
        for (let i = 1; i <= 8; i++) {
            grades.push(`Class ${i}`);
        }
    }
    
    if (grades.length > 0) {
        gradeSelect.innerHTML = '<option value="" disabled selected>Select Grade...</option>';
        grades.forEach(g => {
            const opt = document.createElement('option');
            opt.value = g;
            opt.textContent = g;
            gradeSelect.appendChild(opt);
        });
        
        gradeSelect.style.display = 'block';
        gradeSelect.required = true;
        gradeSelect.name = 'student_grade';
        
        gradeInput.style.display = 'none';
        gradeInput.required = false;
        gradeInput.name = '';
    } else {
        gradeSelect.style.display = 'none';
        gradeSelect.required = false;
        gradeSelect.name = '';
        
        gradeInput.style.display = 'block';
        gradeInput.required = true;
        gradeInput.name = 'student_grade';
    }
    
    toggleAdminCustomCurriculumField();
}

function toggleAdminCustomCurriculumField() {
    const select = document.getElementById('r-curriculum-id');
    const group  = document.getElementById('adminCustomCurriculumGroup');
    const input  = document.getElementById('r-custom-curriculum');
    if (!select || !group) return;

    if (select.value === 'custom') {
        group.style.display = 'block';
        if (input) input.required = true;
    } else {
        group.style.display = 'none';
        if (input) input.required = false;
    }
}

function toggleAdminLocationFields() {
    const venueRadios = document.getElementsByName('venue_preference');
    const locGroup    = document.getElementById('adminLocationFields');
    if (!locGroup) return;

    let selectedVenue = 'school';
    for (const r of venueRadios) {
        if (r.checked) {
            selectedVenue = r.value;
            break;
        }
    }

    if (selectedVenue === 'home_visit' || selectedVenue === 'home') {
        locGroup.style.display = 'block';
    } else {
        locGroup.style.display = 'none';
    }
}

function renderAdminStudentCards() {
    const count = parseInt(document.getElementById('r-student-count')?.value || 1);
    const container = document.getElementById('admin-student-cards-container');
    if (!container) return;

    const days = Array.from({length:31}, (_,i) => `<option value="${i+1}">${i+1}</option>`).join('');
    const months = [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
    ].map((m,i) => `<option value="${i+1}">${m}</option>`).join('');
    const currentYear = new Date().getFullYear();
    const years = Array.from({length:20}, (_,i) => currentYear - i).map(y => `<option value="${y}">${y}</option>`).join('');

    const natOptions = window.adminNationalityOptions || '<option value="">-- Select --</option>';
    const langOptions = window.adminLanguageOptions || '<option value="">-- Select --</option>';

    const defaultSubjs = [
        {id: 'Mathematics', name: 'Mathematics'},
        {id: 'English', name: 'English'},
        {id: 'Swahili', name: 'Swahili'},
        {id: 'Biology', name: 'Biology'},
        {id: 'Chemistry', name: 'Chemistry'},
        {id: 'Physics', name: 'Physics'},
        {id: 'Computer Science', name: 'Computer Science'},
        {id: 'History', name: 'History'},
        {id: 'Geography', name: 'Geography'},
        {id: 'Business Studies', name: 'Business Studies'}
    ];
    const subjsList = (typeof availableSubjects !== 'undefined' && availableSubjects.length > 0) ? availableSubjects : defaultSubjs;

    const renderSubjBoxes = (idx) => subjsList.map(s => {
        const val = s.name || s.id;
        return `<label style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid #E9ECEF; padding:6px 10px; border-radius:6px; font-size:0.82rem; cursor:pointer; font-weight:500;">
            <input type="checkbox" name="students[${idx}][subjects][]" value="${val}" style="accent-color:var(--primary);"> ${val}
        </label>`;
    }).join('');

    let html = '';
    for (let i = 0; i < count; i++) {
        html += `
        <div style="background:linear-gradient(135deg,rgba(74,14,23,0.04) 0%,rgba(229,169,59,0.08) 100%); border:1.5px solid rgba(74,14,23,0.15); border-radius:12px; padding:20px; margin-bottom:20px;">
            <h4 style="margin:0 0 16px; color:var(--primary); font-size:0.95rem; font-weight:700; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-user-graduate" style="color:var(--accent);"></i> Student ${i+1}
            </h4>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:16px; margin-bottom:14px;">
                <div class="form-group">
                    <label>Full Name <span style="color:red;">*</span></label>
                    <input type="text" name="students[${i}][name]" class="form-control" required placeholder="Student Full Name">
                </div>
                <div class="form-group">
                    <label>Grade / Level <span style="color:red;">*</span></label>
                    <input type="text" name="students[${i}][grade]" class="form-control" required placeholder="e.g. Grade 8 / Year 10">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <div style="display:flex; gap:6px;">
                        <select name="students[${i}][dob_day]" class="form-control" style="flex:1;" onchange="syncStudentDob(${i})">
                            <option value="">Day</option>${days}
                        </select>
                        <select name="students[${i}][dob_month]" class="form-control" style="flex:1.5;" onchange="syncStudentDob(${i})">
                            <option value="">Month</option>${months}
                        </select>
                        <select name="students[${i}][dob_year]" class="form-control" style="flex:1.2;" onchange="syncStudentDob(${i})">
                            <option value="">Year</option>${years}
                        </select>
                    </div>
                    <input type="hidden" name="students[${i}][dob]" id="admin-dob-hidden-${i}">
                </div>
                <div class="form-group">
                    <label>Student Nationality</label>
                    <select name="students[${i}][nationality]" class="form-control">
                        <option value="">-- Select Nationality --</option>
                        ${natOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label>First Language</label>
                    <select name="students[${i}][first_language]" class="form-control">
                        <option value="">-- Select Language --</option>
                        ${langOptions}
                    </select>
                </div>
            </div>
            <div>
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:0.85rem; color:var(--dark);"><i class="fa-solid fa-book-bookmark" style="color:var(--accent); margin-right:4px;"></i> Select Subjects to be Taught at School <span style="color:red;">*</span></label>
                <div style="display:flex; flex-wrap:wrap; gap:8px; background:rgba(255,255,255,0.7); padding:12px; border-radius:8px; border:1px solid #E9ECEF;">
                    ${renderSubjBoxes(i)}
                </div>
            </div>
        </div>`;
    }
    container.innerHTML = html;
}

function syncStudentDob(idx) {
    const day   = document.querySelector(`[name="students[${idx}][dob_day]"]`)?.value;
    const month = document.querySelector(`[name="students[${idx}][dob_month]"]`)?.value;
    const year  = document.querySelector(`[name="students[${idx}][dob_year]"]`)?.value;
    const hidden = document.getElementById(`admin-dob-hidden-${idx}`);
    if (hidden && day && month && year) {
        hidden.value = `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    }
}

function createParentAccount(e) {
    e.preventDefault();
    const form = document.getElementById('form-create-parent');
    if (!form) return;

    const fd = new FormData(form);

    fetch('api/api_create_parent.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') {
                form.reset();
                renderAdminStudentCards();
                toggleAdminCustomCurriculumField();
                toggleAdminLocationFields();
                if (typeof loadSystemData === 'function') {
                    loadSystemData();
                }
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'An error occurred while creating the parent account.');
        });
}

// ————————————————————————————————————————————————
// MODULE 3: TIMETABLE
// ————————————————————————————————————————————————
function toggleAddressField() {
    document.getElementById('tt-address-group').style.display =
        document.getElementById('tt-venue').value === 'home_visit' ? 'flex' : 'none';
}

function applySessionPreset(prefix, val) {
    if (!val || val === 'custom') return;
    const parts = val.split('-');
    if (parts.length === 2) {
        const startInput = document.getElementById(prefix + '-start');
        const endInput   = document.getElementById(prefix + '-end');
        if (startInput) startInput.value = parts[0];
        if (endInput)   endInput.value   = parts[1];
    }
}

function filterTeachersBySubject() {
    const subjectId = document.getElementById('tt-subject')?.value;
    const teacherSel = document.getElementById('tt-teacher');
    if (!teacherSel) return;

    teacherSel.innerHTML = '';
    if (!allTeachers || allTeachers.length === 0) {
        teacherSel.innerHTML = '<option value="">No teachers available in system</option>';
        return;
    }

    teacherSel.innerHTML = '<option value="">Select teacher…</option>';

    if (!subjectId) {
        allTeachers.forEach(t => {
            teacherSel.innerHTML += `<option value="${t.id}">${t.name}</option>`;
        });
        return;
    }

    const specialists = [];
    const others = [];

    allTeachers.forEach(t => {
        const ids = t.subject_ids ? String(t.subject_ids).split(',') : [];
        if (ids.includes(String(subjectId))) {
            specialists.push(t);
        } else {
            others.push(t);
        }
    });

    if (specialists.length > 0) {
        const specGroup = document.createElement('optgroup');
        specGroup.label = '⭐ Specialized Teachers for this Subject';
        specialists.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = `⭐ ${t.name} (Specialist)`;
            specGroup.appendChild(opt);
        });
        teacherSel.appendChild(specGroup);
    }

    if (others.length > 0) {
        const otherGroup = document.createElement('optgroup');
        otherGroup.label = specialists.length > 0 ? 'Other Available Teachers' : 'All Teachers';
        others.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            otherGroup.appendChild(opt);
        });
        teacherSel.appendChild(otherGroup);
    }
}

// ── Server-side pre-loaded data (bypasses AJAX session issues) ──
const _phpStudents = <?php echo json_encode($_tt_students, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const _phpTeachers = <?php echo json_encode($_tt_teachers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function _initTimetableUI() {
    loadSubjects().then(() => {
        // Populate schedule form subject dropdown
        const subjectSel = document.getElementById('tt-subject');
        if (subjectSel) {
            subjectSel.innerHTML = '<option value="">Select subject…</option>';
            if (availableSubjects && availableSubjects.length > 0) {
                availableSubjects.forEach(s => subjectSel.innerHTML += `<option value="${s.id}">${s.name}</option>`);
            }
        }

        // Populate teacher dropdown
        filterTeachersBySubject();

        // Populate student schedule dropdown
        const studentSel = document.getElementById('tt-student');
        if (studentSel) {
            studentSel.innerHTML = '<option value="">Select student…</option>';
            allStudentsForTT.forEach(s => {
                const adm = s.admission_no || s.staff_id || 'A000S';
                const subjsText = s.subject_names ? ` - Subjects: ${s.subject_names}` : '';
                studentSel.innerHTML += `<option value="${s.id}">${s.student_name} (${adm} - ${s.grade_level}${subjsText})</option>`;
            });
        }

        // Populate filter dropdowns
        const filterStudent = document.getElementById('tt-filter-student');
        if (filterStudent) {
            filterStudent.innerHTML = '<option value="">All Students</option>';
            allStudentsForTT.forEach(s => {
                const adm = s.admission_no || s.staff_id || 'A000S';
                filterStudent.innerHTML += `<option value="${s.id}">${s.student_name} (${adm})</option>`;
            });
        }
        const filterTeacher = document.getElementById('tt-filter-teacher');
        if (filterTeacher) {
            filterTeacher.innerHTML = '<option value="">All Teachers</option>';
            allTeachers.forEach(t => filterTeacher.innerHTML += `<option value="${t.id}">${t.name}</option>`);
        }

        // Populate edit slot teacher dropdown
        const editTeacherSel = document.getElementById('edit-slot-teacher');
        if (editTeacherSel) {
            editTeacherSel.innerHTML = '<option value="">Select teacher…</option>';
            allTeachers.forEach(t => editTeacherSel.innerHTML += `<option value="${t.id}">${t.name}</option>`);
        }

        // Populate print dropdowns
        const printStudentSel = document.getElementById('print-student-sel');
        if (printStudentSel) {
            printStudentSel.innerHTML = '<option value="">Select student…</option>';
            allStudentsForTT.forEach(s => {
                const adm = s.admission_no || s.staff_id || 'A000S';
                printStudentSel.innerHTML += `<option value="${s.id}">${s.student_name} (${adm} - ${s.grade_level})</option>`;
            });
        }
        const printTeacherSel = document.getElementById('print-teacher-sel');
        if (printTeacherSel) {
            printTeacherSel.innerHTML = '<option value="">Select teacher…</option>';
            allTeachers.forEach(t => printTeacherSel.innerHTML += `<option value="${t.id}">${t.name}</option>`);
        }

        filterTimetableByStudent();
    }).catch(err => {
        // subjects failed but still render grid with what we have
        filterTimetableByStudent();
    });
}

function loadTimetable() {
    // Use PHP pre-loaded data first — bypasses all AJAX/session issues
    allStudentsForTT = _phpStudents || [];
    allTeachers      = _phpTeachers || [];

    // Fetch live slots via AJAX (and optionally refresh students/teachers)
    fetch('api/api_schedule_lesson.php')
        .then(r => r.text())
        .then(raw => {
            try {
                const data = JSON.parse(raw);
                allSlots = (data && data.slots) ? data.slots : [];
                if (data && data.students && data.students.length > 0) allStudentsForTT = data.students;
                if (data && data.teachers && data.teachers.length > 0) allTeachers = data.teachers;
            } catch(e) {
                console.warn('Timetable API parse issue (PHP data used):', raw.substring(0, 300));
                allSlots = [];
            }
            _initTimetableUI();
        })
        .catch(err => {
            console.warn('Timetable API unreachable (PHP data used):', err);
            allSlots = [];
            _initTimetableUI();
        });
}


function renderStudentTimetableGrid(filterStudentId, filterTeacherId) {
    const tbody = document.getElementById('student-tt-tbody');
    if (!tbody) return;

    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    const dayAbbr = { Monday:'Mon', Tuesday:'Tue', Wednesday:'Wed', Thursday:'Thu', Friday:'Fri', Saturday:'Sat', Sunday:'Sun' };

    let students = allStudentsForTT;
    if (filterStudentId) students = students.filter(s => s.id == filterStudentId);

    if (!students.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="empty-row">No students enrolled yet.</td></tr>`;
        return;
    }

    tbody.innerHTML = students.map(s => {
        const studentSlots = allSlots.filter(sl => {
            if (sl.student_id != s.id) return false;
            if (filterTeacherId && sl.teacher_id != filterTeacherId) return false;
            return true;
        });
        const dayCells = days.map(day => {
            const daySlots = studentSlots.filter(sl => sl.day_of_week === day);
            if (!daySlots.length) return `<td style="color:var(--gray-500);text-align:center;font-size:0.75rem;">–</td>`;
            const cells = daySlots.map(sl => `
                <div style="background:${sl.venue_type==='home_visit'?'rgba(229,169,59,0.15)':'rgba(52,152,219,0.12)'};border-radius:6px;padding:5px 8px;margin-bottom:4px;font-size:0.76rem;">
                    <div style="font-weight:700;">${sl.start_time.slice(0,5)}–${sl.end_time.slice(0,5)}</div>
                    <div style="color:var(--gray-600);">${sl.venue_type==='home_visit'?'🏠':'🏫'} ${sl.teacher_name}</div>
                    <div style="display:flex;gap:4px;margin-top:4px;">
                        <button class="btn btn-primary btn-sm" style="padding:3px 6px;font-size:0.68rem;" onclick="openEditSlotModal(${sl.id},'${s.student_name.replace(/'/g,"\\'")}')"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn" style="background:#E74C3C;color:white;padding:3px 6px;font-size:0.68rem;border-radius:6px;" onclick="deleteSlot(${sl.id})"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>`).join('');
            return `<td>${cells}</td>`;
        }).join('');
        return `<tr>
            <td><strong>${s.student_name}</strong></td>
            <td><span class="badge" style="background:rgba(74,14,23,0.08);color:var(--primary);">${s.grade_level}</span></td>
            ${dayCells}
        </tr>`;
    }).join('');
}

function renderSlotsTable(filterStudentId, filterTeacherId) {
    const tbody = document.getElementById('slots-tbody');
    const countEl = document.getElementById('slots-count');
    if (!tbody) return;

    let filtered = allSlots;
    if (filterStudentId) filtered = filtered.filter(s => s.student_id == filterStudentId);
    if (filterTeacherId) filtered = filtered.filter(s => s.teacher_id == filterTeacherId);

    if (countEl) countEl.textContent = `${filtered.length} slot${filtered.length !== 1 ? 's' : ''}`;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="empty-row">No timetable slots found for the selected filter.</td></tr>`;
        return;
    }
    tbody.innerHTML = filtered.map(sl => {
        const vBadge = sl.venue_type === 'home_visit' ? 'background:rgba(229,169,59,0.2);color:#B45309;' : 'background:rgba(52,152,219,0.15);color:#2563EB;';
        const vLabel = sl.venue_type === 'home_visit' ? '🏠 Home Visit' : '🏫 Campus';
        return `<tr>
            <td><strong>${sl.student_name}</strong></td>
            <td>${sl.teacher_name}</td>
            <td>${sl.day_of_week}</td>
            <td>${sl.start_time.slice(0,5)} – ${sl.end_time.slice(0,5)}</td>
            <td><span class="badge" style="${vBadge}">${vLabel}</span></td>
            <td class="btn-group">
                <button class="btn btn-primary btn-sm" onclick="openEditSlotModal(${sl.id},'${sl.student_name.replace(/'/g,"\\'")}')"><i class="fa-solid fa-pen"></i> Edit</button>
                <button class="btn btn-sm" style="background:#E74C3C;color:white;border-radius:8px;" onclick="deleteSlot(${sl.id})"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>`;
    }).join('');
}

let currentTimetableView = 'student';

function setTimetableView(view) {
    currentTimetableView = view;
    
    // Update button states
    const btnStudent = document.getElementById('tt-view-btn-student');
    const btnTeacher = document.getElementById('tt-view-btn-teacher');
    
    if (view === 'student') {
        if (btnStudent) {
            btnStudent.className = 'btn btn-primary btn-sm';
            btnStudent.style.background = '';
            btnStudent.style.color = '';
        }
        if (btnTeacher) {
            btnTeacher.className = 'btn btn-outline btn-sm';
            btnTeacher.style.background = 'transparent';
            btnTeacher.style.color = '';
        }
        
        // Update table head
        const thead = document.getElementById('timetable-grid-thead');
        if (thead) {
            thead.innerHTML = `<tr><th>Student</th><th>Grade</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th></tr>`;
        }
    } else {
        if (btnStudent) {
            btnStudent.className = 'btn btn-outline btn-sm';
            btnStudent.style.background = 'transparent';
            btnStudent.style.color = '';
        }
        if (btnTeacher) {
            btnTeacher.className = 'btn btn-primary btn-sm';
            btnTeacher.style.background = '';
            btnTeacher.style.color = '';
        }
        
        // Update table head
        const thead = document.getElementById('timetable-grid-thead');
        if (thead) {
            thead.innerHTML = `<tr><th>Teacher</th><th>Role</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th></tr>`;
        }
    }
    
    filterTimetableByStudent();
}

function renderTeacherTimetableGrid(filterStudentId, filterTeacherId) {
    const tbody = document.getElementById('student-tt-tbody');
    if (!tbody) return;

    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

    let teachers = allTeachers;
    if (filterTeacherId) teachers = teachers.filter(t => t.id == filterTeacherId);

    if (!teachers.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="empty-row">No teachers registered yet.</td></tr>`;
        return;
    }

    tbody.innerHTML = teachers.map(t => {
        const teacherSlots = allSlots.filter(sl => {
            if (sl.teacher_id != t.id) return false;
            if (filterStudentId && sl.student_id != filterStudentId) return false;
            return true;
        });
        const dayCells = days.map(day => {
            const daySlots = teacherSlots.filter(sl => sl.day_of_week === day);
            if (!daySlots.length) return `<td style="color:var(--gray-500);text-align:center;font-size:0.75rem;">–</td>`;
            const cells = daySlots.map(sl => `
                <div style="background:${sl.venue_type==='home_visit'?'rgba(229,169,59,0.15)':'rgba(52,152,219,0.12)'};border-radius:6px;padding:5px 8px;margin-bottom:4px;font-size:0.76rem;">
                    <div style="font-weight:700;">${sl.start_time.slice(0,5)}–${sl.end_time.slice(0,5)}</div>
                    <div style="color:var(--gray-600);">${sl.venue_type==='home_visit'?'🏠':'🏫'} ${sl.student_name} (${sl.grade_level})</div>
                    <div style="display:flex;gap:4px;margin-top:4px;">
                        <button class="btn btn-primary btn-sm" style="padding:3px 6px;font-size:0.68rem;" onclick="openEditSlotModal(${sl.id},'${sl.student_name.replace(/'/g,"\\'")}')"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn" style="background:#E74C3C;color:white;padding:3px 6px;font-size:0.68rem;border-radius:6px;" onclick="deleteSlot(${sl.id})"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>`).join('');
            return `<td>${cells}</td>`;
        }).join('');
        return `<tr>
            <td><strong>${t.name}</strong></td>
            <td><span class="badge" style="background:rgba(74,14,23,0.08);color:var(--primary);">TEACHER</span></td>
            ${dayCells}
        </tr>`;
    }).join('');
}

function filterTimetableByStudent() {
    const sid = document.getElementById('tt-filter-student')?.value || '';
    const tid = document.getElementById('tt-filter-teacher')?.value || '';
    if (currentTimetableView === 'student') {
        renderStudentTimetableGrid(sid, tid);
    } else {
        renderTeacherTimetableGrid(sid, tid);
    }
    renderSlotsTable(sid, tid);
}

function openEditSlotModal(slotId, studentName) {
    const slot = allSlots.find(s => s.id == slotId);
    if (!slot) return;
    document.getElementById('edit-slot-id').value = slotId;
    document.getElementById('edit-slot-student-name').textContent = studentName;
    document.getElementById('edit-slot-day').value = slot.day_of_week;
    document.getElementById('edit-slot-start').value = slot.start_time.slice(0,5);
    document.getElementById('edit-slot-end').value = slot.end_time.slice(0,5);
    document.getElementById('edit-slot-venue').value = slot.venue_type;
    document.getElementById('edit-slot-address').value = slot.student_address || '';

    // Populate student dropdown
    const studentSel = document.getElementById('edit-slot-student');
    if (studentSel) {
        studentSel.innerHTML = '<option value="">Select student…</option>';
        allStudentsForTT.forEach(s => {
            studentSel.innerHTML += `<option value="${s.id}"${s.id == slot.student_id ? ' selected' : ''}>${s.student_name} (${s.grade_level})</option>`;
        });
    }

    // Populate teacher dropdown and pre-select
    const teacherSel = document.getElementById('edit-slot-teacher');
    if (teacherSel) {
        teacherSel.innerHTML = '<option value="">Select teacher…</option>';
        allTeachers.forEach(t => {
            teacherSel.innerHTML += `<option value="${t.id}"${t.id == slot.teacher_id ? ' selected' : ''}>${t.name}</option>`;
        });
    }

    toggleEditAddressField();
    document.getElementById('editSlotModal').classList.add('open');
}

function toggleEditAddressField() {
    const v = document.getElementById('edit-slot-venue')?.value;
    const g = document.getElementById('edit-slot-address-group');
    if (g) g.style.display = v === 'home_visit' ? 'flex' : 'none';
}

function saveEditSlot(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action',          'edit_slot');
    fd.append('slot_id',         document.getElementById('edit-slot-id').value);
    fd.append('student_id',      document.getElementById('edit-slot-student')?.value || '');
    fd.append('teacher_id',      document.getElementById('edit-slot-teacher').value);
    fd.append('day_of_week',     document.getElementById('edit-slot-day').value);
    fd.append('start_time',      document.getElementById('edit-slot-start').value);
    fd.append('end_time',        document.getElementById('edit-slot-end').value);
    fd.append('venue_type',      document.getElementById('edit-slot-venue').value);
    fd.append('student_address', document.getElementById('edit-slot-address').value);
    fetch('api/api_schedule_lesson.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            closeModal('editSlotModal');
            showAlert(data.status, data.message);
            if (data.status === 'success') loadTimetable();
        });
}

function deleteSlot(slotId) {
    if (!confirm('Permanently delete this timetable slot? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('action',  'delete');
    fd.append('slot_id', slotId);
    fetch('api/api_schedule_lesson.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') loadTimetable();
        });
}


function scheduleLesson(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'schedule');
    fd.append('teacher_id', document.getElementById('tt-teacher').value);
    fd.append('student_id', document.getElementById('tt-student').value);
    fd.append('day_of_week', document.getElementById('tt-day').value);
    fd.append('start_time', document.getElementById('tt-start').value);
    fd.append('end_time', document.getElementById('tt-end').value);
    fd.append('venue_type', document.getElementById('tt-venue').value);
    fd.append('student_address', document.getElementById('tt-address').value);

    fetch('api/api_schedule_lesson.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') { e.target.reset(); loadTimetable(); }
        });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// MODULE 4: ATTENDANCE
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function loadAttendance() {
    // For admin view — show all lessons in the system
    fetch('api/api_lesson_attendance.php?action=fetch_teacher_lessons&teacher_id=all')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('attendance-tbody');
            if (!tbody) return;
            if (!data.lessons || !data.lessons.length) {
                tbody.innerHTML = `<tr><td colspan="8" class="empty-row">No lesson records yet.</td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            data.lessons.forEach(l => {
                const statusMap = { scheduled: 'badge-pending', in_progress: 'badge-progress', completed: 'badge-approved' };
                tbody.innerHTML += `<tr>
                    <td>${l.lesson_date}</td>
                    <td>${l.student_name}</td>
                    <td>${l.teacher_name || '–'}</td>
                    <td>${l.day_of_week} ${l.start_time?.slice(0,5)}–${l.end_time?.slice(0,5)}</td>
                    <td>
                        <span class="badge ${
                            l.venue_type === 'online_meet' ? 'badge-progress' :
                            (l.venue_type === 'online_zoom' ? 'badge-progress' :
                            (l.venue_type === 'home_visit' ? 'badge-home' : 'badge-school'))
                        }">
                            ${
                                l.venue_type === 'online_meet' ? 'Online (Meet)' :
                                (l.venue_type === 'online_zoom' ? 'Online (Zoom)' :
                                (l.venue_type === 'home_visit' ? 'Home (1-on-1)' : 'School (1-on-1)'))
                            }
                        </span>
                    </td>
                    <td><span class="badge ${statusMap[l.session_status]}">${l.session_status}</span></td>
                    <td>${l.check_in_time || '–'}</td>
                    <td>${l.check_out_time || '–'}</td>
                </tr>`;
            });
        })
        .catch(() => {});
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// MODULE 5: EXAMS
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function loadAcademic() {
    loadSubjects(); // Populate subject dropdown for scheduling session

    fetch('api/api_manage_academic.php')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') return;
            currentExams = data.exams || [];

            // Exam dropdown
            const examSel = document.getElementById('ex-session-parent');
            if (examSel) {
                examSel.innerHTML = '<option value="">Select exam series.</option>';
                currentExams.forEach(ex => { examSel.innerHTML += `<option value="${ex.id}">${ex.exam_name} (${ex.term_identifier})</option>`; });
            }

            // Student dropdown for scheduling
            const studentSel = document.getElementById('ex-session-student');
            if (studentSel) {
                studentSel.innerHTML = '<option value="">Select student…</option>';
                (data.students || []).forEach(s => { studentSel.innerHTML += `<option value="${s.id}">${s.student_name} (${s.grade_level})</option>`; });
            }

            // Teacher dropdown for invigilation
            const tSel = document.getElementById('ex-session-teacher');
            if (tSel) {
                tSel.innerHTML = '<option value="">Select teacher.</option>';
                (data.teachers || []).forEach(t => { tSel.innerHTML += `<option value="${t.id}">${t.name}</option>`; });
            }

            // Sessions table
            const tbody = document.getElementById('exams-tbody');
            if (tbody) {
                tbody.innerHTML = '';
                if (!data.sessions?.length) {
                    tbody.innerHTML = `<tr><td colspan="8" class="empty-row">No exam sessions scheduled yet.</td></tr>`;
                } else {
                    data.sessions.forEach(s => {
                        tbody.innerHTML += `<tr>
                            <td>${s.exam_name}</td>
                            <td><strong>${s.student_name || 'N/A'}</strong></td>
                            <td>${s.subject}</td>
                            <td>${s.exam_date}</td>
                            <td>${s.start_time?.slice(0,5)} – ${s.end_time?.slice(0,5)}</td>
                            <td>${s.room_number}</td>
                            <td>${s.teacher_name}</td>
                            <td>—</td>
                        </tr>`;
                    });
                }
            }

            // Load chart
            const chart = document.getElementById('invig-load-chart');
            if (chart && data.invig_load) {
                chart.innerHTML = data.invig_load.map(t => `
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                        <span style="min-width:150px;font-weight:600;font-size:0.88rem;">${t.name}</span>
                        <div style="flex:1;background:var(--cream);border-radius:20px;height:18px;overflow:hidden;">
                            <div style="width:${Math.min(t.session_count * 20, 100)}%;background:var(--primary);height:100%;border-radius:20px;transition:width 0.5s;"></div>
                        </div>
                        <span style="font-size:0.82rem;color:var(--gray-600);">${t.session_count} session(s)</span>
                    </div>`
                ).join('');
            }

            // â”€â”€ Populate Result Slip print selectors â”€â”€
            const slipExam = document.getElementById('slip-exam');
            if (slipExam) {
                slipExam.innerHTML = '<option value="">Select exam…</option>';
                currentExams.forEach(ex => slipExam.innerHTML += `<option value="${ex.id}">${ex.exam_name} (${ex.term_identifier} ${ex.academic_year})</option>`);
            }
            const slipStudent = document.getElementById('slip-student');
            if (slipStudent) {
                slipStudent.innerHTML = '<option value="">Select student…</option>';
                (data.students || []).forEach(s => slipStudent.innerHTML += `<option value="${s.id}">${s.student_name} (${s.grade_level})</option>`);
            }
            const termStudent = document.getElementById('term-report-student');
            if (termStudent) {
                termStudent.innerHTML = '<option value="">Select student…</option>';
                (data.students || []).forEach(s => termStudent.innerHTML += `<option value="${s.id}">${s.student_name} (${s.grade_level})</option>`);
            }

            // â”€â”€ Save sessions and populate Exam Analysis dropdown â”€â”€
            currentExamSessions = data.sessions || [];
            const analExam = document.getElementById('anal-exam');
            if (analExam) {
                analExam.innerHTML = '<option value="">Select exam…</option>';
                currentExams.forEach(ex => analExam.innerHTML += `<option value="${ex.id}">${ex.exam_name} (${ex.term_identifier} ${ex.academic_year})</option>`);
            }
        });
}

function createExam(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'create_exam');
    fd.append('exam_name', document.getElementById('ex-name').value);
    fd.append('academic_year', document.getElementById('ex-year').value);
    fd.append('term_identifier', document.getElementById('ex-term').value);
    fd.append('submission_deadline', document.getElementById('ex-deadline').value);
    const alertsEl = document.getElementById('ex-alerts');
    if (alertsEl && alertsEl.checked) fd.append('automated_alerts_enabled', '1');
    fetch('api/api_manage_academic.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { showAlert(data.status, data.message); if (data.status === 'success') { e.target.reset(); loadAcademic(); } });
}

function scheduleExamSession(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'schedule_exam_session');
    fd.append('exam_id', document.getElementById('ex-session-parent').value);
    fd.append('student_id', document.getElementById('ex-session-student').value);
    fd.append('subject', document.getElementById('ex-session-subject').value);
    fd.append('exam_date', document.getElementById('ex-session-date').value);
    fd.append('start_time', document.getElementById('ex-session-start').value);
    fd.append('end_time', document.getElementById('ex-session-end').value);
    fd.append('room_number', document.getElementById('ex-session-room').value);
    fd.append('invigilator_teacher_id', document.getElementById('ex-session-teacher').value);
    fetch('api/api_manage_academic.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { showAlert(data.status, data.message); if (data.status === 'success') { e.target.reset(); loadAcademic(); } });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// MODULE 6: REPORTS
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// We will hold all fetched reports globally to support frontend filtering and printing
let allSystemReports = [];

function loadReports() {
    fetch('api/api_manage_reports.php')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') return;
            allSystemReports = data.reports || [];

            // Display badge count of pending moderation
            const pendingCount = allSystemReports.filter(r => r.status === 'pending').length;
            const badgeReports = document.getElementById('badge-reports');
            if (badgeReports) badgeReports.textContent = pendingCount;
            
            const reportsCount = document.getElementById('reports-count');
            if (reportsCount) reportsCount.textContent = `${pendingCount} pending moderation`;

            renderReportsTable();

            const otbody = document.getElementById('overdue-tbody');
            if (otbody) {
                otbody.innerHTML = '';
                if (!data.overdue_sessions?.length) {
                    otbody.innerHTML = `<tr><td colspan="6" class="empty-row">No overdue sessions found.</td></tr>`;
                } else {
                    data.overdue_sessions.forEach(s => {
                        otbody.innerHTML += `<tr>
                            <td>${s.exam_name}</td><td>${s.subject}</td>
                            <td><span style="color:var(--danger);font-weight:700;">${s.submission_deadline}</span></td>
                            <td>${s.teacher_name}</td>
                            <td>${s.marks_submitted > 0 ? '<span class="badge badge-approved">Submitted</span>' : '<span class="badge badge-danger">Missing</span>'}</td>
                            <td><button class="btn btn-danger btn-sm" onclick="sendNudge(${s.id})"><i class="fa-solid fa-bell"></i> Send Nudge</button></td>
                        </tr>`;
                    });
                }
            }

            // Populate student selector for accumulator from already loaded users list
            const studentSel = document.getElementById('accum-student');
            if (studentSel) {
                const studentsList = allUsers.filter(u => u.role === 'student');
                studentSel.innerHTML = '<option value="">Choose Student…</option>' +
                    studentsList.map(s => `<option value="${s.profile_id || s.id}">${s.name}</option>`).join('');
            }

            // Pre-populate Term selector for accumulation from loaded term settings
            const termSel = document.getElementById('accum-term-id');
            if (termSel && allTermData) {
                termSel.innerHTML = allTermData.map(t =>
                    `<option value="${t.id}" data-start="${t.start_date}" data-end="${t.end_date}">${t.academic_year} — ${t.term_name}</option>`
                ).join('');
            }

            handleAccumIntervalChange();
        });
}

function handleAccumIntervalChange() {
    const type  = document.getElementById('accum-interval').value;
    const termG = document.getElementById('accum-term-group');
    const monG  = document.getElementById('accum-month-group');
    const yrG   = document.getElementById('accum-year-group');
    const start = document.getElementById('accum-start-date');
    const end   = document.getElementById('accum-end-date');
    const label = document.getElementById('accum-period');

    // Default hide all extra selectors
    termG.style.display = 'none';
    monG.style.display = 'none';
    yrG.style.display = 'none';

    const today = new Date().toISOString().split('T')[0];

    if (type === 'weekly') {
        label.value = 'Term 1 Week 1';
        start.value = today;
        end.value   = today;
    } else if (type === 'terminal') {
        monG.style.display = 'block';
        // Select current month
        const cy = new Date().getFullYear();
        const cm = String(new Date().getMonth() + 1).padStart(2, '0');
        document.getElementById('accum-month-val').value = `${cy}-${cm}`;
        applyAccumMonthDates();
    } else if (type === 'termly') {
        termG.style.display = 'block';
        applyAccumTermDates();
    } else if (type === 'yearly') {
        yrG.style.display = 'block';
        applyAccumYearDates();
    }
}

function applyAccumTermDates() {
    const sel = document.getElementById('accum-term-id');
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    document.getElementById('accum-start-date').value = opt.getAttribute('data-start');
    document.getElementById('accum-end-date').value   = opt.getAttribute('data-end');
    document.getElementById('accum-period').value     = opt.textContent.split(' — ')[1] || opt.textContent;
}

function applyAccumMonthDates() {
    const val = document.getElementById('accum-month-val').value; // YYYY-MM
    if (!val) return;
    const parts = val.split('-');
    const year  = parseInt(parts[0]);
    const month = parseInt(parts[1]);

    const start = `${parts[0]}-${parts[1]}-01`;
    const lastDay = new Date(year, month, 0).getDate();
    const end = `${parts[0]}-${parts[1]}-${String(lastDay).padStart(2, '0')}`;

    document.getElementById('accum-start-date').value = start;
    document.getElementById('accum-end-date').value   = end;
    
    const monthName = new Date(year, month - 1, 1).toLocaleString('en-US', { month: 'long' });
    document.getElementById('accum-period').value     = `${monthName} ${year}`;
}

function applyAccumYearDates() {
    const year = document.getElementById('accum-year-val').value || new Date().getFullYear();
    document.getElementById('accum-start-date').value = `${year}-01-01`;
    document.getElementById('accum-end-date').value   = `${year}-12-31`;
    document.getElementById('accum-period').value     = `Year ${year}`;
}

function loadStudentTeachersForAccum() {
    const studentProfileId = document.getElementById('accum-student').value;
    const teacherSel = document.getElementById('accum-teacher');
    if (!studentProfileId) {
        teacherSel.innerHTML = '<option value="0">All Tutors (Combined)</option>';
        return;
    }

    // Find teachers who scheduled lessons with this student in the timetable_slots
    fetch('api/api_schedule_lesson.php?student_id=' + studentProfileId)
        .then(r => r.json())
        .then(data => {
            teacherSel.innerHTML = '<option value="0">All Tutors (Combined)</option>';
            if (data.status !== 'success') return;
            const slots = data.slots || [];
            
            // Unique teachers
            const seen = new Set();
            slots.forEach(s => {
                if (!seen.has(s.teacher_id)) {
                    seen.add(s.teacher_id);
                    teacherSel.innerHTML += `<option value="${s.teacher_id}">${s.teacher_name}</option>`;
                }
            });
        });
}

function accumulateReports(e) {
    e.preventDefault();
    const student_id = document.getElementById('accum-student').value;
    const teacher_id = document.getElementById('accum-teacher').value;
    const interval   = document.getElementById('accum-interval').value;
    const period     = document.getElementById('accum-period').value.trim();
    const start_date = document.getElementById('accum-start-date').value;
    const end_date   = document.getElementById('accum-end-date').value;

    if (!student_id) { showAlert('error', 'Please select a student.'); return; }

    const fd = new FormData();
    fd.append('action', 'accumulate_daily_reports');
    fd.append('student_id', student_id);
    fd.append('teacher_id', teacher_id);
    fd.append('start_date', start_date);
    fd.append('end_date', end_date);

    fetch('api/api_manage_reports.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                showAlert('error', data.message || 'No daily logs found for the selected parameters.');
                return;
            }

            // Fill Report Modal fields for review/override
            document.getElementById('report-modal-title').textContent = 'Review & Release Accumulated Report';
            document.getElementById('edit-report-id').value          = ''; // Empty ID indicating new report
            document.getElementById('edit-student-id').value         = student_id;
            document.getElementById('edit-teacher-id').value         = data.data.teacher_id;
            document.getElementById('edit-report-type').value        = interval;
            document.getElementById('edit-period-identifier').value  = period;

            document.getElementById('edit-topics').value      = data.data.topics_covered;
            document.getElementById('edit-performance').value = data.data.student_performance_notes;
            document.getElementById('edit-recs').value        = data.data.teacher_recommendations;

            const saveBtn = document.getElementById('report-save-btn');
            if (saveBtn) {
                saveBtn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Approve, Save & Release to Parent';
            }

            // Show report Modal
            document.getElementById('reportModal').classList.add('open');
        });
}

function renderReportsTable() {
    const filterType = document.getElementById('report-filter-type')?.value || 'all';
    const tbody = document.getElementById('reports-tbody');
    if (!tbody) return;

    const filtered = allSystemReports.filter(r => {
        if (filterType !== 'all' && r.report_type !== filterType) return false;
        return true;
    });

    tbody.innerHTML = '';
    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="empty-row">No reports match the selected filter.</td></tr>`;
        return;
    }

    filtered.forEach(r => {
        const statusBadge = r.status === 'approved' ? 'badge-approved' : 'badge-pending';
        const formattedType = r.report_type.charAt(0).toUpperCase() + r.report_type.slice(1);
        
        tbody.innerHTML += `<tr>
            <td><strong>${r.student_name}</strong><br><small style="color:var(--gray-600);">${r.grade_level || ''}</small></td>
            <td>${r.teacher_name}</td>
            <td><span class="badge" style="background:#FAF7F2;color:var(--primary);">${formattedType}</span></td>
            <td><strong>${r.period_identifier}</strong></td>
            <td><div style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${r.topics_covered}">${r.topics_covered}</div></td>
            <td><span class="badge ${statusBadge}">${r.status.toUpperCase()}</span></td>
            <td class="btn-group">
                <button class="btn btn-outline btn-sm" onclick="openReportEditor(${r.id}, \`${btoa(r.topics_covered)}\`, \`${btoa(r.student_performance_notes)}\`, \`${btoa(r.teacher_recommendations)}\`, '${r.status}')"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
                ${r.status === 'pending' ? 
                    `<button class="btn btn-primary btn-sm" onclick="approveAndReleaseReport(${r.id})"><i class="fa-solid fa-paper-plane"></i> Release</button>` : 
                    `<button class="btn btn-outline btn-sm" style="border-color:#27AE60;color:#27AE60;" onclick="printReportCard(${r.id})"><i class="fa-solid fa-file-pdf"></i> Print PDF</button>`
                }
            </td>
        </tr>`;
    });
}

function openReportEditor(id, topicsB64, perfB64, recsB64, status) {
    document.getElementById('edit-report-id').value = id;
    document.getElementById('edit-topics').value = atob(topicsB64);
    document.getElementById('edit-performance').value = atob(perfB64);
    document.getElementById('edit-recs').value = atob(recsB64);
    
    // Customize button text depending on status
    const saveBtn = document.getElementById('report-save-btn');
    if (saveBtn) {
        saveBtn.innerHTML = status === 'approved' ? 
            '<i class="fa-solid fa-floppy-disk"></i> Save Changes' : 
            '<i class="fa-solid fa-circle-check"></i> Approve & Release to Parent';
    }
    
    document.getElementById('reportModal').classList.add('open');
}

function approveAndReleaseReport(id) {
    const fd = new FormData();
    fd.append('action', 'approve_report');
    fd.append('report_id', id);
    fd.append('admin_id', 1); // Mock admin ID

    fetch('api/api_manage_reports.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            loadReports();
        });
}

function approveReport(e) {
    e.preventDefault();
    const id = document.getElementById('edit-report-id').value;
    const topics = document.getElementById('edit-topics').value;
    const performance = document.getElementById('edit-performance').value;
    const recs = document.getElementById('edit-recs').value;

    const fd = new FormData();
    if (!id) {
        // It's a new accumulated report
        fd.append('action', 'create_admin_report');
        fd.append('student_id', document.getElementById('edit-student-id').value);
        fd.append('teacher_id', document.getElementById('edit-teacher-id').value);
        fd.append('report_type', document.getElementById('edit-report-type').value);
        fd.append('period_identifier', document.getElementById('edit-period-identifier').value);
        fd.append('topics_covered', topics);
        fd.append('student_performance_notes', performance);
        fd.append('teacher_recommendations', recs);
        fd.append('admin_id', 1); // Mock admin ID
        
        fetch('api/api_manage_reports.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('success', 'Report accumulated and released successfully!');
                    loadReports();
                    closeModal('reportModal');
                } else {
                    showAlert('error', data.message);
                }
            });
    } else {
        // It's an edit of an existing report
        fd.append('action', 'edit_report');
        fd.append('report_id', id);
        fd.append('topics_covered', topics);
        fd.append('student_performance_notes', performance);
        fd.append('teacher_recommendations', recs);

        fetch('api/api_manage_reports.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const report = allSystemReports.find(r => r.id == id);
                    if (report && report.status === 'pending') {
                        approveAndReleaseReport(id);
                    } else {
                        showAlert('success', 'Report updated successfully.');
                        loadReports();
                    }
                    closeModal('reportModal');
                } else {
                    showAlert('error', data.message);
                }
            });
    }
}

function printReportCard(reportId) {
    const r = allSystemReports.find(rep => rep.id == reportId);
    if (!r) return;

    const formattedType = r.report_type.charAt(0).toUpperCase() + r.report_type.slice(1);
    const win = window.open('', '_blank', 'width=850,height=1100');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Academic Report Card – ${r.student_name}</title>
            <style>
                body { font-family: 'Outfit', 'Segoe UI', sans-serif; padding: 40px; color: #1e293b; line-height: 1.6; }
                .report-header { text-align: center; border-bottom: 3px double #4A0E17; padding-bottom: 20px; margin-bottom: 25px; }
                .report-header h1 { color: #4A0E17; margin: 0; font-size: 24px; letter-spacing: 1px; }
                .report-header p { color: #E5A93B; margin: 5px 0 0; font-weight: 700; font-size: 13px; text-transform: uppercase; }
                .meta-table { width: 100%; margin-bottom: 30px; border-collapse: collapse; }
                .meta-table td { padding: 8px 12px; font-size: 14px; border: 1px solid #e2e8f0; }
                .section-title { font-size: 15px; color: #4A0E17; border-bottom: 2px solid #E5A93B; padding-bottom: 4px; margin-top: 25px; margin-bottom: 10px; font-weight: 700; text-transform: uppercase; }
                .content-box { background: #FAF7F2; padding: 15px 20px; border-radius: 8px; font-size: 14px; border-left: 4px solid #4A0E17; margin-bottom: 15px; white-space: pre-line; }
                .footer-sign { display: flex; justify-content: space-between; margin-top: 60px; font-size: 13px; }
                .sign-line { border-top: 1px solid #4a0e17; width: 200px; text-align: center; padding-top: 5px; }
                @media print { body { padding: 0; } button { display: none; } }
        
        /* Responsive mobile styling */
        @media (max-width: 800px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; padding: 15px; background: linear-gradient(180deg, var(--dark) 0%, var(--primary) 100%); }
            .sidebar-logo { padding-bottom: 12px; margin-bottom: 12px; }
            .sidebar-logo img { height: 42px; }
            .nav-section-label { padding: 8px 12px 4px; }
            .nav-item { padding: 10px 12px; font-size: 0.85rem; }
            .topbar { position: relative; left: 0; right: 0; width: 100%; padding: 0 16px; border-left: none; }
            .main { margin-left: 0; padding: 20px 16px; }
            .page-header h1 { font-size: 1.5rem; }
            .page-header p { font-size: 0.85rem; }
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .metric-card { padding: 16px; border-radius: 12px; }
            .metric-info h4 { font-size: 0.65rem; }
            .metric-info p { font-size: 1.4rem; }
            .metric-icon { width: 42px; height: 42px; font-size: 1.2rem; border-radius: 10px; }
            .panel { padding: 20px 16px; border-radius: 14px; }
            .panel-header h2 { font-size: 1.1rem; }
            th, td { padding: 10px 12px; font-size: 0.82rem; }
        }
        @media (max-width: 480px) {
            .metrics-grid { grid-template-columns: 1fr; }
        }
    </style>
        </head>
        <body>
            <div class="report-header">
                <img src="logo.png" style="height:65px;margin-bottom:12px;">
                <h1>SANITY HOMEBASED TUITION ACADEMY</h1>
                <p>Official Academic Performance Report Card</p>
            </div>
            
            <table class="meta-table">
                <tr>
                    <td style="background:#FAF7F2;width:20%;"><strong>Student Name:</strong></td>
                    <td>${r.student_name}</td>
                    <td style="background:#FAF7F2;width:20%;"><strong>Academic Grade:</strong></td>
                    <td>${r.grade_level || '–'}</td>
                </tr>
                <tr>
                    <td style="background:#FAF7F2;"><strong>Report Type:</strong></td>
                    <td>${formattedType} Report</td>
                    <td style="background:#FAF7F2;"><strong>Period Identifier:</strong></td>
                    <td>${r.period_identifier}</td>
                </tr>
                <tr>
                    <td style="background:#FAF7F2;"><strong>Assigned Tutor:</strong></td>
                    <td>${r.teacher_name}</td>
                    <td style="background:#FAF7F2;"><strong>Moderated By:</strong></td>
                    <td>Academic Board / Admin</td>
                </tr>
            </table>

            <div class="section-title">1. Topics & Modules Covered</div>
            <div class="content-box">${r.topics_covered}</div>

            <div class="section-title">2. Student Performance & Key Observations</div>
            <div class="content-box">${r.student_performance_notes}</div>

            <div class="section-title">3. Tutor Recommendations & Actions</div>
            <div class="content-box">${r.teacher_recommendations}</div>

            <div class="footer-sign">
                <div>
                    <br><br>
                    <div class="sign-line">Tutor Signature</div>
                </div>
                <div style="text-align:right;">
                    <strong style="color:#27AE60;">âœ“ Released & Authorized</strong><br>
                    Date: ${new Date(r.created_at).toLocaleDateString()}<br><br>
                    <div class="sign-line">Director of Studies</div>
                </div>
            </div>
            
            <br><br>
            <div style="text-align:center;">
                <button onclick="window.print()" style="padding:10px 25px;background:#4A0E17;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:700;font-size:14px;box-shadow:0 3px 8px rgba(0,0,0,0.15);"><i class="fa-solid fa-print"></i> Print / Save Report Card (PDF)</button>
            </div>
        </body>
        </html>
    `);
    win.document.close();
}

function sendNudge(sessionId) {
    const fd = new FormData();
    fd.append('action', 'send_nudge');
    fd.append('exam_session_id', sessionId);
    fetch('api/api_resources.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => showAlert(data.status, data.message));
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// MODULE 7: LIBRARY & SUBJECT MANAGEMENT
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let availableSubjects = [];

function loadSubjects() {
    return fetch('api/api_resources.php?action=get_subjects')
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                availableSubjects = data.subjects || [];
                // Populate upload dropdown
                const uploadSel = document.getElementById('lib-subject');
                if (uploadSel) {
                    uploadSel.innerHTML = '<option value="">Select subject…</option>';
                    availableSubjects.forEach(s => uploadSel.innerHTML += `<option value="${s.name}">${s.name}</option>`);
                }
                // Populate edit modal dropdown
                const editSel = document.getElementById('edit-res-subject');
                if (editSel) {
                    editSel.innerHTML = '';
                    availableSubjects.forEach(s => editSel.innerHTML += `<option value="${s.name}">${s.name}</option>`);
                }
                // Populate filter dropdown
                const filterSel = document.getElementById('lib-filter-subject');
                if (filterSel) {
                    const curr = filterSel.value;
                    filterSel.innerHTML = '<option value="">All Subjects</option>';
                    availableSubjects.forEach(s => filterSel.innerHTML += `<option value="${s.name}">${s.name}</option>`);
                    filterSel.value = curr;
                }
                // Populate exam session subject dropdown
                const sessionSubSel = document.getElementById('ex-session-subject');
                if (sessionSubSel) {
                    sessionSubSel.innerHTML = '<option value="">Select subject.</option>';
                    availableSubjects.forEach(s => sessionSubSel.innerHTML += `<option value="${s.name}">${s.name}</option>`);
                }
            }
        });
}

function openSubjectManager() {
    loadSubjects().then(() => {
        const tbody = document.getElementById('subjects-tbody');
        tbody.innerHTML = '';
        if (!availableSubjects.length) {
            tbody.innerHTML = `<tr><td colspan="2" class="empty-row">No subjects added.</td></tr>`;
        } else {
            availableSubjects.forEach(s => {
                const imgHtml = s.image_path
                    ? `<img src="${s.image_path}" alt="" style="width:40px;height:30px;object-fit:cover;border-radius:4px;margin-right:8px;border:1px solid var(--gray-200);">`
                    : `<span style="display:inline-block;width:40px;height:30px;background:var(--gray-200);border-radius:4px;margin-right:8px;vertical-align:middle;"><i class="fa-solid fa-image" style="line-height:30px;width:40px;text-align:center;color:var(--gray-600);font-size:0.8rem;"></i></span>`;
                tbody.innerHTML += `<tr>
                    <td style="display:flex;align-items:center;">${imgHtml}<strong>${s.name}</strong></td>
                    <td class="btn-group">
                        <button class="btn btn-outline btn-sm" onclick="openEditSubjectModal(${s.id}, \`${btoa(s.name)}\`, \`${s.image_path || ''}\`)"><i class="fa-solid fa-pen"></i> Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteSubject(${s.id})"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>`;
            });
        }
        document.getElementById('subjectManagerModal').classList.add('open');
    });
}

function addSubject(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'add_subject');
    fd.append('name', document.getElementById('new-subj-name').value);
    const imgFile = document.getElementById('new-subj-image')?.files[0];
    if (imgFile) fd.append('subject_image', imgFile);
    fetch('api/api_resources.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.status, d.message);
            if (d.status === 'success') {
                document.getElementById('new-subj-name').value = '';
                if (document.getElementById('new-subj-image')) document.getElementById('new-subj-image').value = '';
                openSubjectManager();
            }
        });
}

function openEditSubjectModal(id, nameB64, currentImage) {
    // Build inline edit row or simple prompt with file input
    const name = atob(nameB64);
    const imgPreview = currentImage ? `<img src="${currentImage}" id="edit-subj-preview" style="max-width:80px;max-height:60px;object-fit:cover;border-radius:6px;margin-bottom:8px;border:1px solid var(--gray-200);">` : '';
    // We'll use a simple hidden-div approach with a secondary modal
    const container = document.getElementById('edit-subject-inline-container');
    if (container) {
        container.innerHTML = `
            <div style="background:var(--cream);border:1px solid var(--gray-200);border-radius:8px;padding:16px;margin-top:12px;">
                <h4 style="margin-bottom:12px;color:var(--primary);">Editing: <em>${name}</em></h4>
                ${imgPreview}
                <form id="edit-subj-form" onsubmit="submitEditSubject(event, ${id})" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;">
                    <input type="text" id="edit-subj-name" class="form-control" value="${name}" required placeholder="Subject name">
                    <label style="font-size:0.75rem;font-weight:700;color:var(--gray-600);">Replace Image (Optional)</label>
                    <input type="file" id="edit-subj-image" class="form-control" accept="image/*">
                    <div style="display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('edit-subject-inline-container').innerHTML=''">Cancel</button>
                    </div>
                </form>
            </div>`;
        container.scrollIntoView({ behavior: 'smooth' });
    }
}

function submitEditSubject(e, id) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'edit_subject');
    fd.append('id', id);
    fd.append('name', document.getElementById('edit-subj-name').value);
    const imgFile = document.getElementById('edit-subj-image')?.files[0];
    if (imgFile) fd.append('subject_image', imgFile);
    fetch('api/api_resources.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { showAlert(d.status, d.message); if (d.status === 'success') openSubjectManager(); });
}

function editSubjectPrompt(id, oldNameB64) {
    openEditSubjectModal(id, oldNameB64, '');
}

function deleteSubject(id) {
    if (!confirm('Delete this subject area? Resources attached will remain under that name text.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_subject');
    fd.append('id', id);
    fetch('api/api_resources.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { showAlert(d.status, d.message); openSubjectManager(); });
}

function loadLibrary() {
    loadSubjects();
    const filterSubj = document.getElementById('lib-filter-subject')?.value || '';
    fetch('api/api_resources.php?action=all&subject=' + encodeURIComponent(filterSubj))
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('library-tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            if (!data.resources?.length) {
                tbody.innerHTML = `<tr><td colspan="6" class="empty-row">No resources found for this filter.</td></tr>`;
                return;
            }
            data.resources.forEach(res => {
                const typeColors = { past_paper: 'badge-progress', marking_scheme: 'badge-approved', notes: 'badge-school', other: 'badge-pending' };
                const coverSrc = res.cover_image || res.subject_image || '';
                const thumbHtml = coverSrc
                    ? `<img src="${coverSrc}" style="width:44px;height:34px;object-fit:cover;border-radius:4px;border:1px solid var(--gray-200);">`
                    : `<span style="display:inline-block;width:44px;height:34px;background:var(--gray-200);border-radius:4px;text-align:center;line-height:34px;"><i class="fa-solid fa-image" style="color:var(--gray-600);font-size:0.75rem;"></i></span>`;
                tbody.innerHTML += `<tr>
                    <td style="display:flex;align-items:center;gap:10px;">${thumbHtml}<div><strong>${res.title}</strong><br><small style="color:var(--gray-600);">${(res.description||'').slice(0,55)}…</small></div></td>
                    <td><span class="badge badge-home">${res.subject}</span></td>
                    <td>${res.grade_level}</td>
                    <td><span class="badge ${typeColors[res.material_type] || ''}">${res.material_type.replace('_', ' ')}</span></td>
                    <td><small>${res.created_at?.split(' ')[0]}</small></td>
                    <td class="btn-group">
                        <a href="${res.file_path}" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i> View</a>
                        <button class="btn btn-primary btn-sm" onclick="openEditResourceModal(${res.id}, \`${btoa(res.title)}\`, \`${btoa(res.description)}\`, \`${btoa(res.subject)}\`, \`${btoa(res.grade_level)}\`, '${res.material_type}')"><i class="fa-solid fa-pen"></i> Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteResource(${res.id})"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>`;
            });
        });
}

function uploadResource(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'upload_resource');
    fd.append('title', document.getElementById('lib-title').value);
    fd.append('description', document.getElementById('lib-desc').value);
    fd.append('subject', document.getElementById('lib-subject').value);
    fd.append('grade_level', document.getElementById('lib-grade').value);
    fd.append('material_type', document.getElementById('lib-type').value);
    fd.append('uploaded_by', 1);
    fd.append('resource_file', document.getElementById('lib-file').files[0]);
    const coverFile = document.getElementById('lib-cover')?.files[0];
    if (coverFile) fd.append('cover_image', coverFile);

    showAlert('info', 'Uploading resource, please wait…');
    fetch('api/api_resources.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { showAlert(data.status, data.message); if (data.status === 'success') { e.target.reset(); loadLibrary(); } });
}

function openEditResourceModal(id, titleB64, descB64, subjectB64, gradeB64, type) {
    document.getElementById('edit-res-id').value    = id;
    document.getElementById('edit-res-title').value = atob(titleB64);
    document.getElementById('edit-res-desc').value  = atob(descB64);
    document.getElementById('edit-res-subject').value = atob(subjectB64);
    document.getElementById('edit-res-grade').value = atob(gradeB64);
    document.getElementById('edit-res-type').value  = type;
    document.getElementById('edit-res-file').value  = '';
    if (document.getElementById('edit-res-cover')) document.getElementById('edit-res-cover').value = '';
    document.getElementById('editResourceModal').classList.add('open');
}

function updateResource(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'edit_resource');
    fd.append('resource_id', document.getElementById('edit-res-id').value);
    fd.append('title', document.getElementById('edit-res-title').value);
    fd.append('description', document.getElementById('edit-res-desc').value);
    fd.append('subject', document.getElementById('edit-res-subject').value);
    fd.append('grade_level', document.getElementById('edit-res-grade').value);
    fd.append('material_type', document.getElementById('edit-res-type').value);
    if (document.getElementById('edit-res-file').files.length > 0) {
        fd.append('resource_file', document.getElementById('edit-res-file').files[0]);
    }
    if (document.getElementById('edit-res-cover')?.files.length > 0) {
        fd.append('cover_image', document.getElementById('edit-res-cover').files[0]);
    }

    fetch('api/api_resources.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            closeModal('editResourceModal');
            showAlert(data.status, data.message);
            if (data.status === 'success') loadLibrary();
        });
}

function deleteResource(id) {
    if (!confirm('Delete this resource? This is permanent.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_resource');
    fd.append('resource_id', id);
    fetch('api/api_resources.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { showAlert(data.status, data.message); loadLibrary(); });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// UTILITIES
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function showAlert(type, msg) {
    const el = document.getElementById('globalAlert');
    el.className = `alert alert-${type}`;
    el.innerHTML = msg;
    el.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => { el.style.display = 'none'; }, 8000);
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// PROFILE MANAGEMENT
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function loadProfile() {
    fetch('api/api_profile.php?action=get_profile')
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.user) {
                document.getElementById('prof-name').value  = data.user.name || '';
                document.getElementById('prof-email').value = data.user.email || '';
                document.getElementById('prof-phone').value = data.user.phone || '';
                document.getElementById('prof-role').value  = (data.user.role || '').toUpperCase();
            }
        });
}

function updateProfile(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('csrf_token', getCsrfToken());
    fd.append('action', 'update_profile');
    fd.append('name', document.getElementById('prof-name').value);
    fd.append('email', document.getElementById('prof-email').value);
    fd.append('phone', document.getElementById('prof-phone').value);
    fd.append('current_password', document.getElementById('prof-curr-pass').value);
    fd.append('new_password', document.getElementById('prof-new-pass').value);

    fetch('api/api_profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') {
                document.getElementById('prof-curr-pass').value = '';
                document.getElementById('prof-new-pass').value = '';
                // Refresh top sidebar name display dynamically
                const sidebarName = document.querySelector('.sidebar-logo div div');
                if (sidebarName) sidebarName.textContent = data.user.name;
            }
        });
}

function toggleEditRoleSubjects(checkedIds = []) {
    const role = document.getElementById('aedit-role').value;
    const group = document.getElementById('aedit-subjects-group');
    const container = document.getElementById('aedit-subjects-checkboxes');
    const admGroup = document.getElementById('aedit-admission-group');

    if (admGroup) {
        admGroup.style.display = role === 'student' ? 'block' : 'none';
    }

    if (!group || !container) return;

    if (role === 'teacher') {
        group.style.display = 'block';
        if (availableSubjects.length === 0) {
            loadSubjects().then(() => {
                renderRoleSubjectsCheckboxes(container, 'aedit-subject-item', checkedIds);
            });
        } else {
            renderRoleSubjectsCheckboxes(container, 'aedit-subject-item', checkedIds);
        }
    } else {
        group.style.display = 'none';
    }
}

function openAdminEditUserModal(id, nameB64, emailB64, phoneB64, role, subjectIds = '', admissionNoB64 = '') {
    document.getElementById('aedit-user-id').value = id;
    document.getElementById('aedit-name').value    = atob(nameB64);
    document.getElementById('aedit-email').value   = atob(emailB64);
    document.getElementById('aedit-phone').value   = atob(phoneB64);
    document.getElementById('aedit-role').value    = role;
    document.getElementById('aedit-new-pass').value = '';

    const admInput = document.getElementById('aedit-admission-no');
    if (admInput) {
        admInput.value = admissionNoB64 ? atob(admissionNoB64) : '';
    }
    
    const checkedIds = subjectIds ? subjectIds.split(',') : [];
    toggleEditRoleSubjects(checkedIds);
    
    document.getElementById('adminEditUserModal').classList.add('open');
}

function adminUpdateUser(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('csrf_token', getCsrfToken());
    fd.append('action', 'admin_update_user');
    fd.append('user_id', document.getElementById('aedit-user-id').value);
    fd.append('name', document.getElementById('aedit-name').value);
    fd.append('email', document.getElementById('aedit-email').value);
    fd.append('phone', document.getElementById('aedit-phone').value);
    const role = document.getElementById('aedit-role').value;
    fd.append('role',     role);
    const newPass = document.getElementById('aedit-new-pass').value;
    fd.append('new_password', newPass);

    if (role === 'student') {
        const admVal = document.getElementById('aedit-admission-no')?.value || '';
        fd.append('admission_no', admVal);
    }

    if (role === 'teacher') {
        const checked = document.querySelectorAll('input[name="aedit-subject-item"]:checked');
        checked.forEach(cb => {
            fd.append('subject_ids[]', cb.value);
        });
    }

    fetch('api/api_profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            closeModal('adminEditUserModal');
            if (data.status === 'success' && newPass) {
                // Show a reminder to share the new password with the user
                showAlert('success', `${data.message} ⚠️ Please inform the user their new temporary password is: "${newPass}". They will be asked to set a new security question on first login.`);
            } else {
                showAlert(data.status, data.message);
            }
            if (data.status === 'success') loadSystemData();
        });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// SYSTEM SETTINGS — Term Dates
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let allTermData = [];

function loadAllTermDates() {
    fetch('api/api_settings.php?action=get_term_dates')
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') return;
            allTermData = d.terms || [];
            // Also load grading scales while we're in settings
            loadGradingScales();

            // Populate academic year dropdown (unique years)
            const years = [...new Set(allTermData.map(t => t.academic_year))];
            if (years.length === 0) {
                const cy = new Date().getFullYear();
                years.push(`${cy}`);
            }
            const yearSel = document.getElementById('settings-year-select');
            if (yearSel) {
                const prevYear = yearSel.value;
                yearSel.innerHTML = years.sort((a,b) => b-a).map(y =>
                    `<option value="${y}">${y}</option>`
                ).join('');
                if (prevYear && years.includes(prevYear)) yearSel.value = prevYear;
            }

            // Render full overview table
            const allTbody = document.getElementById('all-terms-tbody');
            const allTermsCount = document.getElementById('all-terms-count');
            if (allTermsCount) allTermsCount.textContent = `${allTermData.length} term entries`;
            if (allTbody) {
                if (!allTermData.length) {
                    allTbody.innerHTML = '<tr><td colspan="7" class="empty-row">No term dates configured yet.</td></tr>';
                } else {
                    allTbody.innerHTML = allTermData.map(t => {
                        const start = new Date(t.start_date);
                        const end   = new Date(t.end_date);
                        const weeks = Math.round((end - start) / (7 * 24 * 3600 * 1000));
                        const fmtDate = d => new Date(d).toLocaleDateString('en-KE', {day:'2-digit', month:'short', year:'numeric'});
                        return `<tr>
                            <td><strong>${t.academic_year}</strong></td>
                            <td><span class="badge" style="background:rgba(74,14,23,0.08);color:var(--primary);">Term ${t.term_number}</span></td>
                            <td>${t.term_name}</td>
                            <td>${fmtDate(t.start_date)}</td>
                            <td>${fmtDate(t.end_date)}</td>
                            <td><span style="color:var(--gray-600);font-size:0.85rem;">${weeks} wks</span></td>
                            <td class="btn-group">
                                <button class="btn btn-outline btn-sm" onclick="prefillTermForm('${t.academic_year}',${t.term_number},'${t.term_name}','${t.start_date}','${t.end_date}')">
                                    <i class="fa-solid fa-pencil"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteTerm(${t.id},'${t.term_name}')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                    }).join('');
                }
            }

            // Load filtered terms for selected year
            loadTermDates();
        });
}

function loadTermDates() {
    const yearSel = document.getElementById('settings-year-select');
    if (!yearSel) return;
    const year   = yearSel.value;
    const tbody  = document.getElementById('term-dates-tbody');
    if (!tbody) return;
    if (!year) return;

    const filtered = allTermData.filter(t => t.academic_year === year)
                                .sort((a,b) => a.term_number - b.term_number);

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="empty-row">No terms configured for ${year}. Use the form below to add terms.</td></tr>`;
        return;
    }

    const fmtDate = d => new Date(d).toLocaleDateString('en-KE', {day:'2-digit', month:'short', year:'numeric'});
    tbody.innerHTML = filtered.map(t => {
        const start = new Date(t.start_date);
        const end   = new Date(t.end_date);
        const weeks = Math.round((end - start) / (7 * 24 * 3600 * 1000));
        const now   = new Date();
        let statusBadge = '';
        if (now < start) statusBadge = '<span style="font-size:0.72rem;background:#DBEAFE;color:#1D4ED8;padding:2px 8px;border-radius:20px;font-weight:700;">Upcoming</span>';
        else if (now > end) statusBadge = '<span style="font-size:0.72rem;background:#F3F4F6;color:#6B7280;padding:2px 8px;border-radius:20px;font-weight:700;">Past</span>';
        else statusBadge = '<span style="font-size:0.72rem;background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:20px;font-weight:700;">Active</span>';

        return `<tr>
            <td><strong>Term ${t.term_number}</strong>&nbsp;${statusBadge}</td>
            <td>${t.term_name}</td>
            <td>${fmtDate(t.start_date)}</td>
            <td>${fmtDate(t.end_date)}</td>
            <td style="color:var(--gray-600);font-size:0.85rem;">${weeks} weeks</td>
            <td class="btn-group">
                <button class="btn btn-outline btn-sm" onclick="prefillTermForm('${t.academic_year}',${t.term_number},'${t.term_name}','${t.start_date}','${t.end_date}')">
                    <i class="fa-solid fa-pencil"></i> Edit
                </button>
                <button class="btn btn-danger btn-sm" onclick="deleteTerm(${t.id},'${t.term_name}')">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

function saveTermDates(e) {
    e.preventDefault();
    const year   = document.getElementById('settings-year-select').value;
    const number = document.getElementById('term-number').value;
    const name   = document.getElementById('term-name').value.trim();
    const start  = document.getElementById('term-start').value;
    const end    = document.getElementById('term-end').value;

    if (!year) { showAlert('error', 'Please select or create an academic year first.'); return; }
    if (start >= end) { showAlert('error', 'Start date must be before end date.'); return; }

    const fd = new FormData();
    fd.append('action', 'save_term_dates');
    fd.append('academic_year', year);
    fd.append('terms[0][term_number]', number);
    fd.append('terms[0][term_name]', name);
    fd.append('terms[0][start_date]', start);
    fd.append('terms[0][end_date]', end);

    fetch('api/api_settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.status, d.message);
            if (d.status === 'success') {
                clearTermForm();
                loadAllTermDates();
            }
        });
}

function deleteTerm(termId, termName) {
    if (!confirm(`Delete "${termName}"? This cannot be undone.`)) return;
    const fd = new FormData();
    fd.append('action', 'delete_term');
    fd.append('term_id', termId);
    fetch('api/api_settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.status, d.message);
            if (d.status === 'success') loadAllTermDates();
        });
}

function prefillTermForm(year, num, name, start, end) {
    document.getElementById('settings-year-select').value = year;
    document.getElementById('term-number').value = num;
    document.getElementById('term-name').value   = name;
    document.getElementById('term-start').value  = start;
    document.getElementById('term-end').value    = end;
    document.getElementById('term-form').scrollIntoView({behavior:'smooth', block:'center'});
}

function clearTermForm() {
    document.getElementById('term-number').value = '1';
    document.getElementById('term-name').value   = '';
    document.getElementById('term-start').value  = '';
    document.getElementById('term-end').value    = '';
}

function promptNewYear() {
    const y = prompt('Enter new academic year (e.g. 2027):');
    if (!y || !/^\d{4}$/.test(y.trim())) { showAlert('error', 'Please enter a valid 4-digit year.'); return; }
    const yearSel = document.getElementById('settings-year-select');
    // Check if already exists
    const exists = Array.from(yearSel.options).some(o => o.value === y.trim());
    if (!exists) {
        const opt = document.createElement('option');
        opt.value = opt.textContent = y.trim();
        yearSel.insertBefore(opt, yearSel.firstChild);
    }
    yearSel.value = y.trim();
    loadTermDates();
    showAlert('success', `Academic year ${y.trim()} ready — now add terms using the form below.`);
}

// Init
window.onload = () => {
    updateTopbarDate();
    loadBellNotifications();
    loadSystemData();
    loadSubjects().then(() => {
        // If Teacher is already selected in the role dropdown on load, show subject section
        toggleRoleSubjects();
    });
    const savedTab = (new URLSearchParams(window.location.search).get('fresh') === '1')
        ? (() => { localStorage.removeItem('admin_dashboard_active_tab'); return 'dashboard'; })()
        : (localStorage.getItem('admin_dashboard_active_tab') || 'dashboard');
    if (savedTab) {
        switchTab(savedTab);
    }
};

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// INDIVIDUAL TIMETABLE PRINTING
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function printIndividualStudentTT() {
    const sel   = document.getElementById('print-student-sel');
    const sid   = sel ? sel.value : '';
    const sname = sel ? (sel.selectedOptions[0]?.text || 'Student') : 'Student';
    if (!sid) { showAlert('error', 'Please select a student first.'); return; }

    const slots = allSlots.filter(s => s.student_id == sid);
    const days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

    let rows = '';
    days.forEach(day => {
        const daySlots = slots.filter(s => s.day_of_week === day);
        if (!daySlots.length) return;
        daySlots.forEach(s => {
            const venueLabel = s.venue_type === 'home_visit' ? 'Home (1-on-1)' :
                (s.venue_type === 'online_meet' ? 'Online (Meet)' :
                (s.venue_type === 'online_zoom' ? 'Online (Zoom)' : 'School (1-on-1)'));
            rows += `<tr>
                <td>${day}</td>
                <td>${s.start_time?.slice(0,5)} – ${s.end_time?.slice(0,5)}</td>
                <td>${s.teacher_name || '—'}</td>
                <td>${venueLabel}</td>
                <td>${s.student_address || '—'}</td>
            </tr>`;
        });
    });

    _openTTPrintWindow(`Student Timetable – ${sname}`, 'Student', sname, rows);
}

function printIndividualTeacherTT() {
    const sel   = document.getElementById('print-teacher-sel');
    const tid   = sel ? sel.value : '';
    const tname = sel ? (sel.selectedOptions[0]?.text || 'Teacher') : 'Teacher';
    if (!tid) { showAlert('error', 'Please select a teacher first.'); return; }

    const slots = allSlots.filter(s => s.teacher_id == tid);
    const days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

    let rows = '';
    days.forEach(day => {
        const daySlots = slots.filter(s => s.day_of_week === day);
        if (!daySlots.length) return;
        daySlots.forEach(s => {
            const venueLabel = s.venue_type === 'home_visit' ? 'Home (1-on-1)' :
                (s.venue_type === 'online_meet' ? 'Online (Meet)' :
                (s.venue_type === 'online_zoom' ? 'Online (Zoom)' : 'School (1-on-1)'));
            rows += `<tr>
                <td>${day}</td>
                <td>${s.start_time?.slice(0,5)} – ${s.end_time?.slice(0,5)}</td>
                <td>${s.student_name || '—'}</td>
                <td>${venueLabel}</td>
                <td>${s.student_address || '—'}</td>
            </tr>`;
        });
    });

    _openTTPrintWindow(`Teacher Timetable – ${tname}`, 'Teacher', tname, rows);
}

function _openTTPrintWindow(pageTitle, roleLabel, personName, rows) {
    const win = window.open('', '_blank', 'width=950,height=720');
    win.document.write(`<!DOCTYPE html><html><head>
        <title>${pageTitle}</title>
        <style>
            body { font-family: 'Segoe UI', sans-serif; padding: 35px; color: #1e293b; }
            .hdr { text-align:center; border-bottom: 3px double #4A0E17; padding-bottom:16px; margin-bottom:22px; }
            .hdr h1 { color:#4A0E17; font-size:20px; margin:0; letter-spacing:1px; }
            .hdr p  { color:#E5A93B; font-weight:700; font-size:11px; text-transform:uppercase; margin:5px 0 0; }
            .meta   { display:flex; gap:30px; margin-bottom:20px; font-size:13px; background:#FAF7F2; padding:12px 16px; border-radius:8px; }
            .meta span { font-weight:700; color:#4A0E17; }
            table   { width:100%; border-collapse:collapse; font-size:13px; }
            th      { background:#4A0E17; color:white; padding:10px 12px; text-align:left; }
            td      { padding:9px 12px; border-bottom:1px solid #e2e8f0; }
            tr:nth-child(even) td { background:#FAF7F2; }
            .footer { margin-top:50px; display:flex; justify-content:space-between; font-size:12px; }
            .sign-line { border-top:1px solid #4A0E17; width:170px; text-align:center; padding-top:5px; }
            @media print { button { display:none !important; } }
    
        /* Responsive mobile styling */
        @media (max-width: 800px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; padding: 15px; background: linear-gradient(180deg, var(--dark) 0%, var(--primary) 100%); }
            .sidebar-logo { padding-bottom: 12px; margin-bottom: 12px; }
            .sidebar-logo img { height: 42px; }
            .nav-section-label { padding: 8px 12px 4px; }
            .nav-item { padding: 10px 12px; font-size: 0.85rem; }
            .topbar { position: relative; left: 0; right: 0; width: 100%; padding: 0 16px; border-left: none; }
            .main { margin-left: 0; padding: 20px 16px; }
            .page-header h1 { font-size: 1.5rem; }
            .page-header p { font-size: 0.85rem; }
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .metric-card { padding: 16px; border-radius: 12px; }
            .metric-info h4 { font-size: 0.65rem; }
            .metric-info p { font-size: 1.4rem; }
            .metric-icon { width: 42px; height: 42px; font-size: 1.2rem; border-radius: 10px; }
            .panel { padding: 20px 16px; border-radius: 14px; }
            .panel-header h2 { font-size: 1.1rem; }
            th, td { padding: 10px 12px; font-size: 0.82rem; }
        }
        @media (max-width: 480px) {
            .metrics-grid { grid-template-columns: 1fr; }
        }
    </style>
    </head><body>
        <div class="hdr">
            <img src="logo.png" style="height:60px;margin-bottom:10px;">
            <h1>SANITY HOMEBASED TUITION ACADEMY</h1>
            <p>Individual ${roleLabel} Weekly Timetable</p>
        </div>
        <div class="meta">
            <div>${roleLabel}: <span>${personName}</span></div>
            <div>Printed: <span>${new Date().toDateString()}</span></div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>${roleLabel === 'Student' ? 'Teacher' : 'Student'}</th>
                    <th>Venue</th>
                    <th>Location / Address</th>
                </tr>
            </thead>
            <tbody>${rows || '<tr><td colspan="5" style="text-align:center;padding:20px;color:#6C757D;">No timetable slots scheduled.</td></tr>'}</tbody>
        </table>
        <div class="footer">
            <div><br><br><div class="sign-line">${roleLabel} Signature</div></div>
            <div style="text-align:right;"><br><br><div class="sign-line">Director of Studies</div></div>
        </div>
        <br>
        <div style="text-align:center;margin-top:20px;">
            <button onclick="window.print()" style="padding:11px 28px;background:#4A0E17;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:700;font-size:14px;">
                ðŸ–¨ Print / Save as PDF
            </button>
        </div>
    </body></html>`);
    win.document.close();
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// PRINT STUDENT EXAM RESULT SLIP
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function printStudentExamResult() {
    const examId    = document.getElementById('slip-exam')?.value;
    const studentId = document.getElementById('slip-student')?.value;
    const examName  = document.getElementById('slip-exam')?.selectedOptions[0]?.text || 'Exam';
    const stuName   = document.getElementById('slip-student')?.selectedOptions[0]?.text || 'Student';

    if (!examId || !studentId) {
        showAlert('error', 'Please select both an exam and a student to print the result slip.');
        return;
    }

    fetch(`api/api_manage_reports.php?action=student_exam_report&exam_id=${examId}&student_id=${studentId}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') { showAlert('error', data.message || 'Failed to load results.'); return; }

            const exam    = data.exam;
            const student = data.student;
            const subjects = data.subjects || [];

            let subjectRows = '';
            subjects.forEach((s, i) => {
                const gradePill = s.grade_letter && s.grade_letter !== '–'
                    ? `<span style="background:rgba(74,14,23,0.1);color:#4A0E17;padding:2px 10px;border-radius:10px;font-weight:800;font-size:0.85rem;">${s.grade_letter}</span>`
                    : '<span style="color:#999;">—</span>';
                subjectRows += `<tr>
                    <td style="text-align:center;color:#6C757D;">${i+1}</td>
                    <td><strong>${s.subject}</strong></td>
                    <td style="text-align:center;">${s.marks_obtained}</td>
                    <td style="text-align:center;">${gradePill}</td>
                    <td style="font-size:0.8rem;color:#555;">${s.teacher_remarks || '—'}</td>
                    <td style="font-size:0.75rem;color:#6C757D;">${s.teacher_name || '—'}</td>
                </tr>`;
            });

            const win = window.open('', '_blank', 'width=900,height=1050');
            win.document.write(`<!DOCTYPE html><html><head>
                <title>Result Slip – ${student.student_name}</title>
                <style>
                    body { font-family: 'Segoe UI', sans-serif; padding: 40px; color: #1e293b; line-height: 1.6; }
                    .hdr { text-align:center; margin-bottom:24px; padding-bottom:18px; border-bottom: 3px double #4A0E17; }
                    .hdr h1 { color:#4A0E17; font-size:22px; margin:0; letter-spacing:1px; }
                    .hdr p  { color:#E5A93B; font-weight:700; font-size:11px; text-transform:uppercase; margin:5px 0 0; }
                    .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:0; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; margin-bottom:24px; font-size:13px; }
                    .meta-cell { padding:9px 14px; border-bottom:1px solid #e2e8f0; }
                    .meta-cell:nth-child(odd) { background:#FAF7F2; font-weight:700; color:#4A0E17; }
                    table { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:20px; }
                    th { background:#4A0E17; color:white; padding:10px 12px; text-align:left; }
                    td { padding:9px 12px; border-bottom:1px solid #e2e8f0; vertical-align:middle; }
                    tr:nth-child(even) td { background:#FAF7F2; }
                    .totals-box { background:linear-gradient(135deg,#4A0E17,#6b1422); color:white; border-radius:12px; padding:18px 24px; display:flex; gap:40px; margin-bottom:24px; }
                    .total-item { text-align:center; }
                    .total-item .val { font-size:28px; font-weight:800; }
                    .total-item .lbl { font-size:11px; opacity:0.75; text-transform:uppercase; letter-spacing:0.5px; }
                    .remarks-box { background:#FEF3C7; border-left:4px solid #E5A93B; border-radius:8px; padding:14px 18px; font-size:13px; margin-bottom:24px; }
                    .remarks-box strong { color:#4A0E17; display:block; margin-bottom:6px; }
                    .footer { display:flex; justify-content:space-between; margin-top:50px; font-size:12px; }
                    .sign-line { border-top:1px solid #4A0E17; width:160px; text-align:center; padding-top:5px; }
                    @media print { button { display:none !important; } body { padding:20px; } }
            
        /* Responsive mobile styling */
        @media (max-width: 800px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; padding: 15px; background: linear-gradient(180deg, var(--dark) 0%, var(--primary) 100%); }
            .sidebar-logo { padding-bottom: 12px; margin-bottom: 12px; }
            .sidebar-logo img { height: 42px; }
            .nav-section-label { padding: 8px 12px 4px; }
            .nav-item { padding: 10px 12px; font-size: 0.85rem; }
            .topbar { position: relative; left: 0; right: 0; width: 100%; padding: 0 16px; border-left: none; }
            .main { margin-left: 0; padding: 20px 16px; }
            .page-header h1 { font-size: 1.5rem; }
            .page-header p { font-size: 0.85rem; }
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .metric-card { padding: 16px; border-radius: 12px; }
            .metric-info h4 { font-size: 0.65rem; }
            .metric-info p { font-size: 1.4rem; }
            .metric-icon { width: 42px; height: 42px; font-size: 1.2rem; border-radius: 10px; }
            .panel { padding: 20px 16px; border-radius: 14px; }
            .panel-header h2 { font-size: 1.1rem; }
            th, td { padding: 10px 12px; font-size: 0.82rem; }
        }
        @media (max-width: 480px) {
            .metrics-grid { grid-template-columns: 1fr; }
        }
    </style>
            </head><body>
                <div class="hdr">
                    <img src="logo.png" style="height:60px;margin-bottom:10px;">
                    <h1>SANITY HOMEBASED TUITION ACADEMY</h1>
                    <p>Official Examination Result Slip</p>
                </div>

                <div class="meta-grid">
                    <div class="meta-cell">Student Name:</div>
                    <div class="meta-cell">${student.student_name}</div>
                    <div class="meta-cell">Grade / Level:</div>
                    <div class="meta-cell">${student.grade_level || '—'}</div>
                    <div class="meta-cell">Exam Series:</div>
                    <div class="meta-cell">${exam.exam_name}</div>
                    <div class="meta-cell">Academic Year:</div>
                    <div class="meta-cell">${exam.academic_year} — ${exam.term_identifier}</div>
                    <div class="meta-cell">Parent / Guardian:</div>
                    <div class="meta-cell">${student.parent_name || '—'}</div>
                    <div class="meta-cell">Printed On:</div>
                    <div class="meta-cell">${new Date().toDateString()}</div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Subject</th>
                            <th style="text-align:center;">Marks</th>
                            <th style="text-align:center;">Grade</th>
                            <th>Teacher Remarks</th>
                            <th>Marked By</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${subjectRows || '<tr><td colspan="6" style="text-align:center;padding:20px;color:#999;">No results recorded for this student.</td></tr>'}
                    </tbody>
                </table>

                <div class="totals-box">
                    <div class="total-item">
                        <div class="val">${data.total}</div>
                        <div class="lbl">Total Marks</div>
                    </div>
                    <div class="total-item">
                        <div class="val">${data.average}%</div>
                        <div class="lbl">Average Score</div>
                    </div>
                    <div class="total-item">
                        <div class="val" style="font-size:36px;">${data.overall_grade}</div>
                        <div class="lbl">Overall Grade</div>
                    </div>
                    <div class="total-item">
                        <div class="val">${subjects.length}</div>
                        <div class="lbl">Subjects Sat</div>
                    </div>
                </div>

                <div class="remarks-box">
                    <strong>ðŸ“ Summarized Teacher Remarks:</strong>
                    ${data.summarized_remark || 'No remarks provided.'}
                </div>

                <div class="footer">
                    <div><br><br><div class="sign-line">Class Teacher Signature</div></div>
                    <div style="text-align:center;"><br><br><div class="sign-line">Head of Academics</div></div>
                    <div style="text-align:right;"><br><br><div class="sign-line">Director of Studies</div></div>
                </div>

                <div style="text-align:center;margin-top:28px;">
                    <button onclick="window.print()" style="padding:11px 28px;background:#4A0E17;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:700;font-size:14px;">
                        ðŸ–¨ Print / Save as PDF
                    </button>
                </div>
            </body></html>`);
            win.document.close();
        })
        .catch(() => showAlert('error', 'Network error loading result data.'));
}

// ─────────────────────────────────────────────────
// PRINT STUDENT TERM PROGRESS REPORT (ACCUMULATED)
// ─────────────────────────────────────────────────
function printStudentTermReport() {
    const year      = document.getElementById('term-report-year')?.value;
    const term      = document.getElementById('term-report-term')?.value;
    const studentId = document.getElementById('term-report-student')?.value;
    const studentName = document.getElementById('term-report-student')?.selectedOptions[0]?.text || 'Student';

    if (!year || !term || !studentId) {
        showAlert('error', 'Please select the Academic Year, Term, and a Student to print the term report.');
        return;
    }

    fetch(`api/api_manage_reports.php?action=student_term_report&student_id=${studentId}&academic_year=${year}&term_identifier=${term}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') { 
                showAlert('error', data.message || 'Failed to load term results.'); 
                return; 
            }

            const student = data.student;
            const exams   = data.exams || [];
            const subjects = data.subjects || [];
            const colAves = data.column_averages || [];

            // Calculate term dates or default based on term
            let startDate = '8th January, ' + year;
            let endDate = '10th April, ' + year;
            if (term.includes('2')) {
                startDate = '6th May, ' + year;
                endDate = '9th August, ' + year;
            } else if (term.includes('3')) {
                startDate = '27th September, ' + year;
                endDate = '23rd December, ' + year;
            }

            // Headers for exams
            let examHeadersHtml = '';
            exams.forEach(ex => {
                examHeadersHtml += `<th style="text-align:center;background:#000000;color:white;padding:8px;font-size:0.75rem;border-right:1px solid #444;min-width:60px;">${escHtml(ex.exam_name)}</th>`;
            });

            // Rows for subjects
            let subjectRowsHtml = '';
            subjects.forEach(s => {
                let scoreCellsHtml = '';
                s.scores.forEach(score => {
                    const displayScore = score !== null ? `${Math.round(score)}%` : '—';
                    scoreCellsHtml += `<td style="text-align:center;padding:10px;border-bottom:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">${displayScore}</td>`;
                });

                const devColor = s.dev.includes('+') ? '#065f46' : (s.dev.includes('-') ? '#991b1b' : '#1e293b');
                const pointsDisplay = s.points > 0 ? s.points : '—';
                const gradeDisplay = s.grade || '—';

                subjectRowsHtml += `
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:10px;font-weight:700;border-right:1px solid #e2e8f0;">${escHtml(s.subject)}</td>
                        ${scoreCellsHtml}
                        <td style="text-align:center;color:${devColor};font-weight:700;border-right:1px solid #e2e8f0;">${s.dev}</td>
                        <td style="text-align:center;font-weight:700;border-right:1px solid #e2e8f0;">${pointsDisplay}</td>
                        <td style="text-align:center;font-weight:700;border-right:1px solid #e2e8f0;">${gradeDisplay}</td>
                        <td style="padding:10px;font-size:0.8rem;color:#475569;">${escHtml(s.comments)}</td>
                    </tr>
                `;
            });

            // Column averages row html
            let averageCellsHtml = '';
            colAves.forEach(ave => {
                const displayAve = ave !== null ? `${Math.round(ave)}%` : '—';
                averageCellsHtml += `<td style="text-align:center;padding:10px;font-weight:800;background:#FFFBEB;border-right:1px solid #e2e8f0;">${displayAve}</td>`;
            });

            const overallMeanScoreDisplay = data.overall_mean_score ? `${Math.round(data.overall_mean_score)}%` : '—';
            const overallDevColor = data.overall_dev.includes('+') ? '#065f46' : (data.overall_dev.includes('-') ? '#991b1b' : '#1e293b');

            const meanRowHtml = `
                <tr style="background:#FFFBEB;font-weight:800;border-top:2px solid #4A0E17;">
                    <td style="padding:10px;color:#4A0E17;border-right:1px solid #e2e8f0;">Mean Score</td>
                    ${averageCellsHtml}
                    <td style="text-align:center;color:${overallDevColor};border-right:1px solid #e2e8f0;">${data.overall_dev}</td>
                    <td style="text-align:center;border-right:1px solid #e2e8f0;">${data.mean_points || '—'}</td>
                    <td style="text-align:center;color:#4A0E17;border-right:1px solid #e2e8f0;">${data.overall_grade || '—'}</td>
                    <td style="padding:10px;color:#4A0E17;">${escHtml(data.overall_comment)}</td>
                </tr>
            `;

            // Setup curriculum sub-titles exactly matching Kenyan KCSE or Cambridge
            let curriculumLabel = student.curriculum_name || 'Kenyan Curriculum';
            let focusSubLabel = 'Kenya Certificate of Secondary Education _ KCSE Focus';
            
            if (curriculumLabel.toLowerCase().includes('cambridge') || curriculumLabel.toLowerCase().includes('igcse')) {
                focusSubLabel = 'Cambridge Assessment International Education _ IGCSE Focus';
            } else if (curriculumLabel.toLowerCase().includes('cbc') || curriculumLabel.toLowerCase().includes('cbe')) {
                focusSubLabel = 'Kenya Primary & Junior School Curriculum _ CBC Focus';
            }

            const studyTypeLabel = student.study_type 
                ? student.study_type.charAt(0).toUpperCase() + student.study_type.slice(1) 
                : 'Homeschooling';

            const win = window.open('', '_blank', 'width=1000,height=1200');
            win.document.write(`<!DOCTYPE html><html><head>
                <title>${studyTypeLabel} Opener Progress Report - ${student.student_name}</title>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; color: #1e293b; line-height: 1.5; background: #fff; }
                    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                    .header-table td { border: none; padding: 0; background: none !important; }
                    .logo-side { width: 40%; text-align: left; }
                    .logo-img { height: 80px; margin-bottom: 5px; }
                    .academy-title { color: #4A0E17; font-size: 1.25rem; font-weight: 800; margin: 0; }
                    .academy-tagline { color: #E5A93B; font-style: italic; font-size: 0.82rem; margin: 2px 0 0; font-weight: 600; }
                    .info-side { width: 60%; text-align: right; font-size: 0.85rem; line-height: 1.4; color: #334155; }
                    .info-side h2 { color: #1e293b; font-size: 1.35rem; margin: 0 0 4px; font-weight: 800; }
                    .info-side a { color: #0284c7; text-decoration: none; font-weight: 600; }
                    .accent-line { border-top: 4px solid #b91c1c; margin: 15px 0; }
                    
                    .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 0.9rem; }
                    .meta-table td { border: none; padding: 6px 10px; background: none !important; }
                    .curriculum-title { text-align: center; font-size: 1.05rem; font-weight: 700; color: #475569; margin-top: 15px; }
                    .curriculum-subtitle { text-align: center; font-size: 1.2rem; font-weight: 900; color: #4A0E17; text-transform: uppercase; margin: 4px 0 15px; letter-spacing: 0.5px; }
                    
                    .progress-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-bottom: 25px; border: 1px solid #cbd5e1; }
                    .progress-table th { background: #b91c1c; color: white; padding: 10px; font-weight: 700; border: 1px solid #cbd5e1; }
                    .progress-table td { border: 1px solid #cbd5e1; }
                    
                    .bottom-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 30px; margin-top: 25px; }
                    .comment-section { font-size: 0.9rem; }
                    .comment-item { margin-bottom: 15px; line-height: 1.4; }
                    .comment-item strong { color: #1e293b; display: block; margin-bottom: 4px; font-size: 0.95rem; }
                    
                    .signature-box { margin-top: 25px; border-top: 1px solid #cbd5e1; padding-top: 10px; }
                    .signature-img { height: 75px; display: block; margin: 5px 0; }
                    
                    .chart-box { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 15px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
                    
                    .footer-wrap { display: flex; justify-content: space-between; align-items: center; margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 15px; font-size: 0.8rem; color: #64748b; font-style: italic; }
                    .circular-badge { width: 56px; height: 56px; border: 2px solid #b91c1c; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; color: #b91c1c; font-style: normal; }
                    
                    @media print {
                        button { display: none !important; }
                        body { padding: 15px; }
                        .progress-table th { background-color: #b91c1c !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        tr:nth-child(even) td { background-color: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        .progress-table tr[style*="background:#FFFBEB"] td { background-color: #FFFBEB !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    }
                </style>
            </head><body>
                
                <table class="header-table">
                    <tr>
                        <td class="logo-side">
                            <img src="logo.png" class="logo-img" onerror="this.src='logo.png';">
                            <h1 class="academy-title">SANITY HOMEBASED</h1>
                            <p class="academy-tagline">Achieving Excellence Together</p>
                        </td>
                        <td class="info-side">
                            <h2>Sanity Home-Based Tuition Academy</h2>
                            Email: <strong>info@sanityeducation.com</strong><br>
                            Link: <a href="https://www.sanityeducation.com" target="_blank">https://www.sanityeducation.com</a><br>
                            Report Date: <strong>${new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}</strong>
                        </td>
                    </tr>
                </table>
                
                <div class="accent-line"></div>
                
                <table class="meta-table">
                    <tr>
                        <td style="width:15%;font-weight:800;color:#4A0E17;">${studyTypeLabel} Opener Progress Report</td>
                        <td style="width:35%;"></td>
                        <td style="width:12%;font-weight:700;color:#4A0E17;text-align:right;">Student:</td>
                        <td style="width:38%;font-weight:800;font-size:1.05rem;">${student.student_name}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:700;color:#4A0E17;">Start Date:</td>
                        <td>${startDate}</td>
                        <td style="font-weight:700;color:#4A0E17;text-align:right;">Form / Grade:</td>
                        <td style="font-weight:800;">${student.grade_level || '—'}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:700;color:#4A0E17;">End Date:</td>
                        <td>${endDate}</td>
                        <td style="font-weight:700;color:#4A0E17;text-align:right;">Mean Grade:</td>
                        <td style="font-weight:900;color:#b91c1c;font-size:1.1rem;">${data.overall_grade || '—'} <span style="color:#1e293b;font-weight:700;font-size:0.95rem;">[${data.mean_points || '0'} pts]</span></td>
                    </tr>
                </table>
                
                <div class="curriculum-title">${escHtml(curriculumLabel)}</div>
                <div class="curriculum-subtitle">${escHtml(focusSubLabel)}</div>
                
                <div class="accent-line" style="margin-top:0;margin-bottom:15px;border-top:1.5px solid #cbd5e1;"></div>
                
                <table class="progress-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;background:#b91c1c;color:white;padding:10px;font-size:0.85rem;border-right:1px solid #cbd5e1;">Subjects</th>
                            ${examHeadersHtml}
                            <th style="text-align:center;background:#000000;color:white;padding:8px;font-size:0.75rem;border-right:1px solid #cbd5e1;width:55px;">Dev</th>
                            <th style="text-align:center;background:#000000;color:white;padding:8px;font-size:0.75rem;border-right:1px solid #cbd5e1;width:55px;">Pts</th>
                            <th style="text-align:center;background:#000000;color:white;padding:8px;font-size:0.75rem;border-right:1px solid #cbd5e1;width:75px;">Grade</th>
                            <th style="text-align:left;background:#b91c1c;color:white;padding:10px;font-size:0.85rem;">Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${subjectRowsHtml}
                        ${meanRowHtml}
                    </tbody>
                </table>
                
                <div class="bottom-grid">
                    <div class="comment-section">
                        <div class="comment-item">
                            <strong>TERM ${term.toUpperCase().replace('TERM ', '')} TERM DATES</strong>
                            <p style="margin:0;color:#475569;">${startDate} — ${endDate}</p>
                        </div>
                        <div class="comment-item" style="margin-top:18px;">
                            <strong>General Comment:</strong>
                            <p style="margin:0;color:#475569;font-style:italic;">${escHtml(data.overall_comment)}</p>
                        </div>
                        <div class="comment-item" style="margin-top:18px;">
                            <strong>Program Coordinator:</strong>
                            <p style="margin:0;color:#475569;">Focus and revise weak subject areas. Work consistently on your daily assignments.</p>
                        </div>
                        
                        <div class="signature-box">
                            <strong>Report Generated by;</strong>
                            <img src="signature.png" class="signature-img" onerror="this.style.display='none';">
                            <span style="display:block;font-weight:700;color:#1e293b;margin-top:4px;">Eddy Corley</span>
                            <span style="display:block;font-size:0.8rem;color:#64748b;">Admin Tutoring Team</span>
                        </div>
                    </div>
                    
                    <div>
                        <div class="chart-box">
                            <canvas id="rankChart" width="440" height="320"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="footer-wrap">
                    <div>Achieving Excellence Together...</div>
                    <div class="circular-badge">
                        <span>AY</span>
                        <span style="font-size:0.88rem;margin-top:2px;">${year}</span>
                    </div>
                </div>
                
                <div style="text-align:center;margin-top:30px;margin-bottom:20px;">
                    <button onclick="window.print()" style="padding:12px 35px;background:#4A0E17;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:700;font-size:15px;box-shadow:0 4px 6px rgba(0,0,0,0.15);transition:background 0.2s;">
                        <i class="fa-solid fa-print"></i> Print / Save as PDF
                    </button>
                </div>
                
                <script>
                    window.onload = function() {
                        const canvas = document.getElementById('rankChart');
                        if (!canvas) return;
                        const ctx = canvas.getContext('2d');
                        
                        const data = ${JSON.stringify(subjects.map(s => ({ name: s.subject, score: s.average_score })))};
                        
                        // Clear canvas
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                        
                        // Chart dimensions
                        const chartWidth = 360;
                        const chartHeight = 190;
                        const chartLeft = 45;
                        const chartTop = 55;
                        const chartBottom = chartTop + chartHeight;
                        
                        // Colors palette
                        const colors = ['#6b9ed4', '#f4b400', '#00b0f0', '#92d050', '#ffc000', '#a88a00', '#7030a0', '#4A0E17'];
                        
                        // Draw grid lines
                        ctx.strokeStyle = '#e2e8f0';
                        ctx.lineWidth = 1;
                        ctx.fillStyle = '#64748b';
                        ctx.font = '10px sans-serif';
                        ctx.textAlign = 'right';
                        ctx.textBaseline = 'middle';
                        
                        for (let i = 0; i <= 100; i += 20) {
                            const y = chartBottom - (i / 100) * chartHeight;
                            ctx.beginPath();
                            ctx.moveTo(chartLeft, y);
                            ctx.lineTo(chartLeft + chartWidth, y);
                            ctx.stroke();
                            ctx.fillText(i, chartLeft - 10, y);
                        }
                        
                        // Draw bars
                        const numBars = data.length;
                        const barSpacing = 12;
                        const totalSpacing = barSpacing * (numBars + 1);
                        const barWidth = (chartWidth - totalSpacing) / numBars;
                        
                        ctx.textAlign = 'center';
                        
                        data.forEach((item, idx) => {
                            const x = chartLeft + barSpacing + idx * (barWidth + barSpacing);
                            const height = (item.score / 100) * chartHeight;
                            const y = chartBottom - height;
                            const color = colors[idx % colors.length];
                            
                            // Draw bar
                            ctx.fillStyle = color;
                            ctx.fillRect(x, y, barWidth, height);
                            
                            // Write value inside the bar (near the top)
                            ctx.fillStyle = '#ffffff';
                            ctx.font = 'bold 11px sans-serif';
                            if (height > 25) {
                                ctx.fillText(Math.round(item.score), x + barWidth / 2, y + 15);
                            } else {
                                ctx.fillStyle = '#1e293b';
                                ctx.fillText(Math.round(item.score), x + barWidth / 2, y - 8);
                            }
                            
                            // Draw rotated subject label
                            ctx.save();
                            ctx.translate(x + barWidth / 2, chartBottom + 10);
                            ctx.rotate(-Math.PI / 4);
                            ctx.fillStyle = '#1e293b';
                            ctx.font = '10px sans-serif';
                            ctx.textAlign = 'right';
                            ctx.textBaseline = 'middle';
                            
                            let name = item.name;
                            if (name.length > 10) name = name.substring(0, 8) + '..';
                            ctx.fillText(name, 0, 0);
                            ctx.restore();
                        });
                        
                        // Title
                        ctx.fillStyle = '#4A0E17';
                        ctx.font = 'bold 12px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.fillText('TERM ${term.toUpperCase()} SUBJECT PERFORMANCE RANK', canvas.width / 2, 22);
                        
                        // Legend
                        ctx.font = '9px sans-serif';
                        ctx.textAlign = 'left';
                        let legendX = chartLeft;
                        let legendY = 38;
                        data.forEach((item, idx) => {
                            const color = colors[idx % colors.length];
                            ctx.fillStyle = color;
                            ctx.fillRect(legendX, legendY - 6, 8, 8);
                            ctx.fillStyle = '#475569';
                            ctx.fillText(item.name, legendX + 11, legendY);
                            legendX += ctx.measureText(item.name).width + 20;
                            if (legendX > canvas.width - 50) {
                                legendX = chartLeft;
                                legendY += 12;
                            }
                        });
                    };
                <\/script>
            </body></html>`);
            win.document.close();
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Network error loading term report details.');
        });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// GRADING SCALE MANAGER
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function loadGradingScales() {
    fetch('api/api_settings.php?action=get_grading_scales')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('grade-scales-tbody');
            if (!tbody) return;
            if (!data.scales || !data.scales.length) {
                tbody.innerHTML = `<tr><td colspan="6" class="empty-row">No grading scales configured yet. Add your first boundary above.</td></tr>`;
                return;
            }
            tbody.innerHTML = data.scales.map(s => `<tr>
                <td><strong>${s.grade_level}</strong></td>
                <td><span style="background:rgba(74,14,23,0.1);color:var(--primary);padding:3px 12px;border-radius:12px;font-weight:800;">${s.letter_grade}</span></td>
                <td>${s.min_mark}%</td>
                <td>${s.max_mark}%</td>
                <td style="font-size:0.82rem;color:var(--gray-600);">${s.remarks_template || '—'}</td>
                <td>
                    <button class="btn btn-danger btn-sm" onclick="deleteGradeScale(${s.id})">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </td>
            </tr>`).join('');
        });
}

function saveGradeScale(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'save_grading_scale');
    fd.append('grade_level',      document.getElementById('gs-level').value);
    fd.append('letter_grade',     document.getElementById('gs-letter').value);
    fd.append('min_mark',         document.getElementById('gs-min').value);
    fd.append('max_mark',         document.getElementById('gs-max').value);
    fd.append('remarks_template', document.getElementById('gs-remarks').value);
    fetch('api/api_settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') {
                document.getElementById('grade-scale-form').reset();
                loadGradingScales();
            }
        });
}

function deleteGradeScale(id) {
    if (!confirm('Delete this grading boundary? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_grading_scale');
    fd.append('scale_id', id);
    fetch('api/api_settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') loadGradingScales();
        });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// EXAM ANALYSIS & EDITING (Admin Only)
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let currentAnalExamId = null;
let currentAnalSubject = '';

function loadAnalSessions() {
    const examId = document.getElementById('anal-exam').value;
    const sel = document.getElementById('anal-session');
    sel.innerHTML = '<option value="">Select subject…</option>';
    document.getElementById('anal-students-container').style.display = 'none';
    document.getElementById('anal-metrics-container').style.display = 'none';
    if (!examId) return;

    // Filter unique subjects scheduled under this exam
    const filtered = currentExamSessions.filter(s => s.exam_id == examId);
    const subjects = [...new Set(filtered.map(s => s.subject))];
    
    if (subjects.length === 0) {
        // Fallback: Populate default subjects if none scheduled
        const defaultSubjects = ['Mathematics', 'Chemistry', 'Biology', 'Physics', 'English', 'Swahili'];
        defaultSubjects.forEach(sub => {
            sel.innerHTML += `<option value="${sub}">${sub}</option>`;
        });
    } else {
        subjects.forEach(sub => {
            sel.innerHTML += `<option value="${sub}">${sub}</option>`;
        });
    }
}

function loadAnalStudents() {
    const examId = document.getElementById('anal-exam').value;
    const subject = document.getElementById('anal-session').value;
    if (!examId || !subject) {
        document.getElementById('anal-students-container').style.display = 'none';
        document.getElementById('anal-metrics-container').style.display = 'none';
        return;
    }
    
    currentAnalExamId = examId;
    currentAnalSubject = subject;

    fetch(`api/api_manage_academic.php?action=admin_subject_students&exam_id=${examId}&subject=${encodeURIComponent(subject)}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') { showAlert('error', data.message); return; }
            currentAnalStudents = data.students || [];
            
            // Calculate exam metrics
            let total = 0, count = 0, high = -1, low = 101;
            currentAnalStudents.forEach(s => {
                if (s.marks_obtained !== '' && s.marks_obtained !== null) {
                    const mk = parseFloat(s.marks_obtained);
                    total += mk;
                    count++;
                    if (mk > high) high = mk;
                    if (mk < low) low = mk;
                }
            });

            if (count > 0) {
                document.getElementById('anal-metric-count').textContent = count;
                document.getElementById('anal-metric-avg').textContent = (total / count).toFixed(1) + '%';
                document.getElementById('anal-metric-high').textContent = high.toFixed(1) + '%';
                document.getElementById('anal-metric-low').textContent = low.toFixed(1) + '%';
                document.getElementById('anal-metrics-container').style.display = 'block';
            } else {
                document.getElementById('anal-metric-count').textContent = '0';
                document.getElementById('anal-metric-avg').textContent = '0.0%';
                document.getElementById('anal-metric-high').textContent = '0.0%';
                document.getElementById('anal-metric-low').textContent = '0.0%';
                document.getElementById('anal-metrics-container').style.display = 'block';
            }

            renderAnalStudentsTable(currentAnalStudents);
            document.getElementById('anal-students-container').style.display = 'block';
        });
}

function renderAnalStudentsTable(students) {
    const tbody = document.getElementById('anal-students-tbody');
    if (!students.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-row">No students found. Ensure students are enrolled in the system.</td></tr>';
        return;
    }
    tbody.innerHTML = students.map(s => `
        <tr>
            <td><strong>${s.student_name}</strong></td>
            <td><span style="font-size:0.8rem;color:var(--gray-600);">${s.grade_level || '—'}</span></td>
            <td>
                <input type="number" class="form-control" style="width:100px;text-align:center;padding:5px;" id="anal-marks-${s.student_id}" 
                    value="${s.marks_obtained !== '' ? s.marks_obtained : ''}" min="0" max="100" step="0.5">
            </td>
            <td>
                <input type="text" class="form-control" id="anal-remarks-${s.student_id}" value="${s.teacher_remarks || ''}" placeholder="Remark...">
            </td>
            <td>
                <select id="anal-pub-${s.student_id}" class="form-control" style="padding:5px;width:130px;">
                    <option value="0" ${s.is_published == 0 ? 'selected' : ''}>Pending Approval</option>
                    <option value="1" ${s.is_published == 1 ? 'selected' : ''}>Approved &amp; Published ✓</option>
                </select>
            </td>
        </tr>
    `).join('');
}

function saveAllAnalMarks() {
    if (!currentAnalExamId || !currentAnalSubject || !currentAnalStudents.length) return;
    const promises = currentAnalStudents.map(s => {
        const marksEl   = document.getElementById(`anal-marks-${s.student_id}`);
        const remarksEl = document.getElementById(`anal-remarks-${s.student_id}`);
        const pubEl     = document.getElementById(`anal-pub-${s.student_id}`);
        
        // Skip saving if mark is empty to avoid creating empty sessions
        if (!marksEl || marksEl.value === '') return Promise.resolve();

        const fd = new FormData();
        fd.append('action', 'submit_marks');
        if (s.exam_session_id) {
            fd.append('exam_session_id', s.exam_session_id);
        }
        fd.append('exam_id', currentAnalExamId);
        fd.append('subject', currentAnalSubject);
        fd.append('student_id', s.student_id);
        fd.append('marks_obtained', marksEl.value);
        fd.append('teacher_remarks', remarksEl ? remarksEl.value : '');
        fd.append('is_published', pubEl ? pubEl.value : '0');
        return fetch('api/api_manage_academic.php', { method: 'POST', body: fd }).then(r => r.json());
    });

    Promise.all(promises).then(results => {
        const errors = results.filter(r => r && r.status === 'error');
        if (errors.length) {
            showAlert('error', 'Some marks failed to update.');
        } else {
            showAlert('success', '✅ All marks and approval/publishing states updated successfully.');
            loadAnalStudents();
        }
    });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// TOPBAR CLOCK
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function updateTopbarDate() {
    const el = document.getElementById('topbar-date');
    if (!el) return;
    const now = new Date();
    el.textContent = now.toLocaleDateString('en-GB', { weekday:'short', day:'numeric', month:'short', year:'numeric' });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// NOTIFICATIONS
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let allNotifications = [];

function toggleNotifDropdown() {
    const dd = document.getElementById('notif-dropdown');
    if (!dd) return;
    if (dd.classList.contains('open')) {
        dd.classList.remove('open');
    } else {
        dd.classList.add('open');
        loadBellNotifications();
        markNotificationsAsRead();
    }
}
function closeNotifDropdown() {
    document.getElementById('notif-dropdown')?.classList.remove('open');
}
// Close bell dropdown when clicking outside
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('notif-bell')?.closest('.notif-bell-wrap');
    if (wrap && !wrap.contains(e.target)) closeNotifDropdown();
});

function markNotificationsAsRead() {
    const fd = new FormData();
    fd.append('action', 'mark_as_read');
    fetch('api/api_notifications.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                const bell = document.getElementById('notif-bell');
                const badge = document.getElementById('badge-notifs');
                const badgeMobile = document.getElementById('badge-notifs-mobile');
                if (bell) bell.classList.remove('has-notif');
                if (badge) badge.style.display = 'none';
                if (badgeMobile) badgeMobile.textContent = '0';
            }
        })
        .catch(err => console.error('Error marking notifications as read:', err));
}

function loadBellNotifications() {
    fetch('api/api_notifications.php?action=get_notifications&unread=1')
        .then(r => r.json())
        .then(data => {
            allNotifications = data.notifications || [];
            const list = document.getElementById('notif-bell-list');
            const bell = document.getElementById('notif-bell');
            if (!list) return;
            if (!allNotifications.length) {
                list.innerHTML = '<div class="notif-empty"><i class="fa-regular fa-bell-slash"></i>No new notifications</div>';
                bell?.classList.remove('has-notif');
                return;
            }
            bell?.classList.add('has-notif');
            list.innerHTML = allNotifications.slice(0, 6).map(n => `
                <div class="notif-item">
                    <div class="notif-item-title">${n.title}</div>
                    <div class="notif-item-msg">${n.message.substring(0,90)}${n.message.length > 90 ? '…' : ''}</div>
                    <div class="notif-item-meta"><i class="fa-regular fa-clock"></i> ${formatNotifDate(n.created_at)} &bull; To: ${n.recipient_role}</div>
                </div>`).join('');
        })
        .catch(() => {});
}

function loadNotifications() {
    const feed = document.getElementById('notif-feed');
    if (!feed) return;
    feed.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray-500);"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>';
    fetch('api/api_notifications.php?action=get_notifications')
        .then(r => r.json())
        .then(data => {
            allNotifications = data.notifications || [];
            // Update bell badge with current unread count
            const unreadCount = allNotifications.filter(n => n.is_read == 0).length;
            const badgeEl = document.getElementById('badge-notifs');
            if (badgeEl) {
                if (unreadCount > 0) {
                    badgeEl.textContent = unreadCount;
                    badgeEl.style.display = 'inline-block';
                    document.getElementById('notif-bell')?.classList.add('has-notif');
                } else {
                    badgeEl.style.display = 'none';
                    document.getElementById('notif-bell')?.classList.remove('has-notif');
                }
            }
            if (!allNotifications.length) {
                feed.innerHTML = `
                    <div style="text-align:center;padding:50px 30px;color:var(--gray-500);">
                        <i class="fa-regular fa-bell-slash" style="font-size:2.5rem;display:block;margin-bottom:14px;color:var(--gray-200);"></i>
                        <strong>No notifications yet</strong><br>
                        <span style="font-size:0.85rem;">Send one using the form above.</span>
                    </div>`;
                return;
            }
            feed.innerHTML = allNotifications.map(n => `
                <div style="display:flex;gap:16px;align-items:flex-start;padding:18px 0;border-bottom:1px solid var(--cream);">
                    <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,rgba(229,169,59,0.15),rgba(229,169,59,0.3));display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fa-solid fa-bell" style="color:#B48D1B;"></i>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:800;color:var(--dark);font-size:0.93rem;">${n.title} ${n.is_read == 1 ? '<span style="font-weight:normal;font-size:0.75rem;color:var(--gray-500);margin-left:8px;background:#E9ECEF;padding:2px 8px;border-radius:10px;">Read</span>' : '<span style="font-weight:bold;font-size:0.75rem;color:#B48D1B;margin-left:8px;background:rgba(229,169,59,0.15);padding:2px 8px;border-radius:10px;">New</span>'}</div>
                        <div style="color:var(--gray-600);font-size:0.88rem;line-height:1.6;margin-top:4px;">${n.message}</div>
                        <div style="font-size:0.75rem;color:var(--gray-500);margin-top:8px;display:flex;gap:16px;">
                            <span><i class="fa-regular fa-clock"></i> ${formatNotifDate(n.created_at)}</span>
                            <span><i class="fa-solid fa-user"></i> From: ${n.sender_name}</span>
                            <span><i class="fa-solid fa-users"></i> To: <strong>${n.recipient_role === 'all' ? 'Everyone' : n.recipient_role}</strong></span>
                        </div>
                    </div>
                </div>`).join('');
        })
        .catch(() => { feed.innerHTML = '<div class="empty-row">Could not load notifications.</div>'; });
}

function sendNotification(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Dispatching...';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'send_notification');
    fd.append('recipient_role', document.getElementById('notif-recipient').value);
    fd.append('title',          document.getElementById('notif-title').value);
    fd.append('message',        document.getElementById('notif-msg').value);

    // Collect selected users if not 'all'
    if (document.getElementById('notif-recipient').value !== 'all') {
        const checkboxes = document.querySelectorAll('.notif-user-cb:checked');
        let selectedIds = [];
        checkboxes.forEach(cb => selectedIds.push(cb.value));
        fd.append('recipient_user_ids', JSON.stringify(selectedIds));
    }

    fetch('api/api_notifications.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') {
                e.target.reset();
                document.getElementById('notif-users-container-wrapper').style.display = 'none';
                document.getElementById('notif-users-list').innerHTML = '';
                loadNotifications();
            }
        })
        .catch(() => showAlert('error', 'Network error.'))
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

function handleNotifRoleChange() {
    const role = document.getElementById('notif-recipient').value;
    const wrapper = document.getElementById('notif-users-container-wrapper');
    const container = document.getElementById('notif-users-container');
    const list = document.getElementById('notif-users-list');
    
    if (role === 'all') {
        wrapper.style.display = 'none';
        container.style.display = 'none';
        list.innerHTML = '';
        return;
    }
    
    wrapper.style.display = 'block';
    container.style.display = 'block';
    list.innerHTML = '<div style="color:var(--gray-500); padding: 10px; text-align:center;"><i class="fa-solid fa-spinner fa-spin"></i> Loading users...</div>';
    document.getElementById('notif-select-all').checked = false;
    
    fetch('api/api_notifications.php?action=get_users_by_role&role=' + role)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.users.length > 0) {
                let html = '';
                data.users.forEach(u => {
                    html += `
                        <label style="display:flex; align-items:center; gap:8px; padding:6px 0; cursor:pointer;">
                            <input type="checkbox" class="notif-user-cb" value="${u.id}" onchange="checkNotifSelectAllState()"> ${u.name} <span style="color:var(--gray-400); font-size:0.8rem;">(${u.email})</span>
                        </label>
                    `;
                });
                list.innerHTML = html;
            } else {
                list.innerHTML = '<div style="color:var(--danger); padding: 5px;">No users found for this role.</div>';
            }
        })
        .catch(err => {
            list.innerHTML = '<div style="color:var(--danger); padding: 5px;">Failed to load users.</div>';
        });
}

function toggleAllNotifUsers(selectAllCb) {
    const checkboxes = document.querySelectorAll('.notif-user-cb');
    checkboxes.forEach(cb => cb.checked = selectAllCb.checked);
}

function checkNotifSelectAllState() {
    const total = document.querySelectorAll('.notif-user-cb').length;
    const checked = document.querySelectorAll('.notif-user-cb:checked').length;
    document.getElementById('notif-select-all').checked = (total > 0 && total === checked);
}

function formatNotifDate(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    if (isNaN(d.getTime())) {
        return dt;
    }
    // Return format: "20 Jul 2026, 02:20 PM" (converted to local browser timezone)
    const dateStr = d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    const timeStr = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    return `${dateStr}, ${timeStr}`;
}

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
    tbody.innerHTML = '<tr><td colspan="6" class="empty-row">Loading pending registrations…</td></tr>';
    
    fetch('api/api_approve_teacher.php?action=get_pending_teachers')
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                if (!d.pending || d.pending.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="empty-row">No pending teacher registration requests</td></tr>';
                    return;
                }
                
                let html = '';
                d.pending.forEach(p => {
                    html += `
                    <tr>
                        <td><strong>${escHtml(p.name)}</strong></td>
                        <td>${escHtml(p.email)}</td>
                        <td>${escHtml(p.phone)}</td>
                        <td><span style="font-size:0.85rem;color:var(--primary);font-weight:600;">${escHtml(p.subject_names || 'None')}</span></td>
                        <td>${escHtml(p.created_at)}</td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-primary btn-sm" onclick="approveTeacher(${p.id}, this)"><i class="fa-solid fa-check"></i> Approve</button>
                                <button class="btn btn-sm btn-outline" style="border-color:var(--danger);color:var(--danger);" onclick="declineTeacher(${p.id}, this)"><i class="fa-solid fa-xmark"></i> Decline</button>
                            </div>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = `<tr><td colspan="6" class="empty-row" style="color:red;">Error: ${escHtml(d.message)}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-row" style="color:red;">Network connection error loading requests</td></tr>';
        });
}

function approveTeacher(id, btn) {
    if(btn) setButtonLoading(btn, true, 'Approving...');
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
        }).finally(() => { if(btn) setButtonLoading(btn, false); });
}

function declineTeacher(id, btn) {
    if (!confirm('Are you sure you want to decline this registration request?')) return;
    if (btn) setButtonLoading(btn, true, 'Declining...');
    
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


function deleteUser(id, role) {
    const u = allUsers.find(x => x.id == id && x.role == role);
    const name = u ? u.name : 'this user';
    if (!confirm(`Are you absolutely sure you want to permanently delete user "${name}" (${role.toUpperCase()}) from the system? This action is irreversible.`)) {
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
    
    let printContent = `
    <html>
    <head>
        <title>S.H.T.A User Roster - ${roleTitle}</title>
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
        <p class="subtitle">Active User Registry Roster — Category: <strong>${roleTitle}</strong> (${filtered.length} active records)</p>
        
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
    `;
    
    filtered.forEach((u, i) => {
        let nameDetail = u.name || u.username || '–';
        if (u.role === 'student' && u.grade_level) {
            nameDetail += ` (${u.grade_level})`;
        }
        if (u.role === 'teacher' && u.subjects) {
            nameDetail += ` - Subjects: ${u.subjects}`;
        }
        printContent += `
            <tr>
                <td>${i + 1}</td>
                <td><strong>${nameDetail}</strong></td>
                <td>${u.email}</td>
                <td>${u.phone || '–'}</td>
                <td><span class="badge">${u.role.toUpperCase()}</span></td>
            </tr>
        `;
    });
    
    printContent += `
            </tbody>
        </table>
        
        <script>
            window.onload = function() {
                window.print();
                setTimeout(function() { window.close(); }, 500);
            }
        <\/script>
    </body>
    </html>
    `;
    
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    printWindow.document.write(printContent);
    printWindow.document.close();
}


// ————————————————————————————————————————————————
// CURRICULUMS MANAGEMENT
// ————————————————————————————————————————————————
function loadCurriculums() {
    const tbody = document.getElementById('curriculums-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="5" class="empty-row">Loading curriculums…</td></tr>';

    const levelLabels = {
        custom: 'Custom / Free Text',
        grades_1_12: 'Grades 1-12 (CBC / American)',
        years_1_13: 'Years 1-13 (Cambridge / Pearson)',
        classes_1_8: 'Classes 1-8 (8-4-4)'
    };

    fetch('api/api_curriculums.php?action=get_all')
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.curriculums) {
                tbody.innerHTML = '';
                if (!data.curriculums.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="empty-row">No curriculums found.</td></tr>';
                    return;
                }
                data.curriculums.forEach((c, idx) => {
                    const statusBadge = c.is_approved == 1 
                        ? '<span class="badge" style="background:#D1FAE5;color:#065F46;">Approved</span>' 
                        : '<span class="badge" style="background:#FEE2E2;color:#991B1B;">Pending Approval</span>';
                    
                    const levelLabel = levelLabels[c.level_type] || 'Custom / Free Text';

                    let actions = `
                        <button class="btn btn-outline btn-sm" onclick="openGradingScaleModal(${c.id}, '${escHtml(c.name).replace(/'/g, "\\'")}')" title="Manage Grading Scale"><i class="fa-solid fa-graduation-cap"></i> Grading Scale</button>
                        <button class="btn btn-outline btn-sm" onclick="editCurriculum(${c.id}, '${escHtml(c.name).replace(/'/g, "\\'")}', '${c.level_type}')"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="deleteCurriculum(${c.id})"><i class="fa-solid fa-trash-can"></i></button>
                    `;
                    
                    if (c.is_approved == 0) {
                        actions = `
                            <button class="btn btn-accent btn-sm" onclick="approveCurriculum(${c.id}, this)"><i class="fa-solid fa-check"></i> Approve</button>
                            ` + actions;
                    }

                    tbody.innerHTML += `
                        <tr>
                            <td>${idx + 1}</td>
                            <td><strong>${escHtml(c.name)}</strong></td>
                            <td><span class="badge" style="background:#EDF2F7;color:#4A5568;">${levelLabel}</span></td>
                            <td>${statusBadge}</td>
                            <td class="btn-group">${actions}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="empty-row" style="color:var(--danger);">${data.message || 'Error loading curriculums.'}</td></tr>`;
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="5" class="empty-row" style="color:var(--danger);">Connection error.</td></tr>';
        });
}

function saveCurriculum(e) {
    e.preventDefault();
    const id = document.getElementById('curric-id').value;
    const name = document.getElementById('curric-name').value.trim();
    const level_type = document.getElementById('curric-level-type').value;
    if (!name) return;

    const fd = new FormData();
    fd.append('name', name);
    fd.append('level_type', level_type);
    
    let url = 'api/api_curriculums.php?action=add';
    if (id) {
        fd.append('id', id);
        url = 'api/api_curriculums.php?action=edit';
    }

    fetch(url, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('success', data.message);
                resetCurriculumForm();
                loadCurriculums();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Failed to save curriculum.');
        });
}

function editCurriculum(id, name, level_type) {
    document.getElementById('curric-id').value = id;
    document.getElementById('curric-name').value = name;
    document.getElementById('curric-level-type').value = level_type || 'custom';
    document.getElementById('curric-form-title').textContent = 'Edit Curriculum';
    document.getElementById('curric-cancel-btn').style.display = 'inline-block';
}

function resetCurriculumForm() {
    document.getElementById('curric-id').value = '';
    document.getElementById('curric-name').value = '';
    document.getElementById('curric-level-type').value = 'custom';
    document.getElementById('curric-form-title').textContent = 'Add New Curriculum';
    document.getElementById('curric-cancel-btn').style.display = 'none';
}

function approveCurriculum(id, btn) {
    if (!confirm('Approve this curriculum and add it to the active registry?')) return;
    if (btn) setButtonLoading(btn, true, 'Approving...');
    const fd = new FormData();
    fd.append('id', id);
    fetch('api/api_curriculums.php?action=approve', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('success', data.message);
                loadCurriculums();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Failed to approve curriculum.');
        }).finally(() => { if (btn) setButtonLoading(btn, false); });
}

function quickApproveCurriculum(curriculumId, leadId) {
    const fd = new FormData();
    fd.append('id', curriculumId);
    fetch('api/api_curriculums.php?action=approve', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('success', 'Curriculum approved successfully.');
                loadSystemData(); // Refresh the leads state in the drawer
                setTimeout(() => {
                    openDrawer(leadId); // Reopen the drawer with the updated lead
                }, 100);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Failed to approve curriculum.');
        }).finally(() => { if (btn) setButtonLoading(btn, false); });
}

function deleteCurriculum(id) {
    if (!confirm('Are you sure you want to delete this curriculum? Warning: Any students linked to it will show no curriculum.')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('api/api_curriculums.php?action=delete', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('success', data.message);
                loadCurriculums();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Failed to delete curriculum.');
        });
}

// Curriculum Grading Scale Modal Functions
let currentGradingScaleCurriculumId = null;

function openGradingScaleModal(curriculumId, name) {
    currentGradingScaleCurriculumId = curriculumId;
    document.getElementById('scale-curriculum-id').value = curriculumId;
    document.getElementById('grading-scale-subtitle').textContent = `Configure the grading boundaries, level names, and comments for "${name}".`;
    
    const tbody = document.getElementById('modal-grading-scale-tbody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--gray-500);"><i class="fa-solid fa-spinner fa-spin"></i> Loading grading scale...</td></tr>';
    
    openModal('curricGradingScaleModal');
    
    fetch(`api/api_curriculums.php?action=get_grading_scale&curriculum_id=${curriculumId}`)
        .then(r => r.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.status === 'success' && data.scales && data.scales.length > 0) {
                data.scales.forEach(s => {
                    addGradingScaleRow(s.letter_grade, s.min_mark, s.max_mark, s.remarks_template, s.grade_level);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" id="no-scales-placeholder" style="text-align:center;padding:25px;color:var(--gray-400);">No grading scale set. Click "Add Grade Row" to configure custom grades.</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--danger);">Failed to load grading scale.</td></tr>';
        });
}

function addGradingScaleRow(letterGrade = '', minMark = '', maxMark = '', remark = '', gradeLevel = 'All') {
    const tbody = document.getElementById('modal-grading-scale-tbody');
    const placeholder = document.getElementById('no-scales-placeholder');
    if (placeholder) {
        placeholder.remove();
    }
    
    // Create new row
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--gray-200)';
    
    tr.innerHTML = `
        <td style="padding:8px;"><input type="text" class="form-control scale-letter-grade" value="${escHtml(letterGrade)}" placeholder="e.g. A, EE1" style="padding:6px;font-size:0.88rem;" required></td>
        <td style="padding:8px;"><input type="number" class="form-control scale-min-mark" value="${minMark}" placeholder="0" min="0" max="100" step="0.01" style="padding:6px;font-size:0.88rem;" required></td>
        <td style="padding:8px;"><input type="number" class="form-control scale-max-mark" value="${maxMark}" placeholder="100" min="0" max="100" step="0.01" style="padding:6px;font-size:0.88rem;" required></td>
        <td style="padding:8px;"><input type="text" class="form-control scale-remarks" value="${escHtml(remark)}" placeholder="Optional comment" style="padding:6px;font-size:0.88rem;"></td>
        <td style="padding:8px; text-align:center;">
            <input type="hidden" class="scale-grade-level" value="${escHtml(gradeLevel)}">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); checkScalesEmptyPlaceholder();" style="padding:6px 10px;"><i class="fa-solid fa-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
}

function checkScalesEmptyPlaceholder() {
    const tbody = document.getElementById('modal-grading-scale-tbody');
    if (tbody.children.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" id="no-scales-placeholder" style="text-align:center;padding:25px;color:var(--gray-400);">No grading scale set. Click "Add Grade Row" to configure custom grades.</td></tr>';
    }
}

function saveCurriculumGradingScale(e) {
    e.preventDefault();
    const curriculumId = document.getElementById('scale-curriculum-id').value;
    if (!curriculumId) return;
    
    const rows = document.querySelectorAll('#modal-grading-scale-tbody tr');
    const scales = [];
    let isValid = true;
    
    rows.forEach(row => {
        if (row.id === 'no-scales-placeholder') return;
        const letter = row.querySelector('.scale-letter-grade').value.trim();
        const min = parseFloat(row.querySelector('.scale-min-mark').value);
        const max = parseFloat(row.querySelector('.scale-max-mark').value);
        const remark = row.querySelector('.scale-remarks').value.trim();
        const gradeLevel = row.querySelector('.scale-grade-level').value.trim() || 'All';
        
        if (!letter) {
            showAlert('error', 'Grade/Level name is required for all rows.');
            isValid = false;
            return;
        }
        if (isNaN(min) || isNaN(max)) {
            showAlert('error', 'Min and Max marks must be valid numbers.');
            isValid = false;
            return;
        }
        if (min < 0 || max > 100 || min > max) {
            showAlert('error', `Invalid mark ranges for "${letter}". Min must be between 0 and 100, and less than or equal to Max.`);
            isValid = false;
            return;
        }
        
        scales.push({
            letter_grade: letter,
            min_mark: min,
            max_mark: max,
            remarks_template: remark,
            grade_level: gradeLevel
        });
    });
    
    if (!isValid) return;
    
    const fd = new FormData();
    fd.append('curriculum_id', curriculumId);
    fd.append('scales', JSON.stringify(scales));
    
    fetch('api/api_curriculums.php?action=save_grading_scale', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('success', data.message);
            closeModal('curricGradingScaleModal');
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('error', 'Failed to save grading scale due to connection error.');
    });
}

function openEditStudentSubjectsModal(profileId, nameB64) {
    const sName = atob(nameB64);
    document.getElementById('edit-subj-profile-id').value = profileId;
    document.getElementById('edit-subj-student-name').textContent = `Select subjects for ${sName}. Saved subjects are used by the academic operations coordinator for scheduling lessons and teachers.`;
    
    const container = document.getElementById('edit-subj-checkboxes-container');
    container.innerHTML = '<span style="color:var(--gray-500);font-size:0.85rem;">Loading subjects...</span>';
    document.getElementById('adminEditStudentSubjectsModal').classList.add('open');

    const defaultSubjs = [
        {id: 'Mathematics', name: 'Mathematics'}, {id: 'English', name: 'English'},
        {id: 'Swahili', name: 'Swahili'}, {id: 'Biology', name: 'Biology'},
        {id: 'Chemistry', name: 'Chemistry'}, {id: 'Physics', name: 'Physics'},
        {id: 'Computer Science', name: 'Computer Science'}, {id: 'History', name: 'History'},
        {id: 'Geography', name: 'Geography'}, {id: 'Business Studies', name: 'Business Studies'}
    ];

    fetch('api/api_parent_students.php?action=list&profile_id=' + profileId)
        .then(r => r.json())
        .then(data => {
            let activeSubjNames = [];
            if (data.status === 'success' && data.students && data.students[0]) {
                const st = data.students[0];
                if (st.subjects && Array.isArray(st.subjects)) {
                    activeSubjNames = st.subjects.map(s => (s.name || s.id || s).toString().toLowerCase());
                }
            }
            const subjsList = (typeof availableSubjects !== 'undefined' && availableSubjects.length > 0) ? availableSubjects : defaultSubjs;
            
            container.innerHTML = subjsList.map(s => {
                const val = s.name || s.id;
                const isChecked = activeSubjNames.includes(val.toLowerCase()) ? 'checked' : '';
                return `<label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-size:0.85rem; font-weight:600; color:var(--dark); background:${isChecked ? '#FFF7F2' : 'white'}; border:1.5px solid ${isChecked ? 'var(--primary)' : 'var(--gray-200)'}; border-radius:8px; padding:8px 14px; transition:all 0.2s; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <input type="checkbox" name="edit_student_subjects[]" value="${escHtml(val)}" ${isChecked} style="accent-color:var(--primary); width:16px; height:16px; cursor:pointer;" onchange="this.closest('label').style.borderColor = this.checked ? 'var(--primary)' : 'var(--gray-200)'; this.closest('label').style.background = this.checked ? '#FFF7F2' : 'white';">
                    ${escHtml(val)}
                </label>`;
            }).join('');
        })
        .catch(() => {
            container.innerHTML = '<span style="color:var(--danger);font-size:0.85rem;">Error loading student subjects.</span>';
        });
}

function saveEditStudentSubjects(e) {
    e.preventDefault();
    const profileId = document.getElementById('edit-subj-profile-id').value;
    const checked = document.querySelectorAll('input[name="edit_student_subjects[]"]:checked');
    const subjects = Array.from(checked).map(cb => cb.value);

    const fd = new FormData();
    fd.append('csrf_token', getCsrfToken());
    fd.append('action', 'update_student_subjects');
    fd.append('profile_id', profileId);
    subjects.forEach(s => fd.append('subjects[]', s));

    fetch('api/api_parent_students.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') {
                closeModal('adminEditStudentSubjectsModal');
                loadSystemData();
            }
        })
        .catch(() => showAlert('error', 'Network connection error saving subjects.'));
}

