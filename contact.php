<?php
// -----------------------------------------------------------------------
// Contact form handler
// -----------------------------------------------------------------------

$formFrom     = 'Luke Higgins Music <hello@luke-higgins.co.za>';
$notifyTo     = 'lukesterhi@gmail.com';
$siteUrl      = 'https://luke-higgins.co.za';

$sent   = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Honeypot — bots fill this hidden field; humans don't
    if (!empty($_POST['website'])) {
        // Silently pretend success so bots get no signal
        $sent = true;
    } else {
        $name       = trim($_POST['name']       ?? '');
        $email      = trim($_POST['email']      ?? '');
        $phone      = trim($_POST['phone']      ?? '');
        $instrument = trim($_POST['instrument'] ?? '');
        $message    = trim($_POST['message']    ?? '');

        // Validation
        if ($name === '') {
            $errors[] = 'Please enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($message === '') {
            $errors[] = 'Please enter a message.';
        }

        if (empty($errors)) {
            $instrumentLabels = [
                'piano'         => 'Piano Lessons',
                'guitar'        => 'Guitar Lessons',
                'drums'         => 'Drum Lessons',
                'vocals'        => 'Vocal Lessons',
                'bass'          => 'Bass Lessons',
                'ukulele'       => 'Ukulele Lessons',
                'performance'   => 'Performance Booking',
                'audio'         => 'Audio Work',
                'transcription' => 'Music Transcription',
                'other'         => 'Other',
            ];
            $instrumentLabel = $instrumentLabels[$instrument] ?? $instrument;

            $safeName       = htmlspecialchars($name,    ENT_QUOTES, 'UTF-8');
            $safeEmail      = htmlspecialchars($email,   ENT_QUOTES, 'UTF-8');
            $safePhone      = htmlspecialchars($phone,   ENT_QUOTES, 'UTF-8');
            $safeInstrument = htmlspecialchars($instrumentLabel, ENT_QUOTES, 'UTF-8');
            $safeMessage    = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

            // ---- Notification email to Luke ----
            $notifySubject = 'New contact: ' . $name . ($instrument !== '' ? ' — ' . $instrumentLabel : '');
            $notifyHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#1a1a18;background:#f8f7f4;padding:0;margin:0;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f7f4;padding:32px 16px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <tr><td style="background:#1a3d1c;padding:20px 28px;">
          <p style="margin:0;color:#ffffff;font-size:18px;font-weight:700;">New Contact Form Submission</p>
          <p style="margin:4px 0 0;color:rgba(255,255,255,.65);font-size:12px;">luke-higgins.co.za</p>
        </td></tr>
        <tr><td style="padding:24px 28px;">
          <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
            <tr>
              <td style="padding:8px 0;border-bottom:1px solid #f0ede4;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#888;width:120px;">Name</td>
              <td style="padding:8px 0;border-bottom:1px solid #f0ede4;font-size:14px;color:#1a1a18;">{$safeName}</td>
            </tr>
            <tr>
              <td style="padding:8px 0;border-bottom:1px solid #f0ede4;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#888;">Email</td>
              <td style="padding:8px 0;border-bottom:1px solid #f0ede4;font-size:14px;"><a href="mailto:{$safeEmail}" style="color:#1a3d1c;">{$safeEmail}</a></td>
            </tr>
HTML;
            if ($phone !== '') {
                $notifyHtml .= <<<HTML
            <tr>
              <td style="padding:8px 0;border-bottom:1px solid #f0ede4;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#888;">Phone</td>
              <td style="padding:8px 0;border-bottom:1px solid #f0ede4;font-size:14px;color:#1a1a18;">{$safePhone}</td>
            </tr>
HTML;
            }
            if ($instrumentLabel !== '') {
                $notifyHtml .= <<<HTML
            <tr>
              <td style="padding:8px 0;border-bottom:1px solid #f0ede4;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#888;">Interested In</td>
              <td style="padding:8px 0;border-bottom:1px solid #f0ede4;font-size:14px;color:#1a1a18;">{$safeInstrument}</td>
            </tr>
HTML;
            }
            $notifyHtml .= <<<HTML
          </table>
          <p style="margin:20px 0 6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#888;">Message</p>
          <div style="background:#f8f7f4;padding:14px 18px;border-radius:6px;font-size:14px;line-height:1.7;color:#1a1a18;">{$safeMessage}</div>
        </td></tr>
        <tr><td style="background:#f8f7f4;padding:14px 28px;border-top:1px solid #f0ede4;">
          <p style="margin:0;font-size:12px;color:#aaa;">Reply to this email to respond directly to {$safeName}.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

            $notifyHeaders  = 'From: ' . $formFrom . "\r\n";
            $notifyHeaders .= 'Reply-To: ' . $name . ' <' . $email . '>' . "\r\n";
            $notifyHeaders .= 'MIME-Version: 1.0' . "\r\n";
            $notifyHeaders .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";

            $notifySent = mail($notifyTo, $notifySubject, $notifyHtml, $notifyHeaders);

            // ---- Confirmation email to the visitor ----
            $confirmSubject = 'Thanks for getting in touch — Luke Higgins Music';
            $confirmHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#1a1a18;background:#f8f7f4;padding:0;margin:0;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f7f4;padding:32px 16px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <tr><td style="background:#1a3d1c;padding:24px 28px;">
          <p style="margin:0;color:#ffffff;font-size:20px;font-weight:700;">Luke Higgins Music</p>
          <p style="margin:4px 0 0;color:rgba(255,255,255,.65);font-size:12px;">Music Lessons · Sandton &amp; Fourways, Johannesburg</p>
        </td></tr>
        <tr><td style="padding:28px 28px 24px;">
          <p style="margin:0 0 16px;font-size:16px;color:#1a1a18;">Hi {$safeName},</p>
          <p style="margin:0 0 16px;font-size:14px;line-height:1.7;color:#444;">
            Thanks for reaching out! I've received your message and will get back to you as soon as possible — usually within 24 hours.
          </p>
          <p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#444;">
            In the meantime, feel free to reach out via WhatsApp on
            <a href="https://wa.me/27766707711" style="color:#1a3d1c;font-weight:600;">+27 76 670 7711</a> if it's urgent.
          </p>
          <p style="margin:0;font-size:14px;color:#1a1a18;">Cheers,<br><strong>Luke Higgins</strong></p>
        </td></tr>
        <tr><td style="background:#f8f7f4;padding:14px 28px;border-top:1px solid #f0ede4;">
          <p style="margin:0;font-size:11px;color:#aaa;line-height:1.6;">
            You are receiving this because you submitted the contact form at
            <a href="{$siteUrl}" style="color:#aaa;">{$siteUrl}</a>.
            Your information is used solely to respond to your enquiry, in accordance with POPIA.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

            $confirmHeaders  = 'From: ' . $formFrom . "\r\n";
            $confirmHeaders .= 'Reply-To: ' . $notifyTo . "\r\n";
            $confirmHeaders .= 'MIME-Version: 1.0' . "\r\n";
            $confirmHeaders .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";

            mail($email, $confirmSubject, $confirmHtml, $confirmHeaders);

            if ($notifySent) {
                $sent = true;
            } else {
                $errors[] = 'Your message could not be sent at the moment. Please email <a href="mailto:lukesterhi@gmail.com">lukesterhi@gmail.com</a> or WhatsApp +27 76 670 7711 directly.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | Luke Higgins — Music Lessons in Sandton &amp; Fourways</title>
    <meta name="description" content="Get in touch with Luke Higgins to book music lessons in Sandton and Fourways, Johannesburg. Guitar, piano, theory and more for all ages.">
    <meta name="keywords" content="contact Luke Higgins, book music lessons Sandton, music teacher Fourways, guitar lessons enquiry Johannesburg">
    <meta name="robots" content="index, follow">
    <meta name="author" content="Luke Higgins">
    <link rel="canonical" href="https://luke-higgins.co.za/Contact">
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://luke-higgins.co.za/Contact">
    <meta property="og:title" content="Contact | Luke Higgins — Music Lessons in Sandton &amp; Fourways">
    <meta property="og:description" content="Get in touch to book music lessons in Sandton and Fourways, Johannesburg. Guitar, piano, theory and more for all ages.">
    <meta property="og:image" content="https://luke-higgins.co.za/assets/media/20220704_111842_IMG_7951.JPG">
    <meta property="og:site_name" content="Luke Higgins Music">
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Contact | Luke Higgins — Music Lessons">
    <meta name="twitter:description" content="Get in touch to book music lessons in Sandton and Fourways, Johannesburg.">
    <meta name="twitter:image" content="https://luke-higgins.co.za/assets/media/20220704_111842_IMG_7951.JPG">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/modern-touches.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600&family=Raleway:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="contact-page">
    <header class="page-header">
        <nav class="main-nav">
            <div class="site-title-area">
                <a href="/" class="home-link">Luke Higgins</a>
                <p class="nav-subtitle">Musician & Educator</p>
            </div>
            <button class="mobile-nav-toggle" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>
            <div class="nav-links">
                <a href="/Bio">BIO</a>
                <a href="/Lessons">LESSONS</a>
                <a href="/Contact" class="active">CONTACT</a>
                <button class="theme-toggle">
                    <span class="theme-toggle-icon"><i class="fas fa-sun"></i></span>
                    <span class="theme-toggle-text">Light</span>
                </button>
            </div>
        </nav>
    </header>

    <main class="page-main">
        <div class="page-container">
            <section class="contact-section">
                <div class="contact-content">
                    <div class="contact-info">
                        <h1>Get In Touch</h1>
                        <p class="contact-intro">
                            Ready to start your musical journey?
                            <br/>
                            Have questions about lessons or pricing?
                            <br/>
                            I'd love to hear from you.</p>

                        <div class="contact-methods">
                            <div class="contact-method">
                                <div class="method-icon"><i class="fas fa-envelope"></i></div>
                                <div class="method-info">
                                    <h3>Email</h3>
                                    <p><a href="mailto:lukesterhi@gmail.com" class="email-link">lukesterhi@gmail.com</a></p>
                                </div>
                            </div>
                            <div class="contact-method">
                                <div class="method-icon"><i class="fab fa-whatsapp"></i></div>
                                <div class="method-info">
                                    <h3>WhatsApp</h3>
                                    <p><a href="https://wa.me/27766707711" target="_blank" class="whatsapp-link">+27 76 670 7711</a></p>
                                </div>
                            </div>
                            <div class="contact-method">
                                <div class="method-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="method-info">
                                    <h3>Location</h3>
                                    <p>91 Lachlan Rd, Glenferness AH, Sandton, 2191</p>
                                    <p class="location-note">Available for lessons and performances</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="contact-form">
                        <h2>Send a Message</h2>

                        <?php if ($sent): ?>
                            <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:18px 20px;border-radius:8px;margin-bottom:20px;font-size:.9375rem;line-height:1.6;">
                                <strong>Message sent!</strong> Thanks for reaching out — I'll be in touch shortly.
                                A confirmation has been sent to your email address.
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:14px 18px;border-radius:8px;margin-bottom:20px;font-size:.875rem;line-height:1.6;">
                                <?= implode('<br>', $errors) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$sent): ?>
                        <form class="message-form" method="POST" action="">

                            <!-- Honeypot — hidden from humans, filled by bots -->
                            <div style="display:none;" aria-hidden="true">
                                <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
                            </div>

                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" required
                                       value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required
                                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone (optional)</label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-group">
                                <label for="instrument">Interested In</label>
                                <select id="instrument" name="instrument">
                                    <option value="">Select an option</option>
                                    <?php
                                    $options = [
                                        'piano'         => 'Piano Lessons',
                                        'guitar'        => 'Guitar Lessons',
                                        'drums'         => 'Drum Lessons',
                                        'vocals'        => 'Vocal Lessons',
                                        'bass'          => 'Bass Lessons',
                                        'ukulele'       => 'Ukulele Lessons',
                                        'performance'   => 'Performance Booking',
                                        'audio'         => 'Audio Work',
                                        'transcription' => 'Music Transcription',
                                        'other'         => 'Other',
                                    ];
                                    $selectedInstrument = $_POST['instrument'] ?? '';
                                    foreach ($options as $val => $label):
                                    ?>
                                        <option value="<?= $val ?>" <?= $selectedInstrument === $val ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" rows="6"
                                          placeholder="Tell me about your musical goals, experience level, pricing questions, or any other inquiries..."
                                          required><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <button type="submit" class="submit-btn">Send Message</button>
                        </form>
                        <?php endif; ?>

                        <p style="margin-top:16px;font-size:.78rem;color:var(--theme-text-secondary);line-height:1.5;">
                            Your information is used solely to respond to your enquiry and will not be shared with third parties,
                            in accordance with the Protection of Personal Information Act (POPIA).
                        </p>
                    </div>
                </div>

                <!-- Studio Gallery Section - Full Width -->
                <div class="studio-gallery-section">
                    <div class="studio-gallery-carousel">
                        <div class="carousel-container">
                            <button class="carousel-btn prev-btn" aria-label="Previous photo">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div class="carousel-track-container">
                                <div class="carousel-track" id="studioCarouselTrack">
                                    <!-- Photos will be dynamically loaded here -->
                                </div>
                            </div>
                            <button class="carousel-btn next-btn" aria-label="Next photo">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <div class="carousel-indicators" id="carouselIndicators">
                            <!-- Indicators will be dynamically generated -->
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Gallery Lightbox Modal -->
    <div class="gallery-lightbox" id="galleryLightbox">
        <div class="lightbox-content">
            <span class="lightbox-close" id="lightboxClose">&times;</span>
            <img src="" alt="" id="lightboxImage">
            <div class="lightbox-caption" id="lightboxCaption"></div>
        </div>
    </div>

    <footer class="page-footer">
        <p>&copy; <span id="current-year">2024</span> Luke Higgins</p>
        <p class="dev-credit">Developed by Adamstribe Technlogies</p>
    </footer>

    <script src="assets/js/url-handler.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/mobile-nav.js"></script>
    <script src="assets/js/studio-gallery.js"></script>
</body>
</html>
