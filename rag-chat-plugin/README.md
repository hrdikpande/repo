# RAG Chat Plugin - WordPress AI Chat Assistant

A complete WordPress plugin that implements RAG (Retrieval-Augmented Generation) functionality with Google's Gemini API for intelligent chat responses based on scraped website content.

## 🚀 Features

### Core Functionality
- **AI-Powered Chat**: Intelligent responses using Google's Gemini API
- **Content Scraping**: Automatic scraping of WordPress posts and pages
- **RAG Processing**: Advanced content retrieval and context preparation
- **Real-time Chat**: Live chat interface with typing indicators
- **Session Management**: Persistent chat sessions with history

### Security & Performance
- **Rate Limiting**: Configurable rate limiting to prevent abuse
- **Input Sanitization**: Comprehensive security measures
- **Caching System**: Intelligent caching for improved performance
- **Error Handling**: Robust error handling and logging
- **API Key Encryption**: Secure storage of API credentials

### User Experience
- **Responsive Design**: Mobile-friendly chat interface
- **Multiple Themes**: Light, dark, and minimal themes
- **Customizable Position**: Bottom-right, bottom-left, top-right, top-left
- **Shortcode Support**: Embed chat anywhere with `[rag_chat]`
- **Accessibility**: WCAG compliant with keyboard navigation

### Admin Features
- **Dashboard**: Comprehensive analytics and statistics
- **Content Management**: Manual and automatic content scraping
- **Settings Panel**: Extensive configuration options
- **Chat History**: View and manage chat conversations
- **Analytics**: Detailed usage statistics and insights

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Required Extensions**: curl, openssl, json, mbstring
- **Google Gemini API**: Valid API key from [Google AI Studio](https://makersuite.google.com/app/apikey)

## 🛠️ Installation

### Method 1: WordPress Admin (Recommended)

1. Download the plugin ZIP file
2. Go to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

### Method 2: Manual Installation

1. Extract the plugin files to `/wp-content/plugins/rag-chat-plugin/`
2. Go to **WordPress Admin → Plugins**
3. Find "RAG Chat Plugin" and click **Activate**

## ⚙️ Configuration

### 1. API Setup

1. Get your Google Gemini API key from [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Go to **WordPress Admin → RAG Chat → Settings**
3. Enter your API key in the **API Configuration** section
4. Test the API connection using the **Test API Key** button
5. Save your settings

### 2. Content Scraping

1. Go to **RAG Chat → Content Management**
2. Select which content types to scrape (posts, pages, etc.)
3. Click **Scrape All Content** to index your website
4. Enable **Auto-scrape** to automatically update content

### 3. Chat Configuration

1. In **Settings → Chat Behavior**, configure:
   - System prompt for AI behavior
   - Greeting message
   - Input placeholder text
   - Response length limits

2. In **Settings → Appearance**, customize:
   - Chat position (bottom-right, bottom-left, etc.)
   - Color theme (default, dark, light, minimal)
   - Widget title

## 🎯 Usage

### Automatic Display
The chat widget automatically appears on all public pages when enabled.

### Shortcode
Embed the chat anywhere using the shortcode:
```
[rag_chat height="400px" width="100%" theme="default" title="Chat Assistant"]
```

### JavaScript API
Control the chat programmatically:
```javascript
// Open chat
RAGChat.open();

// Close chat
RAGChat.close();

// Send message
RAGChat.send("Hello, how can you help me?");

// Clear chat history
RAGChat.clear();
```

## 🔧 Advanced Configuration

### Rate Limiting
Configure rate limits in **Settings → Security**:
- Chat messages: 5 per minute
- API requests: 60 per minute
- Content scraping: 2 per minute

### Caching
Adjust cache settings in **Settings → Performance**:
- Cache duration: 1 hour (default)
- Search results: 30 minutes
- API responses: 1 hour

### Logging
Configure logging in **Settings → Advanced**:
- Log level: error, warning, info, debug
- Log file location: `/wp-content/logs/rag-chat-plugin.log`

## 📊 Analytics

### Dashboard Overview
- Total chat messages
- Content pages indexed
- Average response time
- Last content scrape

### Detailed Analytics
- Common user queries
- Response time trends
- User engagement patterns
- API usage statistics

## 🔒 Security Features

### Input Validation
- Message length limits
- HTML sanitization
- SQL injection prevention
- XSS protection

### Rate Limiting
- Per-user rate limiting
- IP-based tracking
- Configurable limits
- Automatic blocking

### API Security
- Encrypted API key storage
- Request validation
- Error logging
- Secure communication

## 🚨 Troubleshooting

### Common Issues

**Chat not appearing:**
- Check if chat is enabled in settings
- Verify API key is configured
- Check browser console for errors

**API errors:**
- Verify API key is valid
- Check API quota limits
- Review error logs

**Performance issues:**
- Enable caching
- Reduce content scraping frequency
- Check server resources

### Debug Mode
Enable debug logging in **Settings → Advanced** to troubleshoot issues.

## 📝 Changelog

### Version 2.0.0
- Enhanced security with rate limiting
- Improved caching system
- Better error handling
- Performance optimizations
- Enhanced logging system

### Version 1.0.0
- Initial release
- Basic RAG functionality
- Chat interface
- Content scraping

## 🤝 Support

### Documentation
- [Plugin Documentation](https://example.com/docs)
- [API Reference](https://example.com/api)
- [FAQ](https://example.com/faq)

### Support Channels
- **Email**: support@example.com
- **GitHub Issues**: [Report Bugs](https://github.com/example/rag-chat-plugin/issues)
- **Community Forum**: [Get Help](https://example.com/forum)

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🙏 Credits

- **Google Gemini API**: AI language model
- **WordPress**: Content management system
- **jQuery**: JavaScript library
- **Community Contributors**: Bug reports and suggestions

## 🔄 Updates

The plugin checks for updates automatically. You can also manually check for updates in **WordPress Admin → Updates**.

---

**Note**: This plugin requires an active internet connection to communicate with the Google Gemini API. Please ensure your server has outbound HTTPS access.
