const fs = require('fs');

let html = fs.readFileSync('login.html', 'utf8');

// ── 1. Injected Modal CSS Styles ──
const modalCSS = `
        /* Teacher Registration Modal Styles */
        .modal-bg {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .modal-bg.open {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-box {
            background: var(--white);
            border-radius: 18px;
            padding: 30px;
            width: 92%;
            max-width: 580px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            position: relative;
            animation: modalFadeUp 0.3s ease;
        }
        @keyframes modalFadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--cream);
        }
        .modal-header h3 {
            font-size: 1.25rem;
            color: var(--primary);
            font-weight: 800;
            margin: 0;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-600);
            cursor: pointer;
            line-height: 1;
        }
        .modal-close:hover {
            color: var(--primary);
        }
        .subj-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        .subj-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-600);
            cursor: pointer;
        }
        .subj-item input {
            cursor: pointer;
        }
`;

// Insert the CSS before the closing </style>
html = html.replace('    </style>', modalCSS + '\n    </style>');


// ── 2. Add Register Link to the Teacher Login Form ──
const teacherFormTarget = `                        <button type="submit" class="btn-login">
                            <span class="btn-text"><i class="fa-solid fa-book-open"></i> Login to Classroom</span>
                            <span class="btn-spinner"><i class="fa-solid fa-spinner fa-spin"></i> Authenticating…</span>
                        </button>`;

const registerLink = `                        <div style="text-align: center; margin-top: 15px; font-size: 0.88rem; color: var(--gray-600);">
                            Don't have an account? 
                            <a href="javascript:void(0)" onclick="openRegisterModal()" style="color: var(--accent); font-weight: 700; text-decoration: underline;">
                                Register Here
                            </a>
                        </div>`;

if (html.includes(teacherFormTarget)) {
    html = html.replace(teacherFormTarget, teacherFormTarget + '\n' + registerLink);
} else {
    // try with alternative carriage returns
    const teacherFormTargetCRLF = teacherFormTarget.replace(/\n/g, '\r\n');
    html = html.replace(teacherFormTargetCRLF, teacherFormTargetCRLF + '\r\n' + registerLink.replace(/\n/g, '\r\n'));
}


// ── 3. Add Modal HTML markup ──
const modalHTML = `
    <!-- Teacher Registration Modal -->
    <div class="modal-bg" id="register-modal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-chalkboard-teacher" style="margin-right:8px;color:var(--accent);"></i>Teacher Registration</h3>
                <button class="modal-close" onclick="closeRegisterModal()">&times;</button>
            </div>
            <form onsubmit="submitRegister(event)">
                <div id="reg-error" class="login-error" style="display:none; margin-bottom:15px; padding:12px; background:#FEE2E2; color:#991B1B; border-radius:10px; border:1px solid #FCA5A5; font-size:0.85rem; font-weight: 600;"></div>
                <div id="reg-success" class="login-error" style="display:none; margin-bottom:15px; padding:12px; background:#D1FAE5; color:#065F46; border-radius:10px; border:1px solid #A7F3D0; font-size:0.85rem; font-weight: 600;"></div>
                
                <div class="input-group" style="margin-bottom:15px; display:flex; flex-direction:column; gap:6px;">
                    <label style="font-weight:700; font-size:0.85rem; color:var(--primary);">Full Name</label>
                    <input type="text" id="reg-name" required placeholder="e.g. Jane Mwangi" style="width:100%; padding:10px 14px; border:1px solid var(--gray-200); border-radius:8px; font-size:0.9rem; font-family:inherit;">
                </div>
                <div class="input-group" style="margin-bottom:15px; display:flex; flex-direction:column; gap:6px;">
                    <label style="font-weight:700; font-size:0.85rem; color:var(--primary);">Email Address</label>
                    <input type="email" id="reg-email" required placeholder="e.g. jane.mwangi@sanitytuition.com" style="width:100%; padding:10px 14px; border:1px solid var(--gray-200); border-radius:8px; font-size:0.9rem; font-family:inherit;">
                </div>
                <div class="input-group" style="margin-bottom:15px; display:flex; flex-direction:column; gap:6px;">
                    <label style="font-weight:700; font-size:0.85rem; color:var(--primary);">Phone Number</label>
                    <input type="tel" id="reg-phone" required placeholder="e.g. +254 712 345 678" style="width:100%; padding:10px 14px; border:1px solid var(--gray-200); border-radius:8px; font-size:0.9rem; font-family:inherit;">
                </div>
                <div class="input-group" style="margin-bottom:15px; display:flex; flex-direction:column; gap:6px;">
                    <label style="font-weight:700; font-size:0.85rem; color:var(--primary);">Custom Password</label>
                    <input type="password" id="reg-pass" required placeholder="Create secure password" style="width:100%; padding:10px 14px; border:1px solid var(--gray-200); border-radius:8px; font-size:0.9rem; font-family:inherit;">
                </div>
                <div class="input-group" style="margin-bottom:15px; display:flex; flex-direction:column; gap:6px;">
                    <label style="font-weight:700; font-size:0.85rem; color:var(--primary);">Security Question</label>
                    <select id="reg-question" required style="width:100%; padding:10px 14px; border:1px solid var(--gray-200); border-radius:8px; font-size:0.9rem; font-family:inherit; background:#fff; cursor:pointer;">
                        <option value="">Select a security question</option>
                        <option value="What was your first pet's name?">What was your first pet's name?</option>
                        <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                        <option value="What was the name of your first school?">What was the name of your first school?</option>
                        <option value="In what city were you born?">In what city were you born?</option>
                    </select>
                </div>
                <div class="input-group" style="margin-bottom:15px; display:flex; flex-direction:column; gap:6px;">
                    <label style="font-weight:700; font-size:0.85rem; color:var(--primary);">Security Answer</label>
                    <input type="text" id="reg-answer" required placeholder="Your secret answer" style="width:100%; padding:10px 14px; border:1px solid var(--gray-200); border-radius:8px; font-size:0.9rem; font-family:inherit;">
                </div>
                
                <div style="margin-bottom:15px; background: rgba(74,14,23,0.02); padding: 15px; border-radius: 10px; border: 1px dashed rgba(74,14,23,0.12);">
                    <label style="display:block; font-weight:700; margin-bottom:8px; font-size:0.85rem; color:var(--primary);"><i class="fa-solid fa-book" style="margin-right:6px;color:var(--accent);"></i>Subjects You Teach</label>
                    <div class="subj-grid" id="reg-subjects-container">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
                
                <div style="margin-bottom:20px; padding-top:5px;">
                    <label style="display:block; font-weight:700; margin-bottom:6px; font-size:0.85rem; color:var(--primary);">Can't find a subject? Suggest one here:</label>
                    <input type="text" id="reg-custom-subjects" placeholder="e.g. Geography, Music (comma-separated)" style="width:100%; padding:10px 14px; border:1px solid var(--gray-200); border-radius:8px; font-size:0.9rem; font-family:inherit;">
                </div>
                
                <button type="submit" class="btn-login" id="reg-submit-btn" style="width:100%; padding:12px 20px;">
                    <span class="btn-text"><i class="fa-solid fa-user-plus"></i> Submit Registration Request</span>
                </button>
            </form>
        </div>
    </div>
`;

// Insert the HTML before </body>
html = html.replace('</body>', modalHTML + '\n</body>');


// ── 4. Add JS Functions ──
const modalJS = `
function openRegisterModal() {
    document.getElementById('register-modal').classList.add('open');
    const container = document.getElementById('reg-subjects-container');
    container.innerHTML = '<span style="color:var(--gray-400); font-style:italic; font-size:0.82rem;">Loading subjects...</span>';
    
    fetch('api/api_resources.php?action=get_subjects')
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                let html = '';
                d.subjects.forEach(sub => {
                    html += \`
                    <label class="subj-item">
                        <input type="checkbox" name="reg_subjects" value="\${sub.id}">
                        <span>\${sub.name}</span>
                    </label>\`;
                });
                container.innerHTML = html || '<span style="color:var(--gray-400); font-style:italic; font-size:0.82rem;">No subjects configured yet</span>';
            } else {
                container.innerHTML = '<span style="color:red; font-size:0.82rem;">Error loading subjects</span>';
            }
        })
        .catch(() => {
            container.innerHTML = '<span style="color:red; font-size:0.82rem;">Network error loading subjects</span>';
        });
}

function closeRegisterModal() {
    document.getElementById('register-modal').classList.remove('open');
    document.getElementById('reg-error').style.display = 'none';
    document.getElementById('reg-success').style.display = 'none';
    // Clear form
    document.getElementById('reg-name').value = '';
    document.getElementById('reg-email').value = '';
    document.getElementById('reg-phone').value = '';
    document.getElementById('reg-pass').value = '';
    document.getElementById('reg-question').value = '';
    document.getElementById('reg-answer').value = '';
    document.getElementById('reg-custom-subjects').value = '';
}

function submitRegister(e) {
    e.preventDefault();
    const btn = document.getElementById('reg-submit-btn');
    btn.disabled = true;
    
    const name = document.getElementById('reg-name').value;
    const email = document.getElementById('reg-email').value;
    const phone = document.getElementById('reg-phone').value;
    const password = document.getElementById('reg-pass').value;
    const question = document.getElementById('reg-question').value;
    const answer = document.getElementById('reg-answer').value;
    const customSubjects = document.getElementById('reg-custom-subjects').value;
    
    const checkboxes = document.querySelectorAll('input[name="reg_subjects"]:checked');
    const subjectIds = Array.from(checkboxes).map(cb => cb.value).join(',');
    
    const fd = new FormData();
    fd.append('action', 'register_teacher');
    fd.append('name', name);
    fd.append('email', email);
    fd.append('phone', phone);
    fd.append('password', password);
    fd.append('security_question', question);
    fd.append('security_answer', answer);
    fd.append('subject_ids', subjectIds);
    fd.append('custom_subjects', customSubjects);
    
    const errorDiv = document.getElementById('reg-error');
    const successDiv = document.getElementById('reg-success');
    errorDiv.style.display = 'none';
    successDiv.style.display = 'none';
    
    fetch('api/api_teacher_register.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        if (d.status === 'success') {
            successDiv.textContent = d.message;
            successDiv.style.display = 'block';
            setTimeout(() => {
                closeRegisterModal();
            }, 3500);
        } else {
            errorDiv.textContent = d.message;
            errorDiv.style.display = 'block';
        }
    })
    .catch(err => {
        btn.disabled = false;
        errorDiv.textContent = 'A connection error occurred. Please try again.';
        errorDiv.style.display = 'block';
    });
}
`;

// Insert the JS functions inside the script tag (e.g. before the loadStats() call on line 886)
html = html.replace('loadStats();', modalJS + '\n\nloadStats();');

fs.writeFileSync('login.html', html, 'utf8');
console.log('✅ Injected Teacher Registration UI, Modal & JS into login.html!');
