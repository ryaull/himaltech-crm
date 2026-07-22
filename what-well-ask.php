<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Purely informational, read-only preview of what the real "Start Your
// Project" form asks — no submission, no database writes. Exists so
// someone can see what's coming before committing to filling it out.
// Keep these lists in sync with start-your-project.php if that form
// ever changes.

$GOALS = ['More enquiries','Sell online','Better branding','Replace an old website','Improve Google rankings','Launch a new business','Other'];
$CATEGORIES = ['Website Design','Website Redesign','E-commerce','SEO','Digital Marketing','Branding','Hosting','Maintenance','Not sure'];
$ADDONS = ['Logo Design','Graphic Design','Business Cards','Flyer Design','Social Media Setup','Monthly Website Maintenance','Website Security','Website Backups','Website Training'];
$REFERRALS = ['Google','Facebook','LinkedIn','Referral','Existing Client','Other'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>What We'll Ask - Himal Tech</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap');
* { box-sizing:border-box; }
body { margin:0; font-family:'IBM Plex Sans',Arial,sans-serif; background:#faf9fc; color:#14121a; }
h1,h2,h3 { font-family:'Sora',sans-serif; font-weight:700; letter-spacing:-0.01em; margin:0; }
:root { --purple:#9400ff; --purple-dark:#7400cc; --purple-tint:#f4e9ff; --ink:#14121a; --ink-muted:#6b6478; --border:#e5e1ea; }

.topbar { background:#fff; border-bottom:1px solid var(--border); padding:14px 24px; display:flex; align-items:center; justify-content:space-between; }
.topbar .brand { display:flex; align-items:center; gap:9px; font-family:'Sora',sans-serif; font-weight:700; font-size:14px; }
.topbar .mark { width:26px;height:26px;border-radius:7px;background:var(--purple);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px; }

.wrap { max-width:800px; margin:0 auto; padding:40px 24px 80px; }
.header { text-align:center; margin-bottom:36px; }
.header h1 { font-size:28px; margin-bottom:10px; }
.header p { color:var(--ink-muted); font-size:14.5px; max-width:520px; margin:0 auto; line-height:1.6; }
.header .time { display:inline-block; background:var(--purple-tint); color:var(--purple-dark); font-size:12px; font-weight:600; padding:5px 14px; border-radius:20px; margin-top:14px; }

.always-badge { display:inline-block; background:#eaecfc; color:#4c5fe0; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:3px 9px; border-radius:5px; margin-left:8px; vertical-align:middle; }
.cond-badge { display:inline-block; background:var(--purple-tint); color:var(--purple-dark); font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:3px 9px; border-radius:5px; margin-left:8px; vertical-align:middle; }

.section { background:#fff; border:1px solid var(--border); border-radius:14px; padding:24px 26px; margin-bottom:14px; }
.section h2 { font-size:16.5px; margin-bottom:4px; }
.section .note { font-size:12.5px; color:var(--ink-muted); margin-bottom:14px; }
.qlist { margin:0; padding:0; list-style:none; }
.qlist li { font-size:13.5px; padding:7px 0; border-bottom:1px solid var(--border); }
.qlist li:last-child { border-bottom:none; }
.taglist { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.tag { background:#faf9fc; border:1px solid var(--border); font-size:12px; padding:4px 10px; border-radius:20px; color:var(--ink); }

.divider { text-align:center; margin:34px 0 20px; }
.divider span { background:var(--bg,#faf9fc); color:var(--ink-muted); font-size:11.5px; text-transform:uppercase; letter-spacing:.06em; padding:0 12px; position:relative; }
.divider::before { content:''; display:block; height:1px; background:var(--border); margin:12px 0 -24px; }

.cta { text-align:center; margin-top:40px; }
.cta a { display:inline-block; background:var(--purple); color:#fff; text-decoration:none; font-weight:600; font-size:14.5px; padding:14px 34px; border-radius:8px; }
.cta a:hover { background:var(--purple-dark); }
.cta p { color:var(--ink-muted); font-size:12.5px; margin-top:10px; }
</style>
</head>
<body>

<div class="topbar">
  <div class="brand"><span class="mark">HT</span> Himal Tech</div>
  <a href="<?= APP_URL ?>/login.php" style="font-size:12.5px;color:var(--purple-dark);text-decoration:none;font-weight:600">Already a client or staff member? Log in &rarr;</a>
</div>

<div class="wrap">
  <div class="header">
    <h1>What we'll ask you</h1>
    <p>Here's every question in our project discovery form, so you know what to expect before you start. You'll never see everything below in one go — some sections only appear based on what you actually pick.</p>
    <span class="time">Takes about 10&ndash;15 minutes</span>
  </div>

  <div class="section">
    <h2>About you <span class="always-badge">Always asked</span></h2>
    <p class="note">Your name, phone, email, company, existing website (if any), and business address.</p>
  </div>

  <div class="section">
    <h2>What are you trying to achieve? <span class="always-badge">Always asked</span></h2>
    <p class="note">We ask this before anything technical, because it matters more to us than the details.</p>
    <div class="taglist"><?php foreach ($GOALS as $g): ?><span class="tag"><?= htmlspecialchars($g) ?></span><?php endforeach; ?></div>
  </div>

  <div class="section">
    <h2>What can we help you with? <span class="always-badge">Always asked</span></h2>
    <p class="note">Pick as many as apply. This is what decides which of the sections below you'll actually see.</p>
    <div class="taglist"><?php foreach ($CATEGORIES as $c): ?><span class="tag"><?= htmlspecialchars($c) ?></span><?php endforeach; ?></div>
  </div>

  <div class="section">
    <h2>Your business <span class="always-badge">Always asked</span></h2>
    <ul class="qlist">
      <li>What do you do?</li>
      <li>Who are your customers?</li>
      <li>Who are your main competitors? (optional)</li>
      <li>What's the biggest problem you're trying to solve right now?</li>
      <li>What would success look like for this project?</li>
    </ul>
  </div>

  <div class="divider"><span>The sections below only appear if relevant</span></div>

  <div class="section">
    <h2>Your website <span class="cond-badge">If you pick Website Design, Redesign, or E-commerce</span></h2>
    <ul class="qlist">
      <li>How many pages do you need?</li>
      <li>What would you like visitors to be able to do on your site?</li>
      <li>Which pages do you need?</li>
    </ul>
  </div>

  <div class="section">
    <h2>Your online store <span class="cond-badge">If you pick E-commerce</span></h2>
    <ul class="qlist">
      <li>Product catalogue, variations, payment methods, shipping, stock tracking, customer accounts, subscriptions, discount codes — whichever apply to what you're selling.</li>
    </ul>
  </div>

  <div class="section">
    <h2>SEO <span class="cond-badge">If you pick SEO</span></h2>
    <ul class="qlist">
      <li>Which locations are you targeting?</li>
      <li>Any specific keywords you want to rank for?</li>
      <li>Do you know where you currently rank, if at all?</li>
      <li>Have you had SEO work done before?</li>
      <li>Do you already have Google Analytics / Search Console set up?</li>
    </ul>
  </div>

  <div class="section">
    <h2>Digital marketing <span class="cond-badge">If you pick Digital Marketing</span></h2>
    <ul class="qlist">
      <li>Which channels are you interested in?</li>
      <li>What are you currently doing for marketing, if anything?</li>
    </ul>
  </div>

  <div class="section">
    <h2>Your brand <span class="cond-badge">If you pick Branding</span></h2>
    <ul class="qlist">
      <li>Do you already have a logo?</li>
      <li>Brand colours, if you have them</li>
      <li>Do you have brand guidelines?</li>
    </ul>
  </div>

  <div class="section">
    <h2>Domain and hosting <span class="cond-badge">If you pick Hosting</span></h2>
    <ul class="qlist">
      <li>Do you already own a domain, and who's your current hosting provider?</li>
      <li>Do you need hosting, a business email, an SSL certificate, migration, or backups?</li>
    </ul>
  </div>

  <div class="divider"><span>Always asked, near the end</span></div>

  <div class="section">
    <h2>Content</h2>
    <ul class="qlist">
      <li>Do you already have content written? (Don't worry if not — we can help.)</li>
      <li>Do you need copywriting, professional photography, or video?</li>
    </ul>
  </div>

  <div class="section">
    <h2>Design inspiration</h2>
    <ul class="qlist">
      <li>Up to three websites you like, and what you like about them</li>
      <li>Your preferred style (Modern, Corporate, Luxury, Minimal, Colourful, Other)</li>
    </ul>
  </div>

  <div class="section">
    <h2>Budget</h2>
    <p class="note">This doesn't determine your quote — it just helps us recommend the right solution. Five ranges, from under £500 to £5,000+.</p>
  </div>

  <div class="section">
    <h2>Timeline</h2>
    <p class="note">An approximate launch date is perfectly fine, plus how flexible that date is.</p>
  </div>

  <div class="section">
    <h2>Anything else</h2>
    <p class="note">Any special requirements, and anything else we should know.</p>
  </div>

  <div class="section">
    <h2>Optional extras</h2>
    <div class="taglist"><?php foreach ($ADDONS as $a): ?><span class="tag"><?= htmlspecialchars($a) ?></span><?php endforeach; ?></div>
  </div>

  <div class="section">
    <h2>How did you hear about us</h2>
    <div class="taglist"><?php foreach ($REFERRALS as $r): ?><span class="tag"><?= htmlspecialchars($r) ?></span><?php endforeach; ?></div>
  </div>

  <div class="section" style="border-color:var(--purple);background:var(--purple-tint)">
    <h2>Review, then submit</h2>
    <p class="note" style="margin-bottom:0">At the end, you'll see everything you entered laid out clearly, so you can double-check or go back and change anything before it's sent.</p>
  </div>

  <div class="cta">
    <a href="<?= APP_URL ?>/start-your-project.php">Start Your Project &rarr;</a>
    <p>No account needed — takes about 10&ndash;15 minutes</p>
  </div>
</div>

</body>
</html>
