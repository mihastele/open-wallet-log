<?php
/**
 * Open Wallet Log - API Router
 * Main entry point for all API requests
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session
session_start();

// Load configuration
require_once __DIR__ . '/config/config.php';

// Load core classes
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Validator.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Security.php';
require_once __DIR__ . '/core/RateLimiter.php';

// Load models
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Account.php';
require_once __DIR__ . '/models/Transaction.php';
require_once __DIR__ . '/models/Loan.php';
require_once __DIR__ . '/models/Investment.php';
require_once __DIR__ . '/models/Notification.php';

// Get request path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/', '', $path);
$method = $_SERVER['REQUEST_METHOD'];

// Parse JSON body for POST/PUT requests
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Initialize rate limiter
$rateLimiter = new RateLimiter();
if (!$rateLimiter->check($_SERVER['REMOTE_ADDR'])) {
    Response::error('Rate limit exceeded. Please try again later.', 429);
}

// API Routing
$routes = [
    // Auth routes
    'auth/login' => ['POST', 'handleLogin'],
    'auth/register' => ['POST', 'handleRegister'],
    'auth/logout' => ['POST', 'handleLogout'],
    'auth/refresh' => ['POST', 'handleRefreshToken'],
    'auth/forgot-password' => ['POST', 'handleForgotPassword'],
    'auth/reset-password' => ['POST', 'handleResetPassword'],
    'auth/verify-email' => ['GET', 'handleVerifyEmail'],
    'auth/me' => ['GET', 'handleGetProfile'],
    'auth/profile' => ['PUT', 'handleUpdateProfile'],
    'auth/change-password' => ['POST', 'handleChangePassword'],
    
    // Account routes
    'accounts' => ['GET', 'handleGetAccounts'],
    'accounts/create' => ['POST', 'handleCreateAccount'],
    'accounts/balance' => ['GET', 'handleGetBalance'],
    'accounts/details' => ['GET', 'handleGetAccountDetails'],
    'accounts/close' => ['POST', 'handleCloseAccount'],
    
    // Transaction routes
    'transactions' => ['GET', 'handleGetTransactions'],
    'transactions/create' => ['POST', 'handleCreateTransaction'],
    'transactions/transfer' => ['POST', 'handleTransfer'],
    'transactions/deposit' => ['POST', 'handleDeposit'],
    'transactions/withdraw' => ['POST', 'handleWithdraw'],
    'transactions/recent' => ['GET', 'handleGetRecentTransactions'],
    
    // Loan routes
    'loans' => ['GET', 'handleGetLoans'],
    'loans/apply' => ['POST', 'handleApplyLoan'],
    'loans/details' => ['GET', 'handleGetLoanDetails'],
    'loans/payment' => ['POST', 'handleLoanPayment'],
    'loans/schedule' => ['GET', 'handleGetPaymentSchedule'],
    'loans/calculator' => ['POST', 'handleLoanCalculator'],
    
    // Investment routes
    'investments' => ['GET', 'handleGetInvestments'],
    'investments/portfolio' => ['GET', 'handleGetPortfolio'],
    'investments/buy' => ['POST', 'handleBuyInvestment'],
    'investments/sell' => ['POST', 'handleSellInvestment'],
    'investments/prices' => ['GET', 'handleGetMarketPrices'],
    'investments/performance' => ['GET', 'handleGetPortfolioPerformance'],
    
    // Report routes
    'reports/summary' => ['GET', 'handleGetSummary'],
    'reports/income-expense' => ['GET', 'handleGetIncomeExpense'],
    'reports/balance-history' => ['GET', 'handleGetBalanceHistory'],
    'reports/spending-by-category' => ['GET', 'handleGetSpendingByCategory'],
    'reports/export' => ['GET', 'handleExportReport'],
    
    // Notification routes
    'notifications' => ['GET', 'handleGetNotifications'],
    'notifications/mark-read' => ['POST', 'handleMarkNotificationRead'],
    'notifications/settings' => ['GET', 'handleGetNotificationSettings'],
    'notifications/settings' => ['PUT', 'handleUpdateNotificationSettings'],
    
    // Dashboard routes
    'dashboard/stats' => ['GET', 'handleGetDashboardStats'],
    'dashboard/activity' => ['GET', 'handleGetRecentActivity'],
    
    // Admin routes
    'admin/users' => ['GET', 'handleGetAllUsers'],
    'admin/user/status' => ['POST', 'handleUpdateUserStatus'],
    'admin/accounts/all' => ['GET', 'handleGetAllAccounts'],
    'admin/loans/pending' => ['GET', 'handleGetPendingLoans'],
    'admin/loans/approve' => ['POST', 'handleApproveLoan'],
    'admin/reports/system' => ['GET', 'handleGetSystemReports'],
    'admin/settings' => ['GET', 'handleGetSystemSettings'],
    'admin/settings' => ['PUT', 'handleUpdateSystemSettings'],
];

// Route the request
$found = false;
foreach ($routes as $route => $handler) {
    if (strpos($path, $route) !== false && $method === $handler[0]) {
        $found = true;
        try {
            call_user_func($handler[1], $input);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
        break;
    }
}

if (!$found) {
    Response::error('Endpoint not found', 404);
}

// ==================== AUTH HANDLERS ====================

function handleLogin($data) {
    $validator = new Validator($data);
    $validator->required('email')->email('email');
    $validator->required('password')->minLength('password', 6);
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $auth = new Auth();
    $result = $auth->login($data['email'], $data['password']);
    
    if ($result['success']) {
        Response::success([
            'token' => $result['token'],
            'user' => $result['user'],
            'expires' => $result['expires']
        ]);
    } else {
        Response::error($result['message'], 401);
    }
}

function handleRegister($data) {
    $validator = new Validator($data);
    $validator->required('firstname')->minLength('firstname', 2);
    $validator->required('lastname')->minLength('lastname', 2);
    $validator->required('email')->email('email');
    $validator->required('phone')->phone('phone');
    $validator->required('password')->minLength('password', 8);
    $validator->required('confirm');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    if ($data['password'] !== $data['confirm']) {
        Response::error('Passwords do not match', 400);
    }
    
    $auth = new Auth();
    $result = $auth->register([
        'firstname' => $data['firstname'],
        'lastname' => $data['lastname'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'password' => $data['password']
    ]);
    
    if ($result['success']) {
        Response::success(['message' => 'Registration successful. Please verify your email.'], 201);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleLogout($data) {
    $auth = new Auth();
    $auth->logout();
    Response::success(['message' => 'Logged out successfully']);
}

function handleRefreshToken($data) {
    // Implement token refresh logic
    Response::success(['message' => 'Token refreshed']);
}

function handleForgotPassword($data) {
    $validator = new Validator($data);
    $validator->required('email')->email('email');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $auth = new Auth();
    $auth->sendPasswordReset($data['email']);
    
    Response::success(['message' => 'Password reset instructions sent to your email']);
}

function handleResetPassword($data) {
    $validator = new Validator($data);
    $validator->required('token');
    $validator->required('password')->minLength('password', 8);
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $auth = new Auth();
    $result = $auth->resetPassword($data['token'], $data['password']);
    
    if ($result['success']) {
        Response::success(['message' => 'Password reset successful']);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleVerifyEmail($data) {
    $token = $_GET['token'] ?? '';
    
    $auth = new Auth();
    $result = $auth->verifyEmail($token);
    
    if ($result['success']) {
        Response::success(['message' => 'Email verified successfully']);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleGetProfile($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $userModel = new User();
    $profile = $userModel->getById($user['id']);
    
    Response::success(['user' => $profile]);
}

function handleUpdateProfile($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->optional('firstname')->minLength('firstname', 2);
    $validator->optional('lastname')->minLength('lastname', 2);
    $validator->optional('phone')->phone('phone');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $userModel = new User();
    $result = $userModel->update($user['id'], $data);
    
    if ($result) {
        Response::success(['message' => 'Profile updated successfully']);
    } else {
        Response::error('Failed to update profile', 500);
    }
}

function handleChangePassword($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('current_password');
    $validator->required('new_password')->minLength('new_password', 8);
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $auth = new Auth();
    $result = $auth->changePassword($user['id'], $data['current_password'], $data['new_password']);
    
    if ($result['success']) {
        Response::success(['message' => 'Password changed successfully']);
    } else {
        Response::error($result['message'], 400);
    }
}

// ==================== ACCOUNT HANDLERS ====================

function handleGetAccounts($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $accountModel = new Account();
    $accounts = $accountModel->getByUserId($user['id']);
    
    Response::success(['accounts' => $accounts]);
}

function handleCreateAccount($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('type')->inArray('type', ['checking', 'savings', 'investment']);
    $validator->required('currency')->inArray('currency', ['USD', 'EUR', 'GBP']);
    $validator->optional('initial_deposit')->numeric('initial_deposit');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $accountModel = new Account();
    $result = $accountModel->create([
        'user_id' => $user['id'],
        'type' => $data['type'],
        'currency' => $data['currency'],
        'initial_deposit' => $data['initial_deposit'] ?? 0
    ]);
    
    if ($result['success']) {
        Response::success(['account' => $result['account']], 201);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleGetBalance($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $accountId = $_GET['account_id'] ?? null;
    
    $accountModel = new Account();
    $balance = $accountModel->getBalance($user['id'], $accountId);
    
    Response::success(['balance' => $balance]);
}

function handleGetAccountDetails($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $accountId = $_GET['id'] ?? null;
    if (!$accountId) {
        Response::error('Account ID required', 400);
    }
    
    $accountModel = new Account();
    $account = $accountModel->getById($accountId, $user['id']);
    
    if ($account) {
        Response::success(['account' => $account]);
    } else {
        Response::error('Account not found', 404);
    }
}

function handleCloseAccount($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('account_id');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $accountModel = new Account();
    $result = $accountModel->close($data['account_id'], $user['id']);
    
    if ($result['success']) {
        Response::success(['message' => 'Account closed successfully']);
    } else {
        Response::error($result['message'], 400);
    }
}

// ==================== TRANSACTION HANDLERS ====================

function handleGetTransactions($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $filters = [
        'account_id' => $_GET['account_id'] ?? null,
        'type' => $_GET['type'] ?? null,
        'start_date' => $_GET['start_date'] ?? null,
        'end_date' => $_GET['end_date'] ?? null,
        'limit' => $_GET['limit'] ?? 50,
        'offset' => $_GET['offset'] ?? 0
    ];
    
    $transactionModel = new Transaction();
    $transactions = $transactionModel->getByUserId($user['id'], $filters);
    
    Response::success(['transactions' => $transactions]);
}

function handleCreateTransaction($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('account_id');
    $validator->required('type')->inArray('type', ['deposit', 'withdrawal', 'transfer', 'payment']);
    $validator->required('amount')->numeric('amount')->positive('amount');
    $validator->optional('description');
    $validator->optional('category');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $transactionModel = new Transaction();
    $result = $transactionModel->create([
        'user_id' => $user['id'],
        'account_id' => $data['account_id'],
        'type' => $data['type'],
        'amount' => $data['amount'],
        'description' => $data['description'] ?? '',
        'category' => $data['category'] ?? 'uncategorized'
    ]);
    
    if ($result['success']) {
        Response::success(['transaction' => $result['transaction']], 201);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleTransfer($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('from_account_id');
    $validator->required('to_account_id');
    $validator->required('amount')->numeric('amount')->positive('amount');
    $validator->optional('description');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $transactionModel = new Transaction();
    $result = $transactionModel->transfer([
        'user_id' => $user['id'],
        'from_account_id' => $data['from_account_id'],
        'to_account_id' => $data['to_account_id'],
        'amount' => $data['amount'],
        'description' => $data['description'] ?? 'Transfer'
    ]);
    
    if ($result['success']) {
        Response::success(['transaction' => $result['transaction']], 201);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleDeposit($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('account_id');
    $validator->required('amount')->numeric('amount')->positive('amount');
    $validator->optional('description');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $transactionModel = new Transaction();
    $result = $transactionModel->deposit([
        'user_id' => $user['id'],
        'account_id' => $data['account_id'],
        'amount' => $data['amount'],
        'description' => $data['description'] ?? 'Deposit'
    ]);
    
    if ($result['success']) {
        Response::success(['transaction' => $result['transaction']], 201);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleWithdraw($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('account_id');
    $validator->required('amount')->numeric('amount')->positive('amount');
    $validator->optional('description');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $transactionModel = new Transaction();
    $result = $transactionModel->withdraw([
        'user_id' => $user['id'],
        'account_id' => $data['account_id'],
        'amount' => $data['amount'],
        'description' => $data['description'] ?? 'Withdrawal'
    ]);
    
    if ($result['success']) {
        Response::success(['transaction' => $result['transaction']], 201);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleGetRecentTransactions($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $limit = $_GET['limit'] ?? 10;
    
    $transactionModel = new Transaction();
    $transactions = $transactionModel->getRecent($user['id'], $limit);
    
    Response::success(['transactions' => $transactions]);
}

// ==================== LOAN HANDLERS ====================

function handleGetLoans($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $loanModel = new Loan();
    $loans = $loanModel->getByUserId($user['id']);
    
    Response::success(['loans' => $loans]);
}

function handleApplyLoan($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('amount')->numeric('amount')->positive('amount');
    $validator->required('purpose');
    $validator->required('term_months')->numeric('term_months')->positive('term_months');
    $validator->required('type')->inArray('type', ['personal', 'business', 'mortgage', 'auto']);
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $loanModel = new Loan();
    $result = $loanModel->apply([
        'user_id' => $user['id'],
        'amount' => $data['amount'],
        'purpose' => $data['purpose'],
        'term_months' => $data['term_months'],
        'type' => $data['type'],
        'income' => $data['income'] ?? null,
        'employment_status' => $data['employment_status'] ?? null
    ]);
    
    if ($result['success']) {
        Response::success(['loan' => $result['loan']], 201);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleGetLoanDetails($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $loanId = $_GET['id'] ?? null;
    if (!$loanId) {
        Response::error('Loan ID required', 400);
    }
    
    $loanModel = new Loan();
    $loan = $loanModel->getById($loanId, $user['id']);
    
    if ($loan) {
        Response::success(['loan' => $loan]);
    } else {
        Response::error('Loan not found', 404);
    }
}

function handleLoanPayment($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('loan_id');
    $validator->required('amount')->numeric('amount')->positive('amount');
    $validator->required('account_id');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $loanModel = new Loan();
    $result = $loanModel->makePayment([
        'user_id' => $user['id'],
        'loan_id' => $data['loan_id'],
        'amount' => $data['amount'],
        'account_id' => $data['account_id']
    ]);
    
    if ($result['success']) {
        Response::success(['payment' => $result['payment']]);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleGetPaymentSchedule($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $loanId = $_GET['loan_id'] ?? null;
    if (!$loanId) {
        Response::error('Loan ID required', 400);
    }
    
    $loanModel = new Loan();
    $schedule = $loanModel->getPaymentSchedule($loanId, $user['id']);
    
    Response::success(['schedule' => $schedule]);
}

function handleLoanCalculator($data) {
    $validator = new Validator($data);
    $validator->required('amount')->numeric('amount')->positive('amount');
    $validator->required('term_months')->numeric('term_months')->positive('term_months');
    $validator->required('interest_rate')->numeric('interest_rate')->positive('interest_rate');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $loanModel = new Loan();
    $calculation = $loanModel->calculate(
        $data['amount'],
        $data['term_months'],
        $data['interest_rate']
    );
    
    Response::success(['calculation' => $calculation]);
}

// ==================== INVESTMENT HANDLERS ====================

function handleGetInvestments($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $investmentModel = new Investment();
    $investments = $investmentModel->getByUserId($user['id']);
    
    Response::success(['investments' => $investments]);
}

function handleGetPortfolio($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $investmentModel = new Investment();
    $portfolio = $investmentModel->getPortfolio($user['id']);
    
    Response::success(['portfolio' => $portfolio]);
}

function handleBuyInvestment($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('symbol');
    $validator->required('quantity')->numeric('quantity')->positive('quantity');
    $validator->required('account_id');
    $validator->optional('price')->numeric('price');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $investmentModel = new Investment();
    $result = $investmentModel->buy([
        'user_id' => $user['id'],
        'symbol' => $data['symbol'],
        'quantity' => $data['quantity'],
        'account_id' => $data['account_id'],
        'price' => $data['price'] ?? null
    ]);
    
    if ($result['success']) {
        Response::success(['transaction' => $result['transaction']], 201);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleSellInvestment($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $validator = new Validator($data);
    $validator->required('investment_id');
    $validator->required('quantity')->numeric('quantity')->positive('quantity');
    $validator->required('account_id');
    $validator->optional('price')->numeric('price');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $investmentModel = new Investment();
    $result = $investmentModel->sell([
        'user_id' => $user['id'],
        'investment_id' => $data['investment_id'],
        'quantity' => $data['quantity'],
        'account_id' => $data['account_id'],
        'price' => $data['price'] ?? null
    ]);
    
    if ($result['success']) {
        Response::success(['transaction' => $result['transaction']], 201);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleGetMarketPrices($data) {
    $symbols = $_GET['symbols'] ?? '';
    $symbolArray = explode(',', $symbols);
    
    $investmentModel = new Investment();
    $prices = $investmentModel->getMarketPrices($symbolArray);
    
    Response::success(['prices' => $prices]);
}

function handleGetPortfolioPerformance($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $period = $_GET['period'] ?? '1y';
    
    $investmentModel = new Investment();
    $performance = $investmentModel->getPerformance($user['id'], $period);
    
    Response::success(['performance' => $performance]);
}

// ==================== REPORT HANDLERS ====================

function handleGetSummary($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    // Aggregate data from various models
    $accountModel = new Account();
    $transactionModel = new Transaction();
    $loanModel = new Loan();
    $investmentModel = new Investment();
    
    $summary = [
        'total_balance' => $accountModel->getTotalBalance($user['id']),
        'total_accounts' => $accountModel->getAccountCount($user['id']),
        'monthly_income' => $transactionModel->getMonthlyIncome($user['id'], $startDate, $endDate),
        'monthly_expenses' => $transactionModel->getMonthlyExpenses($user['id'], $startDate, $endDate),
        'active_loans' => $loanModel->getActiveLoanCount($user['id']),
        'total_loan_amount' => $loanModel->getTotalLoanAmount($user['id']),
        'portfolio_value' => $investmentModel->getPortfolioValue($user['id']),
        'period' => ['start' => $startDate, 'end' => $endDate]
    ];
    
    Response::success(['summary' => $summary]);
}

function handleGetIncomeExpense($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $period = $_GET['period'] ?? 'monthly';
    $months = $_GET['months'] ?? 12;
    
    $transactionModel = new Transaction();
    $data = $transactionModel->getIncomeExpenseReport($user['id'], $period, $months);
    
    Response::success(['data' => $data]);
}

function handleGetBalanceHistory($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $accountId = $_GET['account_id'] ?? null;
    $months = $_GET['months'] ?? 12;
    
    $accountModel = new Account();
    $history = $accountModel->getBalanceHistory($user['id'], $accountId, $months);
    
    Response::success(['history' => $history]);
}

function handleGetSpendingByCategory($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    $transactionModel = new Transaction();
    $categories = $transactionModel->getSpendingByCategory($user['id'], $startDate, $endDate);
    
    Response::success(['categories' => $categories]);
}

function handleExportReport($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $type = $_GET['type'] ?? 'transactions';
    $format = $_GET['format'] ?? 'csv';
    
    // Implementation for report export
    Response::success(['message' => 'Report export initiated', 'download_url' => '/downloads/report_' . time() . '.' . $format]);
}

// ==================== NOTIFICATION HANDLERS ====================

function handleGetNotifications($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $limit = $_GET['limit'] ?? 20;
    $unreadOnly = $_GET['unread'] ?? false;
    
    $notificationModel = new Notification();
    $notifications = $notificationModel->getByUserId($user['id'], $limit, $unreadOnly);
    $unreadCount = $notificationModel->getUnreadCount($user['id']);
    
    Response::success([
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);
}

function handleMarkNotificationRead($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $notificationId = $data['notification_id'] ?? null;
    $markAll = $data['mark_all'] ?? false;
    
    $notificationModel = new Notification();
    
    if ($markAll) {
        $result = $notificationModel->markAllRead($user['id']);
    } else {
        $result = $notificationModel->markRead($notificationId, $user['id']);
    }
    
    if ($result) {
        Response::success(['message' => 'Notifications updated']);
    } else {
        Response::error('Failed to update notifications', 500);
    }
}

// ==================== DASHBOARD HANDLERS ====================

function handleGetDashboardStats($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $accountModel = new Account();
    $transactionModel = new Transaction();
    $loanModel = new Loan();
    $investmentModel = new Investment();
    
    $stats = [
        'total_balance' => $accountModel->getTotalBalance($user['id']),
        'monthly_change' => $transactionModel->getMonthlyChange($user['id']),
        'pending_transactions' => $transactionModel->getPendingCount($user['id']),
        'active_loans' => $loanModel->getActiveLoanCount($user['id']),
        'portfolio_value' => $investmentModel->getPortfolioValue($user['id']),
        'quick_stats' => [
            ['label' => 'Checking', 'value' => $accountModel->getBalanceByType($user['id'], 'checking')],
            ['label' => 'Savings', 'value' => $accountModel->getBalanceByType($user['id'], 'savings')],
            ['label' => 'Investments', 'value' => $investmentModel->getPortfolioValue($user['id'])],
            ['label' => 'Loans', 'value' => $loanModel->getTotalOwed($user['id'])]
        ]
    ];
    
    Response::success(['stats' => $stats]);
}

function handleGetRecentActivity($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $limit = $_GET['limit'] ?? 10;
    
    $transactionModel = new Transaction();
    $activities = $transactionModel->getRecentActivity($user['id'], $limit);
    
    Response::success(['activities' => $activities]);
}

// ==================== ADMIN HANDLERS ====================

function handleGetAllUsers($data) {
    $user = Auth::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    
    $filters = [
        'search' => $_GET['search'] ?? '',
        'status' => $_GET['status'] ?? '',
        'role' => $_GET['role'] ?? '',
        'limit' => $_GET['limit'] ?? 50,
        'offset' => $_GET['offset'] ?? 0
    ];
    
    $userModel = new User();
    $users = $userModel->getAll($filters);
    
    Response::success(['users' => $users]);
}

function handleUpdateUserStatus($data) {
    $user = Auth::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    
    $validator = new Validator($data);
    $validator->required('user_id');
    $validator->required('status')->inArray('status', ['active', 'inactive', 'suspended']);
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $userModel = new User();
    $result = $userModel->updateStatus($data['user_id'], $data['status']);
    
    if ($result) {
        Response::success(['message' => 'User status updated']);
    } else {
        Response::error('Failed to update user status', 500);
    }
}

function handleGetAllAccounts($data) {
    $user = Auth::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    
    $filters = [
        'type' => $_GET['type'] ?? '',
        'status' => $_GET['status'] ?? '',
        'limit' => $_GET['limit'] ?? 50,
        'offset' => $_GET['offset'] ?? 0
    ];
    
    $accountModel = new Account();
    $accounts = $accountModel->getAll($filters);
    
    Response::success(['accounts' => $accounts]);
}

function handleGetPendingLoans($data) {
    $user = Auth::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    
    $loanModel = new Loan();
    $loans = $loanModel->getPending();
    
    Response::success(['loans' => $loans]);
}

function handleApproveLoan($data) {
    $user = Auth::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    
    $validator = new Validator($data);
    $validator->required('loan_id');
    $validator->required('action')->inArray('action', ['approve', 'reject']);
    $validator->optional('reason');
    
    if (!$validator->isValid()) {
        Response::error($validator->getErrors(), 400);
    }
    
    $loanModel = new Loan();
    $result = $loanModel->processApplication($data['loan_id'], $data['action'], $data['reason'] ?? '');
    
    if ($result['success']) {
        Response::success(['message' => 'Loan application ' . $data['action'] . 'd']);
    } else {
        Response::error($result['message'], 400);
    }
}

function handleGetSystemReports($data) {
    $user = Auth::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    
    // Generate system-wide reports
    $userModel = new User();
    $accountModel = new Account();
    $transactionModel = new Transaction();
    $loanModel = new Loan();
    
    $reports = [
        'total_users' => $userModel->getTotalCount(),
        'active_users_today' => $userModel->getActiveToday(),
        'total_accounts' => $accountModel->getTotalCount(),
        'total_balance' => $accountModel->getSystemTotalBalance(),
        'transactions_today' => $transactionModel->getTodayCount(),
        'transaction_volume_today' => $transactionModel->getTodayVolume(),
        'pending_loans' => $loanModel->getPendingCount(),
        'total_loans_issued' => $loanModel->getTotalIssued()
    ];
    
    Response::success(['reports' => $reports]);
}

// ==================== UTILITY FUNCTIONS ====================

function handleGetSystemSettings($data) {
    $user = Auth::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    
    // Return system configuration
    $settings = [
        'maintenance_mode' => false,
        'registration_enabled' => true,
        'max_accounts_per_user' => 5,
        'default_currency' => 'USD',
        'interest_rates' => [
            'savings' => 0.025,
            'loan_personal' => 0.0899,
            'loan_business' => 0.0699,
            'loan_mortgage' => 0.0459
        ],
        'transaction_limits' => [
            'daily_transfer' => 10000,
            'daily_withdrawal' => 5000,
            'single_transfer' => 5000
        ]
    ];
    
    Response::success(['settings' => $settings]);
}

function handleUpdateSystemSettings($data) {
    $user = Auth::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    
    // Update system settings logic here
    Response::success(['message' => 'Settings updated']);
}

function handleGetNotificationSettings($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    $settings = [
        'email_notifications' => true,
        'push_notifications' => true,
        'sms_notifications' => false,
        'transaction_alerts' => true,
        'security_alerts' => true,
        'marketing_emails' => false,
        'monthly_statements' => true
    ];
    
    Response::success(['settings' => $settings]);
}

function handleUpdateNotificationSettings($data) {
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Unauthorized', 401);
    }
    
    // Save notification settings
    Response::success(['message' => 'Notification settings updated']);
}
