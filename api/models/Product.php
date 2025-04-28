<?php
class Product {
    private $conn;
    private $table_name = "products";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name;
        
        $result = $this->conn->query($query);
        $products = [];
        
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        
        return $products;
    }
    
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE product_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                 (name, description, price, stock) 
                 VALUES (?, ?, ?, ?)";
        
        $description = isset($data->description) ? $data->description : "";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssdi", $data->name, $description, $data->price, $data->stock);
        
        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        
        return false;
    }
    
    public function update($id, $data) {
        // Check if product exists
        if (!$this->getById($id)) {
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . " 
                 SET name = ?, description = ?, price = ?, stock = ? 
                 WHERE product_id = ?";
        
        $description = isset($data->description) ? $data->description : "";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssdii", $data->name, $description, $data->price, $data->stock, $id);
        
        return $stmt->execute() && $stmt->affected_rows > 0;
    }
    
    public function updateStock($id, $quantity) {
        $query = "UPDATE " . $this->table_name . " 
                 SET stock = stock - ? 
                 WHERE product_id = ? AND stock >= ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $quantity, $id, $quantity);
        
        return $stmt->execute() && $stmt->affected_rows > 0;
    }
    
    public function delete($id) {
        // Check if product exists
        if (!$this->getById($id)) {
            return false;
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        
        return $stmt->execute() && $stmt->affected_rows > 0;
    }
}
?>