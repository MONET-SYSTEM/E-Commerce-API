<?php
require_once "../models/User.php";
require_once "../config/Database.php";
require_once "../utilities/Logger.php";
require_once "../utilities/Validator.php";

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$logger = new Logger($db);

// Parse request data
$data = json_decode(file_get_contents("php://input"));

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? $_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            // Get specific user
            $result = $user->getById($id);
            if ($result) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "User not found"]);
            }
        } else {
            // Get all users
            $result = $user->getAll();
            http_response_code(200);
            echo json_encode($result);
        }
        break;

    case 'POST':
        // Validate request
        if (!Validator::validateUserRequest($data)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid data provided"]);
            $logger->log("user_create", $data, "failed", "Invalid data");
            break;
        }
        
        try {
            // Start transaction
            $database->beginTransaction();
            
            // Create user
            $userId = $user->create($data);
            
            if ($userId) {
                $database->commit();
                http_response_code(201);
                echo json_encode([
                    "message" => "User created successfully",
                    "userId" => $userId
                ]);
                $logger->log("user_create", $data, "success");
            } else {
                $database->rollback();
                http_response_code(500);
                echo json_encode(["message" => "Failed to create user"]);
                $logger->log("user_create", $data, "failed", "Database error");
            }
        } catch (Exception $e) {
            $database->rollback();
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to create user",
                "error" => $e->getMessage()
            ]);
            $logger->log("user_create", $data, "failed", $e->getMessage());
        }
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "User ID is required"]);
            break;
        }
        
        // Validate request
        if (!isset($data->name) || !isset($data->email)) {
            http_response_code(400);
            echo json_encode(["message" => "Name and email are required"]);
            $logger->log("user_update", $data, "failed", "Invalid data");
            break;
        }
        
        try {
            $database->beginTransaction();
            if ($user->update($id, $data)) {
                $database->commit();
                http_response_code(200);
                echo json_encode(["message" => "User updated successfully"]);
                $logger->log("user_update", $data, "success");
            } else {
                $database->rollback();
                http_response_code(500);
                echo json_encode(["message" => "Failed to update user"]);
                $logger->log("user_update", $data, "failed", "Database error");
            }
        } catch (Exception $e) {
            $database->rollback();
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to update user",
                "error" => $e->getMessage()
            ]);
            $logger->log("user_update", $data, "failed", $e->getMessage());
        }
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "User ID is required"]);
            break;
        }
        
        try {
            $database->beginTransaction();
            if ($user->delete($id)) {
                $database->commit();
                http_response_code(200);
                echo json_encode(["message" => "User deleted successfully"]);
                $logger->log("user_delete", ["id" => $id], "success");
            } else {
                $database->rollback();
                http_response_code(500);
                echo json_encode(["message" => "Failed to delete user"]);
                $logger->log("user_delete", ["id" => $id], "failed", "Database error");
            }
        } catch (Exception $e) {
            $database->rollback();
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to delete user",
                "error" => $e->getMessage()
            ]);
            $logger->log("user_delete", ["id" => $id], "failed", $e->getMessage());
        }
        break;
}
?>