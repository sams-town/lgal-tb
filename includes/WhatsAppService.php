<?php

class WhatsAppService
{
    private $apiKey;
    private $endpoint = 'https://api.fonnte.com/send';

    public function __construct()
    {
        $this->apiKey = $this->getApiKeyFromDb();
    }

    /**
     * Mengambil kunci API dari database (tabel system_integrations)
     */
    private function getApiKeyFromDb()
    {
        global $pdo;
        
        // Pastikan variabel $pdo tersedia secara global
        if (!isset($pdo)) {
            require_once __DIR__ . '/../config/database.php';
        }

        try {
            $stmt = $pdo->prepare("SELECT api_key FROM system_integrations WHERE provider_name = 'WhatsApp Gateway' AND status = 'Aktif' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            return $row['api_key'] ?? 'YOUR_FONNTE_API_KEY';
        } catch (Exception $e) {
            error_log("Gagal membaca API key WA: " . $e->getMessage());
            return 'YOUR_FONNTE_API_KEY';
        }
    }

    /**
     * Mengirim pesan WhatsApp
     *
     * @param string $target Nomor tujuan WhatsApp
     * @param string $message Isi pesan
     * @return array Status pengiriman
     */
    public function send($target, $message)
    {
        // Format nomor agar diawali 62
        $target = $this->formatPhoneNumber($target);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'target' => $target,
                'message' => $message,
                'countryCode' => '62',
            ),
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $this->apiKey
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return [
                'success' => false,
                'message' => 'cURL Error: ' . $err
            ];
        }

        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status'] == true) {
            return [
                'success' => true,
                'message' => 'Pesan terkirim ke target.'
            ];
        }

        return [
            'success' => false,
            'message' => $result['reason'] ?? 'Gagal ditolak oleh provider WhatsApp.'
        ];
    }

    /**
     * Format nomor HP agar diawali kode negara 62
     */
    private function formatPhoneNumber($number)
    {
        $number = preg_replace('/[^0-9]/', '', $number);
        
        if (substr($number, 0, 1) === '0') {
            $number = '62' . substr($number, 1);
        } elseif (substr($number, 0, 2) === '62') {
            return $number;
        } else {
            $number = '62' . $number;
        }
        
        return $number;
    }
}
?>
