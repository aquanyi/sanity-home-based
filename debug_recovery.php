<?php
/**
 * debug_recovery.php  —  Password Recovery Diagnostic
 * DELETE THIS FILE after diagnosis is complete.
 */
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

$tables = ['admins','teachers','parents','students','timetablers','accounts_officers'];

echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body{font-family:monospace;background:#111;color:#eee;padding:20px;}
  h2{color:#E5A93B;} h3{color:#88d;}
  .ok{color:#4f4;} .fail{color:#f44;} .warn{color:#fa4;}
  table{border-collapse:collapse;width:100%;margin-bottom:20px;}
  th,td{border:1px solid #444;padding:6px 10px;text-align:left;}
  th{background:#222;color:#aaa;}
  tr:nth-child(even){background:#1a1a1a;}
  .box{background:#1a1a1a;border:1px solid #333;padding:12px;margin-bottom:16px;border-radius:6px;}
</style></head><body>';

echo '<h2>🔍 S.H.T.A — Password Recovery Diagnostic</h2>';
echo '<p style="color:#aaa">Run at: ' . date('Y-m-d H:i:s') . '</p>';

// ── 1. Check each table structure ─────────────────────────────────────────────
echo '<h3>1. Table Column Check</h3>';
$requiredCols = ['id','email','staff_id','password','security_question','security_answer','name'];

foreach ($tables as $tbl) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `$tbl`")->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff($requiredCols, $cols);
        $extra   = ($tbl === 'students') ? ['admission_no'] : [];
        $missingExtra = array_diff($extra, $cols);

        echo '<div class="box">';
        echo '<b>' . htmlspecialchars($tbl) . '</b> — columns: <span style="color:#aaa">' . implode(', ', $cols) . '</span><br>';
        if (empty($missing) && empty($missingExtra)) {
            echo '<span class="ok">✔ All required columns present</span>';
        } else {
            if (!empty($missing))      echo '<span class="fail">✘ Missing: ' . implode(', ', $missing) . '</span><br>';
            if (!empty($missingExtra)) echo '<span class="fail">✘ Missing: ' . implode(', ', $missingExtra) . ' (needed for student search)</span>';
        }
        echo '</div>';
    } catch (Exception $e) {
        echo '<div class="box"><b>' . htmlspecialchars($tbl) . '</b> — <span class="fail">TABLE NOT FOUND: ' . $e->getMessage() . '</span></div>';
    }
}

// ── 2. Count users per table ───────────────────────────────────────────────────
echo '<h3>2. Users Per Table</h3>';
echo '<table><tr><th>Table</th><th>Total Users</th><th>Have security_question</th><th>Have security_answer</th><th>Both Set</th></tr>';
foreach ($tables as $tbl) {
    try {
        $total = $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        $hasSQ = $pdo->query("SELECT COUNT(*) FROM `$tbl` WHERE security_question IS NOT NULL AND security_question != ''")->fetchColumn();
        $hasSA = $pdo->query("SELECT COUNT(*) FROM `$tbl` WHERE security_answer IS NOT NULL AND security_answer != ''")->fetchColumn();
        $both  = $pdo->query("SELECT COUNT(*) FROM `$tbl` WHERE security_question IS NOT NULL AND security_question != '' AND security_answer IS NOT NULL AND security_answer != ''")->fetchColumn();
        $bothClass = ($both > 0) ? 'ok' : 'warn';
        echo "<tr><td>$tbl</td><td>$total</td><td>$hasSQ</td><td>$hasSA</td><td class='$bothClass'>$both</td></tr>";
    } catch (Exception $e) {
        echo "<tr><td>$tbl</td><td colspan='4' class='fail'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
}
echo '</table>';

// ── 3. Show sample users (no passwords) ───────────────────────────────────────
echo '<h3>3. Sample User Records (passwords hidden)</h3>';
foreach ($tables as $tbl) {
    try {
        $colsRes = $pdo->query("SHOW COLUMNS FROM `$tbl`")->fetchAll(PDO::FETCH_COLUMN);
        $rows = $pdo->query("SELECT * FROM `$tbl` LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            echo '<div class="box"><b>' . $tbl . '</b>: <span class="warn">No records</span></div>';
            continue;
        }
        echo '<div class="box"><b>' . $tbl . '</b><table><tr>';
        foreach (array_keys($rows[0]) as $col) {
            echo '<th>' . htmlspecialchars($col) . '</th>';
        }
        echo '</tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $col => $val) {
                if (in_array($col, ['password','security_answer'])) {
                    $display = !empty($val) ? '<span class="ok">[HASHED ✔]</span>' : '<span class="fail">[EMPTY]</span>';
                } else {
                    $display = htmlspecialchars((string)$val);
                }
                echo '<td>' . $display . '</td>';
            }
            echo '</tr>';
        }
        echo '</table></div>';
    } catch (Exception $e) {
        echo '<div class="box"><b>' . $tbl . '</b>: <span class="fail">Error: ' . $e->getMessage() . '</span></div>';
    }
}

// ── 4. Live Lookup Test ────────────────────────────────────────────────────────
echo '<h3>4. Live Account Lookup Test</h3>';
echo '<form method="POST" style="background:#1a1a1a;padding:16px;border-radius:8px;margin-bottom:16px;">
  <label style="color:#aaa">Enter Email / Staff ID / Admission No to test:</label><br><br>
  <input name="test_id" style="width:300px;padding:8px;background:#222;color:#eee;border:1px solid #555;border-radius:4px;" 
         value="' . htmlspecialchars($_POST['test_id'] ?? '') . '" placeholder="e.g. teacher@school.com or TCH-2026-001">
  <select name="test_tab" style="padding:8px;background:#222;color:#eee;border:1px solid #555;border-radius:4px;margin-left:8px;">
    <option value="admin"' . (($_POST['test_tab']??'')==='admin'?' selected':'') . '>Admin</option>
    <option value="teachers"' . (($_POST['test_tab']??'')==='teachers'?' selected':'') . '>Teacher</option>
    <option value="parent"' . (($_POST['test_tab']??'')==='parent'?' selected':'') . '>Parent</option>
    <option value="student"' . (($_POST['test_tab']??'')==='student'?' selected':'') . '>Student</option>
    <option value="timetable"' . (($_POST['test_tab']??'')==='timetable'?' selected':'') . '>Academic Operations Coordinator</option>
    <option value="accounts"' . (($_POST['test_tab']??'')==='accounts'?' selected':'') . '>Accounts</option>
  </select>
  <button type="submit" style="padding:8px 16px;background:#4A0E17;color:#fff;border:none;border-radius:4px;margin-left:8px;cursor:pointer;">Test Lookup</button>
</form>';

if (!empty($_POST['test_id'])) {
    $id  = trim($_POST['test_id']);
    $tab = $_POST['test_tab'] ?? 'parent';

    $tabToTable = [
        'admin'     => 'admins',
        'timetable' => 'timetablers',
        'teachers'  => 'teachers',
        'parent'    => 'parents',
        'student'   => 'students',
        'accounts'  => 'accounts_officers',
    ];
    $table = $tabToTable[$tab] ?? 'parents';

    echo '<div class="box"><b>Testing:</b> identifier=<span style="color:#fa4">' . htmlspecialchars($id) . '</span> in table=<span style="color:#fa4">' . $table . '</span><br><br>';

    try {
        if ($table === 'students') {
            $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE email = ? OR staff_id = ? OR admission_no = ? LIMIT 1");
            $stmt->execute([$id, $id, $id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE email = ? OR staff_id = ? LIMIT 1");
            $stmt->execute([$id, $id]);
        }
        $found = $stmt->fetch();

        if ($found) {
            echo '<span class="ok">✔ USER FOUND:</span> ' . htmlspecialchars($found['name']) . ' (ID: ' . $found['id'] . ')<br>';
            echo 'Email: ' . htmlspecialchars($found['email']) . '<br>';
            echo 'security_question: ';
            if (!empty($found['security_question'])) {
                echo '<span class="ok">' . htmlspecialchars($found['security_question']) . '</span><br>';
            } else {
                echo '<span class="fail">NOT SET — user cannot do password recovery</span><br>';
            }
            echo 'security_answer: ';
            if (!empty($found['security_answer'])) {
                echo '<span class="ok">[HASH PRESENT] — starts with: ' . htmlspecialchars(substr($found['security_answer'], 0, 7)) . '...</span><br>';
            } else {
                echo '<span class="fail">NOT SET — user cannot do password recovery</span><br>';
            }

            // ── 5. Answer verification test ───────────────────────────────────
            if (!empty($found['security_answer'])) {
                echo '<br><b>Answer Verification Test:</b><br>';
                echo '<form method="POST" style="margin-top:8px;">
                  <input type="hidden" name="test_id" value="' . htmlspecialchars($id) . '">
                  <input type="hidden" name="test_tab" value="' . htmlspecialchars($tab) . '">
                  <input type="hidden" name="test_user_id" value="' . $found['id'] . '">
                  <input type="hidden" name="test_table" value="' . htmlspecialchars($table) . '">
                  <input name="test_answer" placeholder="Type the security answer to verify" 
                         style="width:280px;padding:8px;background:#222;color:#eee;border:1px solid #555;border-radius:4px;"
                         value="' . htmlspecialchars($_POST['test_answer'] ?? '') . '">
                  <button type="submit" style="padding:8px 16px;background:#2a5;color:#fff;border:none;border-radius:4px;margin-left:8px;cursor:pointer;">Verify Answer</button>
                </form>';

                if (!empty($_POST['test_answer']) && isset($_POST['test_user_id'])) {
                    $ans = trim($_POST['test_answer']);
                    $result = password_verify(strtolower($ans), $found['security_answer']);
                    if ($result) {
                        echo '<br><span class="ok">✔ ANSWER CORRECT — password_verify passed!</span>';
                    } else {
                        echo '<br><span class="fail">✘ ANSWER WRONG — password_verify failed.</span>';
                        echo '<br><span class="warn">Tried (lowercased): "' . htmlspecialchars(strtolower($ans)) . '"</span>';
                        echo '<br><span class="warn">Hash in DB starts: ' . htmlspecialchars(substr($found['security_answer'], 0, 30)) . '...</span>';
                    }
                }
            }
        } else {
            echo '<span class="fail">✘ NO USER FOUND</span> with that identifier in <b>' . htmlspecialchars($table) . '</b><br>';
            echo '<br>Checking ALL tables for this identifier...<br>';
            foreach ($tables as $t) {
                try {
                    $cols2 = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_COLUMN);
                    if ($t === 'students' && in_array('admission_no', $cols2)) {
                        $s2 = $pdo->prepare("SELECT id,name,email FROM `$t` WHERE email=? OR staff_id=? OR admission_no=? LIMIT 1");
                        $s2->execute([$id,$id,$id]);
                    } else {
                        $s2 = $pdo->prepare("SELECT id,name,email FROM `$t` WHERE email=? OR staff_id=? LIMIT 1");
                        $s2->execute([$id,$id]);
                    }
                    $r2 = $s2->fetch();
                    if ($r2) {
                        echo '<span class="ok">  ✔ Found in <b>' . $t . '</b>: ' . htmlspecialchars($r2['name']) . ' (' . htmlspecialchars($r2['email']) . ')</span><br>';
                    }
                } catch (Exception $ex) {}
            }
        }
    } catch (Exception $e) {
        echo '<span class="fail">Query error: ' . htmlspecialchars($e->getMessage()) . '</span>';
    }
    echo '</div>';
}

echo '<hr style="border-color:#333;margin:20px 0;">';
echo '<p style="color:#f44"><b>⚠ DELETE debug_recovery.php from your server after diagnosis!</b></p>';
echo '</body></html>';
