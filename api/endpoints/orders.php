<?php
require_once "../models/Order.php";
require_once "../models/Product.php";
require_once "../models/User.php";
require_once "../utilities/Logger.php";
require_once "../utilities/Validator.php";
require_once "../config/Database.php";


$database = new Database();
$db = $database->getConnection();
$order = new Order($db);
$product = new Product($db);
$user = new User($db);
$logger = new Logger($db);

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Parse request data
$data = json_decode(file_get_contents("php://input"));

switch ($method) {
    case 'GET':
        if ($id) {
            // Get specific order
            $result = $order->getById($id);
            if ($result) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Order not found"]);
            }
        } else {
            // Get all orders
            $result = $order->getAll();
            http_response_code(200);
            echo json_encode($result);
        }
        break;

    case 'POST':
        // Validate request
        if (!Validator::validateOrderRequest($data)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid data provided"]);
            $logger->log("order_create", $data, "failed", "Invalid data");
            break;
        }
        
        // Check if user exists
        if (!$user->getById($data->userId)) {
            http_response_code(400);
            echo json_encode(["message" => "User not found"]);
            $logger->log("order_create", $data, "failed", "User not found");
            break;
        }
        
        // Validate products and stock availability
        $totalCalculated = 0;
        foreach ($data->items as $item) {
            $productData = $product->getById($item->productId);
            if (!$productData) {
                http_response_code(400);
                echo json_encode(["message" => "Product not found: " . $item->productId]);
                $logger->log("order_create", $data, "failed", "Product not found: " . $item->productId);
                break 2; // Break out of both foreach and switch
            }
            
            if ($productData['stock'] < $item->quantity) {
                http_response_code(400);
                echo json_encode([
                    "message" => "Insufficient stock for product ID: " . $item->productId,
                    "available" => $productData['stock'],
                    "requested" => $item->quantity
                ]);
                $logger->log("order_create", $data, "failed", "Insufficient stock for product ID: " . $item->productId);
                break 2; // Break out of both foreach and switch
            }
            
            // Verify price or use the current price
            if (!isset($item->price) || $item->price <= 0) {
                $item->price = $productData['price'];
            }
            
            $totalCalculated += ($item->price * $item->quantity);
        }
        
        // Verify total amount
        if (abs($totalCalculated - $data->totalAmount) > 0.01) {
            http_response_code(400);
            echo json_encode([
                "message" => "Total amount mismatch",
                "calculated" => $totalCalculated,
                "provided" => $data->totalAmount
            ]);
            $logger->log("order_create", $data, "failed", "Total amount mismatch");
            break;
        }
        
        try {
            // Start transaction
            $database->beginTransaction();
            
            // Create order
            $orderId = $order->create($data);
            
            if ($orderId) {
                $database->commit();
                http_response_code(201);
                echo json_encode([
                    "message" => "Order created successfully",
                    "orderId" => $orderId
                ]);
                $logger->log("order_create", $data, "success");
            } else {
                $database->rollback();
                http_response_code(500);
                echo json_encode(["message" => "Failed to create order"]);
                $logger->log("order_create", $data, "failed", "Database error");
            }
        } catch (Exception $e) {
            $database->rollback();
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to create order",
                "error" => $e->getMessage()
            ]);
            $logger->log("order_create", $data, "failed", $e->getMessage());
        }
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "Order ID is required"]);
            break;
        }
        
        // Validate order status if provided
        if (isset($data->status)) {
            $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($data->status, $validStatuses)) {
                http_response_code(400);
                echo json_encode([
                    "message" => "Invalid status value",
                    "validStatuses" => $validStatuses
                ]);
                $logger->log("order_update", $data, "failed", "Invalid status value");
                break;
            }
        }
        
        try {
            $database->beginTransaction();
            
            // Get current order
            $currentOrder = $order->getById($id);
            if (!$currentOrder) {
                $database->rollback();
                http_response_code(404);
                echo json_encode(["message" => "Order not found"]);
                break;
            }
            
            // Handle status change from 'pending' to 'cancelled' - restore stock
            if (isset($data->status) && $data->status === 'cancelled' && $currentOrder['status'] === 'pending') {
                foreach ($currentOrder['items'] as $item) {
                    $query = "UPDATE products 
                             SET stock = stock + ? 
                             WHERE product_id = ?";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to restore stock for product ID: " . $item['product_id']);
                    }
                }
            }
            
            if ($order->update($id, $data)) {
                $database->commit();
                http_response_code(200);
                echo json_encode(["message" => "Order updated successfully"]);
                $logger->log("order_update", $data, "success");
            } else {
                $database->rollback();
                http_response_code(500);
                echo json_encode(["message" => "Failed to update order"]);
                $logger->log("order_update", $data, "failed", "Database error");
            }
        } catch (Exception $e) {
            $database->rollback();
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to update order",
                "error" => $e->getMessage()
            ]);
            $logger->log("order_update", $data, "failed", $e->getMessage());
        }
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "Order ID is required"]);
            break;
        }
        
        try {
            $database->beginTransaction();
            if ($order->delete($id)) {
                $database->commit();
                http_response_code(200);
                echo json_encode(["message" => "Order deleted successfully"]);
                $logger->log("order_delete", ["id" => $id], "success");
            } else {
                $database->rollback();
                http_response_code(500);
                echo json_encode(["message" => "Failed to delete order"]);
                $logger->log("order_delete", ["id" => $id], "failed", "Database error");
            }
        } catch (Exception $e) {
            $database->rollback();
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to delete order",
                "error" => $e->getMessage()
            ]);
            $logger->log("order_delete", ["id" => $id], "failed", $e->getMessage());
        }
        break;
}
?>