<?php

namespace Drupal\usf_ems_proxy\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

final class EmsClient {

  /**
   * External EMS base URL (constant per your latest note).
   */
    private const BASE_URL = 'http://vmi-ems-prod.usfca.edu/EmsPlatform';

  public function __construct(
    private ClientInterface $httpClient,
    private CacheBackendInterface $cache,
    private KeyRepositoryInterface $keyRepository,
    private LoggerInterface $logger,
  ) {}

  private function keyValue(string $key_id): string {
    $key = $this->keyRepository->getKey($key_id);
    if (!$key) {
      throw new \RuntimeException("Key {$key_id} not found.");
    }
    $val = trim((string) $key->getKeyValue());
    if ($val === '') {
      throw new \RuntimeException("Key {$key_id} is empty.");
    }
    return $val;
  }

  private function jwtExp(string $jwt): ?int {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
      return NULL;
    }

    $payloadB64 = strtr($parts[1], '-_', '+/');
    $payloadB64 .= str_repeat('=', (4 - strlen($payloadB64) % 4) % 4);

    $payload = json_decode((string) base64_decode($payloadB64), TRUE);
    return (is_array($payload) && isset($payload['exp'])) ? (int) $payload['exp'] : NULL;
  }

  public function proxySecret(): string {
    return $this->keyValue('ems_proxy_secret');
  }

  public function getClientToken(): string {
    // Use cached token if present.
    if ($cached = $this->cache->get('usf_ems_proxy:client_token')) {
      return (string) $cached->data;
    }

    $clientId = $this->keyValue('ems_client_id');
    $clientSecret = $this->keyValue('ems_client_secret');

    $url = self::BASE_URL . '/api/v1/clientauthentication';

    $res = $this->httpClient->post($url, [
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'json' => [
        'clientID' => $clientId,
        'secret' => $clientSecret,
      ],
      'timeout' => 20,
    ]);

    $data = json_decode((string) $res->getBody(), TRUE) ?? [];
    $token = (string) ($data['clientToken'] ?? '');

    if ($token === '') {
      $this->logger->error('EMS clientauthentication returned no clientToken.');
      throw new \RuntimeException('EMS clientauthentication returned no clientToken.');
    }

    // Cache until just before JWT expiry (or fallback TTL).
    $exp = $this->jwtExp($token);
    $ttl = $exp ? max(60, $exp - time() - 60) : (55 * 60);
    $this->cache->set('usf_ems_proxy:client_token', $token, time() + $ttl);

    return $token;
  }

  public function getBookingsPage(array $query): array {
    $url = self::BASE_URL . '/api/v1/bookings';
    $token = $this->getClientToken();

    try {
      $res = $this->httpClient->get($url, [
        'headers' => [
          'Accept' => 'application/json',
          'x-ems-api-token' => $token,
        ],
        'query' => $query,
        'timeout' => 30,
      ]);

      return json_decode((string) $res->getBody(), TRUE) ?? [];
    }
    catch (\GuzzleHttp\Exception\ClientException $e) {
      // If token expired unexpectedly, clear and retry once.
      if ($e->getResponse() && $e->getResponse()->getStatusCode() === 401) {
        $this->cache->delete('usf_ems_proxy:client_token');

        $token = $this->getClientToken();
        $res = $this->httpClient->get($url, [
          'headers' => [
            'Accept' => 'application/json',
            'x-ems-api-token' => $token,
          ],
          'query' => $query,
          'timeout' => 30,
        ]);

        return json_decode((string) $res->getBody(), TRUE) ?? [];
      }

      throw $e;
    }
  }

  /**
   * Fetch all pages and merge results into one payload (Feeds-friendly).
   */
  public function getBookingsAll(array $baseQuery, int $pageSize = 2000, int $maxPages = 100): array {
    $pageSize = min(max($pageSize, 1), 5000);

    $all = [];
    $page = 1;
    $pageCount = NULL;
    $resultsCount = NULL;

   while ($page <= $maxPages) {
  $data = $this->getBookingsPage($baseQuery + [
    'pageSize' => $pageSize,
    'page' => $page,
  ]);

  $results = $data['results'] ?? [];
  if (!is_array($results) || count($results) === 0) {
    break;
  }

  $all = array_merge($all, $results);

  if ($pageCount === NULL && isset($data['pageCount'])) {
    $pageCount = (int) $data['pageCount'];
  }
  if ($resultsCount === NULL && isset($data['resultsCount'])) {
    $resultsCount = (int) $data['resultsCount'];
  }

  if ($pageCount !== NULL && $page >= $pageCount) {
    break;
  }

  $page++;
}

    return [
      'page' => 0,
      'pageSize' => $pageSize,
      'pageCount' => $pageCount ?? (int) ceil(max(1, count($all)) / $pageSize),
      'resultsCount' => $resultsCount ?? count($all),
      'results' => $all,
    ];
  }

}