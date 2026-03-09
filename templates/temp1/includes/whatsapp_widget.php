<?php
require_once __DIR__ . '/../../../config/plugin_helper.php';

$pluginHelper = new PluginHelper();

if (!$pluginHelper->isPluginActive('whatsapp')) {
    return;
}

$whatsappConfig = $pluginHelper->getPluginConfig('whatsapp');
$phone = $whatsappConfig['whatsapp_phone'] ?? '';
$message = $whatsappConfig['whatsapp_message'] ?? 'Hi! How can we help you?';

if (empty($phone)) {
    return;
}

// Clean phone number (remove spaces, dashes, etc.)
$phone = preg_replace('/[^0-9+]/', '', $phone);
$encodedMessage = urlencode($message);
$whatsappUrl = "https://wa.me/{$phone}?text={$encodedMessage}";
?>

<!-- WhatsApp Chat Widget -->
<div id="whatsapp-widget" class="whatsapp-widget">
    <div class="whatsapp-button" onclick="toggleWhatsAppChat()">
        <i class="fab fa-whatsapp"></i>
    </div>
    
    <div class="whatsapp-chat" id="whatsapp-chat">
        <div class="whatsapp-chat-header">
            <div class="whatsapp-avatar">
                <i class="fab fa-whatsapp"></i>
            </div>
            <div class="whatsapp-info">
                <h6>WhatsApp Support</h6>
                <small>Online now</small>
            </div>
            <button class="whatsapp-close" onclick="toggleWhatsAppChat()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="whatsapp-chat-body">
            <div class="whatsapp-message">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        </div>
        
        <div class="whatsapp-chat-footer">
            <a href="<?php echo $whatsappUrl; ?>" target="_blank" class="whatsapp-start-chat">
                <i class="fab fa-whatsapp"></i> Start Chat
            </a>
        </div>
    </div>
</div>

<style>
.whatsapp-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    font-family: Arial, sans-serif;
}

.whatsapp-button {
    width: 60px;
    height: 60px;
    background: #25D366;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
    transition: all 0.3s ease;
    color: white;
    font-size: 28px;
}

.whatsapp-button:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(37, 211, 102, 0.6);
}

.whatsapp-chat {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 320px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    display: none;
    overflow: hidden;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.whatsapp-chat.show {
    display: block;
}

.whatsapp-chat-header {
    background: #075E54;
    color: white;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.whatsapp-avatar {
    width: 40px;
    height: 40px;
    background: #25D366;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.whatsapp-info h6 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.whatsapp-info small {
    opacity: 0.8;
    font-size: 12px;
}

.whatsapp-close {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 5px;
    margin-left: auto;
    font-size: 16px;
}

.whatsapp-chat-body {
    padding: 15px;
    min-height: 80px;
}

.whatsapp-message {
    background: #E5DDD5;
    padding: 10px 12px;
    border-radius: 8px;
    margin-bottom: 10px;
}

.whatsapp-message p {
    margin: 0;
    font-size: 14px;
    line-height: 1.4;
}

.whatsapp-chat-footer {
    padding: 15px;
    border-top: 1px solid #E5DDD5;
}

.whatsapp-start-chat {
    display: block;
    background: #25D366;
    color: white;
    text-decoration: none;
    padding: 12px 20px;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    transition: background 0.3s ease;
}

.whatsapp-start-chat:hover {
    background: #128C7E;
    color: white;
    text-decoration: none;
}

.whatsapp-start-chat i {
    margin-right: 8px;
}

/* Mobile responsiveness */
@media (max-width: 480px) {
    .whatsapp-widget {
        bottom: 15px;
        right: 15px;
    }
    
    .whatsapp-button {
        width: 50px;
        height: 50px;
        font-size: 24px;
    }
    
    .whatsapp-chat {
        width: 280px;
        right: -10px;
    }
}
</style>

<script>
function toggleWhatsAppChat() {
    const chat = document.getElementById('whatsapp-chat');
    chat.classList.toggle('show');
}

// Close chat when clicking outside
document.addEventListener('click', function(e) {
    const widget = document.getElementById('whatsapp-widget');
    const chat = document.getElementById('whatsapp-chat');
    
    if (!widget.contains(e.target) && chat.classList.contains('show')) {
        chat.classList.remove('show');
    }
});
</script>
