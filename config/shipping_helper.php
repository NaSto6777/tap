<?php

class ShippingHelper
{
    const STATUS_PENDING    = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_SHIPPED    = 2;
    const STATUS_DELIVERED  = 3;
    const STATUS_RETURNED   = 4;
    const STATUS_CANCELED   = 5;

    /**
     * Generic dispatcher by courier name.
     * Returns ['internal' => int, 'label' => string].
     */
    public static function mapStatus(string $courierName, $externalCode): array
    {
        $name = strtolower(trim($courierName));

        switch ($name) {
            case 'first_delivery':
            case 'firstdelivery':
                return self::mapFirstDeliveryStatus((int) $externalCode);

            case 'colissimo':
                return self::mapColissimoStatus((string) $externalCode);

            // Future couriers (e.g. Aramex) can be added here.
            // case 'aramex':
            //     return self::mapAramexStatus($externalCode);

            default:
                return [
                    'internal' => self::STATUS_PENDING,
                    'label'    => 'En attente',
                ];
        }
    }

    /**
     * First Delivery: map raw API codes → internal status + human label.
     *
     * Internal 'Pending'    (0): 0, 100
     * Internal 'Processing' (1): 1, 8, 20, 101, 102
     * Internal 'Shipped'    (2): 103
     * Internal 'Delivered'  (3): 2
     * Internal 'Returned'   (4): 3, 5, 7, 11, 30, 31, 201, 202, 203
     * Internal 'Canceled'   (5): 6, 104, 204
     */
    private static function mapFirstDeliveryStatus(int $code): array
    {
        switch ($code) {
            // Pending
            case 0:   // En attente
            case 100: // Demande d'enlèvement créée
                return ['internal' => self::STATUS_PENDING,    'label' => 'En attente'];

            // Processing
            case 1:   // En cours
            case 8:   // Au magasin
            case 20:  // À vérifier
            case 101: // Demande d'enlèvement assignée
            case 102: // En cours d’enlèvement
                return ['internal' => self::STATUS_PROCESSING, 'label' => 'En cours'];

            // Shipped
            case 103: // Enlevé
                return ['internal' => self::STATUS_SHIPPED,    'label' => 'En expédition'];

            // Delivered
            case 2:   // Livré
                return ['internal' => self::STATUS_DELIVERED,  'label' => 'Livré'];

            // Returned
            case 3:   // Échange
            case 5:   // Retour Expéditeur
            case 7:   // Rtn client/agence
            case 11:  // Rtn dépôt
            case 30:  // Retour reçu
            case 31:  // Rtn définitif
            case 201: // Retour assigné
            case 202: // Retour en cours d'expédition
            case 203: // Retour enlevé
                return ['internal' => self::STATUS_RETURNED,   'label' => 'Retour'];

            // Canceled
            case 6:   // Supprimé
            case 104: // Demande d'enlèvement annulé
            case 204: // Retour annulé
                return ['internal' => self::STATUS_CANCELED,   'label' => 'Annulé'];

            default:
                return ['internal' => self::STATUS_PENDING,    'label' => 'En attente'];
        }
    }

    /**
     * Colissimo: map textual states → internal status + label.
     */
    private static function mapColissimoStatus(string $etat): array
    {
        $label = $etat !== '' ? $etat : 'En Attente';
        $s = mb_strtolower(trim($etat));

        // Pending
        if ($s === 'en attente' || $s === 'a enlever' || $s === 'à enlever') {
            return ['internal' => self::STATUS_PENDING, 'label' => $label];
        }

        // Processing
        if ($s === 'anomalie d’enlévement' || $s === 'anomalie d\'enlévement' ||
            $s === 'anomalie d’enlèvement' || $s === 'anomalie d\'enlèvement' ||
            $s === 'au dépôt' || $s === 'au depot' ||
            $s === 'en cours de livraison') {
            return ['internal' => self::STATUS_PROCESSING, 'label' => $label];
        }

        // Shipped
        if ($s === 'enlevé' || $s === 'enleve') {
            return ['internal' => self::STATUS_SHIPPED, 'label' => $label];
        }

        // Delivered
        if ($s === 'livré' || $s === 'livre' || $s === 'livré payé' || $s === 'livre paye') {
            return ['internal' => self::STATUS_DELIVERED, 'label' => $label];
        }

        // Returned and return-like
        if (in_array($s, [
            'retour dépôt','retour depot',
            'anomalie de livraison',
            'retour client agence',
            'retour définitif','retour definitif',
            'retour expéditeur','retour expediteur',
            'retour reçu','retour recu',
            'echange reçu','echange recu',
        ], true)) {
            return ['internal' => self::STATUS_RETURNED, 'label' => $label];
        }

        // Fallback
        return ['internal' => self::STATUS_PENDING, 'label' => $label];
    }

    /**
     * Helper for badge CSS class per internal status.
     */
    public static function getBadgeClass(int $internalStatus): string
    {
        switch ($internalStatus) {
            case self::STATUS_PENDING:
                return 'shipping-badge-pending';
            case self::STATUS_PROCESSING:
                return 'shipping-badge-processing';
            case self::STATUS_SHIPPED:
                return 'shipping-badge-shipped';
            case self::STATUS_DELIVERED:
                return 'shipping-badge-delivered';
            case self::STATUS_RETURNED:
                return 'shipping-badge-returned';
            case self::STATUS_CANCELED:
                return 'shipping-badge-canceled';
            default:
                return 'shipping-badge-pending';
        }
    }
}

