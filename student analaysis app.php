<?php
session_start();
error_reporting(0);

// ===== FILE SETUP =====
$dataFiles = ['users.json','students.json','results.json','attendance.json','notices.json'];
foreach($dataFiles as $f){
    if(!file_exists($f)) file_put_contents($f, json_encode([]));
}
if(!is_dir('uploads')) mkdir('uploads',0755,true);

// ===== LOAD DATA =====
$users      = json_decode(file_get_contents('users.json'),      true) ?: [];
$students   = json_decode(file_get_contents('students.json'),   true) ?: [];
$results    = json_decode(file_get_contents('results.json'),    true) ?: [];
$attendance = json_decode(file_get_contents('attendance.json'), true) ?: [];
$notices    = json_decode(file_get_contents('notices.json'),    true) ?: [];

// ===== DEFAULT ADMIN =====
$adminExists = false;
foreach($users as $u){ if($u['user_id']=='admin') $adminExists=true; }
if(!$adminExists){
    $users[] = ["user_id"=>"admin","password"=>"admin123","role"=>"admin","name"=>"System Admin"];
    file_put_contents('users.json', json_encode($users, JSON_PRETTY_PRINT));
}

$msg  = "";
$user = $_SESSION['user'] ?? null;

// ===== HELPERS =====
function saveStudents($s){ file_put_contents('students.json', json_encode($s, JSON_PRETTY_PRINT)); }
function saveResults($r)  { file_put_contents('results.json',  json_encode($r, JSON_PRETTY_PRINT)); }
function saveAttendance($a){ file_put_contents('attendance.json', json_encode($a, JSON_PRETTY_PRINT)); }
function saveNotices($n)  { file_put_contents('notices.json',  json_encode($n, JSON_PRETTY_PRINT)); }
function saveUsers($u)    { file_put_contents('users.json',    json_encode($u, JSON_PRETTY_PRINT)); }

function getGrade($marks){
    if($marks>=80) return ["A+","4.00","#10b981"];
    if($marks>=70) return ["A","3.75","#34d399"];
    if($marks>=60) return ["A-","3.50","#6ee7b7"];
    if($marks>=50) return ["B","3.00","#fbbf24"];
    if($marks>=40) return ["C","2.00","#f97316"];
    if($marks>=33) return ["D","1.00","#f87171"];
    return ["F","0.00","#ef4444"];
}

function getAttendancePercent($sid, $attendance){
    $total=0; $present=0;
    foreach($attendance as $a){
        if($a['sid']==$sid){ $total++; if($a['status']=='present') $present++; }
    }
    return $total>0 ? round(($present/$total)*100) : 0;
}

// ================= LOGIN =================
if(isset($_POST['login'])){
    $id   = trim($_POST['u_id']);
    $pass = trim($_POST['u_pass']);
    foreach($users as $u){
        if($u['user_id']==$id && $u['password']==$pass && $u['role']=="admin"){
            $_SESSION['user']=$u; header("Location: index.php"); exit;
        }
    }
    foreach($students as $s){
        if($s['email']==$id && $s['sid']==$pass){
            $_SESSION['user']=["user_id"=>$s['sid'],"name"=>$s['name'],"role"=>"student"];
            header("Location: index.php"); exit;
        }
    }
    $msg = "error:Invalid credentials. Please check your ID and password.";
}

// ================= LOGOUT =================
if(isset($_GET['logout'])){ session_destroy(); header("Location: index.php"); exit; }

// ================= ADD STUDENT =================
if(isset($_POST['add_student']) && $user && $user['role']=='admin'){
    $sid = trim($_POST['sid']);
    $duplicate = false;
    foreach($students as $s){ if($s['sid']==$sid){ $duplicate=true; break; } }
    if($duplicate){
        $msg = "error:Student ID already exists!";
    } else {
        $img = '';
        if(!empty($_FILES['img']['name'])){
            $ext = pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION);
            $img = 'stu_'.time().'_'.uniqid().'.'.$ext;
            move_uploaded_file($_FILES['img']['tmp_name'], "uploads/".$img);
        }
        $students[] = [
            "sid"=>$sid, "name"=>$_POST['name'], "email"=>$_POST['email'],
            "class"=>$_POST['class'], "batch"=>$_POST['batch'],
            "mobile"=>$_POST['mobile'], "address"=>$_POST['address'],
            "dob"=>$_POST['dob'], "img"=>$img,
            "join_date"=>date('Y-m-d')
        ];
        saveStudents($students);
        $msg = "success:Student added successfully!";
    }
}

// ================= EDIT STUDENT =================
if(isset($_POST['edit_student']) && $user && $user['role']=='admin'){
    $sid = $_POST['edit_sid'];
    foreach($students as &$s){
        if($s['sid']==$sid){
            $s['name']    = $_POST['name'];
            $s['email']   = $_POST['email'];
            $s['class']   = $_POST['class'];
            $s['batch']   = $_POST['batch'];
            $s['mobile']  = $_POST['mobile'];
            $s['address'] = $_POST['address'];
            $s['dob']     = $_POST['dob'];
            if(!empty($_FILES['img']['name'])){
                $ext = pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION);
                $img = 'stu_'.time().'_'.uniqid().'.'.$ext;
                move_uploaded_file($_FILES['img']['tmp_name'], "uploads/".$img);
                $s['img'] = $img;
            }
            break;
        }
    }
    saveStudents($students);
    $msg = "success:Student updated successfully!";
}

// ================= DELETE STUDENT =================
if(isset($_GET['delete_student']) && $user && $user['role']=='admin'){
    $sid = $_GET['delete_student'];
    $students = array_filter($students, fn($s)=>$s['sid']!=$sid);
    $students = array_values($students);
    saveStudents($students);
    $results    = array_filter($results,    fn($r)=>$r['sid']!=$sid);
    $attendance = array_filter($attendance, fn($a)=>$a['sid']!=$sid);
    saveResults(array_values($results));
    saveAttendance(array_values($attendance));
    header("Location: index.php?tab=students&msg=".urlencode("success:Student deleted.")); exit;
}

// ================= ADD RESULT =================
if(isset($_POST['add_result']) && $user && $user['role']=='admin'){
    $marks = (int)$_POST['marks'];
    [$grade,$gpa,$color] = getGrade($marks);
    $results[] = [
        "id"=>uniqid(), "sid"=>$_POST['rsid'], "subject"=>$_POST['subject'],
        "exam"=>$_POST['exam'], "marks"=>$marks, "total"=>$_POST['total'],
        "grade"=>$grade, "gpa"=>$gpa, "date"=>date('Y-m-d')
    ];
    saveResults($results);
    $msg = "success:Result added!";
}

// ================= DELETE RESULT =================
if(isset($_GET['delete_result']) && $user && $user['role']=='admin'){
    $results = array_filter($results, fn($r)=>$r['id']!=$_GET['delete_result']);
    saveResults(array_values($results));
    header("Location: index.php?tab=results&msg=".urlencode("success:Result deleted.")); exit;
}

// ================= ATTENDANCE =================
if(isset($_POST['mark_attendance']) && $user && $user['role']=='admin'){
    $date = $_POST['att_date'];
    $sid  = $_POST['att_sid'];
    $status = $_POST['att_status'];
    // remove old if exists
    $attendance = array_filter($attendance, fn($a)=>!($a['sid']==$sid && $a['date']==$date));
    $attendance[] = ["sid"=>$sid,"date"=>$date,"status"=>$status,"marked_by"=>$user['name']];
    saveAttendance(array_values($attendance));
    $msg = "success:Attendance marked!";
}

// ================= NOTICE =================
if(isset($_POST['add_notice']) && $user && $user['role']=='admin'){
    $notices[] = ["id"=>uniqid(),"title"=>$_POST['n_title'],"body"=>$_POST['n_body'],"date"=>date('Y-m-d H:i'),"by"=>$user['name']];
    saveNotices($notices);
    $msg = "success:Notice published!";
}
if(isset($_GET['delete_notice']) && $user && $user['role']=='admin'){
    $notices = array_filter($notices, fn($n)=>$n['id']!=$_GET['delete_notice']);
    saveNotices(array_values($notices));
    header("Location: index.php?tab=notices&msg=".urlencode("success:Notice deleted.")); exit;
}

// ===== MSG FROM REDIRECT =====
if(empty($msg) && isset($_GET['msg'])) $msg = $_GET['msg'];

// ===== ACTIVE TAB =====
$tab = $_GET['tab'] ?? 'dashboard';

// ===== STATS =====
$totalStudents   = count($students);
$todayDate       = date('Y-m-d');
$todayPresent    = count(array_filter($attendance, fn($a)=>$a['date']==$todayDate && $a['status']=='present'));
$totalResults    = count($results);
$totalNotices    = count($notices);

// ===== FIND STUDENT FOR STUDENT LOGIN =====
$myStudent = null;
if($user && $user['role']=='student'){
    foreach($students as $s){
        if($s['sid']==$user['user_id']){ $myStudent=$s; break; }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feni University — Student Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0d0f14;
  --surface:#161922;
  --card:#1e2330;
  --border:#2a3042;
  --accent:#6c63ff;
  --accent2:#f59e0b;
  --accent3:#10b981;
  --danger:#ef4444;
  --text:#e8eaf6;
  --muted:#8892b0;
  --sidebar-w:260px;
  --radius:14px;
  --shadow:0 4px 24px rgba(0,0,0,.4);
}
*{margin:0;padding:0;box-sizing:border-box;}
html,body{height:100%;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);font-size:15px;}
a{text-decoration:none;color:inherit;}
img{max-width:100%;}

/* ===== SCROLLBAR ===== */
::-webkit-scrollbar{width:5px;height:5px;}
::-webkit-scrollbar-track{background:var(--surface);}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:10px;}

/* ===== LOGIN ===== */
.login-wrap{
  min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:radial-gradient(ellipse at 30% 50%,#1a1040 0%,#0d0f14 60%);
}
.login-box{
  background:var(--card);border:1px solid var(--border);border-radius:24px;
  padding:48px 40px;width:420px;max-width:94vw;
  box-shadow:0 0 60px rgba(108,99,255,.15);
  animation:slideUp .5s ease;
}
.login-logo{
  text-align:center;margin-bottom:32px;
}
.login-logo h1{font-family:'Playfair Display',serif;font-size:2.2rem;color:var(--text);letter-spacing:-1px;}
.login-logo span{color:var(--accent);}
.login-logo p{color:var(--muted);font-size:.85rem;margin-top:6px;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;}
.form-group input,.form-group select,.form-group textarea{
  width:100%;background:var(--surface);border:1px solid var(--border);
  border-radius:10px;padding:13px 16px;color:var(--text);font-size:.95rem;
  transition:.2s;font-family:'DM Sans',sans-serif;
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{
  outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(108,99,255,.15);
}
.form-group textarea{min-height:90px;resize:vertical;}
.btn{
  display:inline-flex;align-items:center;gap:8px;padding:12px 22px;
  border-radius:10px;border:none;font-family:'DM Sans',sans-serif;
  font-size:.9rem;font-weight:600;cursor:pointer;transition:.2s;
}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{background:#5a52e0;transform:translateY(-1px);box-shadow:0 6px 20px rgba(108,99,255,.35);}
.btn-success{background:var(--accent3);color:#fff;}
.btn-success:hover{background:#059669;}
.btn-danger{background:var(--danger);color:#fff;}
.btn-danger:hover{background:#dc2626;}
.btn-warning{background:var(--accent2);color:#1a1a1a;}
.btn-warning:hover{background:#d97706;}
.btn-sm{padding:7px 14px;font-size:.8rem;}
.btn-full{width:100%;justify-content:center;}
.hint-box{background:rgba(108,99,255,.08);border:1px solid rgba(108,99,255,.2);border-radius:10px;padding:14px;margin-top:20px;font-size:.8rem;color:var(--muted);}
.hint-box strong{color:var(--accent);}

/* ===== LAYOUT ===== */
.layout{display:flex;min-height:100vh;}
.sidebar{
  width:var(--sidebar-w);background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;
  z-index:100;transition:.3s;overflow-y:auto;
}
.sidebar-header{padding:28px 22px 20px;border-bottom:1px solid var(--border);}
.sidebar-header h2{font-family:'Playfair Display',serif;font-size:1.3rem;letter-spacing:-.5px;}
.sidebar-header h2 span{color:var(--accent);}
.sidebar-header p{color:var(--muted);font-size:.75rem;margin-top:4px;}
.user-pill{
  margin:16px 20px;background:var(--card);border-radius:10px;padding:12px;
  display:flex;align-items:center;gap:10px;border:1px solid var(--border);
}
.user-pill .avatar{
  width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#a78bfa);
  display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;
}
.user-pill .uname{font-size:.85rem;font-weight:600;}
.user-pill .urole{font-size:.72rem;color:var(--muted);text-transform:capitalize;}
nav{padding:8px 12px;flex:1;}
nav a{
  display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;
  color:var(--muted);font-size:.88rem;font-weight:500;margin-bottom:3px;transition:.2s;
}
nav a:hover{background:var(--card);color:var(--text);}
nav a.active{background:rgba(108,99,255,.15);color:var(--accent);border:1px solid rgba(108,99,255,.2);}
nav a .ico{font-size:1.1rem;width:20px;text-align:center;}
.sidebar-footer{padding:16px 20px;border-top:1px solid var(--border);}
.sidebar-footer a{display:flex;align-items:center;gap:8px;color:var(--danger);font-size:.85rem;font-weight:500;}
.main{margin-left:var(--sidebar-w);flex:1;padding:32px;min-height:100vh;}

/* ===== TOPBAR ===== */
.topbar{
  display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;
}
.topbar h1{font-family:'Playfair Display',serif;font-size:1.8rem;letter-spacing:-.5px;}
.topbar .sub{color:var(--muted);font-size:.85rem;margin-top:3px;}
.menu-toggle{
  display:none;background:var(--card);border:1px solid var(--border);border-radius:8px;
  padding:9px 12px;cursor:pointer;color:var(--text);font-size:1.1rem;
}

/* ===== ALERT ===== */
.alert{
  border-radius:10px;padding:13px 18px;margin-bottom:22px;font-size:.9rem;
  display:flex;align-items:center;gap:10px;animation:slideDown .3s ease;
}
.alert-success{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#6ee7b7;}
.alert-error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5;}

/* ===== STATS ===== */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;margin-bottom:28px;}
.stat-card{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  padding:22px;position:relative;overflow:hidden;transition:.2s;
}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow);}
.stat-card .sc-icon{font-size:2rem;margin-bottom:10px;}
.stat-card .sc-val{font-size:2rem;font-weight:700;line-height:1;margin-bottom:4px;}
.stat-card .sc-label{color:var(--muted);font-size:.8rem;font-weight:500;text-transform:uppercase;letter-spacing:.06em;}
.stat-card::after{content:'';position:absolute;right:-10px;top:-10px;width:80px;height:80px;border-radius:50%;opacity:.07;}
.stat-card.s1::after{background:var(--accent);}
.stat-card.s2::after{background:var(--accent2);}
.stat-card.s3::after{background:var(--accent3);}
.stat-card.s4::after{background:var(--danger);}

/* ===== CARD ===== */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.card-head{
  padding:20px 24px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
}
.card-head h3{font-size:1rem;font-weight:700;}
.card-body{padding:24px;}

/* ===== TABLE ===== */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);border-bottom:1px solid var(--border);white-space:nowrap;}
td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:.88rem;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:rgba(255,255,255,.02);}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;}
.badge-green{background:rgba(16,185,129,.15);color:#6ee7b7;}
.badge-red{background:rgba(239,68,68,.15);color:#fca5a5;}
.badge-yellow{background:rgba(245,158,11,.15);color:#fcd34d;}
.badge-purple{background:rgba(108,99,255,.15);color:#a78bfa;}

/* ===== FORM GRID ===== */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-grid .span2{grid-column:span 2;}
@media(max-width:600px){.form-grid{grid-template-columns:1fr;} .form-grid .span2{grid-column:span 1;}}

/* ===== STUDENT CARD ===== */
.stu-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;}
.stu-card{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  padding:24px;text-align:center;transition:.2s;position:relative;
}
.stu-card:hover{transform:translateY(-3px);box-shadow:var(--shadow);}
.stu-card .sc-photo{
  width:80px;height:80px;border-radius:50%;object-fit:cover;
  border:3px solid var(--accent);margin:0 auto 14px;display:block;
}
.stu-card .sc-initials{
  width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#a78bfa);
  display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;
  margin:0 auto 14px;border:3px solid var(--accent);
}
.stu-card h4{font-size:1rem;font-weight:700;margin-bottom:4px;}
.stu-card .stu-id{font-size:.78rem;color:var(--accent);font-weight:600;margin-bottom:8px;}
.stu-card .stu-meta{font-size:.78rem;color:var(--muted);line-height:1.7;}
.stu-card .stu-actions{margin-top:14px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;}

/* ===== PROFILE ===== */
.profile-top{display:flex;align-items:flex-start;gap:28px;flex-wrap:wrap;margin-bottom:24px;}
.profile-photo{width:110px;height:110px;border-radius:50%;object-fit:cover;border:4px solid var(--accent);flex-shrink:0;}
.profile-initials{width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#a78bfa);display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:700;border:4px solid var(--accent);flex-shrink:0;}
.profile-info h2{font-size:1.5rem;font-weight:700;margin-bottom:6px;}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 24px;margin-top:10px;}
.info-item label{font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:600;}
.info-item p{font-size:.9rem;margin-top:2px;}

/* ===== ATTENDANCE ===== */
.att-calendar{display:grid;grid-template-columns:repeat(auto-fill,minmax(38px,1fr));gap:6px;}
.att-day{
  aspect-ratio:1;border-radius:8px;display:flex;align-items:center;justify-content:center;
  font-size:.75rem;font-weight:600;position:relative;cursor:default;
}
.att-day.present{background:rgba(16,185,129,.2);color:#6ee7b7;border:1px solid rgba(16,185,129,.3);}
.att-day.absent{background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.2);}
.att-day.none{background:var(--surface);color:var(--muted);border:1px solid var(--border);}

/* ===== NOTICE ===== */
.notice-item{
  border-left:3px solid var(--accent);padding:16px 20px;margin-bottom:12px;
  background:var(--surface);border-radius:0 10px 10px 0;
}
.notice-item h4{font-size:.95rem;font-weight:700;margin-bottom:4px;}
.notice-item p{font-size:.85rem;color:var(--muted);line-height:1.6;}
.notice-meta{font-size:.75rem;color:var(--accent);margin-top:8px;}

/* ===== MODAL ===== */
.modal-bg{
  position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;
  display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px);
}
.modal-bg.open{display:flex;}
.modal{
  background:var(--card);border:1px solid var(--border);border-radius:20px;
  width:90%;max-width:560px;max-height:90vh;overflow-y:auto;
  animation:slideUp .3s ease;box-shadow:var(--shadow);
}
.modal-head{
  padding:20px 24px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
}
.modal-head h3{font-size:1.05rem;font-weight:700;}
.modal-close{background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.2rem;padding:4px;}
.modal-close:hover{color:var(--danger);}
.modal-body{padding:24px;}

/* ===== PROGRESS ===== */
.progress-bar{background:var(--border);border-radius:20px;height:8px;overflow:hidden;}
.progress-fill{height:100%;border-radius:20px;transition:width .5s ease;}

/* ===== TABS ===== */
.tab-list{display:flex;gap:4px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:5px;margin-bottom:20px;flex-wrap:wrap;}
.tab-btn{padding:8px 16px;border-radius:8px;border:none;background:none;color:var(--muted);font-size:.85rem;font-weight:600;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;}
.tab-btn.active{background:var(--accent);color:#fff;}

/* ===== EMPTY ===== */
.empty{text-align:center;padding:48px 20px;color:var(--muted);}
.empty .ei{font-size:3rem;margin-bottom:12px;}

/* ===== ANIMATIONS ===== */
@keyframes slideUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}

/* ===== RESPONSIVE ===== */
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.open{transform:translateX(0);}
  .main{margin-left:0;padding:20px 16px;}
  .menu-toggle{display:block;}
  .stats-grid{grid-template-columns:1fr 1fr;}
  .profile-top{flex-direction:column;align-items:center;text-align:center;}
  .info-grid{grid-template-columns:1fr;}
  .topbar{flex-direction:row;flex-wrap:wrap;gap:10px;}
  .sidebar-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99;display:none;}
  .sidebar-overlay.open{display:block;}
}
@media(max-width:480px){
  .stats-grid{grid-template-columns:1fr;}
  .btn{padding:10px 16px;font-size:.85rem;}
}

/* ===== RESULT GAUGE ===== */
.grade-badge{
  display:inline-flex;align-items:center;justify-content:center;
  width:42px;height:42px;border-radius:10px;font-weight:700;font-size:1rem;
}
.search-box{
  background:var(--surface);border:1px solid var(--border);border-radius:10px;
  padding:10px 16px;color:var(--text);font-size:.9rem;font-family:'DM Sans',sans-serif;
  width:220px;
}
.search-box:focus{outline:none;border-color:var(--accent);}
</style>
</head>
<body>

<?php if(!$user): ?>
<!-- =================== LOGIN =================== -->
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">
      <h1>Feni <span>University</span></h1>
      <p>Student Management System</p>
    </div>

    <?php if($msg): list($type,$text)=explode(':',$msg,2); ?>
    <div class="alert alert-<?= $type=='success'?'success':'error' ?>">
      <?= $type=='success'?'✓':'⚠' ?> <?= htmlspecialchars($text) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>User ID / Email</label>
        <input name="u_id" placeholder="Admin ID or Student Email" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="u_pass" placeholder="Password or Student ID" required>
      </div>
      <button name="login" class="btn btn-primary btn-full" style="margin-top:8px">Sign In →</button>
    </form>

    <div class="hint-box">
      <strong>Admin:</strong> ID: <code>admin</code> / Pass: <code>admin123</code><br>
      <strong>Student:</strong> Email / Pass: Student ID
    </div>
  </div>
</div>

<?php else: ?>
<!-- =================== APP =================== -->
<div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
<div class="layout">

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <h2>Edu<span>Track</span></h2>
    <p><?= $user['role']=='admin' ? 'Admin Panel' : 'Student Portal' ?></p>
  </div>

  <div class="user-pill">
    <div class="avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
    <div>
      <div class="uname"><?= htmlspecialchars($user['name']) ?></div>
      <div class="urole"><?= $user['role'] ?></div>
    </div>
  </div>

  <nav>
    <?php if($user['role']=='admin'): ?>
    <a href="?tab=dashboard" class="<?= $tab=='dashboard'?'active':'' ?>"><span class="ico">📊</span> Dashboard</a>
    <a href="?tab=students"  class="<?= $tab=='students' ?'active':'' ?>"><span class="ico">🎓</span> Students</a>
    <a href="?tab=attendance"class="<?= $tab=='attendance'?'active':'' ?>"><span class="ico">📅</span> Attendance</a>
    <a href="?tab=results"   class="<?= $tab=='results'  ?'active':'' ?>"><span class="ico">📝</span> Results</a>
    <a href="?tab=notices"   class="<?= $tab=='notices'  ?'active':'' ?>"><span class="ico">📢</span> Notices</a>
    <?php else: ?>
    <a href="?tab=dashboard" class="<?= $tab=='dashboard'?'active':'' ?>"><span class="ico">🏠</span> Dashboard</a>
    <a href="?tab=profile"   class="<?= $tab=='profile'  ?'active':'' ?>"><span class="ico">👤</span> My Profile</a>
    <a href="?tab=my_results"class="<?= $tab=='my_results'?'active':'' ?>"><span class="ico">📝</span> My Results</a>
    <a href="?tab=my_att"    class="<?= $tab=='my_att'   ?'active':'' ?>"><span class="ico">📅</span> Attendance</a>
    <a href="?tab=notices"   class="<?= $tab=='notices'  ?'active':'' ?>"><span class="ico">📢</span> Notices</a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="?logout">🚪 Sign Out</a>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<main class="main">
  <div class="topbar">
    <div>
      <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
    </div>
    <div>
      <h1>
      <?php
      $titles=['dashboard'=>'Dashboard','students'=>'Students','attendance'=>'Attendance',
               'results'=>'Results','notices'=>'Notices','profile'=>'My Profile',
               'my_results'=>'My Results','my_att'=>'My Attendance','student_profile'=>'Student Profile'];
      echo $titles[$tab] ?? 'Dashboard';
      ?>
      </h1>
      <div class="sub"><?= date('l, d F Y') ?></div>
    </div>
    <div></div>
  </div>

  <?php if($msg): list($mtype,$mtext)=explode(':',$msg,2); ?>
  <div class="alert alert-<?= $mtype=='success'?'success':'error' ?>">
    <?= $mtype=='success'?'✓':'⚠' ?> <?= htmlspecialchars($mtext) ?>
  </div>
  <?php endif; ?>

  <!-- ============ ADMIN DASHBOARD ============ -->
  <?php if($tab=='dashboard' && $user['role']=='admin'): ?>

  <div class="stats-grid">
    <div class="stat-card s1">
      <div class="sc-icon">🎓</div>
      <div class="sc-val"><?= $totalStudents ?></div>
      <div class="sc-label">Total Students</div>
    </div>
    <div class="stat-card s2">
      <div class="sc-icon">✅</div>
      <div class="sc-val"><?= $todayPresent ?></div>
      <div class="sc-label">Present Today</div>
    </div>
    <div class="stat-card s3">
      <div class="sc-icon">📝</div>
      <div class="sc-val"><?= $totalResults ?></div>
      <div class="sc-label">Result Entries</div>
    </div>
    <div class="stat-card s4">
      <div class="sc-icon">📢</div>
      <div class="sc-val"><?= $totalNotices ?></div>
      <div class="sc-label">Notices</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap;">
    <!-- Recent Students -->
    <div class="card">
      <div class="card-head"><h3>Recent Students</h3><a href="?tab=students" style="font-size:.8rem;color:var(--accent)">View all →</a></div>
      <div class="tbl-wrap">
        <table>
          <tr><th>Name</th><th>ID</th><th>Class</th></tr>
          <?php $recent=array_slice(array_reverse($students),0,5); foreach($recent as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><span class="badge badge-purple"><?= htmlspecialchars($s['sid']) ?></span></td>
            <td><?= htmlspecialchars($s['class'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($recent)): ?><tr><td colspan="3" style="color:var(--muted);text-align:center">No students yet</td></tr><?php endif; ?>
        </table>
      </div>
    </div>

    <!-- Recent Notices -->
    <div class="card">
      <div class="card-head"><h3>Latest Notices</h3><a href="?tab=notices" style="font-size:.8rem;color:var(--accent)">View all →</a></div>
      <div class="card-body" style="padding:16px;">
        <?php $rnotices=array_slice(array_reverse($notices),0,3); foreach($rnotices as $n): ?>
        <div class="notice-item">
          <h4><?= htmlspecialchars($n['title']) ?></h4>
          <p><?= htmlspecialchars(substr($n['body'],0,80)) ?>...</p>
          <div class="notice-meta">📅 <?= $n['date'] ?></div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($rnotices)): ?><div class="empty"><div class="ei">📭</div>No notices yet</div><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ============ STUDENTS TAB ============ -->
  <?php elseif($tab=='students' && $user['role']=='admin'): ?>

  <div class="card" style="margin-bottom:20px;">
    <div class="card-head">
      <h3>➕ Add New Student</h3>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <div class="form-grid">
          <div class="form-group"><label>Full Name</label><input name="name" placeholder="e.g. Md. Rahim Uddin" required></div>
          <div class="form-group"><label>Student ID</label><input name="sid" placeholder="e.g. STU-001" required></div>
          <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="student@email.com" required></div>
          <div class="form-group"><label>Mobile</label><input name="mobile" placeholder="01XXXXXXXXX"></div>
          <div class="form-group"><label>Class / Course</label><input name="class" placeholder="e.g. Class 10 / CSE"></div>
          <div class="form-group"><label>Batch</label><input name="batch" placeholder="e.g. 2024"></div>
          <div class="form-group"><label>Date of Birth</label><input type="date" name="dob"></div>
          <div class="form-group"><label>Photo</label><input type="file" name="img" accept="image/*" style="padding:8px"></div>
          <div class="form-group span2"><label>Address</label><textarea name="address" placeholder="Full address..."></textarea></div>
        </div>
        <button name="add_student" class="btn btn-success">✚ Add Student</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <h3>All Students (<?= count($students) ?>)</h3>
      <input class="search-box" type="text" id="stuSearch" placeholder="🔍 Search..." onkeyup="searchTable('stuSearch','stuTable')">
    </div>
    <?php if(empty($students)): ?>
    <div class="empty"><div class="ei">🎓</div><p>No students added yet</p></div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table id="stuTable">
        <tr><th>Photo</th><th>Name</th><th>ID</th><th>Class</th><th>Batch</th><th>Mobile</th><th>Email</th><th>Attendance</th><th>Actions</th></tr>
        <?php foreach($students as $s):
          $attPct = getAttendancePercent($s['sid'], $attendance);
          $attColor = $attPct>=75?'var(--accent3)':($attPct>=50?'var(--accent2)':'var(--danger)');
        ?>
        <tr>
          <td>
            <?php if(!empty($s['img']) && file_exists('uploads/'.$s['img'])): ?>
              <img src="uploads/<?= $s['img'] ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
            <?php else: ?>
              <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#a78bfa);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;"><?= strtoupper(substr($s['name'],0,1)) ?></div>
            <?php endif; ?>
          </td>
          <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
          <td><span class="badge badge-purple"><?= htmlspecialchars($s['sid']) ?></span></td>
          <td><?= htmlspecialchars($s['class'] ?? '—') ?></td>
          <td><?= htmlspecialchars($s['batch'] ?? '—') ?></td>
          <td><?= htmlspecialchars($s['mobile'] ?? '—') ?></td>
          <td><?= htmlspecialchars($s['email']) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="progress-bar" style="width:70px;"><div class="progress-fill" style="width:<?= $attPct ?>%;background:<?= $attColor ?>;"></div></div>
              <span style="font-size:.78rem;color:<?= $attColor ?>"><?= $attPct ?>%</span>
            </div>
          </td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <a href="?tab=student_profile&sid=<?= urlencode($s['sid']) ?>" class="btn btn-sm btn-primary">👁</a>
              <button class="btn btn-sm btn-warning" onclick="openEdit(<?= htmlspecialchars(json_encode($s),ENT_QUOTES) ?>)">✏</button>
              <a href="?delete_student=<?= urlencode($s['sid']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete <?= htmlspecialchars($s['name']) ?>?')">🗑</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Edit Modal -->
  <div class="modal-bg" id="editModal">
    <div class="modal">
      <div class="modal-head">
        <h3>✏️ Edit Student</h3>
        <button class="modal-close" onclick="closeEdit()">✕</button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="edit_sid" id="edit_sid">
          <div class="form-grid">
            <div class="form-group"><label>Full Name</label><input name="name" id="edit_name" required></div>
            <div class="form-group"><label>Email</label><input name="email" id="edit_email" required></div>
            <div class="form-group"><label>Class</label><input name="class" id="edit_class"></div>
            <div class="form-group"><label>Batch</label><input name="batch" id="edit_batch"></div>
            <div class="form-group"><label>Mobile</label><input name="mobile" id="edit_mobile"></div>
            <div class="form-group"><label>Date of Birth</label><input type="date" name="dob" id="edit_dob"></div>
            <div class="form-group"><label>New Photo (optional)</label><input type="file" name="img" accept="image/*" style="padding:8px"></div>
            <div class="form-group span2"><label>Address</label><textarea name="address" id="edit_address"></textarea></div>
          </div>
          <button name="edit_student" class="btn btn-primary">💾 Save Changes</button>
        </form>
      </div>
    </div>
  </div>

  <!-- ============ STUDENT PROFILE (ADMIN VIEW) ============ -->
  <?php elseif($tab=='student_profile' && $user['role']=='admin'):
    $viewSid = $_GET['sid'] ?? '';
    $viewStu = null;
    foreach($students as $s){ if($s['sid']==$viewSid){ $viewStu=$s; break; } }
    if($viewStu):
      $stuResults = array_filter($results, fn($r)=>$r['sid']==$viewStu['sid']);
      $stuAtt     = array_filter($attendance, fn($a)=>$a['sid']==$viewStu['sid']);
      $attPct     = getAttendancePercent($viewStu['sid'], $attendance);
      $totalMarks=0; $countMarks=0;
      foreach($stuResults as $r){ $totalMarks+=$r['marks']; $countMarks++; }
      $avgMarks = $countMarks>0?round($totalMarks/$countMarks):0;
  ?>
  <a href="?tab=students" style="color:var(--muted);font-size:.85rem;display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;">← Back to Students</a>

  <div class="card" style="margin-bottom:20px;">
    <div class="card-body">
      <div class="profile-top">
        <?php if(!empty($viewStu['img']) && file_exists('uploads/'.$viewStu['img'])): ?>
          <img src="uploads/<?= $viewStu['img'] ?>" class="profile-photo">
        <?php else: ?>
          <div class="profile-initials"><?= strtoupper(substr($viewStu['name'],0,1)) ?></div>
        <?php endif; ?>
        <div class="profile-info" style="flex:1">
          <h2><?= htmlspecialchars($viewStu['name']) ?></h2>
          <span class="badge badge-purple"><?= htmlspecialchars($viewStu['sid']) ?></span>
          <div class="info-grid" style="margin-top:14px;">
            <div class="info-item"><label>Email</label><p><?= htmlspecialchars($viewStu['email']) ?></p></div>
            <div class="info-item"><label>Mobile</label><p><?= htmlspecialchars($viewStu['mobile']??'—') ?></p></div>
            <div class="info-item"><label>Class</label><p><?= htmlspecialchars($viewStu['class']??'—') ?></p></div>
            <div class="info-item"><label>Batch</label><p><?= htmlspecialchars($viewStu['batch']??'—') ?></p></div>
            <div class="info-item"><label>Date of Birth</label><p><?= htmlspecialchars($viewStu['dob']??'—') ?></p></div>
            <div class="info-item"><label>Joined</label><p><?= htmlspecialchars($viewStu['join_date']??'—') ?></p></div>
            <?php if(!empty($viewStu['address'])): ?>
            <div class="info-item" style="grid-column:span 2"><label>Address</label><p><?= htmlspecialchars($viewStu['address']) ?></p></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:20px;">
        <div style="background:var(--surface);border-radius:12px;padding:18px;text-align:center;border:1px solid var(--border);">
          <div style="font-size:1.6rem;font-weight:700;color:var(--accent)"><?= $attPct ?>%</div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:4px;">Attendance</div>
        </div>
        <div style="background:var(--surface);border-radius:12px;padding:18px;text-align:center;border:1px solid var(--border);">
          <div style="font-size:1.6rem;font-weight:700;color:var(--accent2)"><?= $avgMarks ?></div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:4px;">Avg. Marks</div>
        </div>
        <div style="background:var(--surface);border-radius:12px;padding:18px;text-align:center;border:1px solid var(--border);">
          <div style="font-size:1.6rem;font-weight:700;color:var(--accent3)"><?= count($stuResults) ?></div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:4px;">Exams Taken</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>📝 Results</h3></div>
    <div class="tbl-wrap">
      <table>
        <tr><th>Subject</th><th>Exam</th><th>Marks</th><th>Total</th><th>Grade</th><th>GPA</th><th>Date</th></tr>
        <?php foreach($stuResults as $r):
          [$g,$gpa,$gc]=getGrade($r['marks']);
        ?>
        <tr>
          <td><?= htmlspecialchars($r['subject']) ?></td>
          <td><?= htmlspecialchars($r['exam']) ?></td>
          <td><strong><?= $r['marks'] ?></strong></td>
          <td><?= $r['total'] ?></td>
          <td><div class="grade-badge" style="background:<?= $gc ?>22;color:<?= $gc ?>;border:1px solid <?= $gc ?>44"><?= $g ?></div></td>
          <td><?= $gpa ?></td>
          <td><?= $r['date'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($stuResults)): ?><tr><td colspan="7" style="color:var(--muted);text-align:center;padding:20px">No results</td></tr><?php endif; ?>
      </table>
    </div>
  </div>
  <?php else: ?>
    <div class="card"><div class="empty"><div class="ei">❌</div><p>Student not found</p></div></div>
  <?php endif; ?>

  <!-- ============ ATTENDANCE TAB ============ -->
  <?php elseif($tab=='attendance' && $user['role']=='admin'): ?>

  <div class="card" style="margin-bottom:20px;">
    <div class="card-head"><h3>📅 Mark Attendance</h3></div>
    <div class="card-body">
      <form method="POST">
        <div class="form-grid">
          <div class="form-group">
            <label>Student</label>
            <select name="att_sid" required>
              <option value="">Select Student</option>
              <?php foreach($students as $s): ?>
              <option value="<?= htmlspecialchars($s['sid']) ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['sid']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Date</label>
            <input type="date" name="att_date" value="<?= $todayDate ?>" required>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="att_status" required>
              <option value="present">✅ Present</option>
              <option value="absent">❌ Absent</option>
            </select>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button name="mark_attendance" class="btn btn-success">✓ Mark</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Bulk View -->
  <div class="card">
    <div class="card-head">
      <h3>📊 Attendance Summary</h3>
      <input type="date" id="attDateFilter" value="<?= $todayDate ?>" onchange="filterAttDate()" style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);">
    </div>
    <div class="tbl-wrap">
      <table id="attTable">
        <tr><th>Student</th><th>ID</th><th>Date</th><th>Status</th></tr>
        <?php foreach(array_reverse($attendance) as $a):
          $stuName='Unknown';
          foreach($students as $s){ if($s['sid']==$a['sid']){ $stuName=$s['name']; break; } }
        ?>
        <tr class="att-row" data-date="<?= $a['date'] ?>">
          <td><?= htmlspecialchars($stuName) ?></td>
          <td><?= htmlspecialchars($a['sid']) ?></td>
          <td><?= $a['date'] ?></td>
          <td><span class="badge <?= $a['status']=='present'?'badge-green':'badge-red' ?>"><?= $a['status']=='present'?'✅ Present':'❌ Absent' ?></span></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- Monthly Report per student -->
  <div class="card" style="margin-top:20px;">
    <div class="card-head"><h3>📈 Student Attendance Overview</h3></div>
    <div class="tbl-wrap">
      <table>
        <tr><th>Name</th><th>ID</th><th>Present</th><th>Absent</th><th>%</th><th>Status</th></tr>
        <?php foreach($students as $s):
          $pres=count(array_filter($attendance,fn($a)=>$a['sid']==$s['sid']&&$a['status']=='present'));
          $abs =count(array_filter($attendance,fn($a)=>$a['sid']==$s['sid']&&$a['status']=='absent'));
          $tot =$pres+$abs; $pct=$tot>0?round($pres/$tot*100):0;
          $sc  =$pct>=75?'badge-green':($pct>=50?'badge-yellow':'badge-red');
          $sl  =$pct>=75?'Good':($pct>=50?'Average':'Low');
        ?>
        <tr>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><?= htmlspecialchars($s['sid']) ?></td>
          <td style="color:var(--accent3)"><?= $pres ?></td>
          <td style="color:var(--danger)"><?= $abs ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:<?=$pct?>%;background:<?=$pct>=75?'var(--accent3)':($pct>=50?'var(--accent2)':'var(--danger)')?>"></div></div>
              <span><?= $pct ?>%</span>
            </div>
          </td>
          <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- ============ RESULTS TAB ============ -->
  <?php elseif($tab=='results' && $user['role']=='admin'): ?>

  <div class="card" style="margin-bottom:20px;">
    <div class="card-head"><h3>➕ Add Result</h3></div>
    <div class="card-body">
      <form method="POST">
        <div class="form-grid">
          <div class="form-group">
            <label>Student</label>
            <select name="rsid" required>
              <option value="">Select Student</option>
              <?php foreach($students as $s): ?>
              <option value="<?= htmlspecialchars($s['sid']) ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['sid']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Subject</label><input name="subject" placeholder="e.g. Mathematics" required></div>
          <div class="form-group"><label>Exam Name</label><input name="exam" placeholder="e.g. Mid-Term 2024" required></div>
          <div class="form-group"><label>Marks Obtained</label><input type="number" name="marks" min="0" max="100" required></div>
          <div class="form-group"><label>Total Marks</label><input type="number" name="total" value="100" required></div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button name="add_result" class="btn btn-success">✚ Add Result</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <h3>All Results (<?= count($results) ?>)</h3>
      <input class="search-box" type="text" id="resSearch" placeholder="🔍 Search..." onkeyup="searchTable('resSearch','resTable')">
    </div>
    <div class="tbl-wrap">
      <table id="resTable">
        <tr><th>Student</th><th>Subject</th><th>Exam</th><th>Marks</th><th>Grade</th><th>GPA</th><th>Date</th><th>Del</th></tr>
        <?php foreach(array_reverse($results) as $r):
          $stuName='Unknown'; foreach($students as $s){ if($s['sid']==$r['sid']){ $stuName=$s['name']; break; } }
          [$g,$gpa,$gc]=getGrade($r['marks']);
        ?>
        <tr>
          <td><?= htmlspecialchars($stuName) ?></td>
          <td><?= htmlspecialchars($r['subject']) ?></td>
          <td><?= htmlspecialchars($r['exam']) ?></td>
          <td><strong><?= $r['marks'] ?>/<?= $r['total'] ?></strong></td>
          <td><div class="grade-badge" style="background:<?= $gc ?>22;color:<?= $gc ?>;border:1px solid <?= $gc ?>44"><?= $g ?></div></td>
          <td><?= $gpa ?></td>
          <td><?= $r['date'] ?></td>
          <td><a href="?delete_result=<?= urlencode($r['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">🗑</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($results)): ?><tr><td colspan="8" style="color:var(--muted);text-align:center;padding:20px">No results yet</td></tr><?php endif; ?>
      </table>
    </div>
  </div>

  <!-- ============ NOTICES TAB ============ -->
  <?php elseif($tab=='notices'): ?>

  <?php if($user['role']=='admin'): ?>
  <div class="card" style="margin-bottom:20px;">
    <div class="card-head"><h3>📢 Publish Notice</h3></div>
    <div class="card-body">
      <form method="POST">
        <div class="form-group"><label>Title</label><input name="n_title" placeholder="Notice title..." required></div>
        <div class="form-group"><label>Content</label><textarea name="n_body" placeholder="Write your notice here..." required></textarea></div>
        <button name="add_notice" class="btn btn-warning">📤 Publish</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head"><h3>All Notices (<?= count($notices) ?>)</h3></div>
    <div class="card-body">
      <?php foreach(array_reverse($notices) as $n): ?>
      <div class="notice-item">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
          <div style="flex:1">
            <h4><?= htmlspecialchars($n['title']) ?></h4>
            <p style="margin-top:8px"><?= nl2br(htmlspecialchars($n['body'])) ?></p>
            <div class="notice-meta">📅 <?= $n['date'] ?> &nbsp;|&nbsp; 👤 <?= htmlspecialchars($n['by']) ?></div>
          </div>
          <?php if($user['role']=='admin'): ?>
          <a href="?delete_notice=<?= urlencode($n['id']) ?>&tab=notices" class="btn btn-sm btn-danger" onclick="return confirm('Delete notice?')">🗑</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(empty($notices)): ?><div class="empty"><div class="ei">📭</div><p>No notices published</p></div><?php endif; ?>
    </div>
  </div>

  <!-- ============ STUDENT: DASHBOARD ============ -->
  <?php elseif($tab=='dashboard' && $user['role']=='student'): ?>

  <?php
  $myStuResults = array_filter($results, fn($r)=>$r['sid']==$user['user_id']);
  $myAttPct     = getAttendancePercent($user['user_id'], $attendance);
  $myTotalM=0; $myCountM=0;
  foreach($myStuResults as $r){ $myTotalM+=$r['marks']; $myCountM++; }
  $myAvg = $myCountM>0?round($myTotalM/$myCountM):0;
  $attColor = $myAttPct>=75?'var(--accent3)':($myAttPct>=50?'var(--accent2)':'var(--danger)');
  ?>

  <div class="stats-grid">
    <div class="stat-card s1">
      <div class="sc-icon">📅</div>
      <div class="sc-val" style="color:<?= $attColor ?>"><?= $myAttPct ?>%</div>
      <div class="sc-label">My Attendance</div>
    </div>
    <div class="stat-card s2">
      <div class="sc-icon">📝</div>
      <div class="sc-val"><?= count($myStuResults) ?></div>
      <div class="sc-label">Exams Taken</div>
    </div>
    <div class="stat-card s3">
      <div class="sc-icon">⭐</div>
      <div class="sc-val"><?= $myAvg ?></div>
      <div class="sc-label">Avg. Marks</div>
    </div>
    <div class="stat-card s4">
      <div class="sc-icon">📢</div>
      <div class="sc-val"><?= count($notices) ?></div>
      <div class="sc-label">Notices</div>
    </div>
  </div>

  <?php if($myStudent): ?>
  <div class="card" style="margin-bottom:20px;">
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <?php if(!empty($myStudent['img']) && file_exists('uploads/'.$myStudent['img'])): ?>
          <img src="uploads/<?= $myStudent['img'] ?>" style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);">
        <?php else: ?>
          <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#a78bfa);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:700;flex-shrink:0;"><?= strtoupper(substr($myStudent['name'],0,1)) ?></div>
        <?php endif; ?>
        <div>
          <h3><?= htmlspecialchars($myStudent['name']) ?></h3>
          <p style="color:var(--muted);font-size:.85rem;margin-top:4px;"><?= htmlspecialchars($myStudent['class']??'') ?> <?= !empty($myStudent['batch'])?'· Batch '.$myStudent['batch']:'' ?></p>
          <span class="badge badge-purple" style="margin-top:6px"><?= htmlspecialchars($myStudent['sid']) ?></span>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent Results -->
  <div class="card">
    <div class="card-head"><h3>Recent Results</h3><a href="?tab=my_results" style="font-size:.8rem;color:var(--accent)">View all →</a></div>
    <div class="tbl-wrap">
      <table>
        <tr><th>Subject</th><th>Exam</th><th>Marks</th><th>Grade</th></tr>
        <?php $recentRes=array_slice(array_reverse(array_values($myStuResults)),0,5);
        foreach($recentRes as $r): [$g,$gpa,$gc]=getGrade($r['marks']); ?>
        <tr>
          <td><?= htmlspecialchars($r['subject']) ?></td>
          <td><?= htmlspecialchars($r['exam']) ?></td>
          <td><?= $r['marks'] ?>/<?= $r['total'] ?></td>
          <td><div class="grade-badge" style="background:<?= $gc ?>22;color:<?= $gc ?>;border:1px solid <?= $gc ?>44"><?= $g ?></div></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($recentRes)): ?><tr><td colspan="4" style="color:var(--muted);text-align:center;padding:20px">No results yet</td></tr><?php endif; ?>
      </table>
    </div>
  </div>

  <!-- ============ STUDENT: PROFILE ============ -->
  <?php elseif($tab=='profile' && $user['role']=='student' && $myStudent): ?>

  <div class="card">
    <div class="card-body">
      <div class="profile-top">
        <?php if(!empty($myStudent['img']) && file_exists('uploads/'.$myStudent['img'])): ?>
          <img src="uploads/<?= $myStudent['img'] ?>" class="profile-photo">
        <?php else: ?>
          <div class="profile-initials"><?= strtoupper(substr($myStudent['name'],0,1)) ?></div>
        <?php endif; ?>
        <div class="profile-info" style="flex:1">
          <h2><?= htmlspecialchars($myStudent['name']) ?></h2>
          <span class="badge badge-purple"><?= htmlspecialchars($myStudent['sid']) ?></span>
          <div class="info-grid">
            <div class="info-item"><label>Email</label><p><?= htmlspecialchars($myStudent['email']) ?></p></div>
            <div class="info-item"><label>Mobile</label><p><?= htmlspecialchars($myStudent['mobile']??'—') ?></p></div>
            <div class="info-item"><label>Class</label><p><?= htmlspecialchars($myStudent['class']??'—') ?></p></div>
            <div class="info-item"><label>Batch</label><p><?= htmlspecialchars($myStudent['batch']??'—') ?></p></div>
            <div class="info-item"><label>Date of Birth</label><p><?= htmlspecialchars($myStudent['dob']??'—') ?></p></div>
            <div class="info-item"><label>Joined</label><p><?= htmlspecialchars($myStudent['join_date']??'—') ?></p></div>
            <?php if(!empty($myStudent['address'])): ?>
            <div class="info-item" style="grid-column:span 2"><label>Address</label><p><?= htmlspecialchars($myStudent['address']) ?></p></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ STUDENT: MY RESULTS ============ -->
  <?php elseif($tab=='my_results' && $user['role']=='student'): ?>
  <?php
  $myStuResults = array_values(array_filter($results, fn($r)=>$r['sid']==$user['user_id']));
  $totalM=0; $countM=0; foreach($myStuResults as $r){ $totalM+=$r['marks']; $countM++; }
  $avgM=$countM?round($totalM/$countM):0;
  ?>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;margin-bottom:22px;">
    <?php
    $examGroups=[];
    foreach($myStuResults as $r){ $examGroups[$r['exam']][]=$r; }
    foreach($examGroups as $examName=>$ers):
      $sumM=array_sum(array_column($ers,'marks'));
      $sumT=array_sum(array_column($ers,'total'));
      $pct=$sumT?round($sumM/$sumT*100):0;
    ?>
    <div class="stat-card s1" style="text-align:center;">
      <div class="sc-val" style="font-size:1.4rem;"><?= $pct ?>%</div>
      <div class="sc-label"><?= htmlspecialchars($examName) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>My Results</h3><span style="color:var(--muted);font-size:.85rem">Avg: <?= $avgM ?>/100</span></div>
    <div class="tbl-wrap">
      <table>
        <tr><th>Subject</th><th>Exam</th><th>Marks</th><th>Total</th><th>%</th><th>Grade</th><th>GPA</th></tr>
        <?php foreach(array_reverse($myStuResults) as $r): [$g,$gpa,$gc]=getGrade($r['marks']); ?>
        <tr>
          <td><?= htmlspecialchars($r['subject']) ?></td>
          <td><?= htmlspecialchars($r['exam']) ?></td>
          <td><strong><?= $r['marks'] ?></strong></td>
          <td><?= $r['total'] ?></td>
          <td><?= round($r['marks']/$r['total']*100) ?>%</td>
          <td><div class="grade-badge" style="background:<?= $gc ?>22;color:<?= $gc ?>;border:1px solid <?= $gc ?>44"><?= $g ?></div></td>
          <td><?= $gpa ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($myStuResults)): ?><tr><td colspan="7" style="color:var(--muted);text-align:center;padding:20px">No results yet</td></tr><?php endif; ?>
      </table>
    </div>
  </div>

  <!-- ============ STUDENT: MY ATTENDANCE ============ -->
  <?php elseif($tab=='my_att' && $user['role']=='student'): ?>
  <?php
  $myAtt = array_filter($attendance, fn($a)=>$a['sid']==$user['user_id']);
  $myPres=count(array_filter($myAtt,fn($a)=>$a['status']=='present'));
  $myAbs=count(array_filter($myAtt,fn($a)=>$a['status']=='absent'));
  $myTotal=$myPres+$myAbs;
  $myPct=$myTotal?round($myPres/$myTotal*100):0;
  $attColor2=$myPct>=75?'var(--accent3)':($myPct>=50?'var(--accent2)':'var(--danger)');
  ?>

  <div class="stats-grid" style="margin-bottom:22px;">
    <div class="stat-card s1"><div class="sc-icon">✅</div><div class="sc-val" style="color:var(--accent3)"><?= $myPres ?></div><div class="sc-label">Present Days</div></div>
    <div class="stat-card s2"><div class="sc-icon">❌</div><div class="sc-val" style="color:var(--danger)"><?= $myAbs ?></div><div class="sc-label">Absent Days</div></div>
    <div class="stat-card s3"><div class="sc-icon">📊</div><div class="sc-val" style="color:<?= $attColor2 ?>"><?= $myPct ?>%</div><div class="sc-label">Overall %</div></div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Attendance Record</h3></div>
    <div class="tbl-wrap">
      <table>
        <tr><th>Date</th><th>Status</th></tr>
        <?php foreach(array_reverse(array_values($myAtt)) as $a): ?>
        <tr>
          <td><?= $a['date'] ?></td>
          <td><span class="badge <?= $a['status']=='present'?'badge-green':'badge-red' ?>"><?= $a['status']=='present'?'✅ Present':'❌ Absent' ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($myAtt)): ?><tr><td colspan="2" style="color:var(--muted);text-align:center;padding:20px">No attendance recorded</td></tr><?php endif; ?>
      </table>
    </div>
  </div>

  <?php endif; ?>
</main>
</div>

<?php endif; ?>

<script>
// ===== SIDEBAR TOGGLE =====
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('open');
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
}

// ===== EDIT MODAL =====
function openEdit(s){
  document.getElementById('editModal').classList.add('open');
  document.getElementById('edit_sid').value    = s.sid;
  document.getElementById('edit_name').value   = s.name || '';
  document.getElementById('edit_email').value  = s.email || '';
  document.getElementById('edit_class').value  = s.class || '';
  document.getElementById('edit_batch').value  = s.batch || '';
  document.getElementById('edit_mobile').value = s.mobile || '';
  document.getElementById('edit_dob').value    = s.dob || '';
  document.getElementById('edit_address').value= s.address || '';
}
function closeEdit(){
  document.getElementById('editModal').classList.remove('open');
}
window.addEventListener('click', function(e){
  if(e.target.id=='editModal') closeEdit();
});

// ===== TABLE SEARCH =====
function searchTable(inputId, tableId){
  var q = document.getElementById(inputId).value.toLowerCase();
  var rows = document.querySelectorAll('#'+tableId+' tr:not(:first-child)');
  rows.forEach(function(r){
    r.style.display = r.textContent.toLowerCase().includes(q)?'':'none';
  });
}

// ===== ATTENDANCE DATE FILTER =====
function filterAttDate(){
  var d = document.getElementById('attDateFilter').value;
  document.querySelectorAll('.att-row').forEach(function(r){
    r.style.display = (!d || r.dataset.date==d)?'':'none';
  });
}

// ===== AUTO DISMISS ALERT =====
setTimeout(function(){
  document.querySelectorAll('.alert').forEach(function(a){
    a.style.transition='opacity .5s'; a.style.opacity='0';
    setTimeout(function(){ a.remove(); }, 500);
  });
}, 4000);
</script>

</body>
</html>