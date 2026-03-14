<?php
/**
 * Investment Model
 */
class Investment {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getById($id, $userId = null) {
        $sql = "SELECT i.*, s.name as stock_name, s.symbol
                FROM investments i 
                JOIN stocks s ON i.stock_id = s.id 
                WHERE i.id = ?";
        $params = [$id];
        
        if ($userId !== null) {
            $sql .= " AND i.user_id = ?";
            $params[] = $userId;
        }
        
        return $this->db->fetch($sql, $params);
    }
    
    public function getByUserId($userId) {
        $investments = $this->db->fetchAll(
            "SELECT i.*, s.name as stock_name, s.symbol, s.current_price,
                    (i.quantity * s.current_price) as current_value,
                    (i.quantity * s.current_price) - (i.quantity * i.purchase_price) as profit_loss
             FROM investments i 
             JOIN stocks s ON i.stock_id = s.id 
             WHERE i.user_id = ? AND i.status = 'active'
             ORDER BY i.purchase_date DESC",
            [$userId]
        );
        
        return $investments;
    }
    
    public function getPortfolio($userId) {
        $investments = $this->getByUserId($userId);
        
        $totalInvested = 0;
        $totalValue = 0;
        $totalProfitLoss = 0;
        
        foreach ($investments as &$investment) {
            $totalInvested += $investment['quantity'] * $investment['purchase_price'];
            $totalValue += $investment['current_value'];
            $totalProfitLoss += $investment['profit_loss'];
        }
        
        // Get portfolio distribution by sector
        $distribution = $this->db->fetchAll(
            "SELECT s.sector, 
                    SUM(i.quantity * s.current_price) as value,
                    COUNT(*) as count
             FROM investments i 
             JOIN stocks s ON i.stock_id = s.id 
             WHERE i.user_id = ? AND i.status = 'active'
             GROUP BY s.sector",
            [$userId]
        );
        
        // Get top performers
        $topPerformers = array_slice(
            array_filter($investments, function($inv) {
                return $inv['profit_loss'] > 0;
            }),
            0, 5
        );
        
        return [
            'investments' => $investments,
            'summary' => [
                'total_invested' => round($totalInvested, 2),
                'total_value' => round($totalValue, 2),
                'total_profit_loss' => round($totalProfitLoss, 2),
                'return_percentage' => $totalInvested > 0 ? round(($totalProfitLoss / $totalInvested) * 100, 2) : 0,
                'investment_count' => count($investments)
            ],
            'distribution' => $distribution,
            'top_performers' => $topPerformers
        ];
    }
    
    public function getPortfolioValue($userId) {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(i.quantity * s.current_price), 0) as total 
             FROM investments i 
             JOIN stocks s ON i.stock_id = s.id 
             WHERE i.user_id = ? AND i.status = 'active'",
            [$userId]
        );
        return (float)$result['total'];
    }
    
    public function buy($data) {
        try {
            $this->db->beginTransaction();
            
            // Get stock info
            $stock = $this->db->fetch(
                "SELECT id, current_price FROM stocks WHERE symbol = ? LIMIT 1",
                [$data['symbol']]
            );
            
            if (!$stock) {
                // Create stock if doesn't exist (for demo purposes)
                $stockId = $this->createStock($data['symbol'], $data['price'] ?? 100);
                $stockPrice = $data['price'] ?? 100;
            } else {
                $stockId = $stock['id'];
                $stockPrice = $data['price'] ?? $stock['current_price'];
            }
            
            $totalCost = $stockPrice * $data['quantity'];
            
            // Check account balance
            $account = $this->db->fetch(
                "SELECT balance FROM accounts WHERE id = ? AND user_id = ? LIMIT 1",
                [$data['account_id'], $data['user_id']]
            );
            
            if (!$account) {
                throw new Exception('Account not found');
            }
            
            if ($account['balance'] < $totalCost) {
                throw new Exception('Insufficient funds');
            }
            
            // Check if user already owns this stock
            $existing = $this->db->fetch(
                "SELECT id, quantity, purchase_price FROM investments 
                 WHERE user_id = ? AND stock_id = ? AND status = 'active' LIMIT 1",
                [$data['user_id'], $stockId]
            );
            
            if ($existing) {
                // Update existing investment (average cost basis)
                $newQuantity = $existing['quantity'] + $data['quantity'];
                $newAveragePrice = (($existing['quantity'] * $existing['purchase_price']) + 
                                   ($data['quantity'] * $stockPrice)) / $newQuantity;
                
                $this->db->update(
                    "UPDATE investments SET quantity = ?, purchase_price = ?, updated_at = NOW() 
                     WHERE id = ?",
                    [$newQuantity, $newAveragePrice, $existing['id']]
                );
                
                $investmentId = $existing['id'];
            } else {
                // Create new investment
                $investmentId = $this->db->insert(
                    "INSERT INTO investments (user_id, stock_id, quantity, purchase_price, 
                     purchase_date, status, created_at) 
                     VALUES (?, ?, ?, ?, NOW(), 'active', NOW())",
                    [
                        $data['user_id'],
                        $stockId,
                        $data['quantity'],
                        $stockPrice
                    ]
                );
            }
            
            // Deduct from account
            $this->db->insert(
                "INSERT INTO transactions (user_id, account_id, type, amount, description, 
                 category, status, created_at) 
                 VALUES (?, ?, 'payment', ?, ?, 'investment', 'completed', NOW())",
                [
                    $data['user_id'],
                    $data['account_id'],
                    $totalCost,
                    'Buy ' . $data['quantity'] . ' shares of ' . $data['symbol']
                ]
            );
            
            $this->db->update(
                "UPDATE accounts SET balance = balance - ? WHERE id = ?",
                [$totalCost, $data['account_id']]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'transaction' => [
                    'investment_id' => $investmentId,
                    'symbol' => $data['symbol'],
                    'quantity' => $data['quantity'],
                    'price' => $stockPrice,
                    'total_cost' => $totalCost
                ]
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function sell($data) {
        try {
            $this->db->beginTransaction();
            
            // Get investment
            $investment = $this->db->fetch(
                "SELECT i.*, s.symbol, s.current_price 
                 FROM investments i 
                 JOIN stocks s ON i.stock_id = s.id 
                 WHERE i.id = ? AND i.user_id = ? AND i.status = 'active' LIMIT 1",
                [$data['investment_id'], $data['user_id']]
            );
            
            if (!$investment) {
                throw new Exception('Investment not found');
            }
            
            if ($investment['quantity'] < $data['quantity']) {
                throw new Exception('Insufficient shares');
            }
            
            $sellPrice = $data['price'] ?? $investment['current_price'];
            $totalProceeds = $sellPrice * $data['quantity'];
            $costBasis = $investment['purchase_price'] * $data['quantity'];
            $profitLoss = $totalProceeds - $costBasis;
            
            // Update or delete investment
            $newQuantity = $investment['quantity'] - $data['quantity'];
            if ($newQuantity == 0) {
                $this->db->update(
                    "UPDATE investments SET status = 'sold', sold_date = NOW(), 
                     sold_price = ?, updated_at = NOW() WHERE id = ?",
                    [$sellPrice, $data['investment_id']]
                );
            } else {
                $this->db->update(
                    "UPDATE investments SET quantity = ?, updated_at = NOW() WHERE id = ?",
                    [$newQuantity, $data['investment_id']]
                );
            }
            
            // Add proceeds to account
            $this->db->insert(
                "INSERT INTO transactions (user_id, account_id, type, amount, description, 
                 category, status, created_at) 
                 VALUES (?, ?, 'deposit', ?, ?, 'investment', 'completed', NOW())",
                [
                    $data['user_id'],
                    $data['account_id'],
                    $totalProceeds,
                    'Sell ' . $data['quantity'] . ' shares of ' . $investment['symbol']
                ]
            );
            
            $this->db->update(
                "UPDATE accounts SET balance = balance + ? WHERE id = ?",
                [$totalProceeds, $data['account_id']]
            );
            
            // Record sale
            $this->db->insert(
                "INSERT INTO investment_sales (investment_id, quantity, sell_price, 
                 total_proceeds, profit_loss, account_id, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $data['investment_id'],
                    $data['quantity'],
                    $sellPrice,
                    $totalProceeds,
                    $profitLoss,
                    $data['account_id']
                ]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'transaction' => [
                    'symbol' => $investment['symbol'],
                    'quantity' => $data['quantity'],
                    'sell_price' => $sellPrice,
                    'total_proceeds' => $totalProceeds,
                    'profit_loss' => $profitLoss
                ]
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getMarketPrices($symbols) {
        // In a real app, this would fetch from an API
        // For demo, return mock data
        $prices = [];
        $defaultPrices = [
            'AAPL' => 175.50,
            'GOOGL' => 142.30,
            'MSFT' => 378.90,
            'AMZN' => 178.20,
            'TSLA' => 248.50,
            'META' => 505.20,
            'NVDA' => 875.30,
            'JPM' => 195.40,
            'JNJ' => 152.80,
            'V' => 285.60
        ];
        
        foreach ($symbols as $symbol) {
            $upperSymbol = strtoupper($symbol);
            $basePrice = $defaultPrices[$upperSymbol] ?? 100;
            
            // Add some random variation
            $variation = (random_int(-50, 50) / 1000);
            $price = $basePrice * (1 + $variation);
            
            $change = $basePrice * $variation;
            $changePercent = $variation * 100;
            
            $prices[$upperSymbol] = [
                'symbol' => $upperSymbol,
                'price' => round($price, 2),
                'change' => round($change, 2),
                'change_percent' => round($changePercent, 2),
                'updated_at' => date('c')
            ];
        }
        
        return $prices;
    }
    
    public function getPerformance($userId, $period = '1y') {
        // Generate mock performance data
        $months = $period === '1y' ? 12 : ($period === '6m' ? 6 : 3);
        $data = [];
        
        $baseValue = $this->getPortfolioValue($userId);
        if ($baseValue == 0) $baseValue = 10000;
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = date('Y-m', strtotime("-{$i} months"));
            $variation = (random_int(-100, 150) / 1000);
            $value = $baseValue * (1 + $variation);
            
            $data[] = [
                'month' => $date,
                'value' => round($value, 2),
                'change' => round($variation * 100, 2)
            ];
        }
        
        return $data;
    }
    
    private function createStock($symbol, $price) {
        $sectors = ['Technology', 'Finance', 'Healthcare', 'Consumer', 'Energy'];
        $names = [
            'AAPL' => 'Apple Inc.',
            'GOOGL' => 'Alphabet Inc.',
            'MSFT' => 'Microsoft Corp.',
            'AMZN' => 'Amazon.com Inc.',
            'TSLA' => 'Tesla Inc.',
            'META' => 'Meta Platforms',
            'NVDA' => 'NVIDIA Corp.',
            'JPM' => 'JPMorgan Chase',
            'JNJ' => 'Johnson & Johnson',
            'V' => 'Visa Inc.'
        ];
        
        return $this->db->insert(
            "INSERT INTO stocks (symbol, name, sector, current_price, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [
                strtoupper($symbol),
                $names[strtoupper($symbol)] ?? $symbol . ' Corp.',
                $sectors[array_rand($sectors)],
                $price
            ]
        );
    }
}
