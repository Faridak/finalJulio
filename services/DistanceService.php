<?php
/**
 * Distance from our warehouse to a customer address.
 *
 * Uses the Google Maps Distance Matrix API when GOOGLE_MAPS_API_KEY is set in
 * .env; otherwise falls back to a per-country estimate so checkout never
 * breaks. Results are cached (shipping_distance_cache) so the same route is
 * never billed twice against the API.
 *
 * .env keys:
 *   GOOGLE_MAPS_API_KEY=...            (enables real driving distance)
 *   SHIPPING_ORIGIN_ADDRESS=...        (warehouse; default Mexico City)
 */
class DistanceService
{
    private $pdo;
    private $apiKey;
    private $origin;

    // No-key fallback: rough distance from a Mexico City warehouse.
    private static $countryEstimateKm = [
        'MX' => 600, 'US' => 2500, 'CA' => 4000, 'GT' => 1100, 'BZ' => 1150,
        'GB' => 8900, 'ES' => 9000, 'DE' => 9600, 'FR' => 9200,
        'CN' => 12400, 'JP' => 11300, 'AU' => 13500,
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->apiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
        if (strpos($this->apiKey, 'your_') === 0) $this->apiKey = '';
        $this->origin = $_ENV['SHIPPING_ORIGIN_ADDRESS'] ?? 'Mexico City, CDMX, Mexico';
    }

    /**
     * @return array{km: float, minutes: ?int, source: string}
     */
    public function distanceTo($destAddress, $countryCode = 'MX')
    {
        $dest = trim($destAddress) ?: $countryCode;
        $hash = md5(strtolower($this->origin . '|' . $dest));

        // cache first
        $c = $this->pdo->prepare("SELECT distance_km, duration_min, source FROM shipping_distance_cache WHERE route_hash = ?");
        $c->execute([$hash]);
        if ($row = $c->fetch(PDO::FETCH_ASSOC)) {
            return ['km' => (float) $row['distance_km'], 'minutes' => $row['duration_min'] !== null ? (int) $row['duration_min'] : null, 'source' => $row['source']];
        }

        $result = null;
        if ($this->apiKey !== '') {
            $result = $this->google($dest);
        }
        if ($result === null) {
            $km = self::$countryEstimateKm[strtoupper($countryCode)] ?? 10000;
            $result = ['km' => (float) $km, 'minutes' => null, 'source' => 'estimate'];
        }

        $this->pdo->prepare("
            INSERT IGNORE INTO shipping_distance_cache (route_hash, origin_text, dest_text, distance_km, duration_min, source)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$hash, $this->origin, mb_substr($dest, 0, 255), $result['km'], $result['minutes'], $result['source']]);

        return $result;
    }

    /** Google Distance Matrix; null on any failure (quota, no route, bad key). */
    private function google($dest)
    {
        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . http_build_query([
            'origins' => $this->origin,
            'destinations' => $dest,
            'units' => 'metric',
            'key' => $this->apiKey,
        ]);
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
        $res = curl_exec($curl);
        curl_close($curl);
        if ($res === false) return null;
        $j = json_decode($res, true);
        $el = $j['rows'][0]['elements'][0] ?? null;
        if (($j['status'] ?? '') !== 'OK' || ($el['status'] ?? '') !== 'OK') return null;
        return [
            'km' => round($el['distance']['value'] / 1000, 1),
            'minutes' => (int) round($el['duration']['value'] / 60),
            'source' => 'google',
        ];
    }
}
