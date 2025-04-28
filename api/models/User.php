<?php
class User {
    private $conn;
    private $table_name = "users";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAll() {
        $query = "SELECT user_id, name, email, created_at FROM " . $this->table_name;
        
        $result = $this->conn->query($query);
        $users = [];
        
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        return $users;
    }
    
    public function getById($id) {
        $query = "SELECT user_id, name, email, created_at FROM " . $this->table_name . " WHERE user_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    public function getByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    public function create($data) {
        // Check if email already exists
        if ($this->getByEmail($data->email)) {
            throw new Exception("Email already exists");
        }
        
        $query = "INSERT INTO " . $this->table_name . " 
                 (name, email, password) 
                 VALUES (?, ?, ?)";
        
        $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $data->name, $data->email, $password_hash);
        
        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        
        return false;
    }
    
    public function update($id, $data) {
        // Check if user exists
        if (!$this->getById($id)) {
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . " 
                 SET name = ?, email = ?";
        
        $params = [$data->name, $data->email];
        
        // If password is provided, update it
        if (isset($data->password) && !empty($data->password)) {
            $query .= ", password = ?";
            $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
            $params[] = $password_hash;
        }
        
        $query .= " WHERE user_id = ?";
        $params[] = $id;
        
        $stmt = $this->conn->prepare($query);
        
        $types = str_repeat("s", count($params) - 1) . "i";
        $stmt->bind_param($types, ...$params);
        
        return $stmt->execute() && $stmt->affected_rows > 0;
    }
    
    public function delete($id) {
        // Check if user exists
        if (!$this->getById($id)) {
            return false;
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        
        return $stmt->execute() && $stmt->affected_rows > 0;
    }
}
?>