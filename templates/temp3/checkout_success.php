<?php
$order = $_GET['order'] ?? '';
$siteName = $settings->getSetting('site_name', 'Boutique');
?>

<section class="py-10 sm:py-14">
    <div class="max-w-2xl mx-auto text-center">
        <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-black text-white flex items-center justify-center shadow-soft">
            <span class="text-lg font-semibold">✓</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-brand-900">
            Ya3tik essa7a — order confirmed
        </h1>
        <p class="text-sm text-brand-500 mt-2">
            Thank you for shopping with <?php echo htmlspecialchars($siteName); ?>.
        </p>

        <?php if (!empty($order)): ?>
            <div class="mt-5 inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white px-4 py-2 text-xs text-brand-700">
                <span class="text-brand-400">Order</span>
                <span class="font-semibold text-brand-900"><?php echo htmlspecialchars($order); ?></span>
            </div>
        <?php endif; ?>

        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="index.php?page=shop"
               class="inline-flex items-center justify-center rounded-full bg-black text-white px-6 py-2.5 text-xs font-medium shadow-soft hover:bg-brand-900 transition">
                Continue shopping
            </a>
            <a href="index.php"
               class="inline-flex items-center justify-center rounded-full border border-brand-200 bg-white px-6 py-2.5 text-xs font-medium text-brand-700 hover:bg-brand-50 transition">
                Back home
            </a>
        </div>
    </div>
</section>

