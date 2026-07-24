import os
import re

files_to_modify = [
    'index.html',
    'login.html',
    'portal.html',
    'admin_dashboard.php',
    'parent_portal.php',
    'teacher_portal.php',
    'accounts_dashboard.php',
    'forgot_password.php',
    'first_login_setup.php'
]

directory = r"c:/Users/HP EliteBook 840 G8/Desktop/sanity home based"
favicon_tag = '\n    <link rel="icon" type="image/png" href="logo.png">'

for filename in files_to_modify:
    path = os.path.join(directory, filename)
    if os.path.exists(path):
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
        
        # Check if favicon already exists
        if 'rel="icon"' in content or 'rel="shortcut icon"' in content:
            print(f"Favicon already exists in {filename}")
            continue
            
        # Insert favicon tag inside <head>
        new_content = re.sub(r'(<head[^>]*>)', r'\1' + favicon_tag, content, count=1, flags=re.IGNORECASE)
        
        with open(path, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Added favicon to {filename}")
    else:
        print(f"File not found: {filename}")
