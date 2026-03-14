/**
 * Open Wallet Log - Main Application
 * PWA with comprehensive financial management features
 */

class FinProApp {
    constructor() {
        this.apiUrl = '/api';
        this.token = localStorage.getItem('token');
        this.user = JSON.parse(localStorage.getItem('user') || 'null');
        this.currentPage = 'dashboard';
        this.isOnline = navigator.onLine;
        
        this.init();
    }
    
    async init() {
        this.setupEventListeners();
        this.setupNetworkListeners();
        this.registerServiceWorker();
        
        // Check for password reset token in URL
        const urlParams = new URLSearchParams(window.location.search);
        const resetToken = urlParams.get('reset-token') || urlParams.get('token');
        const verifyToken = urlParams.get('verify');
        
        if (resetToken) {
            // Show reset password form with token
            document.getElementById('reset-token').value = resetToken;
            this.showAuth();
            this.toggleAuthForm('reset');
            
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (verifyToken) {
            // Auto-verify email
            this.verifyEmail(verifyToken);
        } else if (this.token) {
            // Check authentication
            const valid = await this.validateToken();
            if (valid) {
                this.showMainApp();
            } else {
                this.showAuth();
            }
        } else {
            this.showAuth();
        }
        
        // Hide loading screen
        setTimeout(() => {
            document.getElementById('loading-screen').classList.add('hidden');
        }, 1000);
    }
    
    async verifyEmail(token) {
        try {
            const result = await this.apiRequest(`/auth/verify-email?token=${token}`, 'GET');
            if (result.success) {
                this.showToast('Email verified successfully! You can now sign in.', 'success');
            }
        } catch (error) {
            this.showToast(error.message || 'Email verification failed', 'error');
        }
        this.showAuth();
        this.toggleAuthForm('login');
    }
    
    setupEventListeners() {
        // Auth forms
        document.getElementById('login-form').addEventListener('submit', (e) => this.handleLogin(e));
        document.getElementById('register-form').addEventListener('submit', (e) => this.handleRegister(e));
        document.getElementById('forgot-password-form').addEventListener('submit', (e) => this.handleForgotPassword(e));
        document.getElementById('reset-password-form').addEventListener('submit', (e) => this.handleResetPassword(e));
        
        // Toggle auth forms
        document.getElementById('show-register').addEventListener('click', () => this.toggleAuthForm('register'));
        document.getElementById('show-login').addEventListener('click', () => this.toggleAuthForm('login'));
        document.getElementById('show-login-from-forgot').addEventListener('click', () => this.toggleAuthForm('login'));
        document.querySelector('.forgot-password').addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleAuthForm('forgot');
        });
        
        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = item.dataset.page;
                this.navigate(page);
            });
        });
        
        // Mobile menu
        document.getElementById('mobile-menu-toggle').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('open');
        });
        
        // Logout
        document.getElementById('logout-btn').addEventListener('click', () => this.logout());
        
        // Modal
        document.getElementById('modal-overlay').addEventListener('click', (e) => {
            if (e.target.id === 'modal-overlay') {
                this.closeModal();
            }
        });
        document.getElementById('modal-close').addEventListener('click', () => this.closeModal());
        
        // Notifications
        document.getElementById('notifications-btn').addEventListener('click', () => this.showNotifications());
    }
    
    setupNetworkListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            document.getElementById('offline-indicator').classList.add('hidden');
            this.showToast('Back online', 'success');
            this.syncOfflineData();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            document.getElementById('offline-indicator').classList.remove('hidden');
            this.showToast('You are offline', 'warning');
        });
    }
    
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/service-worker.js');
                console.log('Service Worker registered:', registration);
                
                // Request notification permission
                if ('Notification' in window) {
                    Notification.requestPermission();
                }
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }
    }
    
    // ==================== AUTHENTICATION ====================
    
    toggleAuthForm(form) {
        document.getElementById('login-form').classList.toggle('hidden', form !== 'login');
        document.getElementById('register-form').classList.toggle('hidden', form !== 'register');
        document.getElementById('forgot-password-form').classList.toggle('hidden', form !== 'forgot');
        document.getElementById('reset-password-form').classList.toggle('hidden', form !== 'reset');
    }
    
    async handleLogin(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        const data = {
            email: formData.get('email'),
            password: formData.get('password')
        };
        
        try {
            const result = await this.apiRequest('/auth/login', 'POST', data);
            
            if (result.success) {
                this.token = result.data.token;
                this.user = result.data.user;
                
                localStorage.setItem('token', this.token);
                localStorage.setItem('user', JSON.stringify(this.user));
                
                this.showMainApp();
                this.showToast('Welcome back!', 'success');
            }
        } catch (error) {
            this.showToast(error.message || 'Login failed', 'error');
        }
    }
    
    async handleRegister(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        const data = {
            firstname: formData.get('firstname'),
            lastname: formData.get('lastname'),
            email: formData.get('email'),
            phone: formData.get('phone'),
            password: formData.get('password'),
            confirm: formData.get('confirm')
        };
        
        if (data.password !== data.confirm) {
            this.showToast('Passwords do not match', 'error');
            return;
        }
        
        try {
            const result = await this.apiRequest('/auth/register', 'POST', data);
            
            if (result.success) {
                this.showToast('Registration successful! Please check your email to verify your account.', 'success');
                this.toggleAuthForm('login');
            }
        } catch (error) {
            this.showToast(error.message || 'Registration failed', 'error');
        }
    }
    
    async handleForgotPassword(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const result = await this.apiRequest('/auth/forgot-password', 'POST', {
                email: formData.get('email')
            });
            
            if (result.success) {
                this.showToast('If an account exists with that email, you will receive reset instructions.', 'success');
                this.toggleAuthForm('login');
            }
        } catch (error) {
            this.showToast(error.message || 'Failed to send reset email', 'error');
        }
    }
    
    async handleResetPassword(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const password = formData.get('password');
        const confirm = formData.get('confirm');
        
        if (password !== confirm) {
            this.showToast('Passwords do not match', 'error');
            return;
        }
        
        try {
            const result = await this.apiRequest('/auth/reset-password', 'POST', {
                token: formData.get('token'),
                password: password
            });
            
            if (result.success) {
                this.showToast('Password reset successful! You can now sign in.', 'success');
                this.toggleAuthForm('login');
            }
        } catch (error) {
            this.showToast(error.message || 'Failed to reset password', 'error');
        }
    }
    
    async logout() {
        try {
            await this.apiRequest('/auth/logout', 'POST');
        } catch (e) {
            // Ignore errors
        }
        
        this.token = null;
        this.user = null;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        
        this.showAuth();
        this.showToast('Logged out successfully', 'info');
    }
    
    async validateToken() {
        try {
            const result = await this.apiRequest('/auth/me', 'GET');
            return result.success;
        } catch (error) {
            return false;
        }
    }
    
    // ==================== UI NAVIGATION ====================
    
    showAuth() {
        document.getElementById('auth-container').classList.remove('hidden');
        document.getElementById('main-app').classList.add('hidden');
    }
    
    showMainApp() {
        document.getElementById('auth-container').classList.add('hidden');
        document.getElementById('main-app').classList.remove('hidden');
        
        // Update user info
        this.updateUserInfo();
        
        // Load initial page
        const hash = window.location.hash.replace('#', '') || 'dashboard';
        this.navigate(hash);
    }
    
    updateUserInfo() {
        if (this.user) {
            document.getElementById('user-name').textContent = `${this.user.firstname} ${this.user.lastname}`;
            document.getElementById('user-role').textContent = this.user.role || 'User';
            document.getElementById('user-avatar').textContent = this.user.firstname.charAt(0).toUpperCase();
        }
    }
    
    navigate(page) {
        this.currentPage = page;
        
        // Update active nav
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.toggle('active', item.dataset.page === page);
        });
        
        // Update page title
        const titles = {
            'dashboard': 'Dashboard',
            'accounts': 'Accounts',
            'transactions': 'Transactions',
            'transfers': 'Transfers',
            'loans': 'Loans',
            'investments': 'Investments',
            'reports': 'Reports',
            'settings': 'Settings'
        };
        document.getElementById('page-title').textContent = titles[page] || page;
        
        // Load page content
        this.loadPage(page);
        
        // Close mobile menu
        document.getElementById('sidebar').classList.remove('open');
    }
    
    async loadPage(page) {
        const container = document.getElementById('page-content');
        container.innerHTML = '<div class="loading-spinner"></div>';
        
        try {
            switch (page) {
                case 'dashboard':
                    await this.loadDashboard(container);
                    break;
                case 'accounts':
                    await this.loadAccounts(container);
                    break;
                case 'transactions':
                    await this.loadTransactions(container);
                    break;
                case 'transfers':
                    await this.loadTransfers(container);
                    break;
                case 'loans':
                    await this.loadLoans(container);
                    break;
                case 'investments':
                    await this.loadInvestments(container);
                    break;
                case 'reports':
                    await this.loadReports(container);
                    break;
                case 'settings':
                    await this.loadSettings(container);
                    break;
                default:
                    container.innerHTML = '<p>Page not found</p>';
            }
        } catch (error) {
            container.innerHTML = `<p class="error">Error loading page: ${error.message}</p>`;
        }
    }
    
    // ==================== PAGE LOADERS ====================
    
    async loadDashboard(container) {
        try {
            const [stats, recentActivity, accounts] = await Promise.all([
                this.apiRequest('/dashboard/stats', 'GET'),
                this.apiRequest('/dashboard/activity', 'GET'),
                this.apiRequest('/accounts', 'GET')
            ]);
            
            const s = stats.data.stats;
            
            container.innerHTML = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Total Balance</span>
                            <div class="stat-icon primary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="5" width="20" height="14" rx="2"/>
                                    <line x1="2" y1="10" x2="22" y2="10"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">${this.formatCurrency(s.total_balance)}</div>
                        <div class="stat-change ${s.monthly_change.net >= 0 ? 'positive' : 'negative'}">
                            ${s.monthly_change.net >= 0 ? '↑' : '↓'} ${this.formatCurrency(Math.abs(s.monthly_change.net))} this month
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Portfolio Value</span>
                            <div class="stat-icon success">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">${this.formatCurrency(s.portfolio_value)}</div>
                        <div class="stat-change positive">Active investments</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Active Loans</span>
                            <div class="stat-icon warning">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="12"/>
                                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">${s.active_loans}</div>
                        <div class="stat-change">Loan accounts</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Monthly Net</span>
                            <div class="stat-icon ${s.monthly_change.net >= 0 ? 'success' : 'danger'}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                                    <polyline points="17 6 23 6 23 12"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">${this.formatCurrency(s.monthly_change.net)}</div>
                        <div class="stat-change">
                            Income: ${this.formatCurrency(s.monthly_change.income)}<br>
                            Expenses: ${this.formatCurrency(s.monthly_change.expenses)}
                        </div>
                    </div>
                </div>
                
                <div class="grid-2">
                    <div class="card">
                        <div class="card-header">
                            <h2>Quick Actions</h2>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <a href="#transfers" class="quick-action" data-action="transfer">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 1l4 4-4 4"/>
                                        <path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                                        <path d="M7 23l-4-4 4-4"/>
                                        <path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                                    </svg>
                                    <span>Transfer</span>
                                </a>
                                <a href="#transactions" class="quick-action" data-action="deposit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v20M2 12h20"/>
                                    </svg>
                                    <span>Deposit</span>
                                </a>
                                <a href="#accounts" class="quick-action" data-action="new-account">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                    <span>New Account</span>
                                </a>
                                <a href="#loans" class="quick-action" data-action="apply-loan">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line x1="12" y1="8" x2="12" y2="16"/>
                                        <line x1="8" y1="12" x2="16" y2="12"/>
                                    </svg>
                                    <span>Apply Loan</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2>Your Accounts</h2>
                            <a href="#accounts" class="btn btn-secondary">View All</a>
                        </div>
                        <div class="card-body">
                            ${accounts.data.accounts.slice(0, 3).map(acc => `
                                <div class="investment-card mb-2">
                                    <div class="investment-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="5" width="20" height="14" rx="2"/>
                                            <line x1="2" y1="10" x2="22" y2="10"/>
                                        </svg>
                                    </div>
                                    <div class="investment-details">
                                        <div class="investment-name">${acc.type.charAt(0).toUpperCase() + acc.type.slice(1)} Account</div>
                                        <div class="investment-symbol">****${acc.account_number.slice(-4)}</div>
                                    </div>
                                    <div class="investment-price">
                                        <div class="investment-value">${this.formatCurrency(acc.balance)}</div>
                                        <div class="investment-change">${acc.currency}</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h2>Recent Activity</h2>
                        <a href="#transactions" class="btn btn-secondary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th class="text-right">Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${recentActivity.data.activities.slice(0, 5).map(tx => `
                                        <tr>
                                            <td>${this.formatDate(tx.created_at)}</td>
                                            <td>${tx.description || tx.type}</td>
                                            <td><span class="badge badge-secondary">${tx.category}</span></td>
                                            <td class="text-right amount ${tx.type === 'deposit' || tx.type === 'transfer_in' ? 'positive' : 'negative'}">
                                                ${tx.type === 'deposit' || tx.type === 'transfer_in' ? '+' : '-'}${this.formatCurrency(tx.amount)}
                                            </td>
                                            <td><span class="badge badge-success">${tx.status}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            container.innerHTML = `<p class="error">Failed to load dashboard: ${error.message}</p>`;
        }
    }
    
    async loadAccounts(container) {
        try {
            const result = await this.apiRequest('/accounts', 'GET');
            const accounts = result.data.accounts;
            
            container.innerHTML = `
                <div class="mb-4">
                    <button class="btn btn-primary" id="create-account-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Open New Account
                    </button>
                </div>
                
                <div class="account-grid">
                    ${accounts.map(acc => `
                        <div class="account-card ${acc.type}">
                            <div class="account-type">${acc.type.toUpperCase()}</div>
                            <div class="account-number">**** ${acc.account_number.slice(-4)}</div>
                            <div class="account-balance">${this.formatCurrency(acc.balance)}</div>
                            <div class="account-actions">
                                <button class="btn btn-secondary btn-sm" onclick="app.deposit('${acc.id}')">Deposit</button>
                                <button class="btn btn-secondary btn-sm" onclick="app.transfer('${acc.id}')">Transfer</button>
                                <button class="btn btn-icon" onclick="app.viewAccountDetails('${acc.id}')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            document.getElementById('create-account-btn')?.addEventListener('click', () => this.showCreateAccountModal());
        } catch (error) {
            container.innerHTML = `<p class="error">Failed to load accounts: ${error.message}</p>`;
        }
    }
    
    async loadTransactions(container) {
        try {
            const result = await this.apiRequest('/transactions', 'GET');
            const transactions = result.data.transactions;
            
            container.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h2>All Transactions</h2>
                        <div>
                            <button class="btn btn-secondary" id="filter-btn">Filter</button>
                            <button class="btn btn-secondary" id="export-btn">Export</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Account</th>
                                        <th class="text-right">Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${transactions.map(tx => `
                                        <tr>
                                            <td>${this.formatDate(tx.created_at)}</td>
                                            <td><span class="badge badge-${this.getTypeColor(tx.type)}">${tx.type}</span></td>
                                            <td>${tx.description || '-'}</td>
                                            <td>****${tx.account_number?.slice(-4)}</td>
                                            <td class="text-right amount ${this.getAmountClass(tx.type)}">
                                                ${this.getAmountPrefix(tx.type)}${this.formatCurrency(tx.amount)}
                                            </td>
                                            <td><span class="badge badge-${tx.status === 'completed' ? 'success' : 'warning'}">${tx.status}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            container.innerHTML = `<p class="error">Failed to load transactions: ${error.message}</p>`;
        }
    }
    
    async loadTransfers(container) {
        const result = await this.apiRequest('/accounts', 'GET');
        const accounts = result.data.accounts;
        
        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h2>Transfer Money</h2>
                </div>
                <div class="card-body">
                    <form id="transfer-form" class="transfer-form">
                        <div class="transfer-amount">
                            <label>Amount to Transfer</label>
                            <input type="number" name="amount" placeholder="0.00" min="0.01" step="0.01" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>From Account</label>
                                <select name="from_account" class="form-select" required>
                                    <option value="">Select account</option>
                                    ${accounts.map(acc => `<option value="${acc.id}">${acc.type} - ****${acc.account_number.slice(-4)} (${this.formatCurrency(acc.balance)})</option>`).join('')}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>To Account</label>
                                <select name="to_account" class="form-select" required>
                                    <option value="">Select account</option>
                                    ${accounts.map(acc => `<option value="${acc.id}">${acc.type} - ****${acc.account_number.slice(-4)}</option>`).join('')}
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Description (Optional)</label>
                            <input type="text" name="description" placeholder="What's this transfer for?">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">Transfer Now</button>
                    </form>
                </div>
            </div>
        `;
        
        document.getElementById('transfer-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            try {
                await this.apiRequest('/transactions/transfer', 'POST', {
                    from_account_id: formData.get('from_account'),
                    to_account_id: formData.get('to_account'),
                    amount: parseFloat(formData.get('amount')),
                    description: formData.get('description')
                });
                
                this.showToast('Transfer completed successfully', 'success');
                this.navigate('transactions');
            } catch (error) {
                this.showToast(error.message || 'Transfer failed', 'error');
            }
        });
    }
    
    async loadLoans(container) {
        try {
            const result = await this.apiRequest('/loans', 'GET');
            const loans = result.data.loans;
            
            container.innerHTML = `
                <div class="mb-4">
                    <button class="btn btn-primary" id="apply-loan-btn">
                        Apply for Loan
                    </button>
                </div>
                
                <div class="grid-2">
                    ${loans.length > 0 ? loans.map(loan => `
                        <div class="loan-card">
                            <div class="loan-header">
                                <div>
                                    <div class="loan-title">${loan.type.charAt(0).toUpperCase() + loan.type.slice(1)} Loan</div>
                                    <div class="text-muted">Applied ${this.formatDate(loan.created_at)}</div>
                                </div>
                                <span class="badge badge-${loan.status === 'approved' || loan.status === 'active' ? 'success' : (loan.status === 'pending' ? 'warning' : 'secondary')}">${loan.status}</span>
                            </div>
                            <div class="loan-amount">${this.formatCurrency(loan.amount)}</div>
                            <div class="mb-2">
                                <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.5rem;">
                                    <span>Paid: ${this.formatCurrency(loan.amount_paid)}</span>
                                    <span>${loan.progress}%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: ${loan.progress}%"></div>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem; color: var(--text-secondary);">
                                <span>Term: ${loan.term_months} months</span>
                                <span>Rate: ${(loan.interest_rate * 100).toFixed(2)}%</span>
                            </div>
                            ${loan.status === 'active' || loan.status === 'approved' ? `
                                <button class="btn btn-secondary btn-full mt-3" onclick="app.makeLoanPayment('${loan.id}')">
                                    Make Payment
                                </button>
                            ` : ''}
                        </div>
                    `).join('') : `
                        <div class="card">
                            <div class="card-body text-center">
                                <p>No loans yet. Apply for your first loan today!</p>
                            </div>
                        </div>
                    `}
                </div>
            `;
            
            document.getElementById('apply-loan-btn')?.addEventListener('click', () => this.showApplyLoanModal());
        } catch (error) {
            container.innerHTML = `<p class="error">Failed to load loans: ${error.message}</p>`;
        }
    }
    
    async loadInvestments(container) {
        try {
            const result = await this.apiRequest('/investments/portfolio', 'GET');
            const portfolio = result.data.portfolio;
            
            container.innerHTML = `
                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Total Invested</span>
                        </div>
                        <div class="stat-value">${this.formatCurrency(portfolio.summary.total_invested)}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Current Value</span>
                        </div>
                        <div class="stat-value">${this.formatCurrency(portfolio.summary.total_value)}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Profit/Loss</span>
                        </div>
                        <div class="stat-value ${portfolio.summary.total_profit_loss >= 0 ? '' : 'negative'}">
                            ${portfolio.summary.total_profit_loss >= 0 ? '+' : ''}${this.formatCurrency(portfolio.summary.total_profit_loss)}
                        </div>
                        <div class="stat-change ${portfolio.summary.return_percentage >= 0 ? 'positive' : 'negative'}">
                            ${portfolio.summary.return_percentage >= 0 ? '↑' : '↓'} ${Math.abs(portfolio.summary.return_percentage)}%
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <button class="btn btn-primary" id="buy-stock-btn">Buy Stock</button>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Your Holdings</h2>
                    </div>
                    <div class="card-body">
                        ${portfolio.investments.length > 0 ? `
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Symbol</th>
                                            <th>Quantity</th>
                                            <th>Avg Price</th>
                                            <th>Current</th>
                                            <th>Value</th>
                                            <th>P/L</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${portfolio.investments.map(inv => `
                                            <tr>
                                                <td><strong>${inv.symbol}</strong><br><small>${inv.stock_name}</small></td>
                                                <td>${inv.quantity}</td>
                                                <td>${this.formatCurrency(inv.purchase_price)}</td>
                                                <td>${this.formatCurrency(inv.current_price)}</td>
                                                <td>${this.formatCurrency(inv.current_value)}</td>
                                                <td class="${inv.profit_loss >= 0 ? 'positive' : 'negative'}">
                                                    ${inv.profit_loss >= 0 ? '+' : ''}${this.formatCurrency(inv.profit_loss)}
                                                </td>
                                                <td>
                                                    <button class="btn btn-secondary btn-sm" onclick="app.sellStock('${inv.id}', '${inv.symbol}', ${inv.quantity})">Sell</button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : `<p class="text-center">No investments yet. Start building your portfolio!</p>`}
                    </div>
                </div>
            `;
            
            document.getElementById('buy-stock-btn')?.addEventListener('click', () => this.showBuyStockModal());
        } catch (error) {
            container.innerHTML = `<p class="error">Failed to load investments: ${error.message}</p>`;
        }
    }
    
    async loadReports(container) {
        container.innerHTML = `
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <h2>Income vs Expenses</h2>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="income-expense-chart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Spending by Category</h2>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="category-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h2>Balance History</h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="balance-chart"></canvas>
                    </div>
                </div>
            </div>
        `;
        
        // Initialize charts after a short delay
        setTimeout(() => this.initializeCharts(), 100);
    }
    
    async loadSettings(container) {
        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h2>Account Settings</h2>
                </div>
                <div class="card-body">
                    <div class="settings-section">
                        <h3>Profile Information</h3>
                        <form id="profile-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="firstname" value="${this.user?.firstname || ''}">
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="lastname" value="${this.user?.lastname || ''}">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" value="${this.user?.email || ''}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" value="${this.user?.phone || ''}">
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                    
                    <div class="settings-section">
                        <h3>Change Password</h3>
                        <form id="password-form">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" required minlength="8">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                    
                    <div class="settings-section">
                        <h3>Notifications</h3>
                        ${this.renderNotificationSettings()}
                    </div>
                </div>
            </div>
        `;
        
        // Add form handlers
        document.getElementById('profile-form')?.addEventListener('submit', (e) => this.handleProfileUpdate(e));
        document.getElementById('password-form')?.addEventListener('submit', (e) => this.handlePasswordChange(e));
    }
    
    // ==================== API REQUESTS ====================
    
    async apiRequest(endpoint, method = 'GET', data = null) {
        const url = this.apiUrl + endpoint;
        
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if (this.token) {
            options.headers['Authorization'] = `Bearer ${this.token}`;
        }
        
        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }
        
        // If offline, queue the request
        if (!this.isOnline && method !== 'GET') {
            this.queueOfflineRequest(endpoint, method, data);
            throw new Error('You are offline. Request queued for sync.');
        }
        
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            // Handle different error formats from backend
            let errorMessage = 'Request failed';
            
            if (result.error) {
                if (typeof result.error === 'string') {
                    errorMessage = result.error;
                } else if (result.error.message) {
                    // Message can be string or array
                    if (Array.isArray(result.error.message)) {
                        errorMessage = result.error.message.join(', ');
                    } else {
                        errorMessage = result.error.message;
                    }
                }
            } else if (result.message) {
                errorMessage = result.message;
            }
            
            const error = new Error(errorMessage);
            error.response = result;
            throw error;
        }
        
        return result;
    }
    
    queueOfflineRequest(endpoint, method, data) {
        const queue = JSON.parse(localStorage.getItem('offlineQueue') || '[]');
        queue.push({ endpoint, method, data, timestamp: Date.now() });
        localStorage.setItem('offlineQueue', JSON.stringify(queue));
        
        // Register for sync when back online
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            navigator.serviceWorker.ready.then(registration => {
                registration.sync.register('sync-transactions');
            });
        }
    }
    
    async syncOfflineData() {
        const queue = JSON.parse(localStorage.getItem('offlineQueue') || '[]');
        if (queue.length === 0) return;
        
        const failed = [];
        
        for (const request of queue) {
            try {
                await this.apiRequest(request.endpoint, request.method, request.data);
            } catch (error) {
                failed.push(request);
            }
        }
        
        localStorage.setItem('offlineQueue', JSON.stringify(failed));
        
        if (failed.length === 0) {
            this.showToast('All offline data synced', 'success');
        } else {
            this.showToast(`${failed.length} requests failed to sync`, 'warning');
        }
    }
    
    // ==================== UI HELPERS ====================
    
    showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
    
    openModal(title, content, footer = '') {
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-body').innerHTML = content;
        document.getElementById('modal-footer').innerHTML = footer;
        document.getElementById('modal-overlay').classList.remove('hidden');
    }
    
    closeModal() {
        document.getElementById('modal-overlay').classList.add('hidden');
    }
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount || 0);
    }
    
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    getTypeColor(type) {
        const colors = {
            'deposit': 'success',
            'withdrawal': 'danger',
            'transfer_in': 'info',
            'transfer_out': 'warning',
            'payment': 'secondary'
        };
        return colors[type] || 'secondary';
    }
    
    getAmountClass(type) {
        return (type === 'deposit' || type === 'transfer_in') ? 'positive' : 'negative';
    }
    
    getAmountPrefix(type) {
        return (type === 'deposit' || type === 'transfer_in') ? '+' : '-';
    }

    // ==================== MODAL METHODS ====================

    async showNotifications() {
        try {
            const result = await this.apiRequest('/notifications', 'GET');
            const notifications = result.data.notifications;
            const unreadCount = result.data.unread_count;

            const content = notifications.length > 0 ? `
                <div class="notifications-list">
                    ${notifications.map(n => `
                        <div class="notification-item ${n.is_read ? 'read' : 'unread'}" data-id="${n.id}">
                            <div class="notification-icon ${n.type}">
                                ${this.getNotificationIcon(n.type)}
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${n.title}</div>
                                <div class="notification-message">${n.message}</div>
                                <div class="notification-time">${this.formatDate(n.created_at)}</div>
                            </div>
                            ${!n.is_read ? '<span class="unread-dot"></span>' : ''}
                        </div>
                    `).join('')}
                </div>
            ` : '<p class="text-center text-muted">No notifications</p>';

            const footer = unreadCount > 0 ? `
                <button class="btn btn-secondary" id="mark-all-read">Mark All Read</button>
            ` : '';

            this.openModal(`Notifications (${unreadCount} unread)`, content, footer);

            // Add click handlers for notifications
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.addEventListener('click', async () => {
                    await this.apiRequest('/notifications/mark-read', 'POST', { notification_id: item.dataset.id });
                    item.classList.remove('unread');
                    item.querySelector('.unread-dot')?.remove();
                });
            });

            document.getElementById('mark-all-read')?.addEventListener('click', async () => {
                await this.apiRequest('/notifications/mark-read', 'POST', { mark_all: true });
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('unread');
                    item.querySelector('.unread-dot')?.remove();
                });
            });
        } catch (error) {
            this.showToast('Failed to load notifications', 'error');
        }
    }

    getNotificationIcon(type) {
        const icons = {
            transaction: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
            alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            loan: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
            investment: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
            security: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            system: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'
        };
        return icons[type] || icons.system;
    }

    async showCreateAccountModal() {
        const content = `
            <form id="create-account-form">
                <div class="form-group">
                    <label>Account Type</label>
                    <select name="type" class="form-select" required>
                        <option value="">Select account type</option>
                        <option value="checking">Checking Account</option>
                        <option value="savings">Savings Account</option>
                        <option value="investment">Investment Account</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select name="currency" class="form-select" required>
                        <option value="USD">USD - US Dollar</option>
                        <option value="EUR">EUR - Euro</option>
                        <option value="GBP">GBP - British Pound</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Initial Deposit (Optional)</label>
                    <input type="number" name="initial_deposit" placeholder="0.00" min="0" step="0.01">
                </div>
            </form>
        `;

        const footer = `
            <button class="btn btn-secondary" onclick="app.closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="app.submitCreateAccount()">Create Account</button>
        `;

        this.openModal('Open New Account', content, footer);
    }

    async submitCreateAccount() {
        const form = document.getElementById('create-account-form');
        const formData = new FormData(form);

        try {
            await this.apiRequest('/accounts/create', 'POST', {
                type: formData.get('type'),
                currency: formData.get('currency'),
                initial_deposit: parseFloat(formData.get('initial_deposit')) || 0
            });

            this.closeModal();
            this.showToast('Account created successfully', 'success');
            this.loadPage('accounts');
        } catch (error) {
            this.showToast(error.message || 'Failed to create account', 'error');
        }
    }

    async showApplyLoanModal() {
        const content = `
            <form id="apply-loan-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Loan Type</label>
                        <select name="type" class="form-select" required>
                            <option value="personal">Personal Loan</option>
                            <option value="business">Business Loan</option>
                            <option value="mortgage">Mortgage</option>
                            <option value="auto">Auto Loan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" name="amount" placeholder="0.00" min="1000" step="100" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Term (Months)</label>
                        <select name="term_months" class="form-select" required>
                            <option value="12">12 months</option>
                            <option value="24">24 months</option>
                            <option value="36">36 months</option>
                            <option value="48">48 months</option>
                            <option value="60">60 months</option>
                            <option value="120">120 months</option>
                            <option value="180">180 months</option>
                            <option value="360">360 months</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Annual Income</label>
                        <input type="number" name="income" placeholder="0.00" min="0" step="1000">
                    </div>
                </div>
                <div class="form-group">
                    <label>Purpose</label>
                    <textarea name="purpose" class="form-textarea" placeholder="What is the purpose of this loan?" required></textarea>
                </div>
                <div class="form-group">
                    <label>Employment Status</label>
                    <select name="employment_status" class="form-select">
                        <option value="">Select status</option>
                        <option value="employed">Employed</option>
                        <option value="self_employed">Self Employed</option>
                        <option value="business_owner">Business Owner</option>
                        <option value="retired">Retired</option>
                    </select>
                </div>
            </form>
        `;

        const footer = `
            <button class="btn btn-secondary" onclick="app.closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="app.submitLoanApplication()">Apply Now</button>
        `;

        this.openModal('Apply for Loan', content, footer);
    }

    async submitLoanApplication() {
        const form = document.getElementById('apply-loan-form');
        const formData = new FormData(form);

        try {
            await this.apiRequest('/loans/apply', 'POST', {
                type: formData.get('type'),
                amount: parseFloat(formData.get('amount')),
                term_months: parseInt(formData.get('term_months')),
                purpose: formData.get('purpose'),
                income: parseFloat(formData.get('income')) || null,
                employment_status: formData.get('employment_status') || null
            });

            this.closeModal();
            this.showToast('Loan application submitted', 'success');
            this.loadPage('loans');
        } catch (error) {
            this.showToast(error.message || 'Failed to submit application', 'error');
        }
    }

    async showBuyStockModal() {
        try {
            const pricesResult = await this.apiRequest('/investments/prices?symbols=AAPL,GOOGL,MSFT,AMZN,TSLA,META,NVDA,JPM', 'GET');
            const prices = pricesResult.data.prices;

            const accountsResult = await this.apiRequest('/accounts', 'GET');
            const accounts = accountsResult.data.accounts.filter(a => a.type !== 'credit');

            const content = `
                <form id="buy-stock-form">
                    <div class="form-group">
                        <label>Select Stock</label>
                        <div class="stock-list">
                            ${prices.map(stock => `
                                <div class="stock-option" data-symbol="${stock.symbol}" data-price="${stock.current_price}">
                                    <div class="stock-info">
                                        <strong>${stock.symbol}</strong>
                                        <span>${stock.name}</span>
                                    </div>
                                    <div class="stock-price">${this.formatCurrency(stock.current_price)}</div>
                                </div>
                            `).join('')}
                        </div>
                        <input type="hidden" name="symbol" id="selected-symbol" required>
                        <input type="hidden" name="price" id="selected-price">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="quantity" placeholder="0" min="1" step="1" required>
                        </div>
                        <div class="form-group">
                            <label>From Account</label>
                            <select name="account_id" class="form-select" required>
                                ${accounts.map(acc => `<option value="${acc.id}">${acc.type} - ${this.formatCurrency(acc.balance)}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                </form>
            `;

            const footer = `
                <button class="btn btn-secondary" onclick="app.closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="app.submitBuyStock()">Buy Stock</button>
            `;

            this.openModal('Buy Stock', content, footer);

            // Add stock selection handler
            document.querySelectorAll('.stock-option').forEach(option => {
                option.addEventListener('click', () => {
                    document.querySelectorAll('.stock-option').forEach(o => o.classList.remove('selected'));
                    option.classList.add('selected');
                    document.getElementById('selected-symbol').value = option.dataset.symbol;
                    document.getElementById('selected-price').value = option.dataset.price;
                });
            });
        } catch (error) {
            this.showToast('Failed to load stock data', 'error');
        }
    }

    async submitBuyStock() {
        const form = document.getElementById('buy-stock-form');
        const formData = new FormData(form);

        const symbol = formData.get('symbol');
        if (!symbol) {
            this.showToast('Please select a stock', 'error');
            return;
        }

        try {
            await this.apiRequest('/investments/buy', 'POST', {
                symbol: symbol,
                quantity: parseFloat(formData.get('quantity')),
                account_id: formData.get('account_id'),
                price: parseFloat(document.getElementById('selected-price').value)
            });

            this.closeModal();
            this.showToast('Stock purchased successfully', 'success');
            this.loadPage('investments');
        } catch (error) {
            this.showToast(error.message || 'Failed to buy stock', 'error');
        }
    }

    // ==================== ACTION METHODS ====================

    async deposit(accountId) {
        const content = `
            <form id="deposit-form">
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" name="amount" placeholder="0.00" min="0.01" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Description (Optional)</label>
                    <input type="text" name="description" placeholder="e.g., Cash deposit">
                </div>
                <input type="hidden" name="account_id" value="${accountId}">
            </form>
        `;

        const footer = `
            <button class="btn btn-secondary" onclick="app.closeModal()">Cancel</button>
            <button class="btn btn-success" onclick="app.submitDeposit()">Deposit</button>
        `;

        this.openModal('Make Deposit', content, footer);
    }

    async submitDeposit() {
        const form = document.getElementById('deposit-form');
        const formData = new FormData(form);

        try {
            await this.apiRequest('/transactions/deposit', 'POST', {
                account_id: formData.get('account_id'),
                amount: parseFloat(formData.get('amount')),
                description: formData.get('description') || 'Deposit'
            });

            this.closeModal();
            this.showToast('Deposit successful', 'success');
            this.loadPage('accounts');
        } catch (error) {
            this.showToast(error.message || 'Deposit failed', 'error');
        }
    }

    async transfer(fromAccountId) {
        try {
            const result = await this.apiRequest('/accounts', 'GET');
            const accounts = result.data.accounts.filter(a => a.id != fromAccountId);

            const content = `
                <form id="transfer-modal-form">
                    <div class="form-group">
                        <label>To Account</label>
                        <select name="to_account_id" class="form-select" required>
                            ${accounts.map(acc => `<option value="${acc.id}">${acc.type} - ****${acc.account_number.slice(-4)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" name="amount" placeholder="0.00" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <input type="text" name="description" placeholder="e.g., Rent payment">
                    </div>
                    <input type="hidden" name="from_account_id" value="${fromAccountId}">
                </form>
            `;

            const footer = `
                <button class="btn btn-secondary" onclick="app.closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="app.submitTransfer()">Transfer</button>
            `;

            this.openModal('Transfer Money', content, footer);
        } catch (error) {
            this.showToast('Failed to load accounts', 'error');
        }
    }

    async submitTransfer() {
        const form = document.getElementById('transfer-modal-form');
        const formData = new FormData(form);

        try {
            await this.apiRequest('/transactions/transfer', 'POST', {
                from_account_id: formData.get('from_account_id'),
                to_account_id: formData.get('to_account_id'),
                amount: parseFloat(formData.get('amount')),
                description: formData.get('description') || 'Transfer'
            });

            this.closeModal();
            this.showToast('Transfer completed', 'success');
            this.loadPage('accounts');
        } catch (error) {
            this.showToast(error.message || 'Transfer failed', 'error');
        }
    }

    async viewAccountDetails(accountId) {
        try {
            const result = await this.apiRequest(`/accounts/details?id=${accountId}`, 'GET');
            const account = result.data.account;

            // Get recent transactions for this account
            const txResult = await this.apiRequest(`/transactions?account_id=${accountId}&limit=5`, 'GET');
            const transactions = txResult.data.transactions;

            const content = `
                <div class="account-details">
                    <div class="detail-row">
                        <span class="detail-label">Account Number</span>
                        <span class="detail-value">${account.account_number}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Type</span>
                        <span class="detail-value">${account.type.charAt(0).toUpperCase() + account.type.slice(1)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Currency</span>
                        <span class="detail-value">${account.currency}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value"><span class="badge badge-${account.status === 'active' ? 'success' : 'secondary'}">${account.status}</span></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Current Balance</span>
                        <span class="detail-value" style="font-size: 1.25rem; font-weight: 600;">${this.formatCurrency(account.balance)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Opened</span>
                        <span class="detail-value">${this.formatDate(account.opened_at)}</span>
                    </div>
                    
                    <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Recent Transactions</h4>
                    ${transactions.length > 0 ? `
                        <div class="table-container">
                            <table class="table">
                                <tbody>
                                    ${transactions.map(tx => `
                                        <tr>
                                            <td>${this.formatDate(tx.created_at)}</td>
                                            <td>${tx.description || tx.type}</td>
                                            <td class="amount ${this.getAmountClass(tx.type)}">${this.getAmountPrefix(tx.type)}${this.formatCurrency(tx.amount)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : '<p class="text-muted">No recent transactions</p>'}
                </div>
            `;

            const footer = `
                <button class="btn btn-secondary" onclick="app.closeModal()">Close</button>
                <button class="btn btn-primary" onclick="app.deposit('${accountId}')">Deposit</button>
            `;

            this.openModal('Account Details', content, footer);
        } catch (error) {
            this.showToast('Failed to load account details', 'error');
        }
    }

    async makeLoanPayment(loanId) {
        try {
            const accountsResult = await this.apiRequest('/accounts', 'GET');
            const accounts = accountsResult.data.accounts.filter(a => a.balance > 0);

            const content = `
                <form id="loan-payment-form">
                    <div class="form-group">
                        <label>Payment Amount</label>
                        <input type="number" name="amount" placeholder="0.00" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>From Account</label>
                        <select name="account_id" class="form-select" required>
                            ${accounts.map(acc => `<option value="${acc.id}">${acc.type} - ${this.formatCurrency(acc.balance)}</option>`).join('')}
                        </select>
                    </div>
                    <input type="hidden" name="loan_id" value="${loanId}">
                </form>
            `;

            const footer = `
                <button class="btn btn-secondary" onclick="app.closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="app.submitLoanPayment()">Make Payment</button>
            `;

            this.openModal('Make Loan Payment', content, footer);
        } catch (error) {
            this.showToast('Failed to load accounts', 'error');
        }
    }

    async submitLoanPayment() {
        const form = document.getElementById('loan-payment-form');
        const formData = new FormData(form);

        try {
            await this.apiRequest('/loans/payment', 'POST', {
                loan_id: formData.get('loan_id'),
                amount: parseFloat(formData.get('amount')),
                account_id: formData.get('account_id')
            });

            this.closeModal();
            this.showToast('Payment successful', 'success');
            this.loadPage('loans');
        } catch (error) {
            this.showToast(error.message || 'Payment failed', 'error');
        }
    }

    async sellStock(investmentId, symbol, maxQuantity) {
        try {
            const accountsResult = await this.apiRequest('/accounts', 'GET');
            const accounts = accountsResult.data.accounts.filter(a => a.type !== 'credit');

            const content = `
                <form id="sell-stock-form">
                    <div class="form-group">
                        <label>Selling ${symbol}</label>
                        <input type="number" name="quantity" placeholder="0" min="0.01" max="${maxQuantity}" step="0.01" required>
                        <small class="help-text">Max available: ${maxQuantity} shares</small>
                    </div>
                    <div class="form-group">
                        <label>To Account</label>
                        <select name="account_id" class="form-select" required>
                            ${accounts.map(acc => `<option value="${acc.id}">${acc.type} - ${this.formatCurrency(acc.balance)}</option>`).join('')}
                        </select>
                    </div>
                    <input type="hidden" name="investment_id" value="${investmentId}">
                </form>
            `;

            const footer = `
                <button class="btn btn-secondary" onclick="app.closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="app.submitSellStock()">Sell Stock</button>
            `;

            this.openModal('Sell Stock', content, footer);
        } catch (error) {
            this.showToast('Failed to load accounts', 'error');
        }
    }

    async submitSellStock() {
        const form = document.getElementById('sell-stock-form');
        const formData = new FormData(form);

        try {
            await this.apiRequest('/investments/sell', 'POST', {
                investment_id: formData.get('investment_id'),
                quantity: parseFloat(formData.get('quantity')),
                account_id: formData.get('account_id')
            });

            this.closeModal();
            this.showToast('Stock sold successfully', 'success');
            this.loadPage('investments');
        } catch (error) {
            this.showToast(error.message || 'Failed to sell stock', 'error');
        }
    }

    // ==================== CHARTS & REPORTS ====================

    async initializeCharts() {
        try {
            // Get data for charts
            const incomeExpense = await this.apiRequest('/reports/income-expense?months=6', 'GET');
            const categories = await this.apiRequest('/reports/spending-by-category', 'GET');
            const balanceHistory = await this.apiRequest('/reports/balance-history?months=6', 'GET');

            // Income vs Expense Chart
            const ieCtx = document.getElementById('income-expense-chart');
            if (ieCtx) {
                new Chart(ieCtx, {
                    type: 'bar',
                    data: {
                        labels: incomeExpense.data.data.map(d => d.month),
                        datasets: [
                            {
                                label: 'Income',
                                data: incomeExpense.data.data.map(d => d.income),
                                backgroundColor: '#10b981',
                                borderRadius: 4
                            },
                            {
                                label: 'Expenses',
                                data: incomeExpense.data.data.map(d => d.expenses),
                                backgroundColor: '#ef4444',
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#94a3b8' }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { color: '#94a3b8' },
                                grid: { color: '#334155' }
                            },
                            x: {
                                ticks: { color: '#94a3b8' },
                                grid: { display: false }
                            }
                        }
                    }
                });
            }

            // Category Chart
            const catCtx = document.getElementById('category-chart');
            if (catCtx) {
                new Chart(catCtx, {
                    type: 'doughnut',
                    data: {
                        labels: categories.data.categories.map(c => c.category),
                        datasets: [{
                            data: categories.data.categories.map(c => c.amount),
                            backgroundColor: [
                                '#3b82f6', '#10b981', '#f59e0b', '#ef4444',
                                '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#94a3b8' }
                            }
                        }
                    }
                });
            }

            // Balance History Chart
            const balCtx = document.getElementById('balance-chart');
            if (balCtx) {
                new Chart(balCtx, {
                    type: 'line',
                    data: {
                        labels: balanceHistory.data.history.map(h => h.month),
                        datasets: [{
                            label: 'Total Balance',
                            data: balanceHistory.data.history.map(h => h.balance),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { color: '#94a3b8' },
                                grid: { color: '#334155' }
                            },
                            x: {
                                ticks: { color: '#94a3b8' },
                                grid: { display: false }
                            }
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load chart data:', error);
        }
    }

    // ==================== SETTINGS ====================

    renderNotificationSettings() {
        const settings = {
            email_notifications: true,
            push_notifications: true,
            transaction_alerts: true,
            security_alerts: true,
            monthly_statements: true,
            marketing_emails: false
        };

        return Object.entries(settings).map(([key, value]) => `
            <div class="setting-item">
                <div class="setting-info">
                    <h4>${key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</h4>
                </div>
                <label class="toggle">
                    <input type="checkbox" name="${key}" ${value ? 'checked' : ''}>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        `).join('');
    }

    async handleProfileUpdate(e) {
        e.preventDefault();
        const formData = new FormData(e.target);

        try {
            await this.apiRequest('/auth/profile', 'PUT', {
                firstname: formData.get('firstname'),
                lastname: formData.get('lastname'),
                phone: formData.get('phone')
            });

            // Update local user data
            this.user.firstname = formData.get('firstname');
            this.user.lastname = formData.get('lastname');
            this.user.phone = formData.get('phone');
            localStorage.setItem('user', JSON.stringify(this.user));

            this.updateUserInfo();
            this.showToast('Profile updated', 'success');
        } catch (error) {
            this.showToast(error.message || 'Failed to update profile', 'error');
        }
    }

    async handlePasswordChange(e) {
        e.preventDefault();
        const formData = new FormData(e.target);

        if (formData.get('new_password') !== formData.get('confirm_password')) {
            this.showToast('Passwords do not match', 'error');
            return;
        }

        try {
            await this.apiRequest('/auth/change-password', 'POST', {
                current_password: formData.get('current_password'),
                new_password: formData.get('new_password')
            });

            this.showToast('Password changed successfully', 'success');
            e.target.reset();
        } catch (error) {
            this.showToast(error.message || 'Failed to change password', 'error');
        }
    }
}

// Initialize app
const app = new FinProApp();
