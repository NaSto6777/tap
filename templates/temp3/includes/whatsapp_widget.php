<?php
require_once __DIR__ . '/../../../config/plugin_helper.php';

$pluginHelper = new PluginHelper();
if (!$pluginHelper->isPluginActive('whatsapp')) {
    return;
}

$cfg = $pluginHelper->getPluginConfig('whatsapp');
$phoneRaw = (string)($cfg['whatsapp_phone'] ?? '');
$message  = (string)($cfg['whatsapp_message'] ?? 'Hi! How can we help you?');

// Normalize phone to digits only (WhatsApp wa.me expects international format)
$phone = preg_replace('/\D+/', '', $phoneRaw);
if (empty($phone)) {
    return;
}

$encoded = rawurlencode($message);
$url = "https://wa.me/{$phone}?text={$encoded}";
?>

<a href="<?php echo htmlspecialchars($url); ?>"
   target="_blank"
   rel="noreferrer"
   class="fixed z-50 right-4 bottom-24 sm:bottom-6 inline-flex items-center justify-center h-12 w-12 rounded-full bg-[#25D366] shadow-soft hover:scale-[1.03] transition"
   aria-label="Chat on WhatsApp">
    <span class="text-white font-semibold">WA</span>
</a>

