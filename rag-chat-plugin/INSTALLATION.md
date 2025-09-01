# RAG Chat Plugin - Installation Guide

## Prerequisites

Before installing the RAG Chat Plugin, ensure your system meets these requirements:

### System Requirements
- **WordPress**: Version 5.0 or higher
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.6 or higher (or MariaDB 10.1+)
- **Memory Limit**: At least 128MB PHP memory limit
- **Upload Limit**: At least 10MB for plugin installation

### Required PHP Extensions
- `curl` - For API communication
- `openssl` - For API key encryption
- `json` - For data processing
- `mbstring` - For text processing
- `mysqli` - For database operations

### Check Your System
You can check your system requirements by adding this code to a temporary PHP file:

```php
<?php
echo "PHP Version: " . PHP_VERSION . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";

$required_extensions = ['curl', 'openssl', 'json', 'mbstring', 'mysqli'];
foreach ($required_extensions as $ext) {
    echo $ext . ": " . (extension_loaded($ext) ? "✓" : "✗") . "\n";
}
?>
```

## Installation Methods

### Method 1: WordPress Admin (Recommended)

1. **Download the Plugin**
   - Download the `rag-chat-plugin.zip` file
   - Ensure the file is not corrupted (check file size)

2. **Upload via WordPress Admin**
   - Log in to your WordPress admin panel
   - Navigate to **Plugins → Add New**
   - Click **Upload Plugin** button
   - Choose the `rag-chat-plugin.zip` file
   - Click **Install Now**
   - Wait for installation to complete
   - Click **Activate Plugin**

3. **Verify Installation**
   - Check that "RAG Chat" appears in your admin menu
   - Look for any error messages in the admin area

### Method 2: FTP/File Manager Installation

1. **Extract Plugin Files**
   - Extract the `rag-chat-plugin.zip` file
   - Ensure the folder structure is correct:
     ```
     rag-chat-plugin/
     ├── rag-chat-plugin.php
     ├── includes/
     ├── admin/
     ├── public/
     └── uninstall.php
     ```

2. **Upload to Server**
   - Connect to your server via FTP or use file manager
   - Navigate to `/wp-content/plugins/`
   - Upload the `rag-chat-plugin` folder
   - Ensure proper file permissions (755 for folders, 644 for files)

3. **Activate Plugin**
   - Go to **WordPress Admin → Plugins**
   - Find "RAG Chat Plugin"
   - Click **Activate**

### Method 3: Command Line Installation

If you have SSH access to your server:

```bash
# Navigate to WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Download and extract plugin
wget https://example.com/rag-chat-plugin.zip
unzip rag-chat-plugin.zip

# Set proper permissions
chmod -R 755 rag-chat-plugin/
find rag-chat-plugin/ -name "*.php" -exec chmod 644 {} \;

# Activate via WP-CLI (if available)
wp plugin activate rag-chat-plugin
```

## Post-Installation Setup

### 1. Database Tables Creation

The plugin will automatically create required database tables upon activation:

- `wp_rag_chat_scraped_content` - Stores indexed website content
- `wp_rag_chat_history` - Stores chat conversations
- `wp_rag_chat_content_chunks` - Stores content chunks for retrieval

**Manual Table Creation** (if automatic creation fails):
```sql
-- Run these queries in phpMyAdmin or MySQL console
-- Replace 'wp_' with your actual table prefix

CREATE TABLE wp_rag_chat_scraped_content (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    post_id bigint(20) unsigned DEFAULT NULL,
    url varchar(2048) NOT NULL,
    title text DEFAULT NULL,
    content longtext DEFAULT NULL,
    content_type varchar(50) DEFAULT 'post',
    content_hash varchar(64) DEFAULT NULL,
    word_count int(11) DEFAULT 0,
    scraped_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status varchar(20) DEFAULT 'active',
    PRIMARY KEY (id),
    KEY idx_post_id (post_id),
    KEY idx_content_type (content_type),
    KEY idx_content_hash (content_hash),
    KEY idx_status (status),
    KEY idx_scraped_at (scraped_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wp_rag_chat_history (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    session_id varchar(64) NOT NULL,
    user_id bigint(20) unsigned DEFAULT NULL,
    user_ip varchar(45) DEFAULT NULL,
    user_message longtext NOT NULL,
    bot_response longtext DEFAULT NULL,
    context_used longtext DEFAULT NULL,
    response_time float DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    user_agent text DEFAULT NULL,
    page_url varchar(2048) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_session_id (session_id),
    KEY idx_user_id (user_id),
    KEY idx_created_at (created_at),
    KEY idx_user_ip (user_ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wp_rag_chat_content_chunks (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    content_id bigint(20) unsigned NOT NULL,
    chunk_text longtext NOT NULL,
    chunk_index int(11) NOT NULL DEFAULT 0,
    word_count int(11) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_content_id (content_id),
    KEY idx_chunk_index (chunk_index),
    FOREIGN KEY (content_id) REFERENCES wp_rag_chat_scraped_content(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. File Permissions

Ensure proper file permissions for security:

```bash
# Set directory permissions
find /path/to/wordpress/wp-content/plugins/rag-chat-plugin/ -type d -exec chmod 755 {} \;

# Set file permissions
find /path/to/wordpress/wp-content/plugins/rag-chat-plugin/ -type f -exec chmod 644 {} \;

# Create logs directory with proper permissions
mkdir -p /path/to/wordpress/wp-content/logs/
chmod 755 /path/to/wordpress/wp-content/logs/
chown www-data:www-data /path/to/wordpress/wp-content/logs/  # Adjust user:group as needed
```

### 3. WordPress Configuration

Add these lines to your `wp-config.php` for better performance:

```php
// Increase memory limit if needed
define('WP_MEMORY_LIMIT', '256M');

// Enable debugging during setup (remove in production)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## Initial Configuration

### 1. API Key Setup

1. **Get Google Gemini API Key**
   - Visit [Google AI Studio](https://makersuite.google.com/app/apikey)
   - Sign in with your Google account
   - Create a new project or select existing one
   - Generate an API key
   - Copy the API key (starts with "AIza")

2. **Configure Plugin**
   - Go to **WordPress Admin → RAG Chat → Settings**
   - Navigate to **API Configuration** section
   - Paste your API key in the **Gemini API Key** field
   - Click **Test API Key** to verify connection
   - Save settings

### 2. Content Scraping

1. **Configure Content Types**
   - In **Settings → Content Scraping**
   - Select which content types to index (posts, pages, etc.)
   - Set scraping frequency (daily recommended)

2. **Initial Content Indexing**
   - Go to **RAG Chat → Content Management**
   - Click **Scrape All Content**
   - Wait for the process to complete
   - Verify content appears in the list

### 3. Chat Configuration

1. **Basic Settings**
   - **Enable Chat**: Turn on chat functionality
   - **Position**: Choose widget placement
   - **Theme**: Select visual theme

2. **Chat Behavior**
   - **System Prompt**: Instructions for AI behavior
   - **Greeting Message**: Initial message to users
   - **Max Response Length**: Limit response size

## Verification Steps

### 1. Check Plugin Status

Go to **WordPress Admin → RAG Chat → Dashboard** and verify:
- Plugin status shows "Active"
- API configuration shows "Configured"
- Database tables show "OK"
- Content count shows indexed pages

### 2. Test Chat Functionality

1. **Frontend Test**
   - Visit your website frontend
   - Look for chat widget (bottom-right by default)
   - Click to open chat
   - Send a test message
   - Verify AI response

2. **Shortcode Test**
   - Create a test page/post
   - Add shortcode: `[rag_chat]`
   - Publish and test functionality

### 3. Check Logs

Monitor logs for any issues:
- **WordPress Debug Log**: `/wp-content/debug.log`
- **Plugin Log**: `/wp-content/logs/rag-chat-plugin.log`
- **Error Log**: Check server error logs

## Troubleshooting Installation

### Common Installation Issues

**Plugin won't activate:**
- Check PHP version compatibility
- Verify all required extensions are loaded
- Check file permissions
- Review WordPress debug log

**Database tables not created:**
- Check database user permissions
- Verify table prefix is correct
- Run manual SQL creation
- Check for SQL errors in logs

**API key not working:**
- Verify API key format
- Check internet connectivity
- Ensure billing is enabled in Google Cloud
- Test API key manually

**Content not scraping:**
- Check file permissions
- Verify content is published
- Check for memory/timeout issues
- Review scraping logs

### Performance Optimization

**For High-Traffic Sites:**
- Enable object caching (Redis/Memcached)
- Use CDN for static assets
- Optimize database queries
- Monitor server resources

**For Large Content Sites:**
- Increase PHP memory limit
- Extend execution time limits
- Use batch processing for scraping
- Implement content pagination

## Security Considerations

### File Security
- Ensure plugin files are not publicly accessible
- Use proper file permissions
- Regularly update the plugin
- Monitor for unauthorized access

### Database Security
- Use strong database passwords
- Limit database user permissions
- Regularly backup database
- Monitor for suspicious queries

### API Security
- Keep API keys secure
- Monitor API usage
- Set up usage alerts
- Rotate keys regularly

## Support and Maintenance

### Regular Maintenance
- Update plugin regularly
- Monitor error logs
- Clean up old data
- Backup configurations

### Performance Monitoring
- Monitor response times
- Track API usage
- Check server resources
- Optimize as needed

### Security Monitoring
- Review access logs
- Monitor for suspicious activity
- Update security settings
- Test security measures

---

**Note**: This installation guide covers the basic setup. For advanced configurations and customizations, refer to the main documentation or contact support.
