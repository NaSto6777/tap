<?php
$siteName = $settings->getSetting('site_name', 'Ecommerce Store');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Form Submission</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #dee2e6; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px; color: #6c757d; }
        .form-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .form-field { margin-bottom: 15px; }
        .form-field strong { display: block; margin-bottom: 5px; color: #495057; }
        .form-field p { margin: 0; padding: 10px; background: white; border-radius: 4px; border: 1px solid #dee2e6; }
        @media (max-width: 600px) {
            .container { padding: 10px; }
            .content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
            <h2>New Contact Form Submission</h2>
        </div>
        
        <div class="content">
            <p>You have received a new contact form submission from your website.</p>
            
            <div class="form-details">
                <h3>Contact Details</h3>
                
                <div class="form-field">
                    <strong>Name:</strong>
                    <p><?php echo htmlspecialchars($formData['name']); ?></p>
                </div>
                
                <div class="form-field">
                    <strong>Email:</strong>
                    <p><a href="mailto:<?php echo htmlspecialchars($formData['email']); ?>"><?php echo htmlspecialchars($formData['email']); ?></a></p>
                </div>
                
                <div class="form-field">
                    <strong>Subject:</strong>
                    <p><?php echo htmlspecialchars($formData['subject']); ?></p>
                </div>
                
                <div class="form-field">
                    <strong>Message:</strong>
                    <p><?php echo nl2br(htmlspecialchars($formData['message'])); ?></p>
                </div>
                
                <div class="form-field">
                    <strong>Submitted:</strong>
                    <p><?php echo date('F j, Y \a\t g:i A'); ?></p>
                </div>
            </div>
            
            <p><strong>Action Required:</strong> Please respond to this inquiry as soon as possible.</p>
            
            <p>You can reply directly to this email to respond to the customer.</p>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. All rights reserved.</p>
            <p>This notification was sent from your website contact form.</p>
        </div>
    </div>
</body>
</html>
