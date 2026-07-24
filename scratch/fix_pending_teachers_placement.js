const fs = require('fs');

let admin = fs.readFileSync('admin_dashboard.php', 'utf8');

// 1. Identify the block to move
const pendingBlock = `    <!-- Pending Teacher Registrations -->
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

// 2. Remove the pending block from its current location (outside the section)
const originalSearch = pendingBlock + `\n\n    <!-- 📅📅 TIMETABLE`;
const targetReplacement = `\n\n    <!-- 📅📅 TIMETABLE`;

if (admin.includes(originalSearch)) {
    admin = admin.replace(originalSearch, targetReplacement);
} else {
    // Try with CRLF
    const pendingBlockCRLF = pendingBlock.replace(/\n/g, '\r\n');
    const originalSearchCRLF = pendingBlockCRLF + `\r\n\r\n    <!-- 📅📅 TIMETABLE`;
    const targetReplacementCRLF = `\r\n\r\n    <!-- 📅📅 TIMETABLE`;
    admin = admin.replace(originalSearchCRLF, targetReplacementCRLF);
}

// 3. Insert the pending block inside the roles section (right before the section closing div)
const oldSectionEnd = `                <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Provision Account</button></div>
            </form>
        </div>
    </div>
    <?php endif; ?>`;

const newSectionEnd = `                <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Provision Account</button></div>
            </form>
        </div>
        
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
        </div>
    </div>
    <?php endif; ?>`;

if (admin.includes(oldSectionEnd)) {
    admin = admin.replace(oldSectionEnd, newSectionEnd);
} else {
    const oldSectionEndCRLF = oldSectionEnd.replace(/\n/g, '\r\n');
    const newSectionEndCRLF = newSectionEnd.replace(/\n/g, '\r\n');
    admin = admin.replace(oldSectionEndCRLF, newSectionEndCRLF);
}

fs.writeFileSync('admin_dashboard.php', admin, 'utf8');
console.log('✅ Updated admin_dashboard.php layout for Pending Teacher Registrations!');
