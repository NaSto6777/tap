<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/StoreContext.php';
require_once __DIR__ . '/shipping_helper.php';

class FirstDeliveryPlugin
{
    private $conn;
    private $storeId;
    private $settings;
    private $config;

    public function __construct(PDO $conn = null, ?int $storeId = null)
    {
        if ($conn) {
            $this->conn = $conn;
        } else {
            $db = new Database();
            $this->conn = $db->getConnection();
        }

        if ($storeId !== null) {
            $this->storeId = (int) $storeId;
        } elseif (class_exists('StoreContext') && StoreContext::getId()) {
            $this->storeId = (int) StoreContext::getId();
        } else {
            $this->storeId = 1;
        }

        $this->settings = new Settings($this->conn, $this->storeId);

        $rawConfig = $this->settings->getSetting('plugin_firstdelivery_config', '{}');
        $this->config = json_decode($rawConfig, true) ?: [];

        if (empty($this->config['access_token'])) {
            throw new RuntimeException('First Delivery access token is not configured for this store.');
        }
    }

    private function getBaseUrl(): string
    {
        $base = $this->config['env_url'] ?? 'https://www.firstdeliverygroup.com/api/v2';
        return rtrim($base, '/');
    }

    private function getToken(): string
    {
        return (string) $this->config['access_token'];
    }

    /**
     * Sync a local order to First Delivery.
     * - Creates the order via /create
     * - Stores barCode in courier_tracking_number
     * - Sets courier_name = 'first_delivery'
     */
    public function syncOrder(int $orderId): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE id = ? AND store_id = ?");
        $stmt->execute([$orderId, $this->storeId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        $itemsStmt = $this->conn->prepare("SELECT * FROM order_items WHERE order_id = ? AND store_id = ?");
        $itemsStmt->execute([$orderId, $this->storeId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $nombreArticle = 0;
        $articleNames = [];
        foreach ($items as $it) {
            $qty = (int) ($it['quantity'] ?? 1);
            $nombreArticle += $qty;
            $articleNames[] = $it['product_name'] ?? ('#' . $it['product_id']);
        }
        if ($nombreArticle <= 0) {
            $nombreArticle = 1;
        }

        $clientName = $order['customer_name'] ?? '';
        $telephone  = $order['customer_phone'] ?? '';
        $adresse    = $order['shipping_address'] ?: ($order['billing_address'] ?? '');

        $gouvernerat = $order['shipping_governorate']
            ?? $this->settings->getSetting('firstdelivery_gouvernerat_default', 'tunis');
        $ville = $order['shipping_city']
            ?? $this->settings->getSetting('firstdelivery_ville_default', 'tunis');
        $gouvernerat = strtolower(trim($gouvernerat));
        $ville       = strtolower(trim($ville));

        $prix = (float) ($order['total_amount'] ?? 0);
        if ($prix < 0) {
            $prix = 0;
        }

        $payload = [
            'Client' => [
                'nom'         => $clientName,
                'gouvernerat' => $gouvernerat,
                'ville'       => $ville,
                'adresse'     => $adresse,
                'telephone'   => $telephone,
                'telephone2'  => '',
            ],
            'Produit' => [
                'prix'          => $prix,
                'designation'   => 'Commande ' . ($order['order_number'] ?? $orderId),
                'nombreArticle' => $nombreArticle,
                'commentaire'   => '',
                'article'       => implode(', ', array_slice($articleNames, 0, 3)),
                'nombreEchange' => 0,
            ],
        ];

        $url = $this->getBaseUrl() . '/create';

        [$ok, $responseData, $httpCode, $error] = $this->callApi('POST', $url, $payload);

        if (!$ok) {
            return ['success' => false, 'message' => 'API error: ' . $error, 'http_code' => $httpCode];
        }

        $barcode = $responseData['barCode'] ?? ($responseData['barcode'] ?? null);
        if (!$barcode) {
            return ['success' => false, 'message' => 'Missing barCode in First Delivery response'];
        }

        $upd = $this->conn->prepare("
            UPDATE orders 
            SET courier_name = 'first_delivery', courier_tracking_number = ?, courier_status_code = NULL 
            WHERE id = ? AND store_id = ?
        ");
        $upd->execute([$barcode, $orderId, $this->storeId]);

        return ['success' => true, 'barcode' => $barcode];
    }

    /**
     * Update shipping status for a given barcode using /etat.
     * - Stores raw code in courier_status_code
     */
    public function updateStatusByBarcode(string $barcode): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return ['success' => false, 'message' => 'Missing barcode'];
        }

        $url = $this->getBaseUrl() . '/etat';
        $payload = ['barCode' => $barcode];

        [$ok, $responseData, $httpCode, $error] = $this->callApi('POST', $url, $payload);

        if (!$ok) {
            return ['success' => false, 'message' => 'API error: ' . $error, 'http_code' => $httpCode];
        }

        $rawCode = $responseData['state'] ?? $responseData['etat'] ?? null;
        if ($rawCode === null) {
            return ['success' => false, 'message' => 'Missing state code in response'];
        }

        $rawCode = (int) $rawCode;
        $map = ShippingHelper::mapStatus('first_delivery', $rawCode);

        $upd = $this->conn->prepare("
            UPDATE orders 
            SET courier_status_code = ? 
            WHERE courier_tracking_number = ? AND store_id = ?
        ");
        $upd->execute([$rawCode, $barcode, $this->storeId]);

        return [
            'success'         => true,
            'code'            => $rawCode,
            'internal_status' => $map['internal'],
            'label'           => $map['label'],
        ];
    }

    /**
     * Convenience: update by order_id.
     */
    public function updateStatusByOrderId(int $orderId): array
    {
        $stmt = $this->conn->prepare("
            SELECT courier_tracking_number 
            FROM orders 
            WHERE id = ? AND store_id = ?
        ");
        $stmt->execute([$orderId, $this->storeId]);
        $barcode = $stmt->fetchColumn();

        if (!$barcode) {
            return ['success' => false, 'message' => 'No tracking number for this order'];
        }

        return $this->updateStatusByBarcode($barcode);
    }

    /**
     * Low-level HTTP client for First Delivery.
     */
    private function callApi(string $method, string $url, array $payload): array
    {
        $ch = curl_init();

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getToken(),
        ];

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ];

        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return [false, [], $code, $err ?: 'Unknown cURL error'];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [false, [], $code, 'Invalid JSON response'];
        }

        if ($code >= 200 && $code < 300) {
            return [true, $data, $code, null];
        }

        return [false, $data, $code, $data['message'] ?? 'Unknown API error'];
    }
}

