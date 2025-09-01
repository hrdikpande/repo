/**
 * Frontend JavaScript for RAG Chat Plugin
 */

(function($) {
    'use strict';

    // Chat widget class
    class RAGChatWidget {
        constructor() {
            this.widget = null;
            this.container = null;
            this.messagesArea = null;
            this.inputField = null;
            this.sendButton = null;
            this.isOpen = false;
            this.isTyping = false;
            this.sessionId = '';
            this.messageCount = 0;
            
            this.init();
        }

        init() {
            $(document).ready(() => {
                this.setupElements();
                this.bindEvents();
                this.setupAutoResize();
            });
        }

        setupElements() {
            this.widget = $('#rag-chat-widget');
            this.container = $('#rag-chat-container');
            this.messagesArea = $('#rag-chat-messages');
            this.inputField = $('#rag-chat-input');
            this.sendButton = $('#rag-chat-send');
            
            // Get session ID
            this.sessionId = this.widget.data('session-id') || this.generateSessionId();
            
            // Setup shortcode chats too
            this.setupShortcodeChats();
        }

        setupShortcodeChats() {
            $('.rag-chat-shortcode').each((index, element) => {
                const $shortcode = $(element);
                this.bindShortcodeEvents($shortcode);
            });
        }

        bindEvents() {
            // Toggle chat
            $('#rag-chat-toggle').on('click', () => {
                this.toggleChat();
            });

            // Close/minimize buttons
            $('#rag-chat-close').on('click', () => {
                this.closeChat();
            });

            $('#rag-chat-minimize').on('click', () => {
                this.minimizeChat();
            });

            // Send message
            this.sendButton.on('click', () => {
                this.sendMessage();
            });

            // Enter key to send
            this.inputField.on('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            // Auto-resize textarea
            this.inputField.on('input', () => {
                this.autoResizeTextarea();
            });

            // Click outside to close (optional)
            $(document).on('click', (e) => {
                if (this.isOpen && !$(e.target).closest('#rag-chat-widget').length) {
                    // Uncomment to enable click-outside-to-close
                    // this.closeChat();
                }
            });
        }

        bindShortcodeEvents($shortcode) {
            const sessionId = $shortcode.data('session-id') || this.generateSessionId();
            const $input = $shortcode.find('.rag-chat-input');
            const $sendBtn = $shortcode.find('.rag-chat-send-btn');
            const $messagesArea = $shortcode.find('.rag-chat-messages');

            // Send message
            $sendBtn.on('click', () => {
                const message = $input.val().trim();
                if (message) {
                    this.sendMessageToAPI(message, sessionId, $messagesArea, $input);
                }
            });

            // Enter key
            $input.on('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const message = $input.val().trim();
                    if (message) {
                        this.sendMessageToAPI(message, sessionId, $messagesArea, $input);
                    }
                }
            });

            // Auto-resize
            $input.on('input', () => {
                this.autoResizeTextarea($input[0]);
            });
        }

        toggleChat() {
            if (this.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        }

        openChat() {
            this.isOpen = true;
            this.container.show();
            this.widget.addClass('rag-chat-open');
            this.inputField.focus();
            this.scrollToBottom();
            
            // Add animation
            this.container.css({
                'transform': 'scale(0.8)',
                'opacity': '0'
            }).animate({
                'transform': 'scale(1)',
                'opacity': '1'
            }, 200);
        }

        closeChat() {
            this.isOpen = false;
            this.container.hide();
            this.widget.removeClass('rag-chat-open');
        }

        minimizeChat() {
            this.closeChat();
        }

        sendMessage() {
            const message = this.inputField.val().trim();
            if (!message || this.isTyping) {
                return;
            }

            this.sendMessageToAPI(message, this.sessionId, this.messagesArea, this.inputField);
        }

        sendMessageToAPI(message, sessionId, $messagesArea, $inputField) {
            // Add user message to chat
            this.addMessage(message, 'user', $messagesArea);
            
            // Clear input
            $inputField.val('');
            this.autoResizeTextarea($inputField[0]);
            
            // Show typing indicator
            this.showTyping($messagesArea);
            
            // Send to API with enhanced error handling
            const data = {
                action: 'rag_chat_send_message',
                message: message,
                session_id: sessionId,
                page_url: window.location.href,
                nonce: ragChat.nonce
            };

            // Add request timeout and retry logic
            const makeRequest = (retryCount = 0) => {
                $.ajax({
                    url: ragChat.ajaxUrl,
                    type: 'POST',
                    data: data,
                    timeout: 30000,
                    success: (response) => {
                        this.hideTyping($messagesArea);
                        
                        if (response.success && response.data.message) {
                            this.addMessage(response.data.message, 'bot', $messagesArea, false, response.data);
                            
                            // Update session ID if provided
                            if (response.data.session_id) {
                                sessionId = response.data.session_id;
                            }
                            
                            // Show cache indicator if response was cached
                            if (response.data.cached) {
                                this.showCacheIndicator($messagesArea);
                            }
                        } else {
                            const errorMsg = response.data?.message || ragChat.strings.error_message;
                            this.addMessage(errorMsg, 'bot', $messagesArea, true);
                        }
                    },
                    error: (xhr, status, error) => {
                        this.hideTyping($messagesArea);
                        
                        let errorMessage = ragChat.strings.network_error;
                        
                        // Handle specific error types
                        if (status === 'timeout') {
                            errorMessage = 'Request timed out. Please try again.';
                        } else if (xhr.status === 429) {
                            errorMessage = ragChat.strings.rate_limited;
                        } else if (xhr.status === 413) {
                            errorMessage = ragChat.strings.message_too_long;
                        } else if (xhr.status >= 500) {
                            errorMessage = 'Server error. Please try again later.';
                        }
                        
                        this.addMessage(errorMessage, 'bot', $messagesArea, true);
                        
                        // Retry logic for network errors
                        if (retryCount < 2 && (status === 'timeout' || xhr.status >= 500)) {
                            setTimeout(() => {
                                this.addMessage('Retrying...', 'bot', $messagesArea);
                                makeRequest(retryCount + 1);
                            }, 2000);
                        }
                        
                        console.error('RAG Chat Error:', {status, error, xhr: xhr.responseText});
                    }
                });
            };
            
            makeRequest();
        }

        addMessage(text, sender, $messagesArea, isError = false, responseData = null) {
            const timestamp = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            const avatar = sender === 'user' ? '👤' : '🤖';
            const messageClass = `rag-chat-message rag-chat-message-${sender}${isError ? ' rag-chat-message-error' : ''}`;
            
            let messageHtml = `
                <div class="${messageClass}">
                    <div class="rag-chat-message-avatar">${avatar}</div>
                    <div class="rag-chat-message-content">
                        <div class="rag-chat-message-text">${this.escapeHtml(text)}</div>
                        <div class="rag-chat-message-time">${timestamp}</div>
                    </div>
                </div>
            `;
            
            // Add response metadata if available
            if (responseData && responseData.response_time) {
                const responseTime = (responseData.response_time * 1000).toFixed(0);
                messageHtml = messageHtml.replace(
                    '<div class="rag-chat-message-time">',
                    `<div class="rag-chat-message-time">Response time: ${responseTime}ms | `
                );
            }
            
            $messagesArea.append(messageHtml);
            this.scrollToBottom($messagesArea);
            this.messageCount++;
        }

        showCacheIndicator($messagesArea) {
            const cacheHtml = `
                <div class="rag-chat-cache-indicator">
                    <small>💾 Cached response</small>
                </div>
            `;
            $messagesArea.append(cacheHtml);
            this.scrollToBottom($messagesArea);
        }

        showTyping($messagesArea) {
            this.isTyping = true;
            const $typingIndicator = $messagesArea.siblings('.rag-chat-typing');
            if ($typingIndicator.length) {
                $typingIndicator.show();
            } else {
                // Create typing indicator if it doesn't exist (for shortcodes)
                const typingHtml = `
                    <div class="rag-chat-typing">
                        <div class="rag-chat-message rag-chat-message-bot">
                            <div class="rag-chat-message-avatar">🤖</div>
                            <div class="rag-chat-message-content">
                                <div class="rag-chat-typing-dots">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $messagesArea.after(typingHtml);
            }
            this.scrollToBottom($messagesArea);
        }

        hideTyping($messagesArea) {
            this.isTyping = false;
            const $typingIndicator = $messagesArea.siblings('.rag-chat-typing');
            $typingIndicator.hide();
        }

        scrollToBottom($messagesArea = null) {
            const $area = $messagesArea || this.messagesArea;
            if ($area && $area.length) {
                $area.scrollTop($area[0].scrollHeight);
            }
        }

        autoResizeTextarea(textarea = null) {
            const $textarea = textarea ? $(textarea) : this.inputField;
            if (!$textarea || !$textarea.length) return;
            
            const element = $textarea[0];
            element.style.height = 'auto';
            const newHeight = Math.min(element.scrollHeight, 100); // Max 100px
            element.style.height = newHeight + 'px';
        }

        setupAutoResize() {
            // Setup auto-resize for all textareas
            $(document).on('input', '.rag-chat-input', function() {
                const element = this;
                element.style.height = 'auto';
                const newHeight = Math.min(element.scrollHeight, 100);
                element.style.height = newHeight + 'px';
            });
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        generateSessionId() {
            return 'rag_chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        // Public methods for external use
        openChatWidget() {
            this.openChat();
        }

        closeChatWidget() {
            this.closeChat();
        }

        sendChatMessage(message) {
            if (this.inputField && this.inputField.length) {
                this.inputField.val(message);
                this.sendMessage();
            }
        }

        clearChatHistory() {
            if (this.messagesArea && this.messagesArea.length) {
                this.messagesArea.empty();
                // Add greeting message back
                const greeting = ragChat.settings?.greeting || 'Hello! How can I help you today?';
                this.addMessage(greeting, 'bot', this.messagesArea);
            }
        }
    }

    // Utility functions
    const RAGChatUtils = {
        // Format message text with basic markdown support
        formatMessage: function(text) {
            return text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>');
        },

        // Copy message to clipboard
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }
        },

        // Show notification
        showNotification: function(message, type = 'info') {
            const notification = $(`
                <div class="rag-chat-notification rag-chat-notification-${type}">
                    ${message}
                </div>
            `);
            
            $('body').append(notification);
            
            setTimeout(() => {
                notification.fadeOut(() => {
                    notification.remove();
                });
            }, 3000);
        }
    };

    // Initialize chat widget when DOM is ready
    let ragChatWidget;
    
    $(document).ready(function() {
        ragChatWidget = new RAGChatWidget();
        
        // Make available globally
        window.RAGChat = {
            widget: ragChatWidget,
            utils: RAGChatUtils,
            open: () => ragChatWidget.openChatWidget(),
            close: () => ragChatWidget.closeChatWidget(),
            send: (message) => ragChatWidget.sendChatMessage(message),
            clear: () => ragChatWidget.clearChatHistory()
        };
    });

    // Handle dynamic content (for AJAX-loaded content)
    $(document).on('DOMNodeInserted', function(e) {
        const $target = $(e.target);
        if ($target.hasClass('rag-chat-shortcode')) {
            if (ragChatWidget) {
                ragChatWidget.bindShortcodeEvents($target);
            }
        }
    });

    // Add keyboard shortcuts
    $(document).keydown(function(e) {
        // Ctrl/Cmd + Shift + C to toggle chat
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.which === 67) {
            e.preventDefault();
            if (ragChatWidget) {
                ragChatWidget.toggleChat();
            }
        }
        
        // Escape to close chat
        if (e.which === 27 && ragChatWidget && ragChatWidget.isOpen) {
            ragChatWidget.closeChat();
        }
    });

    // Handle page visibility changes
    $(document).on('visibilitychange', function() {
        if (document.hidden && ragChatWidget && ragChatWidget.isOpen) {
            // Optional: minimize chat when page becomes hidden
            // ragChatWidget.minimizeChat();
        }
    });

})(jQuery);
