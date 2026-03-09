<?php
$siteName = $settings->getSetting('site_name', 'Boutique');
$contactEmail = $settings->getSetting('contact_email', 'contact@example.com');
$contactPhone = $settings->getSetting('contact_phone', '');
$terms = $settings->getSetting('terms_of_service', '');
?>

<section class="py-8 sm:py-12">
    <div class="max-w-3xl space-y-4">
        <p class="text-xs font-semibold tracking-[0.3em] uppercase text-brand-500">Terms</p>
        <h1 class="text-2xl sm:text-4xl font-semibold tracking-tight text-brand-900">Terms of Service</h1>

        <?php if (!empty($terms)): ?>
            <div class="text-sm text-brand-500 leading-relaxed">
                <?php echo nl2br(htmlspecialchars($terms)); ?>
            </div>
        <?php else: ?>
            <div class="text-sm text-brand-500 leading-relaxed space-y-3">
                <p>By placing an order with <?php echo htmlspecialchars($siteName); ?>, you agree to these terms.</p>
                <p><span class="font-semibold text-brand-900">Orders:</span> We may contact you to confirm details before shipping.</p>
                <p><span class="font-semibold text-brand-900">Delivery:</span> Delivery times vary by location and availability.</p>
                <p><span class="font-semibold text-brand-900">Returns:</span> Please contact us to request an exchange/return.</p>
                <p><span class="font-semibold text-brand-900">Pricing:</span> Prices can change without notice; confirmed orders keep their price.</p>
            </div>
        <?php endif; ?>

        <div class="rounded-2xl border border-brand-100 bg-white/80 p-4 text-xs text-brand-500">
            <div class="font-semibold text-brand-900 mb-1">Support</div>
            <div>Email: <?php echo htmlspecialchars($contactEmail); ?></div>
            <?php if (!empty($contactPhone)): ?>
                <div>Phone: <?php echo htmlspecialchars($contactPhone); ?></div>
            <?php endif; ?>
        </div>
    </div>
</section>

