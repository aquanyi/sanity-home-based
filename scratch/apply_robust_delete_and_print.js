const fs = require('fs');

// ── PART 1: Patch admin_dashboard.php ──
let adminHtml = fs.readFileSync('admin_dashboard.php', 'utf8');

// 1. Update the row rendering to call deleteUser(id, role) without base64 name encoding
const oldRowHtml = /\${\(u\.id === currentLoggedInUserId && u\.role === currentLoggedInUserRole\) \? '' : `<button class="btn btn-outline btn-sm" onclick="deleteUser\(\${u\.id}, '\${u\.role}', \\\`\${btoa\(u\.name \|\| ''\)}\\\`\)" style="color:var\(--danger\);border-color:var\(--danger\);"><i class="fa-solid fa-trash"><\/i> Delete<\/button>`}/;
const newRowHtml = `\${(u.id === currentLoggedInUserId && u.role === currentLoggedInUserRole) ? '' : \`<button class="btn btn-outline btn-sm" onclick="deleteUser(\${u.id}, '\${u.role}')" style="color:var(--danger);border-color:var(--danger);"><i class="fa-solid fa-trash"></i> Delete</button>\`}`;

adminHtml = adminHtml.replace(oldRowHtml, newRowHtml);

// 2. Append Javascript functions right before closing </script>
const functionsToAppend = `
function deleteUser(id, role) {
    const u = allUsers.find(x => x.id == id && x.role == role);
    const name = u ? u.name : 'this user';
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
        <\/script>
    </body>
    </html>
    \`;
    
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    printWindow.document.write(printContent);
    printWindow.document.close();
}

</script>`;

adminHtml = adminHtml.replace('</script>', functionsToAppend);
fs.writeFileSync('admin_dashboard.php', adminHtml, 'utf8');
console.log('✅ Appended functions successfully to admin_dashboard.php');
