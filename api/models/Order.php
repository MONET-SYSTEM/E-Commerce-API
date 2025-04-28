<?php
class Order {
    private $conn;
    private $table_name = "orders";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAll() {
        $query = "SELECT o.*, u.name as user_name 
                 FROM " . $this->table_name . " o
                 LEFT JOIN users u ON o.user_id = u.user_id
                 ORDER BY o.order_date DESC";
        
        $result = $this->conn->query($query);
        $orders = [];
        
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        return $orders;
    }
    
    public function getById($id) {
        // Get order details
        $query = "SELECT o.*, u.name as user_name 
                 FROM " . $this->table_name . " o
                 LEFT JOIN users u ON o.user_id = u.user_id
                 WHERE o.order_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return false;
        }
        
        $order = $result->fetch_assoc();
        
        // Get order items
        $query = "SELECT oi.*, p.name as product_name 
                 FROM order_items oi
                 LEFT JOIN products p ON oi.product_id = p.product_id
                 WHERE oi.order_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        $order['items'] = $items;
        
        return $order;
    }
    
    public function create($data) {
        // Create order
        $query = "INSERT INTO " . $this->table_name . " 
                 (user_id, total_amount, status) 
                 VALUES (?, ?, 'pending')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("id", $data->userId, $data->totalAmount);
        
        if (!$stmt->execute()) {
            return false;
        }
        
        $orderId = $stmt->insert_id;
        
        // Insert order items
        foreach ($data->items as $item) {
            $query = "INSERT INTO order_items 
                     (order_id, product_id, quantity, price) 
                     VALUES (?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iiid", $orderId, $item->productId, $item->quantity, $item->price);
            
            if (!$stmt->execute()) {
                return false;
            }
            
            // Update product stock with locking
            $query = "SELECT * FROM products WHERE product_id = ? FOR UPDATE";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $item->productId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                throw new Exception("Product not found: " . $item->productId);
            }
            
            $product = $result->fetch_assoc();
            
            if ($product['stock'] < $item->quantity) {
                throw new Exception("Insufficient stock for product ID: " . $item->productId);
            }
            
            $query = "UPDATE products 
                     SET stock = stock - ? 
                     WHERE product_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $item->quantity, $item->productId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update stock for product ID: " . $item->productId);
            }
        }
        
        return $orderId;
    }
    
    public function update($id, $data) {
        // Check if order exists
        $query = "SELECT * FROM " . $this->table_name . " WHERE order_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return false;
        }
        
        // Update order status if provided
        if (isset($data->status)) {
            $query = "UPDATE " . $this->table_name . " 
                     SET status = ? 
                     WHERE order_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("si", $data->status, $id);
            
            if (!$stmt->execute()) {
                return false;
            }
        }
        
        return true;
    }
    
    public function delete($id) {
        // Check if order exists
        $order = $this->getById($id);
        if (!$order) {
            return false;
        }
        
        // If order is not already cancelled or completed, restore product stock
        if ($order['status'] == 'pending') {
            // Get order items
            $query = "SELECT * FROM order_items WHERE order_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($item = $result->fetch_assoc()) {
                // Restore stock
                $query = "UPDATE products 
                         SET stock = stock + ? 
                         WHERE product_id = ?";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to restore stock for product ID: " . $item['product_id']);
                }
            }
        }
        
        // Delete order (cascade will delete related items)
        $query = "DELETE FROM " . $this->table_name . " WHERE order_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        
        return $stmt->execute() && $stmt->affected_rows > 0;
    }
}
?>