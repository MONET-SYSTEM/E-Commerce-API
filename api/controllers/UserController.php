<?php
class UserController {
    private $user;
    private $logger;
    
    public function __construct($db) {
        require_once "../models/User.php";
        $this->user = new User($db);
        $this->logger = new Logger($db);
    }
    
    public function getAll() {
        $result = $this->user->getAll();
        return [
            "status" => 200,
            "data" => $result
        ];
    }
    
    public function getById($id) {
        $result = $this->user->getById($id);
        
        if ($result) {
            return [
                "status" => 200,
                "data" => $result
            ];
        } else {
            return [
                "status" => 404,
                "message" => "User not found"
            ];
        }
    }
    
    public function create($data) {
        // Validate request
        if (!Validator::validateUserRequest($data)) {
            $this->logger->log("user_create", $data, "failed", "Invalid data");
            return [
                "status" => 400,
                "message" => "Invalid data provided"
            ];
        }
        
        try {
            $userId = $this->user->create($data);
            
            if ($userId) {
                $this->logger->log("user_create", $data, "success");
                return [
                    "status" => 201,
                    "message" => "User created successfully",
                    "userId" => $userId
                ];
            } else {
                $this->logger->log("user_create", $data, "failed", "Database error");
                return [
                    "status" => 500,
                    "message" => "Failed to create user"
                ];
            }
        } catch (Exception $e) {
            $this->logger->log("user_create", $data, "failed", $e->getMessage());
            return [
                "status" => 500,
                "message" => "Failed to create user",
                "error" => $e->getMessage()
            ];
        }
    }
    
    public function update($id, $data) {
        if (!isset($data->name) || !isset($data->email)) {
            $this->logger->log("user_update", $data, "failed", "Invalid data");
            return [
                "status" => 400,
                "message" => "Name and email are required"
            ];
        }
        
        try {
            if ($this->user->update($id, $data)) {
                $this->logger->log("user_update", $data, "success");
                return [
                    "status" => 200,
                    "message" => "User updated successfully"
                ];
            } else {
                $this->logger->log("user_update", $data, "failed", "Database error");
                return [
                    "status" => 500,
                    "message" => "Failed to update user"
                ];
            }
        } catch (Exception $e) {
            $this->logger->log("user_update", $data, "failed", $e->getMessage());
            return [
                "status" => 500,
                "message" => "Failed to update user",
                "error" => $e->getMessage()
            ];
        }
    }
    
    public function delete($id) {
        try {
            if ($this->user->delete($id)) {
                $this->logger->log("user_delete", ["id" => $id], "success");
                return [
                    "status" => 200,
                    "message" => "User deleted successfully"
                ];
            } else {
                $this->logger->log("user_delete", ["id" => $id], "failed", "Database error");
                return [
                    "status" => 500,
                    "message" => "Failed to delete user"
                ];
            }
        } catch (Exception $e) {
            $this->logger->log("user_delete", ["id" => $id], "failed", $e->getMessage());
            return [
                "status" => 500,
                "message" => "Failed to delete user",
                "error" => $e->getMessage()
            ];
        }
    }
}
?>