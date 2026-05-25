<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
require 'db.php';

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fix bad due_date data silently
try {
    $pdo->exec("SET SESSION sql_mode = ''");
    $pdo->exec("UPDATE tasks SET due_date = NULL WHERE user_id = $user_id AND (due_date = '' OR due_date = '0000-00-00')");
} catch (Exception $e) {}

$filter   = $_GET['filter']   ?? 'all';
$priority = $_GET['priority'] ?? 'all';
$search   = $_GET['search']   ?? '';

$where = "WHERE user_id = :uid";
if ($filter   === 'active')    $where .= " AND completed = 0";
if ($filter   === 'completed') $where .= " AND completed = 1";
if ($filter   === 'overdue')   $where .= " AND completed = 0 AND due_date < CURDATE() AND due_date IS NOT NULL";
if ($priority === 'high')      $where .= " AND priority = 'high'";
if ($priority === 'medium')    $where .= " AND priority = 'medium'";
if ($priority === 'low')       $where .= " AND priority = 'low'";
if ($search   !== '')          $where .= " AND (title LIKE :search OR description LIKE :search)";

$order = "FIELD(priority,'high','medium','low'), created_at DESC";

$stmt = $pdo->prepare("SELECT * FROM tasks $where ORDER BY $order");
$params = [':uid' => $user_id];
if ($search !== '') $params[':search'] = '%' . $search . '%';
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$editTask = null;
if (isset($_GET['edit'])) {
  $es = $pdo->prepare("SELECT * FROM tasks WHERE id=? AND user_id=?");
  $es->execute([$_GET['edit'], $user_id]);
  $editTask = $es->fetch(PDO::FETCH_ASSOC);
}

$dueDates = [];
$ds = $pdo->prepare("SELECT due_date FROM tasks WHERE user_id=? AND completed=0 AND due_date IS NOT NULL");
$ds->execute([$user_id]);
foreach ($ds->fetchAll(PDO::FETCH_COLUMN) as $d) $dueDates[] = $d;
$dueDatesJson = json_encode($dueDates);

function qstr($filter,$priority,$search,$extra=''){
  return '?filter='.urlencode($filter).'&priority='.urlencode($priority).'&search='.urlencode($search).$extra;
}

$showSplash = isset($_SESSION['show_splash']) && $_SESSION['show_splash'];
unset($_SESSION['show_splash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/jpeg" href="picturess/logo.jpg"/>
  <title><?= htmlspecialchars($user_name) ?>'s Tasks</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Permanent+Marker&display=swap" rel="stylesheet"/>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    html,body{min-height:100vh;height:100%;font-family:'Nunito',sans-serif;color:#2a2a2a;overflow-x:hidden;background-color:#faf8f3;background-image:repeating-linear-gradient(transparent,transparent 39px,rgba(180,150,130,0.35) 39px,rgba(180,150,130,0.35) 40px);}

    /* ── SPLASH ── */
    .splash{position:fixed;inset:0;background:#000;z-index:9999;display:flex;align-items:center;justify-content:center;transition:opacity .6s ease;}
    .splash.hide{opacity:0;pointer-events:none;}
    .splash img{max-width:420px;width:80vw;border-radius:8px;}

    .notebook{display:flex;min-height:100vh;position:relative;}
    .spine{flex-shrink:0;width:52px;background:linear-gradient(to right,#f0e8da,#faf8f3);border-right:2.5px solid rgba(210,60,60,0.25);position:relative;z-index:10;}
    .spine .hole{position:absolute;left:50%;transform:translateX(-50%);width:16px;height:16px;border-radius:50%;background:#faf8f3;border:2px solid #d8cbb8;box-shadow:inset 0 1px 3px rgba(0,0,0,0.12);}
    .spine .hole1{top:14%;}.spine .hole2{top:50%;}.spine .hole3{top:86%;}

    .page{flex:1;min-width:0;background:transparent;position:relative;padding:28px 24px 120px 28px;overflow-y:auto;}
    .page::before{content:'';position:absolute;top:0;bottom:0;left:64px;width:2px;background:rgba(210,60,60,0.3);pointer-events:none;z-index:1;}

    /* ── TABS ── */
    .tabs-sidebar{flex-shrink:0;width:60px;background:transparent;display:flex;flex-direction:column;align-items:flex-end;padding-top:24px;position:relative;z-index:10;}
    .tab-group{width:100%;display:flex;flex-direction:column;align-items:flex-end;margin-bottom:6px;}
    .b-tab{display:flex;align-items:center;justify-content:center;writing-mode:vertical-rl;width:50px;height:76px;margin-bottom:3px;font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;letter-spacing:1px;color:#fff;text-decoration:none;cursor:pointer;border-radius:8px 14px 14px 8px;border:none;padding:8px 6px;transition:width .15s,box-shadow .15s,filter .15s,transform .1s;box-shadow:3px 2px 10px rgba(0,0,0,0.18);position:relative;}
    .b-tab:hover{width:56px;filter:brightness(1.1);box-shadow:5px 3px 14px rgba(0,0,0,0.28);transform:translateX(4px);}
    .b-tab.active-status,.b-tab.active-priority{width:58px;box-shadow:6px 3px 16px rgba(0,0,0,0.3);filter:brightness(1.06);transform:translateX(6px);}
    .b-tab.st-all    {background:#d4689a;}
    .b-tab.st-active {background:#9c6db5;}
    .b-tab.st-done   {background:#50a898;}
    .b-tab.st-overdue{background:#d95f5f;}
    .b-tab.pri-all   {background:#e8b84b;color:#4a2e00;}
    .b-tab.pri-high  {background:#d95f5f;}
    .b-tab.pri-medium{background:#e07b30;}
    .b-tab.pri-low   {background:#4ea854;}
    .tab-connector{width:100%;height:4px;background:transparent;margin:1px 0;}

    /* ── TOP BAR ── */
    .top-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;position:relative;z-index:2;padding-left:20px;}

    /* highlighted title */
    .site-title{font-family:'Permanent Marker',cursive;font-size:28px;display:inline-block;color:#2a2a2a;position:relative;transform:rotate(-1deg);}
    .site-title .hl{position:relative;display:inline-block;}
    .site-title .hl::before{content:'';position:absolute;left:-3px;right:-3px;bottom:1px;height:55%;background:rgba(249,207,106,0.6);z-index:-1;transform:rotate(-1deg);border-radius:2px;}

    .ribbon-banner{position:relative;display:inline-flex;align-items:center;background:#f9cf6a;border:2px solid #e8b84b;padding:5px 26px 5px 18px;font-family:'Nunito',sans-serif;font-size:12px;font-weight:700;color:#5a3a00;margin-left:12px;clip-path:polygon(10px 0%,100% 0%,100% 100%,10px 100%,0% 50%);}
    .user-info{display:flex;align-items:center;gap:14px;}
    .user-name{font-family:'Permanent Marker',cursive;font-size:14px;color:#666;}
    .logout-btn{font-family:'Nunito',sans-serif;font-size:13px;font-weight:600;background:rgba(255,253,245,0.7);border:1.5px solid #c8b89a;color:#666;padding:5px 14px;cursor:pointer;border-radius:6px;transition:background .12s,color .12s;}
    .logout-btn:hover{background:#2a2a2a;color:#fff;border-color:#2a2a2a;}

    .search-wrap{margin-bottom:18px;position:relative;z-index:2;padding-left:20px;}
    .search-outer{position:relative;display:flex;align-items:center;background:rgba(255,253,245,0.85);border:none;border-bottom:2.5px solid #c8b89a;border-left:3px solid #e8b84b;max-width:520px;transition:border-color .15s;}
    .search-outer::before{content:'search';font-family:'Permanent Marker',cursive;font-size:13px;padding:0 10px;opacity:0.35;flex-shrink:0;}
    .search-input{flex:1;font-family:'Nunito',sans-serif;font-size:14px;font-weight:500;border:none;background:transparent;padding:10px 8px 10px 0;outline:none;color:#2a2a2a;}
    .search-input::placeholder{color:#bba990;}
    .search-outer:focus-within{border-bottom-color:#2a2a2a;border-left-color:#d4689a;}
    .search-submit{font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;background:rgba(249,207,106,0.3);border:none;border-left:1.5px solid #e8b84b;padding:8px 14px;color:#5a3a00;cursor:pointer;transition:background .12s;flex-shrink:0;align-self:stretch;}
    .search-submit:hover{background:#f9cf6a;}

    .main-inner{display:flex;gap:20px;align-items:flex-start;position:relative;z-index:2;padding-left:20px;}

    /* ── CALENDAR (notebook themed) ── */
    .cal-wrap{width:clamp(175px,22%,235px);flex-shrink:0;border-radius:4px;overflow:hidden;box-shadow:3px 4px 0 rgba(0,0,0,0.07);border:2px solid #2a2a2a;}
    .cal-header{background:#2a2a2a;height:32px;display:flex;align-items:center;justify-content:center;position:relative;}
    .cal-header-text{font-family:'Permanent Marker',cursive;font-size:13px;color:#f9cf6a;letter-spacing:2px;}
    .cal-header::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:repeating-linear-gradient(90deg,#f9cf6a 0,#f9cf6a 8px,transparent 8px,transparent 14px);}
    .cal-months{display:grid;grid-template-columns:repeat(6,1fr);border-bottom:2px solid #2a2a2a;background:#fffde7;}
    .cal-month-cell{font-family:'Permanent Marker',cursive;font-weight:400;font-size:8px;text-align:center;padding:5px 1px;cursor:pointer;border-right:1px solid #e8e0cc;color:#aaa;transition:background .1s;}
    .cal-month-cell:nth-child(6n){border-right:none;}
    .cal-month-cell:hover{background:#faf5e8;color:#555;}
    .cal-month-cell.active{background:#2a2a2a;color:#f9cf6a;}
    .cal-days-header{display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1px solid #d8cbb8;background:#fffde7;padding:4px 0;}
    .cal-dh{font-family:'Permanent Marker',cursive;font-size:8px;text-align:center;color:#888;padding:2px 0;}
    .cal-dh.sun{color:#d95f5f;}
    .cal-body{display:grid;grid-template-columns:repeat(7,1fr);padding:3px;background:#fffde7;}
    .cal-cell-sk{font-family:'Nunito',sans-serif;font-size:10px;font-weight:600;text-align:center;padding:3px 1px;color:#555;position:relative;border:1px solid transparent;min-height:22px;border-radius:2px;}
    .cal-cell-sk.other{color:#ccc;}
    .cal-cell-sk.sun-col{color:#d95f5f;}
    .cal-cell-sk.today-sk{background:#2a2a2a;color:#f9cf6a;border-radius:2px;font-weight:bold;}
    .cal-cell-sk.has-task-sk::after{content:'';position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;background:#d4689a;border-radius:50%;}
    .cal-nav-row{display:flex;align-items:center;justify-content:space-between;padding:6px 8px;border-top:2px solid #2a2a2a;background:#fffde7;}
    .cal-nav-sk{background:none;border:none;cursor:pointer;font-family:'Permanent Marker',cursive;font-size:18px;color:#aaa;padding:0 2px;line-height:1;}
    .cal-nav-sk:hover{color:#2a2a2a;}
    .cal-year-label{font-family:'Permanent Marker',cursive;font-size:10px;color:#888;}

    .content{flex:1;min-width:0;}
    .notes-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(175px,100%),1fr));gap:20px;}

    /* ── TASK CARDS ── */
    .note-card{border-radius:4px;border:1.5px solid rgba(0,0,0,0.07);box-shadow:2px 3px 0 rgba(0,0,0,0.05);display:flex;flex-direction:column;gap:8px;position:relative;transition:transform .15s,box-shadow .15s;min-height:150px;padding:42px 14px 14px;overflow:hidden;}
    .note-card:hover{transform:translateY(-3px) rotate(0.3deg);box-shadow:4px 7px 0 rgba(0,0,0,0.09);}
    .note-card.p-high  {background:#ffd6d6;}
    .note-card.p-medium{background:#d6eaff;}
    .note-card.p-low   {background:#d6f5e3;}
    .note-card::before{content:'';position:absolute;top:0;left:0;right:0;height:26px;border-radius:3px 3px 0 0;}
    .note-card.p-high::before  {background:#f7b8c2;}
    .note-card.p-medium::before{background:#b8d4f7;}
    .note-card.p-low::before   {background:#b8f0d0;}
    .note-card .tape{position:absolute;top:0;left:50%;transform:translateX(-50%);width:42px;height:12px;border-radius:0 0 3px 3px;opacity:0.85;z-index:2;}
    .note-card.p-high   .tape{background:repeating-linear-gradient(45deg,#f7b8c2,#f7b8c2 3px,#fbd0d7 3px,#fbd0d7 6px);}
    .note-card.p-medium .tape{background:repeating-linear-gradient(45deg,#b8d4f7,#b8d4f7 3px,#cfe3fb 3px,#cfe3fb 6px);}
    .note-card.p-low    .tape{background:repeating-linear-gradient(45deg,#b8f0d0,#b8f0d0 3px,#d0f7e3 3px,#d0f7e3 6px);}
    .note-card::after{content:'';position:absolute;bottom:0;right:0;width:22px;height:22px;background:radial-gradient(circle at 100% 100%,transparent 65%,rgba(0,0,0,0.06) 65%);border-radius:0 0 4px 0;pointer-events:none;}
    .note-card.done{opacity:0.55;}

    /* highlighted title */
    .note-title{font-family:'Permanent Marker',cursive;font-size:15px;line-height:1.4;flex:1;color:#2a2a2a;word-break:break-word;overflow-wrap:break-word;position:relative;display:inline;}
    .note-title-wrap{margin-bottom:2px;}
    .note-title.hl-high  {background:linear-gradient(120deg,rgba(255,100,120,0.28) 0%,rgba(255,100,120,0.18) 100%);border-radius:3px;padding:0 3px;}
    .note-title.hl-medium{background:linear-gradient(120deg,rgba(80,130,255,0.22) 0%,rgba(80,130,255,0.12) 100%);border-radius:3px;padding:0 3px;}
    .note-title.hl-low   {background:linear-gradient(120deg,rgba(60,180,100,0.25) 0%,rgba(60,180,100,0.15) 100%);border-radius:3px;padding:0 3px;}
    .note-card.done .note-title{text-decoration:line-through;color:#aaa;}

    .note-desc{font-family:'Nunito',sans-serif;font-size:13px;font-weight:500;color:#555;line-height:1.55;flex:1;padding-bottom:2px;word-break:break-word;overflow-wrap:break-word;}
    .note-meta{display:flex;flex-wrap:wrap;gap:5px;align-items:center;margin-top:auto;padding-top:4px;}
    .badge{font-family:'Nunito',sans-serif;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;border:1.5px solid;}
    .badge-high  {background:rgba(255,255,255,0.7);border-color:#e8a0aa;color:#9a2030;}
    .badge-medium{background:rgba(255,255,255,0.7);border-color:#80aae0;color:#1a4080;}
    .badge-low   {background:rgba(255,255,255,0.7);border-color:#60c890;color:#0a6030;}
    .due-date{font-family:'Nunito',sans-serif;font-size:11px;font-weight:600;color:#666;}
    .due-date.overdue{color:#c03030;font-weight:800;}

    .note-actions{display:flex;gap:5px;margin-top:6px;flex-wrap:wrap;align-items:center;}
    .act-btn{font-family:'Nunito',sans-serif;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;cursor:pointer;border:1.5px solid;background:rgba(255,255,255,0.6);transition:background .12s,color .12s;text-decoration:none;line-height:1.4;}
    /* checkmark button */
    .check-act{border-color:rgba(0,0,0,0.15);color:#444;font-size:15px;padding:2px 8px;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;}
    .check-act:hover{background:#4ea854;color:#fff;border-color:#4ea854;}
    .note-card.done .check-act{background:#4ea854;color:#fff;border-color:#4ea854;}
    .act-edit{border-color:rgba(0,0,0,0.1);color:#555;}
    .act-edit:hover{background:rgba(255,255,255,0.95);color:#2a2a2a;}
    .act-delete{border-color:rgba(200,60,60,0.3);color:#c03030;}
    .act-delete:hover{background:#e05050;color:#fff;border-color:#e05050;}
    .empty{grid-column:1/-1;text-align:center;padding:60px 20px;font-family:'Permanent Marker',cursive;font-size:20px;color:#c8b89a;}

    /* ── STICKER LAYER ── */
    .sticker-layer{position:fixed;inset:0;pointer-events:none;z-index:50;}
    .sticker{position:absolute;pointer-events:all;cursor:default;filter:drop-shadow(2px 3px 4px rgba(0,0,0,0.2));user-select:none;}
    .sticker img{display:block;border-radius:4px;}
    .sticker.editable{cursor:grab;}
    .sticker.editable:active{cursor:grabbing;}
    .sticker-controls{position:absolute;top:-28px;left:50%;transform:translateX(-50%);display:none;gap:4px;background:#2a2a2a;border-radius:20px;padding:2px 8px;white-space:nowrap;z-index:10;}
    .sticker.editable .sticker-controls{display:flex;}
    .sticker-btn{background:none;border:none;color:#fff;font-size:11px;cursor:pointer;padding:1px 5px;font-family:'Nunito',sans-serif;font-weight:700;border-radius:10px;transition:background .1s;}
    .sticker-btn:hover{background:rgba(255,255,255,0.2);}
    .sticker-btn.del{color:#ff8a8a;}
    .sticker-rotate-handle{position:absolute;bottom:-22px;left:50%;transform:translateX(-50%);width:20px;height:20px;border-radius:50%;background:#f9cf6a;border:2px solid #e8b84b;cursor:grab;display:none;align-items:center;justify-content:center;font-size:10px;color:#5a3a00;font-family:'Nunito',sans-serif;font-weight:800;line-height:1;}
    .sticker.editable .sticker-rotate-handle{display:flex;}

    /* edit mode toggle */
    .edit-mode-btn{position:fixed;bottom:32px;right:160px;background:rgba(255,253,245,0.9);border:1.5px solid #c8b89a;font-family:'Nunito',sans-serif;font-size:12px;font-weight:700;color:#666;padding:7px 14px;border-radius:20px;cursor:pointer;z-index:100;transition:background .12s,color .12s;}
    .edit-mode-btn.active{background:#2a2a2a;color:#f9cf6a;border-color:#2a2a2a;}

    /* ── DRAW PANEL ── */
    .draw-panel-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:300;align-items:center;justify-content:center;}
    .draw-panel-overlay.open{display:flex;}
    .draw-panel{background:rgba(255,253,245,0.97);border:2px solid #d8cbb8;border-radius:4px;padding:20px;width:min(500px,92vw);position:relative;box-shadow:4px 6px 0 rgba(0,0,0,0.12);}
    .draw-panel-tape{position:absolute;top:-13px;left:50%;transform:translateX(-50%);width:54px;height:22px;background:#f9cf6a;border:1.5px solid #e8b84b;border-radius:2px;opacity:0.9;}
    .draw-panel-title{font-family:'Permanent Marker',cursive;font-size:18px;margin-bottom:14px;color:#2a2a2a;}
    .draw-canvas{width:100%;height:260px;border-radius:3px;cursor:crosshair;background:#fff;border:1.5px solid #d8cbb8;touch-action:none;display:block;}
    .draw-toolbar{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:10px;}
    .dtool-btn{font-family:'Nunito',sans-serif;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;cursor:pointer;border:1.5px solid rgba(0,0,0,0.15);background:rgba(255,255,255,0.6);color:#555;transition:background .1s;}
    .dtool-btn:hover,.dtool-btn.sel{background:#2a2a2a;color:#fff;border-color:#2a2a2a;}
    .dcol-dot{width:16px;height:16px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:border-color .1s;flex-shrink:0;}
    .dcol-dot.sel{border-color:#2a2a2a;}
    .draw-panel-actions{display:flex;gap:8px;margin-top:12px;}
    .btn-make-sticker-main{font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;padding:6px 18px;border-radius:20px;cursor:pointer;border:1.5px solid #d4689a;background:rgba(212,104,154,0.1);color:#d4689a;transition:background .12s,color .12s;}
    .btn-make-sticker-main:hover{background:#d4689a;color:#fff;}
    .btn-clear-draw{font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;padding:6px 18px;border-radius:20px;cursor:pointer;border:1.5px solid #c8b89a;background:transparent;color:#888;transition:background .12s;}
    .btn-clear-draw:hover{background:#eee;}
    .btn-close-draw{font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;padding:6px 18px;border-radius:20px;cursor:pointer;border:1.5px solid #2a2a2a;background:#2a2a2a;color:#fff;margin-left:auto;}
    .btn-close-draw:hover{background:#444;}

    /* ── FABs ── */
    .fab-group{position:fixed;bottom:32px;right:80px;display:flex;gap:10px;z-index:100;align-items:center;}
    .fab{background:#f9cf6a;color:#5a3a00;border:2px solid #e8b84b;font-family:'Nunito',sans-serif;font-size:14px;font-weight:800;cursor:pointer;box-shadow:3px 4px 0 rgba(200,150,0,0.2);display:flex;align-items:center;gap:6px;padding:10px 26px 10px 20px;transition:background .12s,transform .12s;clip-path:polygon(10px 0%,calc(100% - 12px) 0%,100% 50%,calc(100% - 12px) 100%,10px 100%,0% 50%);}
    .fab:hover{background:#fce097;transform:scale(1.05) translateY(-2px);}
    .fab-draw{background:#d4689a;border-color:#c0508a;color:#fff;}
    .fab-draw:hover{background:#e078aa;}

    /* ── QUOTES STICKY ── */
    .quotes-sticky{position:fixed;bottom:32px;left:88px;width:200px;background:#fffde7;border:1.5px solid #f9cf6a;border-radius:3px;box-shadow:3px 5px 0 rgba(200,150,0,0.15);padding:0;z-index:40;}
    .quotes-sticky-header{background:#f9cf6a;padding:6px 10px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none;}
    .quotes-sticky-header span{font-family:'Permanent Marker',cursive;font-size:12px;color:#5a3a00;}
    .quotes-sticky-toggle{background:none;border:none;font-family:'Nunito',sans-serif;font-size:14px;color:#5a3a00;cursor:pointer;padding:0 2px;line-height:1;}
    .quotes-sticky-body{padding:12px 12px 14px;position:relative;overflow:hidden;min-height:80px;}
    .quotes-sticky.collapsed .quotes-sticky-body{display:none;}
    .quote-text{font-family:'Nunito',sans-serif;font-size:12px;font-weight:600;color:#5a3a00;line-height:1.6;font-style:italic;text-align:center;transition:opacity .4s ease,transform .4s ease;position:absolute;left:12px;right:12px;}
    .quote-text.fade-out{opacity:0;transform:translateY(-8px);}
    .quote-text.fade-in{opacity:0;transform:translateY(8px);}
    .quote-nav{display:flex;justify-content:center;gap:8px;margin-top:10px;position:relative;top:60px;}
    .q-btn{font-family:'Nunito',sans-serif;background:rgba(249,207,106,0.3);border:1.5px solid #e8b84b;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;color:#5a3a00;cursor:pointer;transition:background .1s;}
    .q-btn:hover{background:#f9cf6a;}
    .quotes-tape{position:absolute;top:-10px;left:50%;transform:translateX(-50%);width:48px;height:18px;background:rgba(249,207,106,0.7);border:1px solid rgba(220,180,50,0.5);border-radius:2px;}

    /* ── POPUPS ── */
    .popup-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;align-items:center;justify-content:center;}
    .popup-overlay.open{display:flex;}
    .popup{background:rgba(255,253,245,0.97);border:2px solid #d8cbb8;border-radius:3px;padding:28px 28px 22px;width:100%;max-width:420px;position:relative;box-shadow:4px 6px 0 rgba(0,0,0,0.12);background-image:repeating-linear-gradient(transparent,transparent 39px,rgba(200,180,160,0.12) 39px,rgba(200,180,160,0.12) 40px);}
    .popup-tape{position:absolute;top:-13px;left:50%;transform:translateX(-50%);width:54px;height:22px;background:#f9cf6a;border:1.5px solid #e8b84b;border-radius:2px;opacity:0.9;}
    .popup-title{font-family:'Permanent Marker',cursive;font-size:20px;margin-bottom:18px;color:#2a2a2a;}
    .f-group{margin-bottom:13px;}
    .f-label{font-family:'Nunito',sans-serif;font-size:12px;font-weight:700;color:#999;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;}
    .f-input,.f-select,.f-textarea{width:100%;background:transparent;border:none;border-bottom:2px solid #c8b89a;padding:7px 4px;font-size:15px;font-weight:500;color:#2a2a2a;font-family:'Nunito',sans-serif;outline:none;transition:border-color .15s;}
    .f-input:focus,.f-select:focus,.f-textarea:focus{border-color:#2a2a2a;}
    .f-select option{background:#faf8f3;color:#2a2a2a;}
    .f-textarea{resize:vertical;min-height:60px;border:1.5px solid #d8cbb8;padding:8px;border-radius:2px;background:rgba(255,255,255,0.6);}
    .f-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .popup-actions{display:flex;gap:10px;margin-top:16px;}
    .btn-primary{font-family:'Nunito',sans-serif;font-size:14px;font-weight:800;background:#f9cf6a;color:#5a3a00;border:2px solid #e8b84b;padding:7px 22px;cursor:pointer;border-radius:6px;transition:background .12s,color .12s;}
    .btn-primary:hover{background:#2a2a2a;color:#fff;border-color:#2a2a2a;}
    .btn-cancel{font-family:'Nunito',sans-serif;font-size:14px;font-weight:700;background:transparent;color:#888;border:2px solid #d8cbb8;padding:7px 18px;cursor:pointer;border-radius:6px;transition:border-color .12s,color .12s;text-decoration:none;display:inline-flex;align-items:center;}
    .btn-cancel:hover{border-color:#888;color:#555;}
    .popup.edit-mode{background:rgba(248,243,255,0.97);border-color:#c8b8d8;}
    input[type="date"]::-webkit-calendar-picker-indicator{filter:opacity(0.5);}

    /* logout popup */
    .logout-msg{font-family:'Permanent Marker',cursive;font-size:15px;color:#555;margin-bottom:20px;line-height:1.6;text-align:center;}
    .btn-danger{font-family:'Nunito',sans-serif;font-size:14px;font-weight:800;background:#e05050;color:#fff;border:2px solid #c03030;padding:7px 22px;cursor:pointer;border-radius:6px;transition:background .12s;}
    .btn-danger:hover{background:#c03030;}

    @media(max-width:700px){
      .notebook{flex-direction:column;}
      .spine{width:100%;height:44px;display:flex;flex-direction:row;align-items:center;justify-content:space-around;padding:0 16px;}
      .spine .hole{position:static;transform:none;margin:0;}
      .page{padding:16px 12px 100px 16px;}.page::before{display:none;}
      .tabs-sidebar{width:100%;height:auto;flex-direction:row;padding:8px 12px;justify-content:center;flex-wrap:wrap;gap:4px;order:3;}
      .tab-group{flex-direction:row;width:auto;gap:4px;}
      .b-tab{writing-mode:horizontal-tb;transform:none !important;width:auto !important;height:auto;padding:6px 14px;border-radius:20px;}
      .tab-connector{display:none;}
      .main-inner{flex-direction:column;padding-left:0;}
      .cal-wrap{width:100%;}
      .f-row{grid-template-columns:1fr;}
      .fab-group{bottom:80px;right:20px;}
      .edit-mode-btn{bottom:80px;right:20px;top:auto;}
      .quotes-sticky{left:16px;bottom:80px;}
      .ribbon-banner{display:none;}
    }
    @media(max-width:480px){.site-title{font-size:22px;}.notes-grid{grid-template-columns:1fr 1fr;}}
    @media(max-width:360px){.notes-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<?php if($showSplash): ?>
<div class="splash" id="splash">
  <img src="picturess/freakysonic.gif" alt="loading"/>
</div>
<script>
  setTimeout(function(){
    var s = document.getElementById('splash');
    s.classList.add('hide');
    setTimeout(function(){ s.remove(); }, 700);
  }, 2800);
</script>
<?php endif; ?>

<div class="sticker-layer" id="stickerLayer"></div>

<div class="notebook">
  <div class="spine">
    <div class="hole hole1"></div>
    <div class="hole hole2"></div>
    <div class="hole hole3"></div>
  </div>

  <div class="page">
    <div class="top-bar">
      <div style="display:flex;align-items:center;gap:0;">
        <div class="site-title"><span class="hl"><?= htmlspecialchars($user_name) ?>'s Tasks</span></div>
        <div class="ribbon-banner">stay organized</div>
      </div>
      <div class="user-info">
        <span class="user-name">lock in, <?= htmlspecialchars($user_name) ?></span>
        <button class="logout-btn" onclick="openLogoutPopup()">logout</button>
      </div>
    </div>

    <div class="search-wrap">
      <form method="GET" action="" style="display:flex;align-items:stretch;max-width:520px;">
        <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>"/>
        <input type="hidden" name="priority" value="<?= htmlspecialchars($priority) ?>"/>
        <div class="search-outer" style="flex:1;">
          <input class="search-input" type="text" name="search"
                 value="<?= htmlspecialchars($search) ?>"
                 placeholder="search your tasks..."
                 autocomplete="off"/>
          <button class="search-submit" type="submit">find</button>
        </div>
      </form>
    </div>

    <div class="main-inner">
      <div class="cal-wrap">
        <div class="cal-header"><span class="cal-header-text">calendar</span></div>
        <div class="cal-months" id="calMonths"></div>
        <div class="cal-days-header">
          <div class="cal-dh sun">S</div><div class="cal-dh">M</div><div class="cal-dh">T</div>
          <div class="cal-dh">W</div><div class="cal-dh">T</div><div class="cal-dh">F</div><div class="cal-dh">S</div>
        </div>
        <div class="cal-body" id="calBody"></div>
        <div class="cal-nav-row">
          <button class="cal-nav-sk" id="calPrev">&#8249;</button>
          <span class="cal-year-label" id="calYearLabel"></span>
          <button class="cal-nav-sk" id="calNext">&#8250;</button>
        </div>
      </div>

      <div class="content">
        <div class="notes-grid">
          <?php if (empty($tasks)): ?>
            <div class="empty">no tasks yet — hit + to add one</div>
          <?php else: ?>
            <?php foreach ($tasks as $t):
              $done    = (bool)$t['completed'];
              $pClass  = 'p-' . $t['priority'];
              $todayD  = date('Y-m-d');
              $dueDate = (!empty($t['due_date']) && $t['due_date'] !== '0000-00-00') ? $t['due_date'] : null;
              $overdue = $dueDate && $dueDate < $todayD && !$done;
            ?>
            <div class="note-card <?= $pClass ?> <?= $done?'done':'' ?>">
              <div class="tape"></div>
              <div class="note-title-wrap">
                <span class="note-title hl-<?= $t['priority'] ?>"><?= htmlspecialchars($t['title']) ?></span>
              </div>
              <?php if ($t['description']): ?>
                <div class="note-desc"><?= nl2br(htmlspecialchars($t['description'])) ?></div>
              <?php endif; ?>
              <div class="note-meta">
                <span class="badge badge-<?= $t['priority'] ?>"><?= $t['priority'] ?></span>
                <?php if ($dueDate): ?>
                  <span class="due-date <?= $overdue?'overdue':'' ?>">
                    <?= $overdue ? 'overdue · ' : '' ?><?= date('M j, Y', strtotime($dueDate)) ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="note-actions">
                <form action="tasks/toggle.php" method="POST" style="margin:0">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>"/>
                  <button class="act-btn check-act" type="submit" title="<?= $done ? 'Mark undone' : 'Mark done' ?>">&#10003;</button>
                </form>
                <a href="<?= qstr($filter,$priority,$search,'&edit='.$t['id']) ?>" class="act-btn act-edit">edit</a>
                <form action="tasks/delete.php" method="POST" style="margin:0" onsubmit="return confirm('delete this task?')">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>"/>
                  <button class="act-btn act-delete" type="submit">delete</button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <nav class="tabs-sidebar">
    <div class="tab-group">
      <a href="<?= qstr('all',$priority,$search) ?>"       class="b-tab st-all    <?= $filter==='all'      ?'active-status':'' ?>">all</a>
      <div class="tab-connector"></div>
      <a href="<?= qstr('active',$priority,$search) ?>"    class="b-tab st-active <?= $filter==='active'   ?'active-status':'' ?>">active</a>
      <div class="tab-connector"></div>
      <a href="<?= qstr('completed',$priority,$search) ?>" class="b-tab st-done   <?= $filter==='completed'?'active-status':'' ?>">done</a>
      <div class="tab-connector"></div>
      <a href="<?= qstr('overdue',$priority,$search) ?>"   class="b-tab st-overdue <?= $filter==='overdue' ?'active-status':'' ?>">overdue</a>
    </div>
    <div style="height:18px;width:100%;"></div>
    <div class="tab-group">
      <a href="<?= qstr($filter,'all',$search) ?>"    class="b-tab pri-all    <?= $priority==='all'   ?'active-priority':'' ?>">all</a>
      <div class="tab-connector"></div>
      <a href="<?= qstr($filter,'high',$search) ?>"   class="b-tab pri-high   <?= $priority==='high'  ?'active-priority':'' ?>">high</a>
      <div class="tab-connector"></div>
      <a href="<?= qstr($filter,'medium',$search) ?>" class="b-tab pri-medium <?= $priority==='medium'?'active-priority':'' ?>">mid</a>
      <div class="tab-connector"></div>
      <a href="<?= qstr($filter,'low',$search) ?>"    class="b-tab pri-low    <?= $priority==='low'   ?'active-priority':'' ?>">low</a>
    </div>
  </nav>
</div>

<!-- QUOTES STICKY -->
<div class="quotes-sticky" id="quoteSticky">
  <div class="quotes-tape"></div>
  <div class="quotes-sticky-header" onclick="toggleQuotes()">
    <span>daily dose</span>
    <button class="quotes-sticky-toggle" id="quotesToggleBtn">v</button>
  </div>
  <div class="quotes-sticky-body">
    <div class="quote-text" id="quoteText"></div>
    <div class="quote-nav">
      <button class="q-btn" onclick="prevQuote()">&larr;</button>
      <button class="q-btn" onclick="nextQuote()">&rarr;</button>
    </div>
  </div>
</div>

<!-- FABs -->
<div class="fab-group">
  <button class="fab fab-draw" onclick="openDrawPanel()" title="draw sticker">
    <span style="font-size:16px;">&#9998;</span> draw
  </button>
  <button class="fab" onclick="openPopup('add')" title="add task">
    <span style="font-size:20px;line-height:1;">+</span> add task
  </button>
</div>

<!-- Edit stickers toggle -->
<button class="edit-mode-btn" id="editModeBtn" onclick="toggleEditMode()">edit stickers</button>

<!-- DRAW PANEL -->
<div class="draw-panel-overlay" id="drawPanelOverlay">
  <div class="draw-panel">
    <div class="draw-panel-tape"></div>
    <div class="draw-panel-title">draw a sticker</div>
    <canvas class="draw-canvas" id="mainCanvas"></canvas>
    <div class="draw-toolbar">
      <button class="dtool-btn sel" id="dtool-pen" onclick="setDrawTool('pen',this)">pen</button>
      <button class="dtool-btn" id="dtool-eraser" onclick="setDrawTool('eraser',this)">eraser</button>
      <button class="dtool-btn" onclick="undoMainDraw()">undo</button>
      <button class="dtool-btn" onclick="redoMainDraw()">redo</button>
      <div class="dcol-dot sel" style="background:#333" onclick="setDrawColor(this,'#333')"></div>
      <div class="dcol-dot" style="background:#e05050" onclick="setDrawColor(this,'#e05050')"></div>
      <div class="dcol-dot" style="background:#4080e0" onclick="setDrawColor(this,'#4080e0')"></div>
      <div class="dcol-dot" style="background:#40a860" onclick="setDrawColor(this,'#40a860')"></div>
      <div class="dcol-dot" style="background:#e09020" onclick="setDrawColor(this,'#e09020')"></div>
      <div class="dcol-dot" style="background:#d4689a" onclick="setDrawColor(this,'#d4689a')"></div>
    </div>
    <div class="draw-panel-actions">
      <button class="btn-make-sticker-main" onclick="makeMainSticker()">make sticker</button>
      <button class="btn-clear-draw" onclick="clearMainCanvas()">clear</button>
      <button class="btn-close-draw" onclick="closeDrawPanel()">close</button>
    </div>
  </div>
</div>

<!-- ADD popup -->
<div class="popup-overlay" id="addPopup">
  <div class="popup">
    <div class="popup-tape"></div>
    <div class="popup-title">new task</div>
    <form action="tasks/add.php" method="POST">
      <div class="f-group">
        <label class="f-label">title</label>
        <input class="f-input" type="text" name="title" placeholder="what's on your mind?" required/>
      </div>
      <div class="f-group">
        <label class="f-label">description</label>
        <textarea class="f-textarea" name="description" placeholder="any details..."></textarea>
      </div>
      <div class="f-row">
        <div class="f-group">
          <label class="f-label">due date</label>
          <input class="f-input" type="date" name="due_date"/>
        </div>
        <div class="f-group">
          <label class="f-label">priority</label>
          <select class="f-select" name="priority">
            <option value="low">low</option>
            <option value="medium" selected>medium</option>
            <option value="high">high</option>
          </select>
        </div>
      </div>
      <div class="popup-actions">
        <button class="btn-primary" type="submit">add task</button>
        <button class="btn-cancel" type="button" onclick="closePopup('add')">cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT popup -->
<?php if ($editTask): ?>
<div class="popup-overlay open" id="editPopup">
  <div class="popup edit-mode">
    <div class="popup-tape"></div>
    <div class="popup-title">edit task</div>
    <form action="tasks/edit.php" method="POST">
      <input type="hidden" name="id"              value="<?= (int)$editTask['id'] ?>"/>
      <input type="hidden" name="filter"          value="<?= htmlspecialchars($filter) ?>"/>
      <input type="hidden" name="priority_filter" value="<?= htmlspecialchars($priority) ?>"/>
      <input type="hidden" name="search"          value="<?= htmlspecialchars($search) ?>"/>
      <div class="f-group">
        <label class="f-label">title</label>
        <input class="f-input" type="text" name="title" value="<?= htmlspecialchars($editTask['title']) ?>" required/>
      </div>
      <div class="f-group">
        <label class="f-label">description</label>
        <textarea class="f-textarea" name="description"><?= htmlspecialchars($editTask['description'] ?? '') ?></textarea>
      </div>
      <div class="f-row">
        <div class="f-group">
          <label class="f-label">due date</label>
          <input class="f-input" type="date" name="due_date" value="<?= htmlspecialchars($editTask['due_date'] ?? '') ?>"/>
        </div>
        <div class="f-group">
          <label class="f-label">priority</label>
          <select class="f-select" name="priority">
            <option value="low"    <?= ($editTask['priority']==='low'   ?'selected':'') ?>>low</option>
            <option value="medium" <?= ($editTask['priority']==='medium'?'selected':'') ?>>medium</option>
            <option value="high"   <?= ($editTask['priority']==='high'  ?'selected':'') ?>>high</option>
          </select>
        </div>
      </div>
      <div class="popup-actions">
        <button class="btn-primary" type="submit">save changes</button>
        <a href="<?= qstr($filter,$priority,$search) ?>" class="btn-cancel">cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- LOGOUT POPUP -->
<div class="popup-overlay" id="logoutPopup">
  <div class="popup">
    <div class="popup-tape"></div>
    <div class="popup-title">leaving so soon?</div>
    <div class="logout-msg" id="logoutMsg"></div>
    <div class="popup-actions">
      <form action="logout.php" method="POST" style="margin:0">
        <button class="btn-danger" type="submit">yeah, let me out</button>
      </form>
      <button class="btn-cancel" onclick="closePopup('logout')">wait, nevermind</button>
    </div>
  </div>
</div>

<script>
var STICKER_KEY = 'stickers_uid_<?= (int)$user_id ?>';
var editModeOn  = false;

/* ── LOGOUT POPUP ── */
var logoutMessages = [
  "You are now free from responsibilities. Temporarily.",
  "Tasks will still be here when you get back. Unfortunately.",
  "The deadline doesn't log out. But okay, you go ahead.",
  "Logging out won't finish the tasks. Just saying.",
  "Rest well. Your to-do list will haunt your dreams.",
  "Freedom unlocked. For now.",
  "Your tasks miss you already. Creepily.",
];
function openLogoutPopup(){
  document.getElementById('logoutMsg').textContent = logoutMessages[Math.floor(Math.random()*logoutMessages.length)];
  openPopup('logout');
}

/* ── POPUP HELPERS ── */
function openPopup(id){document.getElementById(id+'Popup').classList.add('open');}
function closePopup(id){document.getElementById(id+'Popup').classList.remove('open');}
document.querySelectorAll('.popup-overlay').forEach(function(el){
  el.addEventListener('click',function(e){if(e.target===el)el.classList.remove('open');});
});
document.querySelector('.search-input').addEventListener('keydown',function(e){
  if(e.key==='Enter')e.target.closest('form').submit();
});

/* ── CALENDAR ── */
var dueDates=<?= $dueDatesJson ?>;
var today=new Date();today.setHours(0,0,0,0);
var curYear=today.getFullYear(),curMonth=today.getMonth();
var monthNames=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
var monthFull=['January','February','March','April','May','June','July','August','September','October','November','December'];
function buildMonthRow(){
  var mc=document.getElementById('calMonths');mc.innerHTML='';
  monthNames.forEach(function(m,i){
    var el=document.createElement('div');el.className='cal-month-cell'+(i===curMonth?' active':'');
    el.textContent=m;el.addEventListener('click',function(){curMonth=i;renderCal();});mc.appendChild(el);
  });
}
function renderCal(){
  buildMonthRow();
  document.getElementById('calYearLabel').textContent=monthFull[curMonth]+' '+curYear;
  var body=document.getElementById('calBody');body.innerHTML='';
  var firstDay=new Date(curYear,curMonth,1).getDay();
  var dim=new Date(curYear,curMonth+1,0).getDate();
  var dip=new Date(curYear,curMonth,0).getDate();
  for(var i=firstDay-1;i>=0;i--){var el=document.createElement('div');el.className='cal-cell-sk other'+(firstDay-1-i===0?' sun-col':'');el.textContent=dip-i;body.appendChild(el);}
  for(var d=1;d<=dim;d++){
    var el=document.createElement('div');
    var iso=curYear+'-'+String(curMonth+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');
    var isTod=new Date(curYear,curMonth,d).getTime()===today.getTime();
    var hasTsk=dueDates.indexOf(iso)!==-1;
    var isSun=new Date(curYear,curMonth,d).getDay()===0;
    el.className='cal-cell-sk'+(isTod?' today-sk':'')+(hasTsk?' has-task-sk':'')+(isSun&&!isTod?' sun-col':'');
    el.textContent=d;body.appendChild(el);
  }
  var total=firstDay+dim,rem=total%7===0?0:7-(total%7);
  for(var n=1;n<=rem;n++){var el=document.createElement('div');el.className='cal-cell-sk other';el.textContent=n;body.appendChild(el);}
}
renderCal();
document.getElementById('calPrev').addEventListener('click',function(){curMonth--;if(curMonth<0){curMonth=11;curYear--;}renderCal();});
document.getElementById('calNext').addEventListener('click',function(){curMonth++;if(curMonth>11){curMonth=0;curYear++;}renderCal();});

/* ── STICKER SYSTEM ── */
var stickerCount=0;
var stickerData={};

function toggleEditMode(){
  editModeOn=!editModeOn;
  var btn=document.getElementById('editModeBtn');
  btn.textContent=editModeOn?'done editing':'edit stickers';
  btn.classList.toggle('active',editModeOn);
  document.querySelectorAll('.sticker').forEach(function(s){
    s.classList.toggle('editable',editModeOn);
  });
}

function saveStickers(){try{localStorage.setItem(STICKER_KEY,JSON.stringify(Object.values(stickerData)));}catch(e){}}

function loadStickers(){
  try{
    var raw=localStorage.getItem(STICKER_KEY);if(!raw)return;
    JSON.parse(raw).forEach(function(s){createStickerEl(s.src,s.x,s.y,s.rot||0,s.width||160,s.id);});
  }catch(e){}
}

function createStickerEl(src,x,y,rot,width,forceId){
  stickerCount++;
  var id=forceId||('sticker-'+stickerCount);
  stickerData[id]={id:id,src:src,x:x,y:y,rot:rot,width:width};
  var el=document.createElement('div');
  el.className='sticker'+(editModeOn?' editable':'');
  el.id=id;
  el.style.cssText='left:'+x+'px;top:'+y+'px;transform:rotate('+rot+'deg);';
  el.dataset.rot=rot;
  el.innerHTML=
    '<div class="sticker-controls">'+
      '<button class="sticker-btn" onclick="resizeSticker(\''+id+'\',1.2)">+</button>'+
      '<button class="sticker-btn" onclick="resizeSticker(\''+id+'\',0.8)">-</button>'+
      '<button class="sticker-btn del" onclick="discardSticker(\''+id+'\')">x</button>'+
    '</div>'+
    '<img src="'+src+'" style="width:'+width+'px;height:auto;" draggable="false"/>'+
    '<div class="sticker-rotate-handle">o</div>';
  document.getElementById('stickerLayer').appendChild(el);
  makeDraggable(el);
  makeRotatable(el);
  return el;
}

function discardSticker(id){
  var el=document.getElementById(id);if(!el)return;
  el.style.opacity='0';el.style.transition='opacity .2s';
  setTimeout(function(){el.remove();delete stickerData[id];saveStickers();},220);
}

function resizeSticker(id,factor){
  var el=document.getElementById(id);if(!el)return;
  var img=el.querySelector('img');if(!img)return;
  var nw=Math.max(60,Math.min(420,Math.round((parseInt(img.style.width)||160)*factor)));
  img.style.width=nw+'px';
  if(stickerData[id]){stickerData[id].width=nw;saveStickers();}
}

function makeDraggable(el){
  var ox=0,oy=0,sx=0,sy=0,active=false;
  function down(e){
    if(!editModeOn)return;
    if(e.target.closest('.sticker-controls')||e.target.classList.contains('sticker-rotate-handle'))return;
    active=true;var src=e.touches?e.touches[0]:e;
    sx=src.clientX;sy=src.clientY;ox=parseInt(el.style.left)||0;oy=parseInt(el.style.top)||0;
    el.style.zIndex=1000;e.preventDefault();
  }
  function move(e){
    if(!active)return;var src=e.touches?e.touches[0]:e;
    var nx=ox+(src.clientX-sx),ny=oy+(src.clientY-sy);
    el.style.left=nx+'px';el.style.top=ny+'px';
    if(stickerData[el.id]){stickerData[el.id].x=nx;stickerData[el.id].y=ny;}
    e.preventDefault();
  }
  function up(){if(!active)return;active=false;el.style.zIndex='';saveStickers();}
  el.addEventListener('mousedown',down);el.addEventListener('touchstart',down,{passive:false});
  document.addEventListener('mousemove',move);document.addEventListener('touchmove',move,{passive:false});
  document.addEventListener('mouseup',up);document.addEventListener('touchend',up);
}

function makeRotatable(el){
  var handle=el.querySelector('.sticker-rotate-handle');
  var rotating=false,cx=0,cy=0,startAngle=0;
  function getCenter(){var r=el.getBoundingClientRect();return{x:r.left+r.width/2,y:r.top+r.height/2};}
  function down(e){
    if(!editModeOn)return;
    e.stopPropagation();e.preventDefault();
    rotating=true;var c=getCenter();cx=c.x;cy=c.y;
    var src=e.touches?e.touches[0]:e;
    startAngle=Math.atan2(src.clientY-cy,src.clientX-cx)*(180/Math.PI)-(parseFloat(el.dataset.rot)||0);
    document.addEventListener('mousemove',move);document.addEventListener('touchmove',move,{passive:false});
    document.addEventListener('mouseup',up);document.addEventListener('touchend',up);
  }
  function move(e){
    if(!rotating)return;var src=e.touches?e.touches[0]:e;
    var a=Math.atan2(src.clientY-cy,src.clientX-cx)*(180/Math.PI)-startAngle;
    el.dataset.rot=a;el.style.transform='rotate('+a+'deg)';
    if(stickerData[el.id])stickerData[el.id].rot=a;
    e.preventDefault();
  }
  function up(){rotating=false;saveStickers();document.removeEventListener('mousemove',move);document.removeEventListener('touchmove',move);document.removeEventListener('mouseup',up);document.removeEventListener('touchend',up);}
  handle.addEventListener('mousedown',down);handle.addEventListener('touchstart',down,{passive:false});
}

loadStickers();

/* ── DRAW PANEL ── */
var mainCanvas, mainCtx, mainDrawing=false;
var mainTool='pen', mainColor='#333', mainHistory=[], mainFuture=[];

function openDrawPanel(){
  document.getElementById('drawPanelOverlay').classList.add('open');
  if(!mainCanvas){
    mainCanvas=document.getElementById('mainCanvas');
    mainCtx=mainCanvas.getContext('2d');
    mainCanvas.width=mainCanvas.offsetWidth||460;
    mainCanvas.height=260;
    saveMainSnapshot();
    bindMainCanvas();
  }
}
function closeDrawPanel(){ document.getElementById('drawPanelOverlay').classList.remove('open'); }

function saveMainSnapshot(){
  mainHistory.push(mainCanvas.toDataURL());mainFuture=[];
  if(mainHistory.length>40)mainHistory.shift();
}
function undoMainDraw(){
  if(mainHistory.length<=1)return;
  mainFuture.push(mainHistory.pop());
  var img=new Image();img.onload=function(){mainCtx.clearRect(0,0,mainCanvas.width,mainCanvas.height);mainCtx.drawImage(img,0,0);};
  img.src=mainHistory[mainHistory.length-1];
}
function redoMainDraw(){
  if(!mainFuture.length)return;
  var snap=mainFuture.pop();mainHistory.push(snap);
  var img=new Image();img.onload=function(){mainCtx.clearRect(0,0,mainCanvas.width,mainCanvas.height);mainCtx.drawImage(img,0,0);};
  img.src=snap;
}
function clearMainCanvas(){mainCtx.clearRect(0,0,mainCanvas.width,mainCanvas.height);saveMainSnapshot();}

function setDrawTool(tool,btn){
  mainTool=tool;
  document.querySelectorAll('.dtool-btn').forEach(function(b){b.classList.remove('sel');});
  btn.classList.add('sel');
}
function setDrawColor(dot,color){
  mainColor=color;mainTool='pen';
  document.querySelectorAll('.dcol-dot').forEach(function(d){d.classList.remove('sel');});
  dot.classList.add('sel');
  document.getElementById('dtool-pen').classList.add('sel');
  document.getElementById('dtool-eraser').classList.remove('sel');
}

function bindMainCanvas(){
  function getPos(e){var r=mainCanvas.getBoundingClientRect();var src=e.touches?e.touches[0]:e;return{x:(src.clientX-r.left)*(mainCanvas.width/r.width),y:(src.clientY-r.top)*(mainCanvas.height/r.height)};}
  function start(e){e.preventDefault();mainDrawing=true;var p=getPos(e);mainCtx.beginPath();mainCtx.moveTo(p.x,p.y);}
  function move(e){
    e.preventDefault();if(!mainDrawing)return;
    var p=getPos(e);
    mainCtx.lineWidth=mainTool==='eraser'?20:2.5;
    mainCtx.lineCap='round';mainCtx.lineJoin='round';
    mainCtx.globalCompositeOperation=mainTool==='eraser'?'destination-out':'source-over';
    mainCtx.strokeStyle=mainColor;
    mainCtx.lineTo(p.x,p.y);mainCtx.stroke();mainCtx.beginPath();mainCtx.moveTo(p.x,p.y);
  }
  function stop(){if(!mainDrawing)return;mainDrawing=false;mainCtx.globalCompositeOperation='source-over';saveMainSnapshot();}
  mainCanvas.addEventListener('mousedown',start);mainCanvas.addEventListener('mousemove',move);
  mainCanvas.addEventListener('mouseup',stop);mainCanvas.addEventListener('mouseleave',stop);
  mainCanvas.addEventListener('touchstart',start,{passive:false});mainCanvas.addEventListener('touchmove',move,{passive:false});
  mainCanvas.addEventListener('touchend',stop);
}

function makeMainSticker(){
  var blank=document.createElement('canvas');blank.width=mainCanvas.width;blank.height=mainCanvas.height;
  if(mainCanvas.toDataURL()===blank.toDataURL()){alert('Draw something first!');return;}
  var id='sticker-'+(++stickerCount);
  var x=Math.random()*Math.max(80,window.innerWidth-320)+40;
  var y=Math.random()*Math.max(80,window.innerHeight-320)+40;
  createStickerEl(mainCanvas.toDataURL(),x,y,0,200,id);
  saveStickers();
  clearMainCanvas();
  closeDrawPanel();
}

/* ── QUOTES ── */
var quotes=[
  "The voices demand productivity.",
  "Lock in before the consequences lock in first.",
  "Your to-do list is developing consciousness.",
  "Failure is cringe. Continue working.",
  "You have 24 hours and terrible decision-making.",
  "The task won't finish itself, coward.",
  "Every second you waste empowers the deadline.",
  "Productivity is stored in the panic.",
  "Do it scared. Do it confused. Just do it.",
  "You are one iced coffee away from greatness.",
  "The grind never asked for your opinion.",
  "If the task looks easy, you misunderstood it.",
  "A focused individual is a dangerous creature.",
  "We ball until the submission portal closes.",
  "You vs. one unfinished assignment. Fight.",
  "The workflow hungers.",
  "Your academic comeback starts every 3 business days.",
  "Multitasking? No. Simultaneous suffering.",
  "Complete the task before the task completes you."
];

// shuffle on load
quotes.sort(function(){return Math.random()-0.5;});
var qIdx=0;

function showQuote(newIdx){
  var el=document.getElementById('quoteText');
  el.classList.add('fade-out');
  setTimeout(function(){
    qIdx=(newIdx+quotes.length)%quotes.length;
    el.textContent=quotes[qIdx];
    el.classList.remove('fade-out');
    el.classList.add('fade-in');
    setTimeout(function(){el.classList.remove('fade-in');},50);
  },350);
}

function nextQuote(){showQuote(qIdx+1);}
function prevQuote(){showQuote(qIdx-1);}
function toggleQuotes(){
  var el=document.getElementById('quoteSticky');
  var btn=document.getElementById('quotesToggleBtn');
  el.classList.toggle('collapsed');
  btn.textContent=el.classList.contains('collapsed')?'^':'v';
}

document.getElementById('quoteText').textContent=quotes[0];
setInterval(function(){showQuote(qIdx+1);},30000);
</script>
</body>
</html>