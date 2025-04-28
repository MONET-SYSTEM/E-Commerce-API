<?php
class Validator {
    public static function validateOrderRequest($data) {
        // Check if required fields exist
        if (!isset($data->userId) || !isset($data->items) || !isset($data->totalAmount)) {
            return false;
        }
        
        // Check if userId is valid
        if (!is_numeric($data->userId) || $data->userId <= 0) {
            return false;
        }
        
        // Check if items is an array and not empty
        if (!is_array($data->items) || empty($data->items)) {
            return false;
        }
        
        // Check if totalAmount is valid
        if (!is_numeric($data->totalAmount) || $data->totalAmount <= 0) {
            return false;
        }
        
        // Validate each item
        foreach ($data->items as $item) {
            if (!isset($item->productId) || !isset($item->quantity) || !isset($item->price)) {
                return false;
            }
            
            if (!is_numeric($item->productId) || $item->productId <= 0) {
                return false;
            }
            
            if (!is_numeric($item->quantity) || $item->quantity <= 0) {
                return false;
            }
            
            if (!is_numeric($item->price) || $item->price <= 0) {
                return false;
            }
        }
        
        return true;
    }
    
    public static function validateUserRequest($data) {
        // Check if required fields exist
        if (!isset($data->name) || !isset($data->email) || !isset($data->password)) {
            return false;
        }
        
        // Validate email format
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Validate name and password length
        if (strlen($data->name) < 3 || strlen($data->password) < 6) {
            return false;
        }
        
        return true;
    }
    
    public static function validateProductRequest($data) {
        // Check if required fields exist
        if (!isset($data->name) || !isset($data->price) || !isset($data->stock)) {
            return false;
        }
        
        // Validate name
        if (strlen($data->name) < 3) {
            return false;
        }
        
        // Validate price and stock
        if (!is_numeric($data->price) || $data->price <= 0) {
            return false;
        }
        
        if (!is_numeric($data->stock) || $data->stock < 0) {
            return false;
        }
        
        return true;
    }
}
?>