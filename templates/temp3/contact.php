<?php
/**
 * TEMP3 — Contact
 * Customer message form with CSRF.
 */

require_once __DIR__ . '/../../config/plugin_helper.php';
require_once __DIR__ . '/../../config/email_helper.php';
require_once __DIR__ . '/../../config/CsrfHelper.php';

$pluginHelper = new PluginHelper();
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfHelper::validateToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh and try again.';
    }

    $name    = trim((string)($_POST['name'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($error_message === '' && ($name === '' || $email === '' || $subject === '' || $message === '')) {
        $error_message = 'Please fill in all required fields.';
    }

    if ($error_message === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    }

    // reCAPTCHA plugin (optional)
    if ($error_message === '' && $pluginHelper->isPluginActive('recaptcha')) {
        $recaptcha_token = $_POST['g-recaptcha-response'] ?? '';
        if (!$pluginHelper->verifyRecaptcha($recaptcha_token)) {
            $error_message = 'Please complete the reCAPTCHA verification.';
        }
    }

    if ($error_message === '') {
        try {
            $emailService = new EmailService();
            $ok = $emailService->sendContactFormNotification([
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message,
            ]);
            if ($ok) {
                $success_message = 'Ya3tik essa7a! Your message was sent. We will reply soon.';
                $_POST = [];
            } else {
                $error_message = 'Sorry, there was an error sending your message. Please try again.';
            }
        } catch (Exception $e) {
            $error_message = 'Contact service is not available right now. Please try again later.';
        }
    }
}

$v = fn($k) => htmlspecialchars((string)($_POST[$k] ?? ''), ENT_QUOTES, 'UTF-8');
?>

<section class="py-8 sm:py-12">
    <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="rounded-2xl border border-brand-100 bg-white/85 p-5">
            <p class="text-xs font-semibold tracking-[0.3em] uppercase text-brand-500 mb-3">Contact</p>
            <h1 class="text-2xl font-semibold tracking-tight text-brand-900 mb-2">Get in touch</h1>
            <p class="text-sm text-brand-500 leading-relaxed mb-6">
                Send us a message and we’ll respond as soon as possible.
            </p>

            <div class="space-y-2 text-xs text-brand-500">
                <div><span class="font-semibold text-brand-900">Email:</span> <?php echo htmlspecialchars($settings->getSetting('contact_email', 'contact@example.com')); ?></div>
                <div><span class="font-semibold text-brand-900">Phone:</span> <?php echo htmlspecialchars($settings->getSetting('contact_phone', '')); ?></div>
            </div>

            <div class="mt-6 flex items-center gap-2 text-[11px] text-brand-400">
                <a class="hover:text-brand-700" href="<?php echo htmlspecialchars($settings->getSetting('instagram_url', '#')); ?>">Instagram</a>
                <span>·</span>
                <a class="hover:text-brand-700" href="<?php echo htmlspecialchars($settings->getSetting('facebook_url', '#')); ?>">Facebook</a>
            </div>
        </div>

        <div class="rounded-2xl border border-brand-100 bg-white/85 p-5">
            <?php if ($success_message): ?>
                <div class="mb-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-xs text-green-700">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-3">
                <?php echo CsrfHelper::getTokenField(); ?>

                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-1">Name *</label>
                    <input name="name" value="<?php echo $v('name'); ?>"
                           class="w-full rounded-2xl border border-brand-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-300" required>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-1">Email *</label>
                    <input type="email" name="email" value="<?php echo $v('email'); ?>"
                           class="w-full rounded-2xl border border-brand-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-300" required>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-1">Subject *</label>
                    <input name="subject" value="<?php echo $v('subject'); ?>"
                           class="w-full rounded-2xl border border-brand-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-300" required>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-1">Message *</label>
                    <textarea name="message" rows="5"
                              class="w-full rounded-2xl border border-brand-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-300" required><?php echo $v('message'); ?></textarea>
                </div>

                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-black text-white px-5 py-2.5 text-xs font-medium shadow-soft hover:bg-brand-900 transition">
                    Send message
                </button>
            </form>
        </div>
    </div>
</section>

