<?php
class Logger {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function log($type, $data, $status, $error = null) {
        $query = "INSERT INTO transaction_logs 
                 (type, data, status, error_message) 
                 VALUES (?, ?, ?, ?)";
        
        $data_json = json_encode($data);
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssss", $type, $data_json, $status, $error);
        
        return $stmt->execute();
    }
    
    public function getRecentLogs($limit = 100) {
        $query = "SELECT * FROM transaction_logs ORDER BY timestamp DESC LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        return $logs;
    }
}
?>