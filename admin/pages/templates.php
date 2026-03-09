<?php
require_once __DIR__ . '/../../config/language.php';

$database = new Database();
$conn = $database->getConnection();
$settings = new Settings();

// Initialize language
Language::init();
$t = function($key, $default = null) {
    return Language::t($key, $default);
};

$save_success = false;
$save_error = '';

// Handle template switching
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'switch_template') {
        $template_name = $_POST['template_name'] ?? '';
        if (!empty($template_name)) {
            $settings->setSetting('active_template', $template_name);
            $success_msg = $t('template_switched', 'Template switched to') . ' ' . $template_name . ' ' . $t('successfully', 'successfully') . '!';
            header('Location: ?page=templates&success=' . urlencode($success_msg));
            exit;
        }
    }
}

// Get current active template
$active_template = $settings->getSetting('active_template', 'temp1');

// Get available templates
$templates_dir = '../templates/';
$available_templates = [];

if (is_dir($templates_dir)) {
    $dirs = scandir($templates_dir);
    foreach ($dirs as $dir) {
        if ($dir != '.' && $dir != '..' && is_dir($templates_dir . $dir)) {
            $template_info = [
                'name' => $dir,
                'path' => $templates_dir . $dir,
                'active' => $dir == $active_template
            ];
            
            // Check if template has required files
            $required_files = ['includes/header.php', 'includes/footer.php', 'home.php', 'shop.php'];
            $template_info['complete'] = true;
            $missing_files = [];
            foreach ($required_files as $file) {
                if (!file_exists($templates_dir . $dir . '/' . $file)) {
                    $template_info['complete'] = false;
                    $missing_files[] = $file;
                }
            }
            $template_info['missing_files'] = $missing_files;
            
            // Count template files
            $file_count = 0;
            if (is_dir($templates_dir . $dir)) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templates_dir . $dir));
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $file_count++;
                    }
                }
            }
            $template_info['file_count'] = $file_count;
            
            $available_templates[] = $template_info;
        }
    }
}

// Get template preview image
function getTemplatePreview($template_name) {
    $projectRoot = dirname(__DIR__); // admin/
    $projectRoot = dirname($projectRoot); // project root

    $candidatePaths = [
        "templates/$template_name/assets/images/preview.jpg",
        "templates/$template_name/assets/images/preview.png",
        "templates/$template_name/preview.jpg",
        "templates/$template_name/assets/images/placeholder.jpg",
        "templates/$template_name/placeholder.jpg",
        "uploads/templates/$template_name.jpg",
        "uploads/templates/$template_name.png",
    ];
    
    foreach ($candidatePaths as $relativePath) {
        $absolutePath = $projectRoot . '/' . $relativePath;
        if (file_exists($absolutePath)) {
            return '../' . $relativePath;
        }
    }
    return "../uploads/placeholder.jpg";
}
?>

<!-- Modern Template Management Interface -->
<div class="template-management-container">
    
    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Current Active Template -->
    <div class="active-template-card">
        <div class="active-badge">
            <i class="fas fa-check-circle"></i>
            <?php echo $t('currently_active', 'Currently Active'); ?>
            </div>
        <div class="template-preview-large">
                        <img src="<?php echo getTemplatePreview($active_template); ?>" 
                 onerror="this.src='../uploads/placeholder.jpg'"
                 alt="<?php echo $active_template; ?>">
                    </div>
        <div class="template-info-large">
            <h2><?php echo ucfirst(str_replace('_', ' ', $active_template)); ?></h2>
            <p><?php echo $t('current_active_template_desc', 'This is your current active template that visitors see'); ?></p>
            <div class="template-actions">
                <a href="../index.php" target="_blank" class="btn-action primary">
                    <i class="fas fa-eye"></i>
                    <?php echo $t('preview_live', 'Preview Live'); ?>
                </a>
                <button class="btn-action secondary" onclick="alert('<?php echo $t('template_editor_coming_soon', 'Template editor coming soon!'); ?>')">
                    <i class="fas fa-code"></i>
                    <?php echo $t('edit_files', 'Edit Files'); ?>
                            </button>
                        </div>
                    </div>
                    </div>

    <!-- Available Templates -->
    <div class="section-header">
        <h2><?php echo $t('available_templates', 'Available Templates'); ?></h2>
        <p><?php echo $t('switch_themes_desc', 'Switch between different themes for your store'); ?></p>
                </div>

    <div class="templates-grid">
    <?php foreach ($available_templates as $template): ?>
            <div class="template-card <?php echo $template['active'] ? 'active' : ''; ?>">
                        <?php if ($template['active']): ?>
                    <div class="template-badge active">
                        <i class="fas fa-star"></i>
                        <?php echo $t('active'); ?>
            </div>
                        <?php endif; ?>
                
                <div class="template-preview">
                    <img src="<?php echo getTemplatePreview($template['name']); ?>" 
                         onerror="this.src='../uploads/placeholder.jpg'"
                         alt="<?php echo $template['name']; ?>">
                    
                    <div class="template-overlay">
                        <a href="../index.php?preview=<?php echo $template['name']; ?>" 
                           target="_blank" 
                           class="overlay-btn" 
                           title="<?php echo $t('preview'); ?>">
                            <i class="fas fa-eye"></i>
                        </a>
    </div>
</div>

                <div class="template-details">
                    <h3 class="template-name"><?php echo ucfirst(str_replace('_', ' ', $template['name'])); ?></h3>
                    
                    <div class="template-meta">
                        <div class="template-status">
                        <?php if ($template['complete']): ?>
                                <span class="status-badge complete">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo $t('complete', 'Complete'); ?>
                                </span>
                        <?php else: ?>
                                <span class="status-badge incomplete">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo $t('incomplete', 'Incomplete'); ?>
                                </span>
                        <?php endif; ?>
                        </div>
                        <div class="template-files">
                            <i class="fas fa-file-code"></i>
                            <?php echo $template['file_count']; ?> <?php echo $t('files', 'files'); ?>
                        </div>
                    </div>
                    
                    <?php if (!$template['complete'] && !empty($template['missing_files'])): ?>
                        <div class="missing-files">
                            <small><?php echo $t('missing', 'Missing'); ?>: <?php echo implode(', ', array_slice($template['missing_files'], 0, 2)); ?></small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="template-actions">
                        <?php if (!$template['active']): ?>
                            <form method="POST" style="width: 100%;">
                                <?php echo CsrfHelper::getTokenField(); ?>
                                <input type="hidden" name="action" value="switch_template">
                                <input type="hidden" name="template_name" value="<?php echo $template['name']; ?>">
                                <button type="submit" class="btn-activate" 
                                        onclick="return confirm('<?php echo $t('switch_template_confirm', 'Switch to this template? Your current template will be deactivated.'); ?>')">
                                    <i class="fas fa-check"></i>
                                    <?php echo $t('activate_template', 'Activate Template'); ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn-current" disabled>
                                <i class="fas fa-star"></i>
                                <?php echo $t('current_template', 'Current Template'); ?>
                            </button>
                        <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

                </div>
                
<script>
// Auto-dismiss alerts after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);
</script>

<style>
/* Modern Template Management Styles */
:root {
    --primary: #000000;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --purple: #8b5cf6;
    --pink: #ec4899;
}

.template-management-container {
    padding: 0;
}

/* Alert Messages */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    border: none;
    display: flex;
    align-items: center;
    font-weight: 500;
    animation: slideInDown 0.3s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background: var(--color-success-light);
    color: #166534;
}

.alert .btn-close {
    margin-left: auto;
    opacity: 0.5;
}

.alert .btn-close:hover {
    opacity: 1;
}


/* Active Template Card */
.active-template-card {
    background: var(--bg-card);
    border-radius: 6px;
    padding: 2rem;
    margin-bottom: 3rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    position: relative;
    overflow: hidden;
}

.active-badge {
    position: absolute;
    top: 2rem;
    right: 2rem;
    padding: 0.5rem 1.5rem;
    border-radius: 100px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: var(--text-inverse);
    font-weight: 700;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.template-preview-large {
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
}

.template-preview-large img {
    width: 100%;
    height: 400px;
    object-fit: cover;
    display: block;
}

.template-info-large h2 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.template-info-large p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

.template-actions {
    display: flex;
    gap: 1rem;
}

.btn-action {
    flex: 1;
    padding: 1rem 1.5rem;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-action.primary {
    background: var(--pink);
    color: var(--text-inverse);
}

.btn-action.primary:hover {
    background: #be185d;
    transform: translateY(-2px);
    color: var(--text-inverse);
}

.btn-action.secondary {
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border: 2px solid #e2e8f0;
}

.btn-action.secondary:hover {
    border-color: var(--pink);
    color: var(--pink);
}

/* Section Header */
.section-header {
    margin-bottom: 2rem;
}

.section-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.section-header p {
    margin: 0;
    color: var(--text-secondary);
}

/* Templates Grid */
.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
}

.template-card {
    background: var(--bg-card);
    border: 2px solid #f1f5f9;
    border-radius: 6px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.template-card:hover {
    border-color: var(--pink);
    box-shadow: 0 8px 24px rgba(236, 72, 153, 0.15);
    transform: translateY(-4px);
}

.template-card.active {
    border-color: #10b981;
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.15);
}

.template-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.8125rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    z-index: 2;
}

.template-badge.active {
    background: #10b981;
    color: var(--text-inverse);
}

.template-preview {
    position: relative;
    width: 100%;
    height: 240px;
    overflow: hidden;
    background: var(--bg-secondary);
}

.template-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.template-card:hover .template-preview img {
    transform: scale(1.05);
}

.template-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    opacity: 0;
    transition: all 0.3s ease;
}

.template-card:hover .template-overlay {
    opacity: 1;
}

.overlay-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: none;
    background: var(--bg-card);
    color: var(--pink);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.overlay-btn:hover {
    transform: scale(1.1);
    color: var(--pink);
}

.template-details {
    padding: 1.5rem;
}

.template-name {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 1rem 0;
    color: var(--text-primary);
}

.template-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge.complete {
    background: var(--color-success-light);
    color: #166534;
}

.status-badge.incomplete {
    background: var(--color-warning-light);
    color: #92400e;
}

.template-files {
    font-size: 0.875rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.missing-files {
    padding: 0.75rem;
    background: var(--color-warning-light);
    border-radius: 6px;
    margin-bottom: 1rem;
}

.missing-files small {
    color: #92400e;
    font-size: 0.8125rem;
}

.template-actions {
    width: 100%;
}

.btn-activate, .btn-current {
    width: 100%;
    padding: 0.875rem 1.5rem;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-activate {
    background: var(--pink);
    color: var(--text-inverse);
}

.btn-activate:hover {
    background: #be185d;
    transform: translateY(-2px);
}

.btn-current {
    background: var(--color-success-light);
    color: #166534;
    cursor: not-allowed;
}

/* Responsive */
@media (max-width: 1024px) {
    .active-template-card {
        grid-template-columns: 1fr;
    }
    
    .active-badge {
        top: 1rem;
        right: 1rem;
    }
}

@media (max-width: 768px) {
    
    .templates-grid {
        grid-template-columns: 1fr;
    }
    
    .template-preview-large img {
        height: 250px;
    }
}
</style>
