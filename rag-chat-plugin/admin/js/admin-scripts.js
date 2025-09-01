/**
 * Admin JavaScript for RAG Chat Plugin
 */

(function($) {
    'use strict';

    // Admin class
    class RAGChatAdmin {
        constructor() {
            this.init();
        }

        init() {
            $(document).ready(() => {
                this.bindEvents();
                this.initializeDashboard();
                this.initializeSettings();
            });
        }

        bindEvents() {
            // API key testing
            $('#test-api-key').on('click', (e) => {
                e.preventDefault();
                this.testApiKey();
            });

            // Content scraping
            $('button[name="action"][value="scrape_all"]').on('click', (e) => {
                const $button = $(e.target);
                this.showProgress($button, 'Scraping content...');
            });

            // Dismissible notices
            $('.notice-dismissible').on('click', '.notice-dismiss', function() {
                const $notice = $(this).parent();
                const noticeId = $notice.data('dismissible');
                if (noticeId) {
                    // Send AJAX request to dismiss notice
                    $.post(ajaxurl, {
                        action: 'rag_chat_dismiss_notice',
                        notice_id: noticeId,
                        nonce: ragChatAdmin.nonce
                    });
                }
            });

            // Settings form validation
            $('form[action="options.php"]').on('submit', (e) => {
                return this.validateSettings(e);
            });

            // Auto-save settings (debounced)
            let saveTimeout;
            $('input, textarea, select').on('change', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    // Could implement auto-save here
                }, 2000);
            });

            // Dashboard refresh
            $('#refresh-dashboard').on('click', (e) => {
                e.preventDefault();
                location.reload();
            });

            // Export/Import settings
            $('#export-settings').on('click', (e) => {
                e.preventDefault();
                this.exportSettings();
            });

            $('#import-settings').on('change', (e) => {
                this.importSettings(e.target.files[0]);
            });

            // Content management actions
            $('.rag-chat-content-actions button').on('click', function() {
                const action = $(this).closest('form').find('input[name="action"]').val();
                if (action === 'clear_content') {
                    return confirm('Are you sure you want to clear all scraped content? This action cannot be undone.');
                }
                return true;
            });
        }

        initializeDashboard() {
            this.loadDashboardStats();
            this.initializeCharts();
            
            // Refresh stats every 30 seconds
            setInterval(() => {
                this.loadDashboardStats();
            }, 30000);
        }

        initializeSettings() {
            // Initialize color pickers if available
            if (typeof wpColorPicker !== 'undefined') {
                $('.rag-chat-color-picker').wpColorPicker();
            }

            // Initialize tooltips
            this.initializeTooltips();

            // Settings tabs
            this.initializeSettingsTabs();
        }

        testApiKey() {
            const $button = $('#test-api-key');
            const $result = $('#api-test-result');
            const apiKey = $('#rag_chat_gemini_api_key').val();

            if (!apiKey) {
                this.showApiTestResult('error', 'Please enter an API key first.');
                return;
            }

            // Debug what we're sending
            console.log('RAG Chat: Testing API key with nonce:', ragChatAdmin.nonce);
            console.log('RAG Chat: API key length:', apiKey.length);

            // Show loading state
            $button.prop('disabled', true).text('Testing...');
            $result.html('<span class="spinner is-active" style="float: none;"></span> Testing API key...');

            // Test API key
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rag_chat_test_api',
                    api_key: apiKey
                },
                success: (response) => {
                    console.log('RAG Chat: API test response:', response);
                    if (response.success) {
                        this.showApiTestResult('success', response.data.message);
                    } else {
                        this.showApiTestResult('error', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.log('RAG Chat: API test error:', error);
                    this.showApiTestResult('error', 'Network error: ' + error);
                },
                complete: () => {
                    $button.prop('disabled', false).text('Test API Key');
                }
            });
        }

        showApiTestResult(type, message) {
            const $result = $('#api-test-result');
            const iconClass = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-dismiss';
            const colorClass = type === 'success' ? 'rag-chat-success' : 'rag-chat-error';
            
            $result.html(`
                <span class="dashicons ${iconClass}" style="color: var(--rag-chat-${type});"></span>
                <span class="${colorClass}">${message}</span>
            `);
        }

        loadDashboardStats() {
            // This would load real-time stats via AJAX
            // For now, we'll just update the timestamp
            $('.rag-chat-last-updated').text('Last updated: ' + new Date().toLocaleTimeString());
        }

        initializeCharts() {
            // Initialize charts if Chart.js is available
            if (typeof Chart !== 'undefined') {
                this.initializeResponseTimeChart();
                this.initializeEngagementChart();
            }
        }

        initializeResponseTimeChart() {
            const ctx = document.getElementById('response-time-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Response Time (seconds)',
                        data: [1.2, 1.4, 1.1, 1.3, 1.5, 1.2, 1.0],
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        initializeEngagementChart() {
            const ctx = document.getElementById('engagement-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                    datasets: [{
                        label: 'Messages',
                        data: [5, 12, 45, 78, 65, 32],
                        backgroundColor: '#4CAF50'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        initializeTooltips() {
            // Add tooltips to help icons
            $('[data-tooltip]').each(function() {
                const $this = $(this);
                const tooltip = $this.data('tooltip');
                
                $this.on('mouseenter', function() {
                    const $tooltip = $(`<div class="rag-chat-tooltip">${tooltip}</div>`);
                    $('body').append($tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    $tooltip.css({
                        top: rect.top - $tooltip.outerHeight() - 5,
                        left: rect.left + (rect.width / 2) - ($tooltip.outerWidth() / 2)
                    });
                });
                
                $this.on('mouseleave', function() {
                    $('.rag-chat-tooltip').remove();
                });
            });
        }

        initializeSettingsTabs() {
            const $tabs = $('.rag-chat-settings-tabs');
            if (!$tabs.length) return;

            $tabs.find('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                const $tab = $(this);
                const targetId = $tab.attr('href');
                
                // Update active tab
                $tabs.find('.nav-tab').removeClass('nav-tab-active');
                $tab.addClass('nav-tab-active');
                
                // Show target section
                $('.rag-chat-settings-section').hide();
                $(targetId).show();
                
                // Save active tab
                localStorage.setItem('rag_chat_active_tab', targetId);
            });

            // Restore active tab
            const activeTab = localStorage.getItem('rag_chat_active_tab');
            if (activeTab) {
                $tabs.find(`[href="${activeTab}"]`).click();
            }
        }

        validateSettings(e) {
            let isValid = true;
            const errors = [];

            // Validate API key format
            const apiKey = $('#rag_chat_gemini_api_key').val();
            if (apiKey && !this.isValidApiKeyFormat(apiKey)) {
                errors.push('Invalid API key format. Gemini API keys should start with "AIza".');
                isValid = false;
            }

            // Validate temperature range
            const temperature = parseFloat($('#rag_chat_temperature').val());
            if (temperature < 0 || temperature > 2) {
                errors.push('Temperature must be between 0.0 and 2.0.');
                isValid = false;
            }

            // Validate max response length
            const maxLength = parseInt($('#rag_chat_max_response_length').val());
            if (maxLength < 50 || maxLength > 2048) {
                errors.push('Max response length must be between 50 and 2048 tokens.');
                isValid = false;
            }

            // Show errors if any
            if (!isValid) {
                this.showNotice('error', 'Please fix the following errors:\n' + errors.join('\n'));
                e.preventDefault();
                return false;
            }

            return true;
        }

        isValidApiKeyFormat(apiKey) {
            // Gemini API keys typically start with 'AIza' and are 39 characters long
            return /^AIza[a-zA-Z0-9_-]{35}$/.test(apiKey);
        }

        showProgress($button, message) {
            const originalText = $button.text();
            $button.prop('disabled', true).text(message);
            
            // Restore button after form submission
            setTimeout(() => {
                $button.prop('disabled', false).text(originalText);
            }, 2000);
        }

        showNotice(type, message) {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut();
            }, 5000);
        }

        exportSettings() {
            // Create download of current settings
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rag_chat_export_settings',
                    nonce: ragChatAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const blob = new Blob([JSON.stringify(response.data, null, 2)], {
                            type: 'application/json'
                        });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'rag-chat-settings.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    }
                }
            });
        }

        importSettings(file) {
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const settings = JSON.parse(e.target.result);
                    
                    // Import settings via AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rag_chat_import_settings',
                            settings: JSON.stringify(settings),
                            nonce: ragChatAdmin.nonce
                        },
                        success: (response) => {
                            if (response.success) {
                                this.showNotice('success', 'Settings imported successfully. Please refresh the page.');
                            } else {
                                this.showNotice('error', 'Failed to import settings: ' + response.data.message);
                            }
                        }
                    });
                } catch (error) {
                    this.showNotice('error', 'Invalid settings file format.');
                }
            };
            reader.readAsText(file);
        }
    }

    // Utility functions
    const RAGChatAdminUtils = {
        // Format numbers with commas
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        // Format bytes
        formatBytes: function(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        },

        // Format time duration
        formatDuration: function(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = Math.floor(seconds % 60);
            
            if (hours > 0) {
                return `${hours}h ${minutes}m ${secs}s`;
            } else if (minutes > 0) {
                return `${minutes}m ${secs}s`;
            } else {
                return `${secs}s`;
            }
        },

        // Copy text to clipboard
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }
        }
    };

    // Initialize admin interface
    let ragChatAdmin;
    
    $(document).ready(function() {
        ragChatAdmin = new RAGChatAdmin();
        
        // Make utilities available globally
        window.RAGChatAdmin = {
            instance: ragChatAdmin,
            utils: RAGChatAdminUtils
        };
    });

    // Handle page unload
    $(window).on('beforeunload', function() {
        // Save any pending changes
        const hasUnsavedChanges = $('.rag-chat-settings-form').data('has-changes');
        if (hasUnsavedChanges) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    // Track form changes
    $('.rag-chat-settings-form input, .rag-chat-settings-form textarea, .rag-chat-settings-form select').on('change', function() {
        $(this).closest('.rag-chat-settings-form').data('has-changes', true);
    });

    // Clear changes flag on successful save
    $('.rag-chat-settings-form').on('submit', function() {
        $(this).data('has-changes', false);
    });

})(jQuery);
