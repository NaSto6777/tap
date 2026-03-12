<?php
// TEMP3 — Footer
// Assumes $activeTemplate is set in index.php
?>
    </main>

    <!-- Sticky bottom navigation — mobile only (Home, Shop, Cart with badge, WhatsApp) -->
    <?php
    $whatsapp_url = '';
    if (file_exists(__DIR__ . '/../../config/plugin_helper.php')) {
        require_once __DIR__ . '/../../config/plugin_helper.php';
        $ph = new PluginHelper();
        if ($ph->isPluginActive('whatsapp')) {
            $cfg = $ph->getPluginConfig('whatsapp');
            $phoneRaw = (string)($cfg['whatsapp_phone'] ?? '');
            $message  = (string)($cfg['whatsapp_message'] ?? 'Hi! How can we help you?');
            $phone = preg_replace('/\D+/', '', $phoneRaw);
            if (!empty($phone)) {
                $whatsapp_url = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
            }
        }
    }
    $current_page = $page ?? '';
    $show_bottom_nav = ($current_page !== 'product_view'); // hide on product view so Add to Cart bar is visible
    ?>
    <?php if ($show_bottom_nav): ?>
    <nav class="fixed bottom-0 inset-x-0 z-40 bt-safe-area-bottom border-t border-brand-200 bg-white/95 backdrop-blur-sm md:hidden" aria-label="Mobile navigation">
        <div class="max-w-2xl mx-auto flex items-stretch justify-between text-[11px] font-medium text-brand-500">
            <a href="index.php" class="flex-1 flex flex-col items-center justify-center py-3 <?php echo $current_page === 'home' ? 'text-brand-900' : 'hover:text-brand-900'; ?>">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Home</span>
            </a>
            <a href="index.php?page=shop" class="flex-1 flex flex-col items-center justify-center py-3 <?php echo $current_page === 'shop' ? 'text-brand-900' : 'hover:text-brand-900'; ?>">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <span>Shop</span>
            </a>
            <a href="index.php?page=cart" class="flex-1 flex flex-col items-center justify-center py-3 relative">
                <span class="relative inline-block mb-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span class="bt-cart-count absolute -top-2 -right-2 min-w-[18px] h-[18px] flex items-center justify-center rounded-full bg-black text-[10px] font-semibold text-white">0</span>
                </span>
                <span>Cart</span>
            </a>
            <?php if ($whatsapp_url !== ''): ?>
            <a href="<?php echo htmlspecialchars($whatsapp_url); ?>" target="_blank" rel="noreferrer" class="flex-1 flex flex-col items-center justify-center py-3 text-[#25D366] hover:opacity-90">
                <svg class="w-5 h-5 mb-0.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                <span>WhatsApp</span>
            </a>
            <?php else: ?>
            <span class="flex-1 flex flex-col items-center justify-center py-3 text-brand-300"><span>—</span></span>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>

    <footer class="border-t border-brand-100 bg-white/90 pb-20 md:pb-0">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <div>
                <p class="text-xs font-semibold text-brand-800"><?php echo htmlspecialchars($settings->getSetting('site_name', 'Boutique')); ?></p>
                <p class="text-[11px] text-brand-400 mt-1 max-w-sm">
                    <?php echo htmlspecialchars($settings->getSetting('site_description', 'Premium pieces, clean design, and a smooth checkout experience.')); ?>
                </p>
                <div class="mt-3 flex items-center gap-3 text-[11px] text-brand-400">
                    <a href="index.php?page=terms" class="hover:text-brand-700 transition">Terms</a>
                    <a href="index.php?page=privacy" class="hover:text-brand-700 transition">Privacy</a>
                    <a href="index.php?page=contact" class="hover:text-brand-700 transition">Contact</a>
                </div>
            </div>

            <div class="rounded-2xl border border-brand-100 bg-white/80 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-400 mb-2">Newsletter</p>
                <form id="bt-newsletter-form" class="flex items-center gap-2">
                    <input
                        type="email"
                        name="email"
                        required
                        placeholder="you@email.com"
                        class="flex-1 rounded-full border border-brand-200 bg-white px-3 py-2 text-xs text-brand-800 placeholder:text-brand-300 focus:outline-none focus:ring-1 focus:ring-brand-300"
                    >
                    <button
                        type="submit"
                        class="rounded-full bg-black text-white px-4 py-2 text-[11px] font-medium shadow-soft hover:bg-brand-900 transition"
                    >
                        Join
                    </button>
                </form>
                <p class="text-[11px] text-brand-400 mt-2">
                    Get drops & offers. No spam.
                </p>
            </div>
        </div>
    </footer>

    <?php
    $whatsappWidgetPath = __DIR__ . '/whatsapp_widget.php';
    if (file_exists($whatsappWidgetPath)) {
        include $whatsappWidgetPath;
    }
    ?>

    <!-- Temp3 app script (AJAX cart + toasts) -->
    <script src="templates/<?php echo htmlspecialchars($activeTemplate); ?>/assets/js/app.js"></script>
  </body>
</html>

