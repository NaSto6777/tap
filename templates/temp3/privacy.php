<?php
$siteName = $settings->getSetting('site_name', 'Boutique');
$contactEmail = $settings->getSetting('contact_email', 'privacy@example.com');
$contactPhone = $settings->getSetting('contact_phone', '');
$policy = $settings->getSetting('privacy_policy', '');
?>

<section class="py-8 sm:py-12">
    <div class="max-w-3xl space-y-4">
        <p class="text-xs font-semibold tracking-[0.3em] uppercase text-brand-500">Privacy</p>
        <h1 class="text-2xl sm:text-4xl font-semibold tracking-tight text-brand-900">Privacy Policy</h1>

        <?php if (!empty($policy)): ?>
            <div class="text-sm text-brand-500 leading-relaxed">
                <?php echo nl2br(htmlspecialchars($policy)); ?>
            </div>
        <?php else: ?>
            <div class="text-sm text-brand-500 leading-relaxed space-y-3">
                <p>We respect your privacy. We only collect information needed to process your order and support you.</p>
                <p><span class="font-semibold text-brand-900">Data we may collect:</span> name, phone, email (optional), shipping address, and order details.</p>
                <p><span class="font-semibold text-brand-900">How we use it:</span> to deliver your order, confirm details, and improve service.</p>
                <p><span class="font-semibold text-brand-900">Sharing:</span> only with delivery partners when required to ship your order.</p>
                <p><span class="font-semibold text-brand-900">Retention:</span> kept only as long as necessary for order and accounting purposes.</p>
            </div>
        <?php endif; ?>

        <div class="rounded-2xl border border-brand-100 bg-white/80 p-4 text-xs text-brand-500">
            <div class="font-semibold text-brand-900 mb-1">Contact</div>
            <div>Email: <?php echo htmlspecialchars($contactEmail); ?></div>
            <?php if (!empty($contactPhone)): ?>
                <div>Phone: <?php echo htmlspecialchars($contactPhone); ?></div>
            <?php endif; ?>
        </div>
    </div>
</section>

