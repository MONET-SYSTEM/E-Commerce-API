<?php
class OrderController {
    private $order;
    private $product;
    private $user;
    private $logger;
    private $db;
    
    public function __construct($db) {
        require_once "../models/Order.php";
        require_once "../models/Product.php";
        require_once "../models/User.php";
        
        $this->db = $db;
        $this->order = new Order($db);
        $this->product = new Product($db);
        $this->user = new User($db);
        $this->logger = new Logger($db);
    }
    
    public function getAll() {
        $result = $this->order->getAll();
        return [
            "status" => 200,
            "data" => $result
        ];
    }
    
    public function getById($id) {
        $result = $this->order->getById($id);
        
        if ($result) {
            return [
                "status" => 200,
                "data" => $result
            ];
        } else {
            return [
                "status" => 404,
                "message" => "Order not found"
            ];
        }
    }
    
    public function create($data) {
        // Validate request
        if (!Validator::validateOrderRequest($data)) {
            $this->logger->log("order_create", $data, "failed", "Invalid data");
            return [
                "status" => 400,
                "message" => "Invalid data provided"
            ];
        }
        
        // Check if user exists
        if (!$this->user->getById($data->userId)) {
            $this->logger->log("order_create", $data, "failed", "User not found");
            return [
                "status" => 400,
                "message" => "User not found"
            ];
        }
        
        // Validate products and stock availability
        $totalCalculated = 0;
        foreach ($data->items as $item) {
            $productData = $this->product->getById($item->productId);
            if (!$productData) {
                $this->logger->log("order_create", $data, "failed", "Product not found: " . $item->productId);
                return [
                    "status" => 400,
                    "message" => "Product not found: " . $item->productId
                ];
            }
            
            if ($productData['stock'] < $item->quantity) {
                $this->logger->log("order_create", $data, "failed", "Insufficient stock for product ID: " . $item->productId);
                return [
                    "status" => 400,
                    "message" => "Insufficient stock for product ID: " . $item->productId,
                    "available" => $productData['stock'],
                    "requested" => $item->quantity
                ];
            }
            
            // Verify price or use the current price
            if (!isset($item->price) || $item->price <= 0) {
                $item->price = $productData['price'];
            }
            
            $totalCalculated += ($item->price * $item->quantity);
        }
        
        // Verify total amount
        if (abs($totalCalculated - $data->totalAmount) > 0.01) {
            $this->logger->log("order_create", $data, "failed", "Total amount mismatch");
            return [
                "status" => 400,
                "message" => "Total amount mismatch",
                "calculated" => $totalCalculated,
                "provided" => $data->totalAmount
            ];
        }
        
        try {
            // Begin transaction
            $this->db->begin_transaction();
            
            // Create order
            $orderId = $this->order->create($data);
            
            if ($orderId) {
                $this->db->commit();
                $this->logger->log("order_create", $data, "success");
                return [
                    "status" => 201,
                    "message" => "Order created successfully",
                    "orderId" => $orderId
                ];
            } else {
                $this->db->rollback();
                $this->logger->log("order_create", $data, "failed", "Database error");
                return [
                    "status" => 500,
                    "message" => "Failed to create order"
                ];
            }
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logger->log("order_create", $data, "failed", $e->getMessage());
            return [
                "status" => 500,
                "message" => "Failed to create order",
                "error" => $e->getMessage()
            ];
        }
    }
    
    public function update($id, $data) {
        // Validate order status if provided
        if (isset($data->status)) {
            $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($data->status, $validStatuses)) {
                $this->logger->log("order_update", $data, "failed", "Invalid status value");
                return [
                    "status" => 400,
                    "message" => "Invalid status value",
                    "validStatuses" => $validStatuses
                ];
            }
        }
        
        try {
            $this->db->begin_transaction();
            
            // Get current order
            $currentOrder = $this->order->getById($id);
            if (!$currentOrder) {
                $this->db->rollback();
                return [
                    "status" => 404,
                    "message" => "Order not found"
                ];
            }
            
            // Handle status change from 'pending' to 'cancelled' - restore stock
            if (isset($data->status) && $data->status === 'cancelled' && $currentOrder['status'] === 'pending') {
                foreach ($currentOrder['items'] as $item) {
                    $query = "UPDATE products 
                             SET stock = stock + ? 
                             WHERE product_id = ?";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to restore stock for product ID: " . $item['product_id']);
                    }
                }
            }
            
            if ($this->order->update($id, $data)) {
                $this->db->commit();
                $this->logger->log("order_update", $data, "success");
                return [
                    "status" => 200,
                    "message" => "Order updated successfully"
                ];
            } else {
                $this->db->rollback();
                $this->logger->log("order_update", $data, "failed", "Database error");
                return [
                    "status" => 500,
                    "message" => "Failed to update order"
                ];
            }
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logger->log("order_update", $data, "failed", $e->getMessage());
            return [
                "status" => 500,
                "message" => "Failed to update order",
                "error" => $e->getMessage()
            ];
        }
    }
    
    public function delete($id) {
        try {
            $this->db->begin_transaction();
            if ($this->order->delete($id)) {
                $this->db->commit();
                $this->logger->log("order_delete", ["id" => $id], "success");
                return [
                    "status" => 200,
                    "message" => "Order deleted successfully"
                ];
            } else {
                $this->db->rollback();
                $this->logger->log("order_delete", ["id" => $id], "failed", "Database error");
                return [
                    "status" => 404,
                    "message" => "Order not found or could not be deleted"
                ];
            }
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logger->log("order_delete", ["id" => $id], "failed", $e->getMessage());
            return [
                "status" => 500,
                "message" => "Failed to delete order",
                "error" => $e->getMessage()
            ];
        }
    }
    
    public function getUserOrders($userId) {
        $query = "SELECT o.*, u.name as user_name 
                 FROM orders o
                 LEFT JOIN users u ON o.user_id = u.user_id
                 WHERE o.user_id = ?
                 ORDER BY o.order_date DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            // Get order items
            $query = "SELECT oi.*, p.name as product_name 
                     FROM order_items oi
                     LEFT JOIN products p ON oi.product_id = p.product_id
                     WHERE oi.order_id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $row['order_id']);
            $stmt->execute();
            $itemsResult = $stmt->get_result();
            
            $items = [];
            while ($itemRow = $itemsResult->fetch_assoc()) {
                $items[] = $itemRow;
            }
            
            $row['items'] = $items;
            $orders[] = $row;
        }
        
        return [
            "status" => 200,
            "data" => $orders
        ];
    }
    
    public function getOrderStats() {
        // Get orders count by status
        $query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
        $result = $this->db->query($query);
        
        $statusCounts = [];
        while ($row = $result->fetch_assoc()) {
            $statusCounts[$row['status']] = (int)$row['count'];
        }
        
        // Get total sales amount
        $query = "SELECT SUM(total_amount) as total_sales FROM orders WHERE status != 'cancelled'";
        $result = $this->db->query($query);
        $totalSales = $result->fetch_assoc()['total_sales'] ?? 0;
        
        // Get top selling products
        $query = "SELECT p.product_id, p.name, SUM(oi.quantity) as total_quantity, 
                 SUM(oi.quantity * oi.price) as total_revenue
                 FROM order_items oi
                 JOIN products p ON oi.product_id = p.product_id
                 JOIN orders o ON oi.order_id = o.order_id
                 WHERE o.status != 'cancelled'
                 GROUP BY p.product_id
                 ORDER BY total_quantity DESC
                 LIMIT 5";
        
        $result = $this->db->query($query);
        
        $topProducts = [];
        while ($row = $result->fetch_assoc()) {
            $topProducts[] = $row;
        }
        
        // Get recent orders
        $query = "SELECT o.order_id, o.user_id, u.name as user_name, 
                 o.total_amount, o.status, o.order_date
                 FROM orders o
                 JOIN users u ON o.user_id = u.user_id
                 ORDER BY o.order_date DESC
                 LIMIT 10";
        
        $result = $this->db->query($query);
        
        $recentOrders = [];
        while ($row = $result->fetch_assoc()) {
            $recentOrders[] = $row;
        }
        
        return [
            "status" => 200,
            "data" => [
                "statusCounts" => $statusCounts,
                "totalSales" => (float)$totalSales,
                "topProducts" => $topProducts,
                "recentOrders" => $recentOrders
            ]
        ];
    }
}
?>