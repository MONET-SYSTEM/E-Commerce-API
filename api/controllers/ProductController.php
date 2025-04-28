<?php
class ProductController {
    private $product;
    private $logger;
    
    public function __construct($db) {
        require_once "../models/Product.php";
        $this->product = new Product($db);
        $this->logger = new Logger($db);
    }
    
    public function getAll() {
        $result = $this->product->getAll();
        return [
            "status" => 200,
            "data" => $result
        ];
    }
    
    public function getById($id) {
        $result = $this->product->getById($id);
        
        if ($result) {
            return [
                "status" => 200,
                "data" => $result
            ];
        } else {
            return [
                "status" => 404,
                "message" => "Product not found"
            ];
        }
    }
    
    public function create($data) {
        // Validate request
        if (!Validator::validateProductRequest($data)) {
            $this->logger->log("product_create", $data, "failed", "Invalid data");
            return [
                "status" => 400,
                "message" => "Invalid data provided"
            ];
        }
        
        try {
            $productId = $this->product->create($data);
            
            if ($productId) {
                $this->logger->log("product_create", $data, "success");
                return [
                    "status" => 201,
                    "message" => "Product created successfully",
                    "productId" => $productId
                ];
            } else {
                $this->logger->log("product_create", $data, "failed", "Database error");
                return [
                    "status" => 500,
                    "message" => "Failed to create product"
                ];
            }
        } catch (Exception $e) {
            $this->logger->log("product_create", $data, "failed", $e->getMessage());
            return [
                "status" => 500,
                "message" => "Failed to create product",
                "error" => $e->getMessage()
            ];
        }
    }
    
    public function update($id, $data) {
        // Validate request
        if (!Validator::validateProductRequest($data)) {
            $this->logger->log("product_update", $data, "failed", "Invalid data");
            return [
                "status" => 400,
                "message" => "Invalid data provided"
            ];
        }
        
        try {
            if ($this->product->update($id, $data)) {
                $this->logger->log("product_update", $data, "success");
                return [
                    "status" => 200,
                    "message" => "Product updated successfully"
                ];
            } else {
                $this->logger->log("product_update", $data, "failed", "Database error");
                return [
                    "status" => 500,
                    "message" => "Failed to update product"
                ];
            }
        } catch (Exception $e) {
            $this->logger->log("product_update", $data, "failed", $e->getMessage());
            return [
                "status" => 500,
                "message" => "Failed to update product",
                "error" => $e->getMessage()
            ];
        }
    }
    
    public function delete($id) {
        try {
            if ($this->product->delete($id)) {
                $this->logger->log("product_delete", ["id" => $id], "success");
                return [
                    "status" => 200,
                    "message" => "Product deleted successfully"
                ];
            } else {
                $this->logger->log("product_delete", ["id" => $id], "failed", "Database error");
                return [
                    "status" => 404,
                    "message" => "Product not found or could not be deleted"
                ];
            }
        } catch (Exception $e) {
            $this->logger->log("product_delete", ["id" => $id], "failed", $e->getMessage());
            return [
                "status" => 500,
                "message" => "Failed to delete product",
                "error" => $e->getMessage()
            ];
        }
    }
}
?>