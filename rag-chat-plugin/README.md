# RAG Chat Plugin

A complete WordPress plugin that implements RAG (Retrieval-Augmented Generation) functionality with Google's Gemini API for intelligent chat responses based on scraped website content.

## Features

### Core Functionality
- **Smart Chat Interface**: Responsive chat widget with modern UI
- **RAG Implementation**: Retrieval-Augmented Generation using website content
- **Google Gemini Integration**: Powered by Google's advanced AI model
- **Content Scraping**: Automatic extraction and indexing of website content
- **Real-time Responses**: Fast, contextual AI responses based on your content

### Admin Features
- **Comprehensive Dashboard**: Overview of chat statistics and system status
- **API Key Management**: Secure storage and validation of Gemini API keys
- **Content Management**: View, scrape, and manage indexed content
- **Analytics**: Detailed insights into chat usage and performance
- **Settings Panel**: Complete configuration options for all aspects

### Technical Features
- **Security First**: Input validation, sanitization, and rate limiting
- **Database Optimization**: Efficient storage and retrieval of content
- **Mobile Responsive**: Works perfectly on all devices
- **Accessibility**: WCAG 2.1 compliant interface
- **WordPress Standards**: Follows all WordPress coding standards

## Installation

1. **Download**: Download the plugin files to your WordPress plugins directory
2. **Upload**: Upload the `rag-chat-plugin` folder to `/wp-content/plugins/`
3. **Activate**: Activate the plugin through the 'Plugins' menu in WordPress
4. **Configure**: Configure your Google Gemini API key in the settings

## Quick Setup

### 1. Get Google Gemini API Key
1. Visit [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Create or select a project
3. Generate an API key
4. Copy the API key for configuration

### 2. Configure Plugin
1. Go to **WordPress Admin → RAG Chat → Settings**
2. Enter your Gemini API key
3. Click "Test API Key" to verify connection
4. Configure chat behavior and appearance settings
5. Save settings

### 3. Scrape Content
1. Go to **WordPress Admin → RAG Chat → Content Management**
2. Click "Scrape All Content" to index your website
3. Wait for the process to complete
4. Verify content has been indexed

### 4. Test Chat
1. Visit your website frontend
2. Look for the chat widget (bottom-right by default)
3. Click to open and test the chat functionality
4. Ask questions about your website content

## Configuration Options

### General Settings
- **Enable/Disable Chat**: Toggle chat functionality
- **Chat Position**: Choose widget placement (bottom-right, bottom-left, etc.)
- **Theme**: Select visual theme (default, dark, light, minimal)

### API Configuration
- **Gemini API Key**: Your Google Gemini API key
- **AI Temperature**: Control response creativity (0.0-2.0)
- **Max Response Length**: Limit response size (50-2048 tokens)

### Content Scraping
- **Auto-scrape**: Automatically index new/updated content
- **Content Types**: Select which post types to include
- **Scraping Frequency**: Schedule automatic re-indexing

### Chat Behavior
- **System Prompt**: Instructions for AI behavior
- **Greeting Message**: Initial message to users
- **Input Placeholder**: Text field placeholder

### Appearance
- **Widget Title**: Chat window title
- **Position**: Where to show the widget
- **Color Theme**: Visual styling options

## Usage

### Frontend Chat Widget
The chat widget appears on your website frontend and provides:
- **Instant Responses**: AI-powered answers about your content
- **Contextual Understanding**: Responses based on relevant website content
- **Conversation History**: Maintains context within sessions
- **Mobile Friendly**: Responsive design for all devices

### Shortcode Usage
You can embed chat interfaces anywhere using the shortcode:

```php
[rag_chat]
```

With options:
```php
[rag_chat height="400px" width="100%" theme="dark" title="Custom Assistant"]
```

### Programmatic Access
Developers can interact with the chat system programmatically:

```javascript
// Open chat widget
RAGChat.open();

// Send a message
RAGChat.send("Hello, how can you help me?");

// Close chat widget
RAGChat.close();

// Clear chat history
RAGChat.clear();
```

## Database Structure

The plugin creates three main tables:

### `wp_rag_chat_scraped_content`
Stores indexed website content:
- `id`: Unique identifier
- `post_id`: WordPress post ID (if applicable)
- `url`: Content URL
- `title`: Content title
- `content`: Processed text content
- `content_type`: Type of content (post, page, etc.)
- `content_hash`: Hash for duplicate detection
- `word_count`: Number of words
- `scraped_at`: Index timestamp
- `updated_at`: Last update timestamp

### `wp_rag_chat_history`
Stores chat conversations:
- `id`: Unique identifier
- `session_id`: Chat session identifier
- `user_id`: WordPress user ID (if logged in)
- `user_ip`: User IP address
- `user_message`: User's message
- `bot_response`: AI response
- `context_used`: Content used for response
- `response_time`: API response time
- `created_at`: Message timestamp

### `wp_rag_chat_content_chunks`
Stores content chunks for better retrieval:
- `id`: Unique identifier
- `content_id`: Reference to scraped content
- `chunk_text`: Text chunk
- `chunk_index`: Chunk order
- `word_count`: Chunk word count

## Security Features

### Input Validation
- All user inputs are sanitized and validated
- SQL injection prevention
- XSS protection
- CSRF token validation

### API Security
- Encrypted storage of API keys
- Rate limiting to prevent abuse
- Secure API communication
- Error handling without data exposure

### Access Control
- WordPress capability checks
- Nonce verification for admin actions
- User permission validation
- Secure file handling

## Performance Optimization

### Database Optimization
- Indexed tables for fast queries
- Efficient content chunking
- Query optimization
- Cleanup routines for old data

### Caching
- Content similarity caching
- Response time optimization
- Database query caching
- Static asset optimization

### Resource Management
- Lazy loading of admin assets
- Minified CSS/JavaScript
- Optimized database queries
- Memory usage optimization

## Troubleshooting

### Common Issues

#### Chat Widget Not Appearing
1. Check if plugin is activated
2. Verify chat is enabled in settings
3. Check for JavaScript errors in browser console
4. Ensure theme compatibility

#### API Key Not Working
1. Verify API key format (should start with "AIza")
2. Check API key permissions in Google Cloud Console
3. Test API key using the built-in test function
4. Ensure billing is enabled for your Google Cloud project

#### No Content Being Found
1. Run content scraping from admin panel
2. Check if content types are selected in settings
3. Verify content is published and public
4. Check for any error messages in logs

#### Slow Response Times
1. Check internet connection
2. Verify Google Gemini API status
3. Reduce max response length
4. Check server performance

### Debug Mode
Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs in `/wp-content/debug.log` for RAG Chat related errors.

## API Reference

### Hooks and Filters

#### Actions
```php
// Before sending message to API
do_action('rag_chat_before_api_request', $message, $context);

// After receiving API response
do_action('rag_chat_after_api_response', $response, $message);

// When content is scraped
do_action('rag_chat_content_scraped', $content_id, $post_id);
```

#### Filters
```php
// Modify chat settings
$settings = apply_filters('rag_chat_settings', $settings);

// Customize system prompt
$prompt = apply_filters('rag_chat_system_prompt', $prompt, $context);

// Filter scraped content
$content = apply_filters('rag_chat_scraped_content', $content, $post_id);

// Modify API request
$request = apply_filters('rag_chat_api_request', $request, $message);
```

### REST API Endpoints
The plugin exposes these AJAX endpoints:

- `rag_chat_send_message`: Send chat message
- `rag_chat_scrape_content`: Trigger content scraping
- `rag_chat_test_api`: Test API key

## Changelog

### Version 1.0.0
- Initial release
- Complete RAG implementation
- Google Gemini API integration
- Responsive chat interface
- Comprehensive admin panel
- Content scraping system
- Security features
- Analytics and reporting

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support, please:
1. Check the troubleshooting section
2. Review WordPress debug logs
3. Test with default WordPress theme
4. Verify plugin compatibility

## Contributing

To contribute to this plugin:
1. Follow WordPress coding standards
2. Include proper documentation
3. Add security measures
4. Test thoroughly

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Google Gemini API key
- cURL extension
- JSON extension
- OpenSSL extension (for API key encryption)

## Credits

- Built with WordPress best practices
- Powered by Google Gemini AI
- Uses modern web technologies
- Follows accessibility guidelines

---

**Note**: This plugin requires a Google Gemini API key to function. Make sure to set up billing in your Google Cloud Console if you plan to use this in production.
