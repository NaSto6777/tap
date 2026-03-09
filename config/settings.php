<?php
class Settings {
    private $conn;
    private $table_name = "settings";
    private $store_id;

    public function __construct($conn = null, $storeId = null) {
        if ($conn) {
            $this->conn = $conn;
        } else {
            $database = new Database();
            $this->conn = $database->getConnection();
        }
        $this->store_id = $storeId !== null ? (int) $storeId : (class_exists('StoreContext') && StoreContext::isResolved() ? StoreContext::getId() : 1);
    }

    public function getSetting($key, $default = null) {
        $query = "SELECT value FROM " . $this->table_name . " WHERE store_id = ? AND setting_key = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->store_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $key);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['value'];
        }

        return $default;
    }

    public function setSetting($key, $value) {
        $query = "INSERT INTO " . $this->table_name . " (store_id, setting_key, value) VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE value = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->store_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $key);
        $stmt->bindParam(3, $value);
        $stmt->bindParam(4, $value);

        return $stmt->execute();
    }

    public function getAllSettings() {
        $query = "SELECT setting_key, value FROM " . $this->table_name . " WHERE store_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->store_id, PDO::PARAM_INT);
        $stmt->execute();

        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['value'];
        }

        return $settings;
    }

    public function getStoreId() {
        return $this->store_id;
    }
}
?>
