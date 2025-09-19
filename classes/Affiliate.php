<?php
require_once '../config/database.php';

class Affiliate {
    private $conn;
    private $table_name = "affiliates";
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Generate unique affiliate ID
    private function generateAffiliateId() {
        do {
            $id = 'AFF' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $query = "SELECT id FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
        } while ($stmt->rowCount() > 0);
        
        return $id;
    }
    
    // Create new affiliate
    public function create($name, $email, $password) {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET id = :id, name = :name, email = :email, password_hash = :password";
        
        $stmt = $this->conn->prepare($query);
        
        // Generate ID and hash password
        $affiliate_id = $this->generateAffiliateId();
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt->bindParam(':id', $affiliate_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password_hash);
        
        if($stmt->execute()) {
            return $affiliate_id;
        }
        return false;
    }
    
    // Login affiliate
    public function login($email, $password) {
        $query = "SELECT id, name, email, password_hash, status FROM " . $this->table_name . " 
                  WHERE email = :email";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($row['status'] !== 'active') {
                return ['error' => 'Account is not active'];
            }
            
            if(password_verify($password, $row['password_hash'])) {
                // Create session token
                $token = $this->createSession($row['id']);
                return [
                    'success' => true,
                    'affiliate_id' => $row['id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'token' => $token
                ];
            }
        }
        
        return ['error' => 'Invalid email or password'];
    }
    
    // Create session token
    private function createSession($affiliate_id) {
        // Clean old expired sessions
        $this->cleanExpiredSessions();
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $query = "INSERT INTO affiliate_sessions SET 
                  affiliate_id = :affiliate_id, 
                  session_token = :token, 
                  expires_at = :expires";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':affiliate_id', $affiliate_id);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires', $expires);
        
        if($stmt->execute()) {
            return $token;
        }
        return false;
    }
    
    // Verify session token
    public function verifyToken($token) {
        $query = "SELECT a.*, s.expires_at 
                  FROM affiliate_sessions s 
                  JOIN affiliates a ON s.affiliate_id = a.id 
                  WHERE s.session_token = :token AND s.expires_at > NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }
    
    // Get affiliate dashboard data
    public function getDashboardData($affiliate_id) {
        // Get basic affiliate info
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $affiliate_id);
        $stmt->execute();
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$affiliate) return false;
        
        // Calculate conversion rate
        $conversion_rate = $affiliate['total_clicks'] > 0 ? 
                          round(($affiliate['total_sales'] / $affiliate['total_clicks']) * 100, 2) : 0;
        
        // Get recent activity
        $recent_activity = $this->getRecentActivity($affiliate_id, 10);
        
        // Get tier info
        $tier_info = $this->getTierInfo($affiliate['tier'], $affiliate['total_earnings']);
        
        // Get next payout date
        $next_payout = $this->getNextPayoutDate();
        
        return [
            'id' => $affiliate['id'],
            'name' => $affiliate['name'],
            'email' => $affiliate['email'],
            'tier' => $affiliate['tier'],
            'commissionRate' => $tier_info['rate'],
            'totalEarnings' => (float)$affiliate['total_earnings'],
            'totalClicks' => (int)$affiliate['total_clicks'],
            'totalSales' => (int)$affiliate['total_sales'],
            'conversionRate' => $conversion_rate,
            'pendingBalance' => (float)$affiliate['pending_balance'],
            'paidToDate' => (float)$affiliate['paid_to_date'],
            'tierProgress' => $tier_info['progress'],
            'nextTierThreshold' => $tier_info['next_threshold'],
            'nextTierName' => $tier_info['next_name'],
            'nextTierRate' => $tier_info['next_rate'],
            'recentActivity' => $recent_activity,
            'nextPayoutDate' => $next_payout,
            'payoutMethod' => 'e-transfer'
        ];
    }
    
    // Get recent sales activity
    private function getRecentActivity($affiliate_id, $limit = 10) {
        $query = "SELECT product_details as description, commission_amount as amount, timestamp as date
                  FROM affiliate_sales 
                  WHERE affiliate_id = :affiliate_id 
                  ORDER BY timestamp DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':affiliate_id', $affiliate_id);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $activities = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activities[] = [
                'description' => $row['description'] ?: 'Sale completed',
                'amount' => (float)$row['amount'],
                'date' => $row['date']
            ];
        }
        
        return $activities;
    }
    
    // Calculate tier information
    private function getTierInfo($current_tier, $total_earnings) {
        $tiers = [
            'Starter' => ['rate' => 20, 'threshold' => 0],
            'Pro' => ['rate' => 25, 'threshold' => 500],
            'Elite' => ['rate' => 30, 'threshold' => 2000]
        ];
        
        $current = $tiers[$current_tier];
        $progress = 0;
        $next_threshold = null;
        $next_name = null;
        $next_rate = null;
        
        if ($current_tier === 'Starter' && $total_earnings < 500) {
            $progress = ($total_earnings / 500) * 100;
            $next_threshold = 500;
            $next_name = 'Pro';
            $next_rate = 25;
        } elseif ($current_tier === 'Pro' && $total_earnings < 2000) {
            $progress = (($total_earnings - 500) / 1500) * 100;
            $next_threshold = 2000;
            $next_name = 'Elite';
            $next_rate = 30;
        } elseif ($current_tier === 'Elite') {
            $progress = 100;
        }
        
        return [
            'rate' => $current['rate'],
            'progress' => round($progress, 1),
            'next_threshold' => $next_threshold,
            'next_name' => $next_name,
            'next_rate' => $next_rate
        ];
    }
    
    // Get next payout date (15th of next month)
    private function getNextPayoutDate() {
        $next_month = date('Y-m-15', strtotime('first day of next month'));
        return $next_month;
    }
    
    // Clean expired sessions
    private function cleanExpiredSessions() {
        $query = "DELETE FROM affiliate_sessions WHERE expires_at <= NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
    }
    
    // Record a click
    public function recordClick($affiliate_id, $page, $campaign = null) {
        $query = "INSERT INTO affiliate_clicks SET 
                  affiliate_id = :affiliate_id, 
                  page = :page, 
                  campaign = :campaign, 
                  ip_address = :ip, 
                  user_agent = :user_agent, 
                  referrer = :referrer";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':affiliate_id', $affiliate_id);
        $stmt->bindParam(':page', $page);
        $stmt->bindParam(':campaign', $campaign);
        $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $stmt->bindParam(':referrer', $_SERVER['HTTP_REFERER'] ?? '');
        
        if($stmt->execute()) {
            // Update total clicks
            $this->updateTotalClicks($affiliate_id);
            return true;
        }
        return false;
    }
    
    // Record a sale
    public function recordSale($affiliate_id, $order_id, $sale_amount, $product_details = '') {
        // Get affiliate tier for commission rate
        $query = "SELECT tier FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $affiliate_id);
        $stmt->execute();
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$affiliate) return false;
        
        // Calculate commission
        $rates = ['Starter' => 0.20, 'Pro' => 0.25, 'Elite' => 0.30];
        $commission_rate = $rates[$affiliate['tier']] ?? 0.20;
        $commission_amount = $sale_amount * $commission_rate;
        
        // Record the sale
        $query = "INSERT INTO affiliate_sales SET 
                  affiliate_id = :affiliate_id, 
                  order_id = :order_id, 
                  sale_amount = :sale_amount, 
                  commission_amount = :commission_amount, 
                  commission_rate = :commission_rate, 
                  product_details = :product_details";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':affiliate_id', $affiliate_id);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':sale_amount', $sale_amount);
        $stmt->bindParam(':commission_amount', $commission_amount);
        $stmt->bindParam(':commission_rate', $commission_rate * 100); // Store as percentage
        $stmt->bindParam(':product_details', $product_details);
        
        if($stmt->execute()) {
            // Update affiliate totals
            $this->updateAffiliateTotals($affiliate_id);
            
            // Check for tier upgrade
            $this->checkTierUpgrade($affiliate_id);
            
            return $commission_amount;
        }
        return false;
    }
    
    // Update affiliate totals after sale
    private function updateAffiliateTotals($affiliate_id) {
        $query = "UPDATE " . $this->table_name . " SET 
                  total_earnings = (SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_sales WHERE affiliate_id = :id1),
                  total_sales = (SELECT COUNT(*) FROM affiliate_sales WHERE affiliate_id = :id2),
                  pending_balance = pending_balance + (SELECT commission_amount FROM affiliate_sales WHERE affiliate_id = :id3 ORDER BY id DESC LIMIT 1)
                  WHERE id = :id4";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id1', $affiliate_id);
        $stmt->bindParam(':id2', $affiliate_id);
        $stmt->bindParam(':id3', $affiliate_id);
        $stmt->bindParam(':id4', $affiliate_id);
        $stmt->execute();
    }
    
    // Update total clicks
    private function updateTotalClicks($affiliate_id) {
        $query = "UPDATE " . $this->table_name . " SET 
                  total_clicks = (SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = :id1)
                  WHERE id = :id2";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id1', $affiliate_id);
        $stmt->bindParam(':id2', $affiliate_id);
        $stmt->execute();
    }
    
    // Check and upgrade tier if needed
    private function checkTierUpgrade($affiliate_id) {
        $query = "SELECT total_earnings, tier FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $affiliate_id);
        $stmt->execute();
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_tier = $affiliate['tier'];
        if ($affiliate['total_earnings'] >= 2000 && $affiliate['tier'] !== 'Elite') {
            $new_tier = 'Elite';
        } elseif ($affiliate['total_earnings'] >= 500 && $affiliate['tier'] === 'Starter') {
            $new_tier = 'Pro';
        }
        
        if ($new_tier !== $affiliate['tier']) {
            $query = "UPDATE " . $this->table_name . " SET tier = :tier WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':tier', $new_tier);
            $stmt->bindParam(':id', $affiliate_id);
            $stmt->execute();
            
            // Send upgrade notification email
            $this->sendTierUpgradeEmail($affiliate_id, $new_tier);
        }
    }
    
    // Send tier upgrade email
    private function sendTierUpgradeEmail($affiliate_id, $new_tier) {
        // Get affiliate details
        $query = "SELECT name, email FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $affiliate_id);
        $stmt->execute();
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $rates = ['Pro' => 25, 'Elite' => 30];
        $rate = $rates[$new_tier] ?? 20;
        
        $subject = "Congratulations! You've been upgraded to {$new_tier} tier";
        $message = "Hi {$affiliate['name']},\n\n";
        $message .= "Great news! Your affiliate account has been upgraded to {$new_tier} tier.\n";
        $message .= "Your new commission rate is {$rate}%.\n\n";
        $message .= "Keep up the great work!\n\n";
        $message .= "Best regards,\nNoyes Boys Team";
        
        mail($affiliate['email'], $subject, $message);
    }
}
?>