<?php
require_once "../models/Product.php";
require_once "../models/User.php";
require_once "../utilities/Logger.php";
require_once "../utilities/Validator.php";
require_once "../config/Database.php";


$database = new Database();
$db = $database->getConnection();
$product = new Product($db);
$logger = new Logger($db);

// Parse request data
$data = json_decode(file_get_contents("php://input"));
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? $_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            // Get specific product
            $result = $product->getById($id);
            if ($result) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Product not found"]);
            }
        } else {
            // Get all products
            $result = $product->getAll();
            http_response_code(200);
            echo json_encode($result);
        }
        break;

    case 'POST':
        // Validate request
        if (!Validator::validateProductRequest($data)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid data provided"]);
            $logger->log("product_create", $data, "failed", "Invalid data");
            break;
        }
        
        try {
            // Start transaction
            $database->beginTransaction();
            
            // Create product
            $productId = $product->create($data);
            
            if ($productId) {
                $database->commit();
                http_response_code(201);
                echo json_encode([
                    "message" => "Product created successfully",
                    "productId" => $productId
                ]);
                $logger->log("product_create", $data, "success");
            } else {
                $database->rollback();
                http_response_code(500);
                echo json_encode(["message" => "Failed to create product"]);
                $logger->log("product_create", $data, "failed", "Database error");
            }
        } catch (Exception $e) {
            $database->rollback();
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to create product",
                "error" => $e->getMessage()
            ]);
            $logger->log("product_create", $data, "failed", $e->getMessage());
        }
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "Product ID is required"]);
            break;
        }
        
        // Validate request
        if (!Validator::validateProductRequest($data)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid data provided"]);
            $logger->log("product_update", $data, "failed", "Invalid data");
            break;
        }
        
        try {
            $database->beginTransaction();
            if ($product->update($id, $data)) {
                $database->commit();
                http_response_code(200);
                echo json_encode(["message" => "Product updated successfully"]);
                $logger->log("product_update", $data, "success");
            } else {
                $database->rollback();
                http_response_code(500);
                echo json_encode(["message" => "Failed to update product"]);
                $logger->log("product_update", $data, "failed", "Database error");
            }
        } catch (Exception $e) {
            $database->rollback();
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to update product",
                "error" => $e->getMessage()
            ]);
            $logger->log("product_update", $data, "failed", $e->getMessage());
        }
        break;
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(["message" => "Product ID is required"]);
                break;
            }
            
            try {
                $database->beginTransaction();
                if ($product->delete($id)) {
                    $database->commit();
                    http_response_code(200);
                    echo json_encode(["message" => "Product deleted successfully"]);
                    $logger->log("product_delete", ["id" => $id], "success");
                } else {
                    $database->rollback();
                    http_response_code(500);
                    echo json_encode(["message" => "Failed to delete product"]);
                    $logger->log("product_delete", ["id" => $id], "failed", "Database error");
                }
            } catch (Exception $e) {
                $database->rollback();
                http_response_code(500);
                echo json_encode([
                    "message" => "Failed to delete product",
                    "error" => $e->getMessage()
                ]);
                $logger->log("product_delete", ["id" => $id], "failed", $e->getMessage());
            }
            break;
    }
?>