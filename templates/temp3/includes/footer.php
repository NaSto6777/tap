<?php
// TEMP3 — Footer
// Assumes $activeTemplate is set in index.php
?>
    </main>

    <footer class="border-t border-brand-100 bg-white/90">
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

