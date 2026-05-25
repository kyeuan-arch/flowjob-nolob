<?php
session_start();

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
  require 'db.php';
  $token = $_COOKIE['remember_token'];
  $s = $pdo->prepare("SELECT u.id, u.name FROM users u
    JOIN remember_tokens t ON t.user_id = u.id
    WHERE t.token = ? AND t.expires_at > NOW()");
  $s->execute([$token]);
  $u = $s->fetch(PDO::FETCH_ASSOC);
  if ($u) {
    $_SESSION['user_id']   = $u['id'];
    $_SESSION['user_name'] = $u['name'];
    header("Location: dashboard.php");
    exit();
  } else {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
  }
}

if (isset($_SESSION['user_id'])) {
  header("Location: dashboard.php");
  exit();
}
$err = $_GET['err'] ?? '';
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/jpeg" href="picturess/logo.jpg"/>
  <title>Flow Job, No Lob</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    html,body{min-height:100vh;width:100%;}
    body{
      background:#faf8f2;
      display:flex;flex-direction:column;
      align-items:center;justify-content:center;
      font-family:'DM Sans',sans-serif;
      position:relative;overflow:hidden;
      padding:40px 20px;min-height:100vh;
    }
    .paper-lines{
      position:fixed;inset:0;pointer-events:none;z-index:0;
      background-image:repeating-linear-gradient(
        transparent,transparent 39px,#93b8d4 39px,#93b8d4 40px
      );
      opacity:0.75;
    }
    .margin-line{
      position:fixed;top:0;bottom:0;left:72px;
      width:1.5px;background:#e8a0a0;opacity:0.5;
      pointer-events:none;z-index:0;
    }
    .drawings-layer{position:fixed;inset:0;pointer-events:none;z-index:1;}
    .bg-drawing{position:absolute;mix-blend-mode:multiply;}
    .bg-drawing img{width:100%;height:auto;display:block;}
    .d-neilsen{ top:3vh;  left:1vw;  width:21vw; transform:rotate(-7deg);  }
    .d-cj     { top:3vh;  left:18vw; width:16vw; transform:rotate(0deg);   }
    .d-jhus   { top:47vh; left:2vw;  width:16vw; transform:rotate(11deg);  }
    .d-niggs  { top:52vh; left:15vw; width:9vw;  transform:rotate(11deg);  }
    .d-smack  { top:38vh; left:24vw; width:15vw; transform:rotate(-13deg); }
    .d-shrimp { top:40vh; left:62vw; width:14vw; transform:rotate(9deg);   }
    .d-lujille{ top:69vh; left:62vw; width:16vw; transform:rotate(-9deg);  }
    .d-trish  { top:9vh;  left:62vw; width:14vw; transform:rotate(0deg);   }
    .d-ahhh   { top:2vh;  left:78vw; width:17vw; transform:rotate(5deg);   }
    .d-steve  { top:40vh; left:82vw; width:20vw; transform:rotate(-6deg);  }
    .d-aniq   { top:57vh; left:79vw; width:16vw; transform:rotate(-8deg);  }
    .d-andrei { top:72vh; left:22vw; width:18vw; transform:rotate(-6deg);  }
    .d-dowe   { top:80vh; left:3vw;  width:18vw; transform:rotate(-10deg); }

    .header{text-align:center;margin-bottom:28px;position:relative;z-index:5;}
    .title{font-family:'Permanent Marker',cursive;font-size:52px;color:#1a1a1a;letter-spacing:1px;line-height:1;transform:rotate(-1.5deg);display:inline-block;}
    .title-underline{width:110%;height:3px;background:#1a1a1a;margin:-4px auto 0;transform:rotate(-0.5deg) skewX(-2deg);border-radius:2px;}
    .subtitle{font-family:'Permanent Marker',cursive;font-size:17px;color:#555;margin-top:10px;transform:rotate(0.8deg);display:inline-block;}

    .main{display:flex;justify-content:center;position:relative;z-index:5;width:100%;}
    .form-side{display:flex;flex-direction:column;align-items:center;}

    /* ── FLIP BOOK ── */
    .book{
      width:340px;
      perspective:1400px;
      position:relative;
    }
    .book-sizer{
      visibility:hidden;
      pointer-events:none;
      width:100%;
    }
    .page{
      position:absolute;
      top:0;left:0;width:100%;
      backface-visibility:hidden;
      -webkit-backface-visibility:hidden;
      transform-style:preserve-3d;
      transition:transform 0.7s cubic-bezier(0.77,0,0.18,1),
                 box-shadow 0.7s ease,
                 z-index 0s 0.35s;
      transform-origin:left center;
      border-radius:2px;
    }
    #loginPage   { transform:rotateY(0deg);   z-index:2; box-shadow:4px 6px 0 rgba(0,0,0,0.10); }
    #registerPage{ transform:rotateY(180deg);  z-index:1; box-shadow:none; }
    .book.flipped #loginPage   { transform:rotateY(-180deg); z-index:1; box-shadow:none; }
    .book.flipped #registerPage{ transform:rotateY(0deg);    z-index:2; box-shadow:4px 6px 0 rgba(0,0,0,0.10); }

    .form-box{
      background:rgba(255,255,255,0.93);
      border:2.5px solid #1a1a1a;
      padding:20px 28px 18px;
      width:100%;
      border-radius:2px;
      position:relative;
      background-image:repeating-linear-gradient(
        transparent,transparent 31px,rgba(147,184,212,0.2) 31px,rgba(147,184,212,0.2) 32px
      );
    }
    .tape{position:absolute;top:-14px;left:50%;transform:translateX(-50%);width:60px;height:22px;background:rgba(255,220,100,0.65);border:1px solid rgba(180,150,60,0.4);z-index:3;}
    .form-mode{font-family:'Permanent Marker',cursive;font-size:22px;color:#1a1a1a;text-align:center;margin-bottom:16px;}

    .alert{font-family:'Permanent Marker',cursive;font-size:14px;padding:8px 12px;border-radius:2px;margin-bottom:12px;text-align:center;border:2px solid;}
    .alert-err{background:#fff0f0;border-color:#e05050;color:#c03030;}
    .alert-ok {background:#f0fff0;border-color:#50a050;color:#207020;}

    .f-group{margin-bottom:10px;}
    .f-label{font-family:'Permanent Marker',cursive;font-size:15px;color:#333;margin-bottom:4px;display:block;}
    .f-input{width:100%;background:transparent;border:none;border-bottom:2px solid #1a1a1a;padding:6px 4px;font-size:16px;color:#1a1a1a;font-family:'DM Sans',sans-serif;outline:none;border-radius:0;transition:border-color 0.15s;}
    .f-input:focus{border-color:#e8a020;}
    .f-input::placeholder{color:#bbb;}

    .pw-wrap{position:relative;display:flex;align-items:center;}
    .pw-wrap .f-input{padding-right:52px;}
    .pw-toggle{
      position:absolute;right:0;
      background:rgba(249,207,106,0.2);
      border:1.5px solid #e8b84b;
      border-radius:4px;cursor:pointer;
      font-family:'Permanent Marker',cursive;
      font-size:9px;color:#5a3a00;
      padding:2px 6px;line-height:1.6;
      letter-spacing:0.5px;
      transition:background .12s,color .12s;white-space:nowrap;
    }
    .pw-toggle:hover{background:#f9cf6a;color:#3a2000;}

    .strength-wrap{margin-top:5px;}
    .strength-bar{display:flex;gap:3px;margin-bottom:3px;}
    .strength-seg{height:3px;flex:1;border-radius:2px;background:#e0e0e0;transition:background .2s;}
    .strength-label{font-family:'DM Sans',sans-serif;font-size:11px;color:#aaa;min-height:13px;}
    .strength-1 .strength-seg:nth-child(1){background:#e05050;}
    .strength-2 .strength-seg:nth-child(1),
    .strength-2 .strength-seg:nth-child(2){background:#e8a020;}
    .strength-3 .strength-seg:nth-child(1),
    .strength-3 .strength-seg:nth-child(2),
    .strength-3 .strength-seg:nth-child(3){background:#4ea854;}
    .strength-4 .strength-seg{background:#207020;}

    .req-list{list-style:none;margin:5px 0 8px;padding:0;display:flex;flex-direction:column;gap:2px;}
    .req-list li{font-family:'DM Sans',sans-serif;font-size:11px;color:#bbb;display:flex;align-items:center;gap:5px;transition:color .15s;}
    .req-list li::before{content:'○';font-size:9px;flex-shrink:0;}
    .req-list li.met{color:#207020;}
    .req-list li.met::before{content:'●';}

    .confirm-msg{font-family:'DM Sans',sans-serif;font-size:11px;margin-top:3px;min-height:14px;}
    .confirm-msg.match{color:#207020;}
    .confirm-msg.nomatch{color:#c03030;}

    .remember-row{display:flex;align-items:center;gap:8px;margin:8px 0 4px;}
    .remember-row input[type="checkbox"]{
      appearance:none;-webkit-appearance:none;
      width:15px;height:15px;border:2px solid #1a1a1a;border-radius:2px;
      background:transparent;cursor:pointer;position:relative;flex-shrink:0;
      transition:background .12s;
    }
    .remember-row input[type="checkbox"]:checked{background:#1a1a1a;}
    .remember-row input[type="checkbox"]:checked::after{
      content:'✓';position:absolute;top:-1px;left:1px;
      font-size:10px;color:#faf8f2;font-weight:bold;font-family:sans-serif;
    }
    .remember-row label{font-family:'Permanent Marker',cursive;font-size:12px;color:#555;cursor:pointer;user-select:none;}

    .submit-btn{margin-top:8px;width:100%;background:#1a1a1a;color:#faf8f2;border:2.5px solid #1a1a1a;padding:7px;font-family:'Permanent Marker',cursive;font-size:16px;cursor:pointer;letter-spacing:0.06em;transition:background 0.12s,color 0.12s;border-radius:2px;}
    .submit-btn:hover{background:#ffd166;color:#1a1a1a;}
    .submit-btn:disabled{background:#ccc;border-color:#ccc;color:#fff;cursor:not-allowed;}
    .toggle-row{font-family:'Permanent Marker',cursive;font-size:14px;text-align:center;color:#555;margin-top:14px;line-height:1.8;}
    .toggle-row span{color:#c05000;cursor:pointer;text-decoration:underline;text-underline-offset:2px;}

    @media(max-width:720px){
      .book{width:90vw;}
      .d-smack,.d-shrimp,.d-andrei,.d-cj,.d-steve{display:none;}
      .d-neilsen{width:32vw;}.d-jhus{width:28vw;}
      .d-ahhh{width:20vw;}.d-aniq{width:20vw;}
    }
  </style>
</head>
<body>
  <div class="paper-lines"></div>
  <div class="margin-line"></div>

  <div class="drawings-layer">
    <div class="bg-drawing d-neilsen"><img src="picturess/neilsen.png" alt=""/></div>
    <div class="bg-drawing d-cj"     ><img src="picturess/cj.png"      alt=""/></div>
    <div class="bg-drawing d-jhus"   ><img src="picturess/jhus.png"    alt=""/></div>
    <div class="bg-drawing d-niggs"  ><img src="picturess/niggs.png"   alt=""/></div>
    <div class="bg-drawing d-smack"  ><img src="picturess/smack.png"   alt=""/></div>
    <div class="bg-drawing d-shrimp" ><img src="picturess/shrimp.png"  alt=""/></div>
    <div class="bg-drawing d-lujille"><img src="picturess/lujille.png" alt=""/></div>
    <div class="bg-drawing d-trish"  ><img src="picturess/trish.png"   alt=""/></div>
    <div class="bg-drawing d-ahhh"   ><img src="picturess/ahhh.png"    alt=""/></div>
    <div class="bg-drawing d-steve"  ><img src="picturess/steve.png"   alt=""/></div>
    <div class="bg-drawing d-aniq"   ><img src="picturess/aniq.png"    alt=""/></div>
    <div class="bg-drawing d-andrei" ><img src="picturess/andrei.png"  alt=""/></div>
    <div class="bg-drawing d-dowe"   ><img src="picturess/dowe.png"    alt=""/></div>
  </div>

  <div class="header">
    <div class="title">FLOW JOB, NO LOB</div>
    <div class="title-underline"></div>
    <div class="subtitle">The workflow is flowing.</div>
  </div>

  <div class="main">
    <div class="form-side">
      <div class="book" id="book">

        <!-- sizer: tallest form (register) sets the book height -->
        <div class="book-sizer form-box">
          <div class="form-mode">join to touch tasks!</div>
          <div class="f-group"><label class="f-label">name</label><input class="f-input" type="text" disabled/></div>
          <div class="f-group"><label class="f-label">email</label><input class="f-input" type="email" disabled/></div>
          <div class="f-group">
            <label class="f-label">password</label>
            <div class="pw-wrap"><input class="f-input" type="password" disabled/></div>
            <div class="strength-wrap">
              <div class="strength-bar"><div class="strength-seg"></div><div class="strength-seg"></div><div class="strength-seg"></div><div class="strength-seg"></div></div>
              <div class="strength-label">&nbsp;</div>
            </div>
            <ul class="req-list">
              <li>at least 8 characters</li>
              <li>one uppercase letter</li>
              <li>one number</li>
              <li>one special character (!@#$...)</li>
            </ul>
          </div>
          <div class="f-group">
            <label class="f-label">confirm password</label>
            <div class="pw-wrap"><input class="f-input" type="password" disabled/></div>
            <div class="confirm-msg">&nbsp;</div>
          </div>
          <button class="submit-btn" disabled>create account</button>
          <div class="toggle-row">already in? <span>sign in</span></div>
        </div>

        <!-- LOGIN PAGE -->
        <div class="page" id="loginPage">
          <div class="form-box">
            <div class="tape"></div>
            <?php if($err === 'invalid'): ?>
              <div class="alert alert-err">wrong email or password!</div>
            <?php elseif($msg === 'registered'): ?>
              <div class="alert alert-ok">account created! sign in now :)</div>
            <?php endif; ?>
            <div class="form-mode">welcome back, task gremlin :)</div>
            <form action="auth/login.php" method="POST">
              <div class="f-group">
                <label class="f-label">email</label>
                <input class="f-input" type="email" name="email" placeholder="you@flowjob.nolob" required/>
              </div>
              <div class="f-group">
                <label class="f-label">password</label>
                <div class="pw-wrap">
                  <input class="f-input" type="password" name="password" id="loginPw" placeholder="shh... its a secret" required/>
                  <button type="button" class="pw-toggle" onclick="togglePw('loginPw',this)">show</button>
                </div>
              </div>
              <div class="remember-row">
                <input type="checkbox" name="remember" id="rememberMe" value="1"/>
                <label for="rememberMe">keep me logged in (30 days)</label>
              </div>
              <button class="submit-btn" type="submit">let me in</button>
            </form>
            <div class="toggle-row">
              no account? <span onclick="flipToRegister()">register here</span>
            </div>
          </div>
        </div>

        <!-- REGISTER PAGE -->
        <div class="page" id="registerPage">
          <div class="form-box">
            <div class="tape"></div>
            <?php if($err === 'exists'): ?>
              <div class="alert alert-err">email already registered!</div>
            <?php elseif($err === 'weak'): ?>
              <div class="alert alert-err">password is too weak!</div>
            <?php endif; ?>
            <div class="form-mode">join to touch tasks!</div>
            <form action="auth/register.php" method="POST" id="regForm">
              <div class="f-group">
                <label class="f-label">name</label>
                <input class="f-input" type="text" name="name" placeholder="what do we call you?" required/>
              </div>
              <div class="f-group">
                <label class="f-label">email</label>
                <input class="f-input" type="email" name="email" placeholder="you@flowjob.nolob" required/>
              </div>
              <div class="f-group">
                <label class="f-label">password</label>
                <div class="pw-wrap">
                  <input class="f-input" type="password" name="password" id="regPw" placeholder="make it strong!" required oninput="checkStrength(this.value)"/>
                  <button type="button" class="pw-toggle" onclick="togglePw('regPw',this)">show</button>
                </div>
                <div class="strength-wrap">
                  <div class="strength-bar" id="strengthBar">
                    <div class="strength-seg"></div><div class="strength-seg"></div>
                    <div class="strength-seg"></div><div class="strength-seg"></div>
                  </div>
                  <div class="strength-label" id="strengthLabel"></div>
                </div>
                <ul class="req-list">
                  <li id="req-len">at least 8 characters</li>
                  <li id="req-upper">one uppercase letter</li>
                  <li id="req-num">one number</li>
                  <li id="req-special">one special character (!@#$...)</li>
                </ul>
              </div>
              <div class="f-group">
                <label class="f-label">confirm password</label>
                <div class="pw-wrap">
                  <input class="f-input" type="password" id="confirmPw" placeholder="type it again" required oninput="checkConfirm()"/>
                  <button type="button" class="pw-toggle" onclick="togglePw('confirmPw',this)">show</button>
                </div>
                <div class="confirm-msg" id="confirmMsg"></div>
              </div>
              <button class="submit-btn" type="submit" id="regSubmit" disabled>create account</button>
            </form>
            <div class="toggle-row">
              already in? <span onclick="flipToLogin()">sign in</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script>
    var book = document.getElementById('book');

    function flipToRegister(){ book.classList.add('flipped'); }
    function flipToLogin(){ book.classList.remove('flipped'); }

    <?php if($err === 'exists' || $err === 'weak'): ?>
      book.classList.add('flipped');
    <?php endif; ?>

    function togglePw(id, btn){
      var input = document.getElementById(id);
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.textContent = input.type === 'password' ? 'show' : 'hide';
    }

    var reqs = { len:false, upper:false, num:false, special:false };

    function checkStrength(val){
      reqs.len     = val.length >= 8;
      reqs.upper   = /[A-Z]/.test(val);
      reqs.num     = /[0-9]/.test(val);
      reqs.special = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(val);
      document.getElementById('req-len').classList.toggle('met', reqs.len);
      document.getElementById('req-upper').classList.toggle('met', reqs.upper);
      document.getElementById('req-num').classList.toggle('met', reqs.num);
      document.getElementById('req-special').classList.toggle('met', reqs.special);
      var score = Object.values(reqs).filter(Boolean).length;
      document.getElementById('strengthBar').className = 'strength-bar strength-' + score;
      var labels = ['','weak','fair','good','strong!'];
      document.getElementById('strengthLabel').textContent = val.length ? labels[score] : '';
      checkConfirm();
      updateSubmit();
    }

    function checkConfirm(){
      var pw  = document.getElementById('regPw').value;
      var cpw = document.getElementById('confirmPw').value;
      var msg = document.getElementById('confirmMsg');
      if(!cpw){ msg.textContent=''; msg.className='confirm-msg'; return; }
      if(pw === cpw){ msg.textContent='passwords match';         msg.className='confirm-msg match'; }
      else           { msg.textContent='passwords do not match'; msg.className='confirm-msg nomatch'; }
      updateSubmit();
    }

    function updateSubmit(){
      var pw     = document.getElementById('regPw').value;
      var cpw    = document.getElementById('confirmPw').value;
      var allMet = Object.values(reqs).every(Boolean);
      document.getElementById('regSubmit').disabled = !(allMet && pw === cpw && cpw.length > 0);
    }

    document.getElementById('regForm').addEventListener('submit', function(e){
      var allMet = Object.values(reqs).every(Boolean);
      var pw  = document.getElementById('regPw').value;
      var cpw = document.getElementById('confirmPw').value;
      if(!allMet || pw !== cpw) e.preventDefault();
    });
  </script>
</body>
</html>