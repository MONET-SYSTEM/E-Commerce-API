<?php
class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "Sheamar@442211";
    private $database = "e_commerce_db";
    private $conn;
    
    public function __construct() {
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
            if ($this->conn->connect_error) {
                die("Connection failed: " . $this->conn->connect_error);
            }
        } catch (Exception $e) {
            echo "Database connection error: " . $e->getMessage();
            exit;
        }
    }
    
    public function beginTransaction() {
        $this->conn->begin_transaction();
    }
    
    public function commit() {
        $this->conn->commit();
    }
    
    public function rollback() {
        $this->conn->rollback();
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $this->conn->error);
        }
        
        if (!empty($params)) {
            $types = '';
            $bindParams = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param) || is_double($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $bindParams[] = $param;
            }
            
            $bindParamRef = [];
            $bindParamRef[] = $types;
            
            for ($i = 0; $i < count($bindParams); $i++) {
                $bindParamRef[] = &$bindParams[$i];
            }
            
            call_user_func_array([$stmt, 'bind_param'], $bindParamRef);
        }
        
        $stmt->execute();
        
        if ($stmt->errno) {
            throw new Exception("Query execution failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result) {
            return $result;
        }
        
        return $stmt->insert_id ? $stmt->insert_id : true;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function close() {
        $this->conn->close();
    }
    
    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
}
?>