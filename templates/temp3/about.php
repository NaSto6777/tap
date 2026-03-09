<?php
$siteName = $settings->getSetting('site_name', 'Boutique');
$about = $settings->getSetting('about_text', '');
?>

<section class="py-8 sm:py-12">
    <div class="max-w-3xl">
        <p class="text-xs font-semibold tracking-[0.3em] uppercase text-brand-500 mb-3">About</p>
        <h1 class="text-2xl sm:text-4xl font-semibold tracking-tight text-brand-900 mb-4">
            <?php echo htmlspecialchars($siteName); ?>
        </h1>
        <p class="text-sm text-brand-500 leading-relaxed">
            <?php
            if (!empty($about)) {
                echo nl2br(htmlspecialchars($about));
            } else {
                echo "We’re a boutique store focused on thoughtful products, clean design, and a smooth shopping experience.";
            }
            ?>
        </p>

        <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-2xl border border-brand-100 bg-white/80 p-4">
                <div class="text-sm font-semibold text-brand-900">Curated</div>
                <div class="text-xs text-brand-400 mt-1">Only products we’d recommend to friends.</div>
            </div>
            <div class="rounded-2xl border border-brand-100 bg-white/80 p-4">
                <div class="text-sm font-semibold text-brand-900">Fast</div>
                <div class="text-xs text-brand-400 mt-1">Optimized mobile-first experience.</div>
            </div>
            <div class="rounded-2xl border border-brand-100 bg-white/80 p-4">
                <div class="text-sm font-semibold text-brand-900">Trusted</div>
                <div class="text-xs text-brand-400 mt-1">Clear policies and support.</div>
            </div>
        </div>
    </div>
</section>

