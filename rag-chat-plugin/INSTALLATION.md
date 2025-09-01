# RAG Chat Plugin - Installation Guide

## Quick Start Guide

### Step 1: Get Google Gemini API Key

1. **Visit Google AI Studio**
   - Go to [https://makersuite.google.com/app/apikey](https://makersuite.google.com/app/apikey)
   - Sign in with your Google account

2. **Create API Key**
   - Click "Create API Key"
   - Select or create a Google Cloud project
   - Copy the generated API key (starts with "AIza...")

3. **Enable Billing** (if required)
   - Go to Google Cloud Console
   - Enable billing for your project
   - Gemini API has generous free tiers

### Step 2: Install Plugin

1. **Download/Upload**
   ```bash
   # If you have the files, upload to:
   /wp-content/plugins/rag-chat-plugin/
   ```

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "RAG Chat Plugin"
   - Click "Activate"

### Step 3: Configure Settings

1. **Navigate to Settings**
   - Go to WordPress Admin
   - Click "RAG Chat" in the menu
   - Click "Settings"

2. **Enter API Key**
   - Paste your Gemini API key
   - Click "Test API Key" to verify
   - Should show "API key is valid and working"

3. **Configure Basic Settings**
   ```
   ✓ Enable Chat: ON
   ✓ Chat Position: Bottom Right
   ✓ Theme: Default
   ✓ AI Temperature: 0.7
   ✓ Max Response Length: 500
   ```

4. **Save Settings**

### Step 4: Scrape Content

1. **Go to Content Management**
   - Click "RAG Chat" → "Content Management"

2. **Start Scraping**
   - Click "Scrape All Content"
   - Wait for completion (may take a few minutes)
   - Verify content appears in the table

### Step 5: Test Chat

1. **Visit Your Website**
   - Go to any page on your site
   - Look for chat widget (bottom-right corner)

2. **Test Interaction**
   - Click the chat widget
   - Type a question about your website
   - Wait for AI response

## Advanced Configuration

### Content Scraping Options

```php
// Auto-scrape settings
Auto-scrape Content: ON
Content Types: Posts, Pages
Scraping Frequency: Daily
```

### Chat Behavior

```php
// System prompt example
"You are a helpful assistant for [Your Website Name]. 
Answer questions based on the website content provided. 
Be concise and helpful."

// Greeting message
"Hello! I'm here to help you find information about our website. What would you like to know?"
```

### Appearance Customization

```php
// Widget settings
Widget Title: "Chat Assistant"
Position: Bottom Right
Theme: Default (or Dark/Light/Minimal)
```

## Troubleshooting Installation

### Common Issues

1. **Plugin Won't Activate**
   - Check PHP version (7.4+ required)
   - Verify file permissions
   - Check for conflicting plugins

2. **API Key Test Fails**
   - Verify key format (starts with "AIza")
   - Check Google Cloud billing
   - Ensure Gemini API is enabled

3. **No Content Scraped**
   - Check if posts/pages are published
   - Verify content types selected
   - Look for error messages

4. **Chat Widget Not Showing**
   - Clear browser cache
   - Check theme compatibility
   - Verify plugin is activated

### Debug Mode

Enable WordPress debugging:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at `/wp-content/debug.log`

## File Structure

```
rag-chat-plugin/
├── rag-chat-plugin.php          # Main plugin file
├── includes/
│   ├── class-database.php       # Database management
│   ├── class-security.php       # Security functions
│   ├── class-scraper.php        # Content scraping
│   ├── class-rag-processor.php  # RAG implementation
│   ├── class-gemini-api.php     # API integration
│   └── class-chat-handler.php   # Chat processing
├── admin/
│   ├── class-admin.php          # Admin interface
│   ├── class-settings.php       # Settings management
│   ├── css/admin-styles.css     # Admin styling
│   └── js/admin-scripts.js      # Admin JavaScript
├── public/
│   ├── class-public.php         # Frontend functionality
│   ├── css/chat-styles.css      # Chat styling
│   └── js/chat-scripts.js       # Chat JavaScript
├── uninstall.php                # Cleanup script
├── README.md                    # Documentation
└── INSTALLATION.md              # This file
```

## System Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Extensions**: cURL, JSON, OpenSSL
- **Memory**: 128MB minimum (256MB recommended)

## Security Checklist

✓ API keys are encrypted in database  
✓ All inputs are sanitized  
✓ CSRF protection enabled  
✓ Rate limiting implemented  
✓ SQL injection prevention  
✓ XSS protection  

## Performance Tips

1. **Optimize Content**
   - Scrape only necessary content types
   - Set reasonable chunk sizes
   - Clean old chat history regularly

2. **API Usage**
   - Monitor token usage
   - Adjust response length limits
   - Use appropriate temperature settings

3. **Database**
   - Regular cleanup of old data
   - Monitor table sizes
   - Use proper indexing

## Next Steps

After successful installation:

1. **Customize Settings**
   - Adjust chat behavior
   - Set up scheduled scraping
   - Configure appearance

2. **Monitor Usage**
   - Check analytics dashboard
   - Monitor API usage
   - Review chat logs

3. **Optimize Performance**
   - Clean old data
   - Adjust settings based on usage
   - Monitor response times

## Support

If you encounter issues:

1. Check this installation guide
2. Review the main README.md
3. Enable debug mode and check logs
4. Test with default WordPress theme
5. Verify system requirements

---

**Need Help?** Make sure all requirements are met and follow each step carefully. Most issues are resolved by proper API key configuration and content scraping.
