<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/uploads.php';

// Public, no-login "Project Discovery" intake form. Shows no prices —
// the client picks a self-selected budget bracket, and staff work out
// the real numbers privately afterward. Deliberately kept short: content
// that used to span many separate steps now lives as conditional
// sub-sections within one "Project Details" step.

$GOALS = ['More enquiries','Sell online','Better branding','Replace an old website','Get found on Google & AI search','Launch a new business','Other'];
$CATEGORIES = ['Website Design & Development','SEO & AI Search','Digital Marketing','IT Services','Maintenance','Not sure'];
$PAGE_COUNTS = ['1-5','6-10','11-20','20+'];
$REQUIRED_PAGES = ['Home','About','Services','Products','Blog','Contact','FAQs','Testimonials','Gallery','Careers','Privacy Policy','Terms & Conditions'];
$WEBSITE_FEATURES = ['Contact Form','Online Booking','Online Payments','E-commerce Shop','Customer Login','Live Chat','Newsletter Signup','Google Maps','Social Media Integration','Reviews','WhatsApp Chat','Multi-language','Search Function','Events Calendar','Custom Forms'];
$SEO_REQUIREMENTS = ['Keyword Research','Local SEO','Monthly SEO','Google Business Profile','Technical SEO','Analytics Setup'];
$IT_SERVICES = ['Reliable IT Support','Microsoft 365','Cybersecurity','Cloud Solutions','Remote IT Services','Hosting'];
$SOLUTION_TYPES = ['AI-powered Website','Theme-based WordPress Website','Custom WordPress Website','Fully Custom Website','Web Application / Management System','Not Sure'];
$MANAGEMENT_SYSTEM_TYPES = ['Office Management','School Management','Hospital Management','Restaurant Management','Inventory Management','CRM','HR Management','Booking System','Customer Portal','Employee Portal','Other'];
$MARKETING_CHANNELS = ['Google Ads','Facebook / Instagram Ads','Social Media Management','Email Marketing','Other'];
$STYLES = ['Modern','Corporate','Luxury','Minimal','Colourful','Other'];
$BUDGETS = ['600 to 1000 GBP','1000 to 2500 GBP','2500 to 5000 GBP','5000 GBP or more','Custom'];
$FLEXIBILITY = ['Flexible','Somewhat flexible','Fixed deadline'];
$ADDONS = ['Logo Design','Graphic Design','Business Cards','Flyer Design','Social Media Setup','Monthly Website Maintenance','Website Security','Website Backups','Website Training','Custom Plugins','Other'];
$REFERRALS = ['Google','Facebook','LinkedIn','Referral','Existing Client','Agent','Other'];

function checklist(string $name, array $options): string {
    $html = '';
    foreach ($options as $opt) {
        $html .= '<label class="chk"><input type="checkbox" name="' . e($name) . '[]" value="' . e($opt) . '"> ' . e($opt) . '</label>';
    }
    return $html;
}
function radios(string $name, array $options): string {
    $html = '';
    foreach ($options as $opt) {
        $html .= '<label class="chk"><input type="radio" name="' . e($name) . '" value="' . e($opt) . '"> ' . e($opt) . '</label>';
    }
    return $html;
}

$error = '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $honeypot = trim($_POST['website_check'] ?? '');
    $loadedAt = (int)($_POST['loaded_at'] ?? 0);
    $tooFast = $loadedAt > 0 && (time() - $loadedAt) < 3;

    $p = function ($k) { return trim($_POST[$k] ?? ''); };
    $arr = function ($k) { return implode(', ', array_filter(array_map('trim', $_POST[$k] ?? []))); };

    $name = $p('contact_name');
    $email = $p('contact_email');
    $company = $p('company_name');
    // company_name is a required column on clients — for someone without
    // a company (freelancers, sole traders), fall back to their own name
    // rather than forcing them to type something into an optional field.
    $companyForRecord = $company !== '' ? $company : $name;

    $gdprConsent = isset($_POST['gdpr_consent']) && $_POST['gdpr_consent'] === '1';

    if ($honeypot !== '' || $tooFast) {
        $submitted = true;
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please fill in your name and a valid email.';
    } elseif (!$gdprConsent) {
        $error = 'Please confirm you consent to us processing your data before submitting.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO clients (company_name, status, source, website, address) VALUES (?,?,?,?,?)');
            $stmt->execute([$companyForRecord, 'lead', 'Project Discovery form', $p('website_url') ?: null, $p('business_address') ?: null]);
            $clientId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO contacts (client_id, name, email, phone, is_primary) VALUES (?,?,?,?,1)');
            $stmt->execute([$clientId, $name, $email, $p('contact_phone') ?: null]);

            $leadTitle = ($arr('project_type') ?: 'Project inquiry') . ' - ' . $companyForRecord;
            $stmt = $pdo->prepare('INSERT INTO leads (client_id, title, description, stage) VALUES (?,?,?,?)');
            $stmt->execute([$clientId, mb_substr($leadTitle, 0, 150), $p('business_description') ?: null, 'new']);
            $leadId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO quote_requests (
                    client_id, lead_id, contact_name, contact_email, contact_phone, company_name, website_url, business_address,
                    is_decision_maker, current_host,
                    project_goals, project_type,
                    business_description, services_detail,
                    has_logo, wants_logo_design, brand_colours, solution_type,
                    page_count_bracket, required_pages_other, website_features, website_features_other,
                    has_content, needs_photography, needs_stock_images, needs_video,
                    seo_requirements, seo_primary_goal, seo_target_locations, seo_current_analytics,
                    it_services_needed,
                    marketing_channels, marketing_current_activity,
                    design_references, design_references_why, preferred_style,
                    budget_range, budget_custom_amount, preferred_launch_date, is_urgent, timeline_flexibility,
                    additional_notes,
                    addon_services, addon_services_other, referral_source, referral_source_other, referral_agent_name,
                    gdpr_consent, gdpr_consent_at
                ) VALUES (?,?,?,?,?,?,?,?, ?,?, ?,?, ?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?, ?,?, ?,?,?, ?,?,?,?,?, ?, ?,?,?,?,?, ?,?)'
            );
            $stmt->execute([
                $clientId, $leadId, $name, $email, $p('contact_phone') ?: null, $companyForRecord, $p('website_url') ?: null, $p('business_address') ?: null,
                $p('is_decision_maker') ?: null, $p('current_host') ?: null,
                $arr('project_goals') ?: null, $arr('project_type') ?: null,
                $p('business_description') ?: null, $p('services_detail') ?: null,
                $p('has_logo') ?: null, $p('wants_logo_design') ?: null, $p('brand_colours') ?: null, $p('solution_type') ?: null,
                $p('page_count_bracket') ?: null, $p('required_pages_other') ?: null, $arr('website_features') ?: null, $p('website_features_other') ?: null,
                $p('has_content') ?: null, $p('needs_photography') ?: null, $p('needs_stock_images') ?: null, $p('needs_video') ?: null,
                $arr('seo_requirements') ?: null, $p('seo_primary_goal') ?: null, $p('seo_target_locations') ?: null, $p('seo_current_analytics') ?: null,
                $arr('it_services_needed') ?: null,
                $arr('marketing_channels') ?: null, $p('marketing_current_activity') ?: null,
                $p('design_references') ?: null, $p('design_references_why') ?: null, $p('preferred_style') ?: null,
                $p('budget_range') ?: null, $p('budget_custom_amount') ?: null, $p('preferred_launch_date') ?: null, $p('is_urgent') ?: null, $p('timeline_flexibility') ?: null,
                $p('additional_notes') ?: null,
                $arr('addon_services') ?: null, $p('addon_services_other') ?: null, $p('referral_source') ?: null, $p('referral_source_other') ?: null, $p('referral_agent_name') ?: null,
                1, date('Y-m-d H:i:s'),
            ]);

            log_activity($pdo, null, $clientId, $leadId, 'New project discovery form submitted.');
            $pdo->commit();

            // Optional brief/logo upload — handled after the main commit so
            // a problem here (wrong file type, too large, etc.) never costs
            // the client their otherwise-successful submission.
            if (!empty($_FILES['brief_upload']['name'])) {
                try {
                    $uploaded = handle_upload($_FILES['brief_upload'], 'quote_requests');
                    $stmt = $pdo->prepare(
                        'INSERT INTO quote_request_attachments (client_id, lead_id, original_filename, stored_filename, filesize) VALUES (?,?,?,?,?)'
                    );
                    $stmt->execute([$clientId, $leadId, $uploaded['original_filename'], $uploaded['stored_filename'], $uploaded['filesize']]);
                    log_activity($pdo, null, $clientId, $leadId, 'Client uploaded a file with their request: ' . $uploaded['original_filename']);
                } catch (Throwable $e) {
                    error_log('Quote request file upload failed: ' . $e->getMessage());
                }
            }

            try {
                $admins = $pdo->query("SELECT email, name FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
                foreach ($admins as $a) {
                    send_email($a['email'], $a['name'], 'New project inquiry - ' . $companyForRecord,
                        '<p>New project discovery submission from <strong>' . htmlspecialchars($name) . '</strong> (' . htmlspecialchars($companyForRecord) . ').</p>
                         <p><a href="' . APP_URL . '/leads/view.php?id=' . $leadId . '">View the lead</a></p>');
                }
            } catch (Throwable $e) { /* non-fatal */ }

            $submitted = true;
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Project discovery submission failed: ' . $ex->getMessage());
            $error = 'Sorry, something went wrong submitting this. Please try again or contact us directly.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Start Your Project - Himal Tech</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap');
* { box-sizing: border-box; }
body { margin:0; font-family:'IBM Plex Sans',Arial,sans-serif; background:#faf9fc; color:#14121a; }
h1,h2,h3 { font-family:'Sora',sans-serif; font-weight:700; letter-spacing:-0.01em; margin:0; }
:root { --purple:#9400ff; --purple-dark:#7400cc; --purple-tint:#f4e9ff; --ink:#14121a; --ink-muted:#6b6478; --border:#e5e1ea; --success:#1f9d6b; --success-bg:#e3f5ec; }

.topbar { background:#fff; border-bottom:1px solid var(--border); padding:14px 24px; display:flex; align-items:center; justify-content:space-between; }
.topbar .brand { display:flex; align-items:center; gap:9px; font-family:'Sora',sans-serif; font-weight:700; font-size:14px; }
.topbar .mark { width:26px;height:26px;border-radius:7px;background:var(--purple);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px; }
.topbar a.login-link { font-size:12.5px; color:var(--purple-dark); text-decoration:none; font-weight:600; }

.stepper-wrap { background:#fff; border-bottom:1px solid var(--border); padding:0 24px; overflow-x:auto; }
.stepper { display:flex; align-items:center; justify-content:space-between; max-width:900px; margin:0 auto; padding:14px 0; gap:10px; }
.step-count { display:flex; align-items:center; gap:8px; }
.step { display:flex; align-items:center; gap:8px; flex-shrink:0; }
.step .dot { width:22px;height:22px;border-radius:50%; display:flex;align-items:center;justify-content:center; font-size:10.5px; font-weight:700; flex-shrink:0; border:1.5px solid var(--border); color:var(--ink-muted); background:#fff; }
.step.done .dot { background:var(--success); border-color:var(--success); color:#fff; }
.step.current .dot { background:var(--purple); border-color:var(--purple); color:#fff; }
.step .label { font-size:12px; font-weight:600; color:var(--ink-muted); white-space:nowrap; }
.step.done .label, .step.current .label { color:var(--ink); }
.step-line { width:20px; height:1.5px; background:var(--border); margin:0 4px; flex-shrink:0; }
.step-line.done { background:var(--success); }
.time-estimate { font-family:'IBM Plex Mono',monospace; font-size:11.5px; color:var(--ink-muted); background:#faf9fc; border:1px solid var(--border); border-radius:20px; padding:4px 12px; white-space:nowrap; }

.layout { max-width:760px; margin:0 auto; padding:32px 24px 80px; }
.stage { background:#fff; border:1px solid var(--border); border-radius:16px; padding:30px 32px; box-shadow:0 4px 20px -8px rgba(20,18,26,.07); animation:stageFade .25s ease; }
.stage.hidden { display:none; }
@keyframes stageFade { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
.eyebrow { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--purple-dark); font-weight:700; margin-bottom:8px; }
.stage h2 { font-size:22px; margin-bottom:8px; line-height:1.25; }
.stage .sub { color:var(--ink-muted); font-size:13.5px; margin-bottom:24px; max-width:520px; line-height:1.5; }
.helper-box { background:#faf9fc; border-left:3px solid var(--purple); border-radius:0 8px 8px 0; padding:11px 14px; font-size:12.5px; color:var(--ink-muted); margin:16px 0; line-height:1.5; }

.field { margin-bottom:16px; }
.field label { display:block; font-weight:600; font-size:13px; margin-bottom:6px; }
.field input[type=text], .field input[type=email], .field input[type=tel], .field input[type=date], .field textarea {
  width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit; font-size:13.5px;
}
.field textarea { min-height:70px; resize:vertical; }
.field-row { display:flex; gap:14px; }
.field-row .field { flex:1; }

.card-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:10px; margin-bottom:8px; }
.pick-card {
  border:1.5px solid var(--border); border-radius:10px; padding:16px 40px 16px 16px; cursor:pointer;
  transition:border-color .15s ease, background-color .15s ease;
  display:block; background:#fff; position:relative;
}
.pick-card:hover { border-color:#c9a6ff; }
.pick-card.selected { border-color:var(--purple); background:var(--purple-tint); }
.pick-card .title { font-weight:600; font-size:13.5px; line-height:1.4; }
.pick-card .check {
  position:absolute; top:14px; right:14px; width:16px;height:16px;border-radius:4px;
  border:1.5px solid var(--border); display:flex; align-items:center; justify-content:center;
  font-family:'IBM Plex Mono',monospace; font-size:10px; color:transparent; background:#fff;
}
.pick-card.selected .check { background:var(--purple); border-color:var(--purple); color:#fff; }
.pick-card.selected .check::after { content:'\2713'; }
.card-input { position:absolute; opacity:0; width:1px; height:1px; margin:0; }
.pick-card:has(.card-input:focus-visible) { outline:2px solid var(--purple); outline-offset:2px; }
@media (prefers-reduced-motion: reduce) { .pick-card, .stage { transition:none !important; animation:none !important; } }

.simple-list { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:2px 14px; }
.chk { display:flex; align-items:center; gap:8px; font-size:13px; padding:3px 0; cursor:pointer; }
.chk input { width:auto; }

.subsection { border-top:1px solid var(--border); margin-top:22px; padding-top:22px; }
.subsection:first-child { border-top:none; margin-top:0; padding-top:0; }
.subsection.hidden { display:none; }
.subsection h3 { font-size:15px; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
.subsection h3::before { content:''; width:6px; height:6px; border-radius:50%; background:var(--purple); flex-shrink:0; }

.stage-nav { display:flex; justify-content:space-between; align-items:center; margin-top:28px; padding-top:20px; border-top:1px solid var(--border); }
.btn { font-family:inherit; font-weight:600; font-size:13.5px; padding:11px 22px; border-radius:8px; cursor:pointer; border:none; }
.btn-primary { background:var(--purple); color:#fff; }
.btn-primary:hover { background:var(--purple-dark); }
.btn-ghost { background:none; color:var(--ink-muted); }
.btn-ghost:hover { color:var(--ink); }
.step-error { color:#d64545; font-size:12.5px; margin-top:10px; display:none; }
.step-error.show { display:block; }

.review-block { margin-bottom:14px; background:#faf9fc; border:1px solid var(--border); border-radius:12px; padding:16px 18px; }
.review-block h4 { font-size:13px; letter-spacing:.01em; color:var(--ink); margin:0 0 10px; font-weight:700; }
.review-block .line { font-size:13.5px; padding:5px 0; border-bottom:1px solid var(--border); line-height:1.5; }
.review-block .line:last-child { border-bottom:none; }
.review-block .line .k { color:var(--ink-muted); }

.error-box { background:#fbeaea; color:#d64545; border:1px solid #f3cdcd; padding:12px 16px; border-radius:8px; margin-bottom:16px; }
.hp { position:absolute; left:-9999px; }

.ok-box { max-width:640px; margin:60px auto; background:#fff; border:1px solid var(--border); border-radius:16px; padding:44px; text-align:center; }
.ok-box .ok { width:56px;height:56px;border-radius:50%;background:var(--success-bg);color:var(--success);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:26px; }
.ref { font-family:monospace; background:var(--purple-tint); color:var(--purple-dark); padding:4px 12px; border-radius:6px; display:inline-block; font-size:13px; margin:6px 0 24px; }
.pipeline { display:flex; align-items:center; justify-content:center; margin:0 0 20px; flex-wrap:wrap; }
.pipe-step { display:flex; flex-direction:column; align-items:center; gap:6px; width:100px; }
.pipe-dot { width:18px;height:18px;border-radius:50%; border:2px solid var(--border); background:#fff; }
.pipe-step.done .pipe-dot { background:var(--success); border-color:var(--success); }
.pipe-label { font-size:10.5px; color:var(--ink-muted); text-align:center; }
.pipe-step.done .pipe-label { color:var(--ink); font-weight:600; }
.pipe-line { flex:1; height:2px; background:var(--border); margin-bottom:22px; max-width:50px; }
.pipe-line.done { background:var(--success); }

@media (max-width:700px) {
  .layout { padding:16px 14px 100px; }
  .stage { padding:24px 18px; border-radius:14px; }
  .stage h2 { font-size:20px; }
  .field-row { flex-direction:column; gap:0; }
  .card-grid { grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); }
  .stage-nav { position:sticky; bottom:0; background:#fff; margin:28px -18px -24px; padding:14px 18px; border-top:1px solid var(--border); border-radius:0 0 14px 14px; }
  .step .label { display:none; }
}
a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible, [tabindex]:focus-visible {
  outline:2px solid var(--purple); outline-offset:2px; border-radius:4px;
}
</style>
</head>
<body>

<?php if ($submitted): ?>
  <div class="ok-box">
    <div class="ok">&#10003;</div>
    <h2>Your project has been submitted</h2>
    <?php if (!empty($leadId)): ?>
      <div class="ref">Reference: PROJECT-<?= date('Y') ?>-<?= str_pad($leadId, 4, '0', STR_PAD_LEFT) ?></div>
    <?php endif; ?>
    <p style="color:var(--ink-muted);font-size:13.5px;max-width:440px;margin:0 auto 8px;line-height:1.6">
      Our team has received your project. We'll now review your requirements — if we need anything more, we'll get in touch. Otherwise, we'll prepare your proposal and let you know by email.
    </p>
    <p style="color:var(--ink-muted);font-size:12.5px;max-width:440px;margin:0 auto 24px">Most proposals are ready within 2&ndash;3 business days.</p>
    <div class="pipeline">
      <div class="pipe-step done"><div class="pipe-dot"></div><div class="pipe-label">Received</div></div>
      <div class="pipe-line"></div>
      <div class="pipe-step"><div class="pipe-dot"></div><div class="pipe-label">Under Review</div></div>
      <div class="pipe-line"></div>
      <div class="pipe-step"><div class="pipe-dot"></div><div class="pipe-label">Proposal</div></div>
      <div class="pipe-line"></div>
      <div class="pipe-step"><div class="pipe-dot"></div><div class="pipe-label">Quote Ready</div></div>
      <div class="pipe-line"></div>
      <div class="pipe-step"><div class="pipe-dot"></div><div class="pipe-label">Decision</div></div>
    </div>
    <div style="border-top:1px solid var(--border);margin-top:8px;padding-top:20px;font-size:12.5px;color:var(--ink-muted);line-height:1.8">
      Questions in the meantime?<br>
      <strong style="color:var(--ink)">02033058141</strong> &middot; WhatsApp <strong style="color:var(--ink)">07344279055</strong><br>
      Gunnery House, Suite G58A, Woolwich, London
    </div>
    <p style="margin-top:20px"><a href="https://himaltech.co.uk" class="btn btn-primary" style="text-decoration:none;display:inline-block">Back to himaltech.co.uk</a></p>
  </div>

<?php else: ?>

<div class="topbar">
  <div class="brand"><span class="mark">HT</span> Himal Tech</div>
  <a class="login-link" href="<?= APP_URL ?>/login.php">Already a client or staff member? Log in &rarr;</a>
</div>

<div class="stepper-wrap">
  <div class="stepper" id="stepperBar">
    <div class="step-count" id="stepCount"></div>
    <div class="time-estimate" id="timeEstimate">~3 min left</div>
  </div>
</div>
<div aria-live="polite" class="hp" id="stepAnnouncer"></div>

<?php if ($error): ?><div style="max-width:760px;margin:16px auto 0;padding:0 24px"><div class="error-box"><?= e($error) ?></div></div><?php endif; ?>

<form method="post" id="discoveryForm" enctype="multipart/form-data">
<div class="layout">
  <div id="stagesWrap">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="loaded_at" value="<?= time() ?>">
    <div class="hp"><input type="text" name="website_check" tabindex="-1" autocomplete="off"></div>

    <div class="stage" data-step="about-you" data-title="About You">
      <div class="eyebrow">Let's start</div>
      <h2>About you</h2>
      <p class="sub">Just the basics, so we know who we're talking to.</p>
      <a href="<?= APP_URL ?>/what-well-ask.php" style="display:inline-flex;align-items:center;gap:6px;background:#faf9fc;border:1px solid var(--border);border-radius:8px;padding:9px 14px;font-size:12.5px;font-weight:600;color:var(--purple-dark);text-decoration:none;margin-bottom:22px">See every question we'll ask, before you start &rarr;</a>
      <div class="field-row">
        <div class="field"><label>Full Name *</label><input type="text" name="contact_name" required></div>
        <div class="field"><label>Phone Number *</label><input type="tel" name="contact_phone" required></div>
      </div>
      <div class="field"><label>Email Address *</label><input type="email" name="contact_email" required></div>
      <div class="field-row">
        <div class="field"><label>Company Name</label><input type="text" name="company_name"></div>
        <div class="field"><label>Website (if you already have one)</label><input type="text" name="website_url"></div>
      </div>
      <div class="field"><label>Business Address</label><input type="text" name="business_address"></div>
      <div class="field">
        <label>Are you the primary decision-maker for this project?</label>
        <div class="simple-list"><?= radios('is_decision_maker', ['Yes','I\'m gathering options for my team/management']) ?></div>
      </div>
      <div class="field">
        <label>Where is your current website/domain hosted, if you have one?</label>
        <select name="current_host">
          <option value="">Select...</option>
          <?php foreach (['WordPress','Wix','Squarespace','Shopify','Custom','Don\'t know / not applicable'] as $h): ?>
            <option value="<?= e($h) ?>"><?= e($h) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Have brand guidelines, a logo file, or a project brief ready? (optional)</label>
        <input type="file" name="brief_upload" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp,.zip">
        <span style="display:block;font-size:11.5px;color:var(--ink-muted);margin-top:4px">PDF, Word, image, or zip file, up to 8MB. Saves us going back and forth over email later.</span>
      </div>
      <div class="step-error" id="aboutYouError">Please fill in your name, phone, and a valid email.</div>
    </div>

    <div class="stage" data-step="services-goals-business" data-title="Services, Goals & Business">
      <div class="eyebrow">Step 2</div>
      <h2>What can we help you with?</h2>
      <p class="sub">Choose everything you're interested in — we'll only ask questions that are relevant to what you pick.</p>
      <div class="card-grid" data-field="project_type" data-mode="multi" id="servicesCardGrid" role="group" aria-label="What can we help you with">
                <?php foreach ($CATEGORIES as $cat): ?>
          <label class="pick-card cat-card" data-value="<?= e($cat) ?>"><input type="checkbox" name="project_type[]" value="<?= e($cat) ?>" class="card-input cat-check" data-cat="<?= e($cat) ?>"><span class="title"><?= e($cat) ?></span><span class="check" aria-hidden="true"></span></label>
        <?php endforeach; ?>
      </div>
      <div class="step-error" id="servicesError">Please choose at least one option so we know what to focus on — or pick "Not sure" if you're still deciding.</div>

      <div class="subsection">
        <h3>What are you trying to achieve?</h3>
        <p class="sub" style="margin-bottom:14px">This matters more to us than the technical details.</p>
        <div class="card-grid" data-field="project_goals" data-mode="multi" role="group" aria-label="What are you trying to achieve">
                    <?php foreach ($GOALS as $g): ?>
            <label class="pick-card" data-value="<?= e($g) ?>"><input type="checkbox" name="project_goals[]" value="<?= e($g) ?>" class="card-input"><span class="title"><?= e($g) ?></span><span class="check" aria-hidden="true"></span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="subsection">
        <h3>Tell us about your business</h3>
        <p class="sub" style="margin-bottom:8px">Do you already have a logo?</p>
        <div class="simple-list" id="hasLogoList">
          <?php foreach (['Yes','No'] as $hl): ?><label class="chk"><input type="radio" name="has_logo" value="<?= e($hl) ?>" class="has-logo-radio"> <?= e($hl) ?></label><?php endforeach; ?>
        </div>
        <div class="field hidden" id="wantsLogoDesignField" style="margin-top:10px">
          <p class="sub" style="margin-bottom:8px">Would you like us to design one for you?</p>
          <div class="simple-list"><?= radios('wants_logo_design', ['Yes','No']) ?></div>
        </div>
        <div class="field" style="margin-top:12px"><label>Brand colours, if you have them</label><input type="text" name="brand_colours" placeholder="Optional"></div>
        <div class="field" style="margin-top:14px"><label>About your business</label><textarea name="business_description" placeholder="A sentence or two is fine"></textarea></div>
        <div class="field"><label>Detail about your services</label><textarea name="services_detail"></textarea></div>
      </div>
    </div>

    <div class="stage" data-step="project-details" data-title="Project Details">
      <div class="eyebrow">Step 3</div>
      <h2>Project details</h2>
      <p class="sub">Only the sections relevant to what you picked will show up here.</p>

      <div class="subsection hidden" data-shows-for="Website Design &amp; Development">
        <h3>Your project</h3>
        <p class="sub" style="margin-bottom:8px">What kind of solution do you need?</p>
        <div class="simple-list" id="solutionTypeList">
          <?php foreach ($SOLUTION_TYPES as $st): ?><label class="chk"><input type="radio" name="solution_type" value="<?= e($st) ?>" class="solution-type-radio"> <?= e($st) ?></label><?php endforeach; ?>
        </div>
        <div class="helper-box hidden" id="turnaroundNote"></div>

        <div id="websitePageQuestions">
          <p class="sub" style="margin:16px 0 8px">Number of pages</p>
          <div class="simple-list">
            <?php foreach ($PAGE_COUNTS as $pc): ?><label class="chk"><input type="radio" name="page_count_bracket" value="<?= e($pc) ?>" class="page-count-radio"> <?= e($pc) ?></label><?php endforeach; ?>
          </div>
          <p class="sub" style="margin:16px 0 8px">Any specific pages you know you need, besides the standard Home/About/Contact? (optional)</p>
          <textarea name="required_pages_other" placeholder="e.g. a blog, a gallery, a careers page"></textarea>
          <p class="sub" style="margin:16px 0 8px">What would you like visitors to be able to do on your site?</p>
          <div class="simple-list"><?= checklist('website_features', $WEBSITE_FEATURES) ?></div>
          <div class="field" style="margin-top:10px"><label>Anything else you'd like your site to do? (optional)</label><input type="text" name="website_features_other"></div>
        </div>
      </div>

      <div class="subsection hidden" data-shows-for="SEO &amp; AI Search">
        <h3>SEO &amp; AI Search</h3>
        <div class="simple-list"><?= checklist('seo_requirements', $SEO_REQUIREMENTS) ?></div>
        <p class="sub" style="margin-top:14px">What's your primary SEO goal?</p>
        <div class="simple-list"><?= radios('seo_primary_goal', ['Rank locally in my area','Target nationwide','Fix technical site issues','Don\'t know']) ?></div>
        <div class="field" style="margin-top:12px"><label>Which locations are you targeting? (optional)</label><input type="text" name="seo_target_locations"></div>
        <p class="sub" style="margin-top:12px">Do you already have Google Analytics / Search Console set up?</p>
        <div class="simple-list"><?= radios('seo_current_analytics', ['Yes','No','Not sure']) ?></div>
      </div>

      <div class="subsection hidden" data-shows-for="Digital Marketing">
        <h3>Digital marketing</h3>
        <p class="sub" style="margin-bottom:8px">Which channels are you interested in?</p>
        <div class="simple-list"><?= checklist('marketing_channels', $MARKETING_CHANNELS) ?></div>
        <p class="sub" style="margin-top:14px">Are you currently doing any marketing?</p>
        <div class="simple-list"><?= radios('marketing_current_activity', ['Yes, and it\'s working', 'Yes, but not working well', 'No, starting from scratch', 'Not sure']) ?></div>
      </div>

      <div class="subsection hidden" data-shows-for="IT Services">
        <h3>IT Services</h3>
        <p class="sub" style="margin-bottom:8px">Which of these do you need?</p>
        <div class="simple-list"><?= checklist('it_services_needed', $IT_SERVICES) ?></div>
      </div>

      <div class="subsection">
        <h3>Content</h3>
        <p class="sub" style="margin-bottom:8px">Don't worry if everything isn't ready yet — we can help create content if needed.</p>
        <div class="simple-list"><?= radios('has_content', ['Yes','No','Some of it']) ?></div>
        <div class="field" style="margin-top:12px">
          <label style="font-weight:500;font-size:12.5px">Need photography?</label>
          <div class="simple-list"><?= radios('needs_photography', ['Yes','No','Not sure']) ?></div>
        </div>
      </div>

      <div class="subsection">
        <h3>A little inspiration</h3>
        <div class="field"><label>Any websites, brands, or styles you like? Tell us what you like about them.</label><textarea name="design_references_why" placeholder="Doesn't need to be websites — anything that captures the feel you're after"></textarea></div>
        <p class="sub" style="margin-bottom:8px">Preferred style</p>
        <div class="card-grid" data-field="preferred_style" data-mode="single" role="group" aria-label="Preferred style">
          <?php foreach ($STYLES as $s): ?>
            <label class="pick-card" data-value="<?= e($s) ?>"><input type="radio" name="preferred_style" value="<?= e($s) ?>" class="card-input"><span class="title"><?= e($s) ?></span><span class="check" aria-hidden="true"></span></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="stage" data-step="wrap-up" data-title="Budget, Extras & Review">
      <div class="eyebrow">Step 4</div>
      <h2>Almost there</h2>
      <p class="sub">This doesn't determine your quote — it simply helps us recommend the most appropriate solution for you.</p>
      <div class="card-grid" data-field="budget_range" data-mode="single" role="group" aria-label="Budget range">
        <?php foreach ($BUDGETS as $b): ?>
          <label class="pick-card budget-card" data-value="<?= e($b) ?>"><input type="radio" name="budget_range" value="<?= e($b) ?>" class="card-input budget-radio"><span class="title"><?= e(str_replace(' GBP', '', $b)) ?></span><span class="check" aria-hidden="true"></span></label>
        <?php endforeach; ?>
      </div>
      <div class="field hidden" id="budgetCustomField" style="margin-top:10px"><input type="text" name="budget_custom_amount" placeholder="Tell us your budget"></div>

      <div class="subsection">
        <h3>Timeline</h3>
        <p class="sub" style="margin-bottom:8px">An approximate date is perfectly fine.</p>
        <div class="field"><label>Preferred launch date</label><input type="date" name="preferred_launch_date"></div>
        <p class="sub" style="margin:14px 0 8px">How flexible is this date?</p>
        <div class="card-grid" data-field="timeline_flexibility" data-mode="single" role="group" aria-label="Timeline flexibility">
          <?php foreach ($FLEXIBILITY as $f): ?>
            <label class="pick-card" data-value="<?= e($f) ?>"><input type="radio" name="timeline_flexibility" value="<?= e($f) ?>" class="card-input"><span class="title"><?= e($f) ?></span><span class="check" aria-hidden="true"></span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="subsection">
        <h3>Optional extras</h3>
        <div class="card-grid" data-field="addon_services" data-mode="multi" role="group" aria-label="Optional extras">
          <?php foreach ($ADDONS as $ad): ?>
            <label class="pick-card addon-card" data-value="<?= e($ad) ?>"><input type="checkbox" name="addon_services[]" value="<?= e($ad) ?>" class="card-input addon-check"><span class="title"><?= e($ad) ?></span><span class="check" aria-hidden="true"></span></label>
          <?php endforeach; ?>
        </div>
        <div class="field hidden" id="addonOtherField" style="margin-top:10px"><label>What else are you after?</label><input type="text" name="addon_services_other"></div>
      </div>

      <div class="subsection">
        <h3>Anything else?</h3>
        <div class="field"><label>Is there anything else we should know about your business or project?</label><textarea name="additional_notes"></textarea></div>
      </div>

      <div class="subsection">
        <h3>How did you hear about us?</h3>
        <div class="simple-list" id="referralList">
          <?php foreach ($REFERRALS as $r): ?><label class="chk"><input type="radio" name="referral_source" value="<?= e($r) ?>" class="referral-radio" data-value="<?= e($r) ?>"> <?= e($r) ?></label><?php endforeach; ?>
        </div>
        <div class="field hidden" id="referralAgentField" style="margin-top:10px"><input type="text" name="referral_agent_name" placeholder="Agent's name"></div>
        <div class="field hidden" id="referralOtherField" style="margin-top:10px"><input type="text" name="referral_source_other" placeholder="Please tell us more"></div>
      </div>

      <div class="subsection">
        <h3>Review your answers</h3>
        <p class="sub" style="margin-bottom:14px">Take a look before you submit — go back to change anything.</p>
        <div id="reviewContent"></div>
      </div>

      <div class="subsection">
        <label style="display:flex;align-items:flex-start;gap:10px;font-weight:400;font-size:13px;cursor:pointer;line-height:1.5">
          <input type="checkbox" name="gdpr_consent" value="1" id="gdprConsent" style="width:auto;margin-top:2px" required>
          <span>I consent to Himal Tech processing my personal data as described in the <a href="https://himaltech.co.uk/privacy-policy/" target="_blank" rel="noopener" style="color:var(--purple-dark)">Privacy Policy</a>, so that we can respond to this request.</span>
        </label>
        <div class="step-error" id="consentError">Please confirm you consent to us processing your data before submitting.</div>
      </div>
    </div>

    <div class="stage-nav" id="globalNav">
      <button type="button" class="btn btn-ghost" id="backBtn" style="visibility:hidden">&larr; Back</button>
      <div></div>
      <button type="button" class="btn btn-primary" id="nextBtn">Continue &rarr;</button>
      <button type="submit" class="btn btn-primary" id="submitBtn" style="display:none">Submit Project Request</button>
    </div>
  </div>
</div>
</form>

<?php endif; ?>
<?php if (!$submitted): ?>
<script>
(function() {
  var allStages = Array.prototype.slice.call(document.querySelectorAll('.stage'));
  var current = 0;

  var backBtn = document.getElementById('backBtn');
  var nextBtn = document.getElementById('nextBtn');
  var submitBtn = document.getElementById('submitBtn');
  var aboutYouError = document.getElementById('aboutYouError');
  var servicesError = document.getElementById('servicesError');

  function isCategoryChecked(cat) {
    var cb = document.querySelector('.cat-check[data-cat="' + cat.replace(/"/g, '\\"') + '"]');
    return cb && cb.checked;
  }

  // Subsections inside "Project Details" show/hide based on which
  // categories were picked in step 2 - separate from the (fixed) list of
  // top-level stages, which no longer changes.
  function updateSubsections() {
    document.querySelectorAll('[data-shows-for]').forEach(function(section) {
      var cats = section.dataset.showsFor.split(',');
      var shouldShow = cats.some(isCategoryChecked);
      section.classList.toggle('hidden', !shouldShow);
    });
  }

  // Rough, honest time estimate: a base amount per remaining step, plus a
  // bit more for each conditional subsection that's actually going to show
  // up, so someone who picked one thing sees a shorter estimate than
  // someone who picked four.
  function updateTimeEstimate() {
    var stepsLeft = allStages.length - current - 1;
    var visibleSubsections = document.querySelectorAll('[data-shows-for]:not(.hidden)').length;
    var seconds = stepsLeft * 35 + visibleSubsections * 25;
    var mins = Math.max(1, Math.round(seconds / 60));
    document.getElementById('timeEstimate').textContent = '~' + mins + ' min left';
  }

  function renderStepper() {
    var bar = document.getElementById('stepCount');
    bar.innerHTML = '';
    allStages.forEach(function(stage, i) {
      var cls = i < current ? 'done' : (i === current ? 'current' : '');
      var stepEl = document.createElement('div');
      stepEl.className = 'step ' + cls;
      stepEl.innerHTML = '<span class="dot">' + (i < current ? '&#10003;' : (i + 1)) + '</span><span class="label">' + stage.dataset.title + '</span>';
      bar.appendChild(stepEl);
      if (i < allStages.length - 1) {
        var line = document.createElement('div');
        line.className = 'step-line ' + (i < current ? 'done' : '');
        bar.appendChild(line);
      }
    });
  }

  function showCurrent() {
    allStages.forEach(function(s) { s.classList.add('hidden'); });
    allStages[current].classList.remove('hidden');
    backBtn.style.visibility = current === 0 ? 'hidden' : 'visible';
    var isLast = current === allStages.length - 1;
    nextBtn.style.display = isLast ? 'none' : 'inline-flex';
    submitBtn.style.display = isLast ? 'inline-flex' : 'none';
    if (isLast) renderReview();
    var announcer = document.getElementById('stepAnnouncer');
    if (announcer) announcer.textContent = 'Step ' + (current + 1) + ' of ' + allStages.length + ': ' + allStages[current].dataset.title;
    renderStepper();
    updateTimeEstimate();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function validateAboutYou() {
    var name = document.querySelector('[name="contact_name"]').value.trim();
    var phone = document.querySelector('[name="contact_phone"]').value.trim();
    var email = document.querySelector('[name="contact_email"]').value.trim();
    var emailOk = /^\S+@\S+\.\S+$/.test(email);
    var msg = '';
    if (!name) msg = 'Please enter your name before continuing.';
    else if (!phone) msg = 'Please enter your phone number before continuing.';
    else if (!email) msg = 'Please enter your email address before continuing.';
    else if (!emailOk) msg = 'Please enter a valid email address.';
    aboutYouError.textContent = msg;
    aboutYouError.classList.toggle('show', !!msg);
    return !msg;
  }
  ['contact_name','contact_phone','contact_email'].forEach(function(n) {
    var el = document.querySelector('[name="' + n + '"]');
    if (el) el.addEventListener('input', function() {
      if (aboutYouError.classList.contains('show')) validateAboutYou();
    });
  });

  function validateServices() {
    var anyChecked = document.querySelectorAll('.cat-check:checked').length > 0;
    servicesError.classList.toggle('show', !anyChecked);
    return anyChecked;
  }

  nextBtn.addEventListener('click', function() {
    var stageEl = allStages[current];
    if (stageEl.dataset.step === 'about-you' && !validateAboutYou()) return;
    if (stageEl.dataset.step === 'services-goals') {
      if (!validateServices()) return;
      updateSubsections();
    }
    if (current < allStages.length - 1) { current++; showCurrent(); }
  });
  backBtn.addEventListener('click', function() {
    if (current > 0) { current--; showCurrent(); }
  });

  document.querySelectorAll('.card-grid').forEach(function(grid) {
    var mode = grid.dataset.mode;
    grid.querySelectorAll('.card-input').forEach(function(input) {
      input.addEventListener('change', function() {
        if (mode === 'single') {
          grid.querySelectorAll('.pick-card').forEach(function(c) { c.classList.remove('selected'); });
        }
        input.closest('.pick-card').classList.toggle('selected', input.checked);
        if (input.name === 'project_type[]') {
          servicesError.classList.remove('show');
          updateSubsections();
          updateTimeEstimate();
        }
      });
    });
  });

  // Referral "Other" reveals a text box to say where, instead of a dead end.
  document.querySelectorAll('.referral-radio').forEach(function(r) {
    r.addEventListener('change', function() {
      var checkedVal = document.querySelector('.referral-radio:checked');
      checkedVal = checkedVal ? checkedVal.value : '';
      document.getElementById('referralOtherField').classList.toggle('hidden', checkedVal !== 'Other');
      document.getElementById('referralAgentField').classList.toggle('hidden', checkedVal !== 'Agent');
    });
  });

  // Budget "Custom" and Extras "Other" reveal their own text fields, same pattern.
  document.querySelectorAll('.budget-radio').forEach(function(r) {
    r.addEventListener('change', function() {
      var checkedVal = document.querySelector('.budget-radio:checked');
      document.getElementById('budgetCustomField').classList.toggle('hidden', !checkedVal || checkedVal.value !== 'Custom');
    });
  });
  document.querySelectorAll('.addon-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
      var otherChecked = Array.prototype.some.call(document.querySelectorAll('.addon-check'), function(c) { return c.checked && c.value === 'Other'; });
      document.getElementById('addonOtherField').classList.toggle('hidden', !otherChecked);
    });
  });

  // Offer to design a logo the moment someone says they don't have one.
  document.querySelectorAll('.has-logo-radio').forEach(function(r) {
    r.addEventListener('change', function() {
      var checkedVal = document.querySelector('.has-logo-radio:checked');
      document.getElementById('wantsLogoDesignField').classList.toggle('hidden', !checkedVal || checkedVal.value !== 'No');
    });
  });

  // Solution type determines whether the management-system questions or
  // the normal page/feature questions show up - nested one level inside
  // the existing category-based conditional logic. It also drives a
  // small note about expected turnaround, so people get a sense of
  // speed even though we never show a price here.
  function updateTurnaroundNote() {
    var note = document.getElementById('turnaroundNote');
    var solutionVal = document.querySelector('.solution-type-radio:checked');
    var pageVal = document.querySelector('.page-count-radio:checked');
    var text = '';
    if (solutionVal && solutionVal.value === 'Web Application / Management System') {
      text = 'Custom application requirements take our team ~24\u201348 hours to carefully scope into a detailed proposal.';
    } else if (pageVal && pageVal.value === '1-5') {
      text = 'Standard site setup \u2014 we can usually turn around a quote for this within 24 hours!';
    } else if (pageVal && (pageVal.value === '11-20' || pageVal.value === '20+')) {
      text = 'Larger builds like this take our team a bit longer to scope properly \u2014 usually 24\u201348 hours for a detailed proposal.';
    }
    note.textContent = text;
    note.classList.toggle('hidden', !text);
  }
  document.querySelectorAll('.page-count-radio').forEach(function(r) {
    r.addEventListener('change', updateTurnaroundNote);
  });

  document.querySelectorAll('.solution-type-radio').forEach(function(r) {
    r.addEventListener('change', function() {
      var checkedVal = document.querySelector('.solution-type-radio:checked');
      var isWebApp = checkedVal && checkedVal.value === 'Web Application / Management System';
      document.getElementById('websitePageQuestions').classList.toggle('hidden', isWebApp);
      updateTurnaroundNote();
    });
  });

  function fieldValue(name) {
    var el = document.querySelector('[name="' + name + '"]');
    return el ? el.value.trim() : '';
  }
  function checkedValues(name) {
    return Array.prototype.slice.call(document.querySelectorAll('[name="' + name + '[]"]:checked')).map(function(i) { return i.value; });
  }
  function radioValue(name) {
    var el = document.querySelector('[name="' + name + '"]:checked');
    return el ? el.value : '';
  }

  function renderReview() {
    var blocks = [
      ['About You', [['Name', fieldValue('contact_name')], ['Phone', fieldValue('contact_phone')], ['Email', fieldValue('contact_email')], ['Company', fieldValue('company_name') || '-']]],
      ['Services & Goals', [['Services', checkedValues('project_type').join(', ') || '-'], ['Goals', checkedValues('project_goals').join(', ') || '-']]],
      ['Business', [['About your business', fieldValue('business_description') || '-'], ['Services detail', fieldValue('services_detail') || '-'], ['Has a logo', radioValue('has_logo') || '-'], ['Wants a logo designed', radioValue('wants_logo_design') || '-']]],
      ['Project Solution', [['Solution type', radioValue('solution_type') || '-']]],
      ['Budget & Timeline', [['Budget', radioValue('budget_range') || '-'], ['Flexibility', radioValue('timeline_flexibility') || '-'], ['Launch date', fieldValue('preferred_launch_date') || '-']]],
      ['Extras', [['Selected', checkedValues('addon_services').join(', ') || 'None selected']]],
    ];
    var html = '';
    blocks.forEach(function(b) {
      html += '<div class="review-block"><h4>' + b[0] + '</h4>';
      b[1].forEach(function(line) {
        html += '<div class="line"><span class="k">' + line[0] + ':</span> ' + line[1] + '</div>';
      });
      html += '</div>';
    });
    document.getElementById('reviewContent').innerHTML = html;
  }

  document.getElementById('discoveryForm').addEventListener('submit', function(e) {
    var consent = document.getElementById('gdprConsent');
    if (!consent.checked) {
      e.preventDefault();
      document.getElementById('consentError').classList.add('show');
      consent.focus();
    }
  });
  document.getElementById('gdprConsent').addEventListener('change', function() {
    if (this.checked) document.getElementById('consentError').classList.remove('show');
  });

  updateSubsections();
  showCurrent();
})();
</script>
<?php endif; ?>
</body>
</html>