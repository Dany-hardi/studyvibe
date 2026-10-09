<?php
declare(strict_types=1);

/** Control center. Not linked from anywhere. Anyone but the owner gets the normal 404 page. */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/ControlGate.php';
require_once __DIR__ . '/../lib/ControlCommands.php';

$user = ControlGate::enter();
$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$v = (string)@filemtime(__DIR__ . '/ops.js');
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<title>Control center</title>
<?= Brand::headLinks() ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..600;1,9..144,400&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="ops.css?v=<?= $v ?>">
</head>
<body class="ops">
<header class="ops-top">
  <div class="ops-brand"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 7v5c0 4.5 3.2 7.7 8 9 4.8-1.3 8-4.5 8-9V7z"/><path d="M9 12l2 2 4-4"/></svg><b>Control center</b><span class="ops-pill">private</span></div>
  <nav class="ops-tabs" role="tablist" aria-label="Sections">
    <button role="tab" data-tab="ov" aria-selected="true">Overview</button>
    <button role="tab" data-tab="sim" aria-selected="false">Simulator</button>
    <button role="tab" data-tab="cmd" aria-selected="false">Commands</button>
    <button role="tab" data-tab="cal" aria-selected="false">Calibration</button>
  </nav>
  <div class="ops-who"><span><?= $h($user['name']) ?></span><span class="ops-clock" id="clock"></span></div>
</header>

<main class="ops-main">
  <!-- ═════════ OVERVIEW ═════════ -->
  <section id="tab-ov" class="ops-tab">
    <div class="ops-hero">
      <div><p class="k">Read from the running installation</p><h1>The whole platform at a glance</h1><p class="lede">Every value below is read from the live server, the database and the source files. Tags tell where it comes from: <span class="src read">read</span> from a configuration file or the system, <span class="src measured">measured</span> by a timed probe, <span class="src assumed">assumed</span> when it cannot be read.</p></div>
      <div class="ops-hero-act"><button class="btn primary" id="go-sim">Open the simulator</button><button class="btn" id="reload-state">Refresh</button></div>
    </div>
    <div id="ov" class="cards"><div class="skel"></div><div class="skel"></div><div class="skel"></div></div>
  </section>

  <!-- ═════════ SIMULATOR ═════════ -->
  <section id="tab-sim" class="ops-tab" hidden>
    <div class="sim-grid">
      <aside class="panel sim-controls" aria-label="Scenario">
        <h2>Scenario</h2>
        <label class="f"><span>What happens</span>
          <select class="cfg" data-cfg="scenario" id="c-scenario">
            <option value="live_exam">A live evaluation: everyone joins, answers, finishes</option>
            <option value="signup_wave">A sign-up wave: many people create an account</option>
          </select></label>
        <label class="f"><span>People at the same time <b id="c-users-v"></b></span>
          <input type="range" id="c-users-r" min="0" max="100" step="1"><input class="cfg num" type="number" min="1" max="20000" data-cfg="users" id="c-users"></label>
        <div class="f2" data-for="live_exam">
          <label class="f"><span>Questions</span><input class="cfg num" type="number" min="1" max="100" data-cfg="exam.questions"></label>
          <label class="f"><span>Seconds each</span><input class="cfg num" type="number" min="3" max="300" data-cfg="exam.secondsPerQuestion"></label>
          <label class="f"><span>Arrival window (s)</span><input class="cfg num" type="number" min="1" max="600" data-cfg="exam.joinWindowS"></label>
        </div>
        <div class="f2" data-for="signup_wave" hidden>
          <label class="f"><span>Arrival window (s)</span><input class="cfg num" type="number" min="1" max="1200" data-cfg="signup.windowS"></label>
        </div>
        <label class="f"><span>Their connection</span>
          <select id="c-net"><option value="25">Good Wi-Fi (25 ms)</option><option value="80" selected>Mobile 4G (80 ms)</option><option value="250">Weak 3G (250 ms)</option></select></label>

        <details open><summary>Servers <small>starts from what was detected</small></summary>
          <label class="f chk"><input type="checkbox" class="cfg" data-cfg="infra.keepAlive"><span>Apache keep-alive <i class="src" data-src="infra.keepAlive"></i></span></label>
          <div class="f2">
            <label class="f"><span>Keep-alive seconds <i class="src" data-src="infra.keepAliveTimeoutS"></i></span><input class="cfg num" type="number" min="0" max="60" data-cfg="infra.keepAliveTimeoutS"></label>
            <label class="f"><span>Apache workers <i class="src" data-src="infra.workers"></i></span><input class="cfg num" type="number" min="5" max="5000" data-cfg="infra.workers"></label>
            <label class="f"><span>CPU cores <i class="src" data-src="infra.cores"></i></span><input class="cfg num" type="number" min="1" max="256" data-cfg="infra.cores"></label>
            <label class="f"><span>Memory (MB) <i class="src" data-src="infra.ramMB"></i></span><input class="cfg num" type="number" min="512" max="1048576" data-cfg="infra.ramMB"></label>
            <label class="f"><span>DB connections <i class="src" data-src="infra.dbMaxConnections"></i></span><input class="cfg num" type="number" min="10" max="10000" data-cfg="infra.dbMaxConnections"></label>
            <label class="f"><span>DB write flush <i class="src" data-src="infra.flushLog"></i></span><select class="cfg" data-cfg="infra.flushLog" data-int><option value="1">At every commit (1)</option><option value="2">Every second (2)</option></select></label>
          </div>
          <label class="f chk"><input type="checkbox" class="cfg" data-cfg="infra.opcache"><span>PHP opcache <i class="src" data-src="infra.opcache"></i></span></label>
          <label class="f chk"><input type="checkbox" class="cfg" data-cfg="infra.dbPersistent"><span>Persistent DB connections <i class="src" data-src="infra.dbPersistent"></i></span></label>
          <label class="f chk"><input type="checkbox" class="cfg" data-cfg="infra.dbOnSameHost"><span>Database on the same machine <i class="src" data-src="infra.dbOnSameHost"></i></span></label>
        </details>
        <details><summary>Code and email <small>what the code does today</small></summary>
          <label class="f"><span>Request paths <i class="src" data-src="code.profile"></i></span><select class="cfg" data-cfg="code.profile"><option value="optimized">Optimized</option><option value="legacy">Original</option></select></label>
          <label class="f chk"><input type="checkbox" class="cfg" data-cfg="code.mailQueued"><span>Results emails sent in the background</span></label>
          <label class="f chk"><input type="checkbox" class="cfg" data-cfg="code.signupMailQueued"><span>Sign-up emails sent in the background</span></label>
          <div class="f2">
            <label class="f"><span>One email takes (ms)</span><input class="cfg num" type="number" min="50" max="20000" data-cfg="mail.smtpMs"></label>
            <label class="f"><span>Mail senders</span><input class="cfg num" type="number" min="1" max="64" data-cfg="mail.workers"></label>
            <label class="f"><span>Daily email limit</span><input class="cfg num" type="number" min="10" max="1000000" data-cfg="mail.dailyQuota"></label>
          </div>
          <label class="f chk"><input type="checkbox" class="cfg" data-cfg="mail.down"><span>Simulate: the mail server is down</span></label>
        </details>
        <div class="sim-act"><button class="btn primary big" id="b-run">Run the simulation</button><div class="row"><button class="btn" id="b-sweep">Find the breaking point</button><button class="btn ghost" id="b-reset">Reset to detected</button></div></div>
        <p class="note" id="speed-note"></p>
      </aside>

      <div class="sim-main">
        <div class="stage-wrap"><canvas id="stage" aria-label="Animated diagram of the servers"></canvas><div class="busy" id="busy" hidden><div class="spin"></div><p id="busy-t">Simulating…</p></div></div>
        <div class="transport">
          <button class="btn icon" id="b-play" aria-label="Play or pause">▶</button>
          <input type="range" id="scrub" min="0" max="1000" value="0" aria-label="Position in the run">
          <span id="tlabel" class="mono">0:00 / 0:00</span>
          <select id="speed" aria-label="Speed"><option value="0.5">0.5×</option><option value="1">1×</option><option value="2" selected>2×</option><option value="5">5×</option><option value="10">10×</option><option value="20">20×</option></select>
        </div>
        <div class="charts"><canvas id="ch1"></canvas><canvas id="ch2"></canvas><canvas id="ch3"></canvas></div>
        <div class="panel"><h2>What happened, step by step</h2><ol id="steps" class="steps"><li class="dim">Run a simulation to see the story of the run.</li></ol></div>
        <div class="panel" id="sweep-panel" hidden><h2>Breaking point</h2><p class="note" id="sweep-note"></p><div id="sweep"></div></div>
      </div>

      <aside class="sim-results" aria-label="Results">
        <div class="panel" id="verdict"><h2>Verdict</h2><p class="dim">Nothing simulated yet.</p></div>
        <div class="panel"><h2>Key figures</h2><div id="metrics" class="metrics"></div></div>
        <div class="panel"><h2>Why, and what to change</h2><div id="findings"></div></div>
        <div class="panel"><h2>Runs compared</h2><div id="runs"></div></div>
      </aside>
    </div>
  </section>

  <!-- ═════════ COMMANDS ═════════ -->
  <section id="tab-cmd" class="ops-tab" hidden>
    <div class="ops-hero"><div><p class="k">Powerful, and logged</p><h1>Commands</h1><p class="lede">Each run is written to the audit log with your name. High-risk commands ask you to type their keyword.</p></div></div>
    <div class="cmd-grid"><div id="cmds" class="cmds"></div><div class="panel cmd-log"><h2>Output</h2><div id="cmd-out"><p class="dim">The result of the last command shows here.</p></div></div></div>
  </section>

  <!-- ═════════ CALIBRATION ═════════ -->
  <section id="tab-cal" class="ops-tab" hidden>
    <div class="ops-hero"><div><p class="k">So the simulation is about this server</p><h1>Calibration and model check</h1><p class="lede">A timed probe measures how fast this server is right now (PHP, database, web round trips) and scales the simulator to it. Below it, the model is replayed against real load tests to show how far it can be trusted.</p></div><div class="ops-hero-act"><button class="btn primary" id="b-cal">Measure this server</button></div></div>
    <div class="cal-grid"><div class="panel"><h2>Measured now</h2><div id="cal-out"><p class="dim">Press “Measure this server”. It takes a few seconds and writes one tiny scratch table (ops_probe).</p></div></div>
    <div class="panel"><h2>Model check against real runs</h2><p class="note">Each row replays a load test I ran for real (see docs/PERFORMANCE.md) and compares what the model predicts with what was measured.</p><button class="btn" id="b-val">Run the check</button><div id="val-out"></div></div></div>
  </section>
</main>

<div class="modal" id="modal" hidden><div class="modal-in"><h3 id="m-t"></h3><p id="m-p"></p><input id="m-in" class="num" type="text" autocomplete="off" hidden><div class="row"><button class="btn" id="m-no">Cancel</button><button class="btn primary" id="m-yes">Run</button></div></div></div>

<script>window.OPS = <?= json_encode(['token' => ControlGate::token(), 'commands' => ControlCommands::catalog()], JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="sim.js?v=<?= $v ?>"></script>
<script src="viz.js?v=<?= $v ?>"></script>
<script src="ops.js?v=<?= $v ?>"></script>
</body>
</html>
