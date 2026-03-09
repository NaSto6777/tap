<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/StoreContext.php';
require_once __DIR__ . '/shipping_helper.php';

class ColissimoPlugin
{
    private $conn;
    private $storeId;
    private $settings;
    private $config;

    public function __construct(PDO $conn = null, ?int $storeId = null)
    {
        $this->conn = $conn ?: (new Database())->getConnection();
        $this->storeId = $storeId ?? (class_exists('StoreContext') ? (int) StoreContext::getId() : 1);
        $this->settings = new Settings($this->conn, $this->storeId);

        $raw = $this->settings->getSetting('plugin_colissimo_config', '{}');
        $this->config = json_decode($raw, true) ?: [];

        if (empty($this->config['colis_user']) || empty($this->config['colis_pass'])) {
            throw new RuntimeException('Colissimo credentials (colis_user / colis_pass) are not configured.');
        }
    }

    private function getWsdl(): string
    {
        return $this->config['wsdl_url']
            ?? 'http://delivery.colissimo.com.tn/wsColissimoGo/wsColissimoGo.asmx?wsdl';
    }

    private function buildSoapClient(): SoapClient
    {
        $wsdl = $this->getWsdl();
        $client = new SoapClient($wsdl, [
            'soap_version' => SOAP_1_2,
            'exceptions'   => true,
        ]);

        $auth = new stdClass();
        $auth->Uilisateur = $this->config['colis_user'];
        $auth->Pass       = $this->config['colis_pass'];

        $header = new SoapHeader('http://tempuri.org/', 'AuthHeader', $auth, false);
        $client->__setSoapHeaders($header);

        return $client;
    }

    /**
     * Create a shipment via AjouterColis and save tracking number.
     */
    public function createShipment(int $orderId): array
    {
        try {
            // Load order
            $stmt = $this->conn->prepare("SELECT * FROM orders WHERE id = ? AND store_id = ?");
            $stmt->execute([$orderId, $this->storeId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                return ['success' => false, 'message' => 'Order not found'];
            }

            // Load items
            $itemsStmt = $this->conn->prepare("SELECT * FROM order_items WHERE order_id = ? AND store_id = ?");
            $itemsStmt->execute([$orderId, $this->storeId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $nbPieces = 0;
            foreach ($items as $it) {
                $nbPieces += (int) ($it['quantity'] ?? 1);
            }
            if ($nbPieces <= 0) {
                $nbPieces = 1;
            }

            $colis = [
                'reference'   => $order['order_number'] ?? (string) $orderId,
                'client'      => $order['customer_name'] ?? '',
                'adresse'     => $order['shipping_address'] ?? '',
                'ville'       => $order['shipping_city'] ?? '',
                'gouvernorat' => $order['shipping_governorate'] ?? '',
                'nb_pieces'   => $nbPieces,
                'prix'        => (float) ($order['total_amount'] ?? 0),
                'tel1'        => $order['customer_phone'] ?? '',
                'tel2'        => '',
                'designation' => 'Commande ' . ($order['order_number'] ?? $orderId),
                'commentaire' => '',
                'type'        => 'VO',
                'echange'     => 0,
            ];

            $client = $this->buildSoapClient();

            $params = [
                'AuthHeader' => [
                    'Uilisateur' => $this->config['colis_user'],
                    'Pass'       => $this->config['colis_pass'],
                ],
                'pic' => json_encode($colis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];

            $response = $client->__soapCall('AjouterColis', [$params]);

            $arr = json_decode(json_encode($response), true);

            $jsonPayload = null;
            foreach ($arr as $v) {
                if (is_string($v)) {
                    $test = json_decode($v, true);
                    if (is_array($test) && isset($test['result_type'], $test['result_content'])) {
                        $jsonPayload = $test;
                        break;
                    }
                }
            }
            if (!$jsonPayload) {
                return ['success' => false, 'message' => 'Unexpected Colissimo response'];
            }

            $content = $jsonPayload['result_content'] ?? [];
            $barcode = $content['code_barre'] ?? ($content['code'] ?? null);

            if (!$barcode) {
                return ['success' => false, 'message' => 'No barcode returned from Colissimo'];
            }

            $initial = ShippingHelper::mapStatus('colissimo', 'En Attente');

            $upd = $this->conn->prepare("
                UPDATE orders
                SET courier_name = 'colissimo',
                    courier_tracking_number = ?,
                    courier_status_code = ?,
                    courier_status_text = ?
                WHERE id = ? AND store_id = ?
            ");
            $upd->execute([
                $barcode,
                $initial['internal'],
                'En Attente',
                $orderId,
                $this->storeId,
            ]);

            return ['success' => true, 'barcode' => $barcode];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Track order by barcode using GetColis and update status.
     */
    public function trackOrder(string $barcode): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return ['success' => false, 'message' => 'Missing barcode'];
        }

        try {
            $client = $this->buildSoapClient();

            $params = [
                'AuthHeader' => [
                    'Uilisateur' => $this->config['colis_user'],
                    'Pass'       => $this->config['colis_pass'],
                ],
                'code_barre' => $barcode,
            ];

            $response = $client->__soapCall('GetColis', [$params]);
            $arr = json_decode(json_encode($response), true);

            $jsonPayload = null;
            foreach ($arr as $v) {
                if (is_string($v)) {
                    $test = json_decode($v, true);
                    if (is_array($test) && isset($test['result_type'], $test['result_content'])) {
                        $jsonPayload = $test;
                        break;
                    }
                }
            }
            if (!$jsonPayload) {
                return ['success' => false, 'message' => 'Unexpected Colissimo tracking response'];
            }

            $content = $jsonPayload['result_content'] ?? [];
            $etat = $content['etat'] ?? ($content['Etat'] ?? null);
            if (!$etat) {
                return ['success' => false, 'message' => 'No status (etat) in Colissimo response'];
            }

            $map = ShippingHelper::mapStatus('colissimo', $etat);

            $upd = $this->conn->prepare("
                UPDATE orders
                SET courier_status_code = ?, courier_status_text = ?
                WHERE courier_tracking_number = ? AND store_id = ?
            ");
            $upd->execute([$map['internal'], $etat, $barcode, $this->storeId]);

            return [
                'success'         => true,
                'barcode'         => $barcode,
                'raw_status_text' => $etat,
                'internal_status' => $map['internal'],
                'label'           => $map['label'],
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

