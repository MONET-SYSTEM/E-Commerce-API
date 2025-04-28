<?php
class Payment {
    private $conn;
    private $table_name = "payments";
    
    // Payment properties
    public $id;
    public $orderId;
    public $amount;
    public $paymentMethod;
    public $status;
    public $transactionId;
    public $createdAt;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create new payment
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (order_id, amount, payment_method, status, transaction_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->orderId = htmlspecialchars(strip_tags($this->orderId));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->paymentMethod = htmlspecialchars(strip_tags($this->paymentMethod));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->transactionId = htmlspecialchars(strip_tags($this->transactionId));
        
        // Bind parameters
        $stmt->bind_param("idsss", 
            $this->orderId, 
            $this->amount, 
            $this->paymentMethod, 
            $this->status, 
            $this->transactionId
        );
        
        // Execute query
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            return true;
        }
        
        return false;
    }
    
    // Get single payment by ID
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Set properties
            $this->id = $row['id'];
            $this->orderId = $row['order_id'];
            $this->amount = $row['amount'];
            $this->paymentMethod = $row['payment_method'];
            $this->status = $row['status'];
            $this->transactionId = $row['transaction_id'];
            $this->createdAt = $row['created_at'];
            
            return true;
        }
        
        return false;
    }
    
    // Get payments by order ID
    public function readByOrder() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE order_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        
        return $payments;
    }
    
    // Update payment status
    public function updateStatus() {
        $query = "UPDATE " . $this->table_name . " SET status = ? WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        // Bind parameters
        $stmt->bind_param("si", $this->status, $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    // Delete payment
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Get all payments with pagination
    public function read($limit = 10, $offset = 0) {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY created_at DESC LIMIT ?, ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        
        return $payments;
    }
    
    // Count all payments
    public function count() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['total'];
    }
}
?>