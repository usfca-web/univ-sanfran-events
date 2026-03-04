<?php

namespace Drupal\usf_ems_proxy\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\usf_ems_proxy\Service\EmsClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class EmsBookingsController extends ControllerBase {

  public function __construct(
    private EmsClient $emsClient,
    private CacheBackendInterface $cache,
  ) {}

  public function bookingsAll(Request $request) {

    // Protect endpoint with shared secret.
    $k = (string) $request->query->get('k', '');
    if (!hash_equals($this->emsClient->proxySecret(), $k)) {
      return new JsonResponse(['error' => 'Forbidden'], 403);
    }

    $tz = new \DateTimeZone(date_default_timezone_get());
    $range = (string) $request->query->get('range', '3months');

    $min = (string) $request->query->get('minReserveStartTime', '');
    $max = (string) $request->query->get('maxReserveStartTime', '');

    // Dynamic date range if not explicitly provided
    if ($min === '' || $max === '') {

      $dmin = new \DateTime('today', $tz);
      $dmax = new \DateTime('today', $tz);

      switch ($range) {
        case '30days':
          $dmax->modify('+30 days');
          break;

        case '6months':
          $dmax->modify('+6 months');
          break;

        case '12months':
          $dmax->modify('+12 months');
          break;

        case '3months':
        default:
          $dmax->modify('+3 months');
          break;
      }

      if ($min === '') {
        $min = $dmin->format('c');
      }

      if ($max === '') {
        $max = $dmax->format('c');
      }

    }

    // Page size sanity limits
    $pageSize = (int) $request->query->get('pageSize', 2000);
    $pageSize = min(max($pageSize, 1), 5000);

    // ---------------------------
    // Event Type Parsing
    // ---------------------------

    $eventTypeIds = [];

    $rawAll = $request->query->all();
    $raw = $rawAll['eventTypeIds'] ?? '';

    if (is_array($raw)) {

      // eventTypeIds[]=12&eventTypeIds[]=14
      $eventTypeIds = array_values(array_unique(array_filter(array_map('intval', $raw))));

    } else {

      $raw = trim((string) $raw);

      if ($raw !== '') {
        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $eventTypeIds = array_values(array_unique(array_filter(array_map('intval', $parts))));
      }

    }

    // Fallback single event type
    if (empty($eventTypeIds)) {

      $eventTypeIdSingle = (int) $request->query->get('eventTypeId', 0);

      if ($eventTypeIdSingle > 0) {
        $eventTypeIds = [$eventTypeIdSingle];
      }

    }

    // Ensure consistent cache keys
    sort($eventTypeIds);

    // ---------------------------
    // Base EMS Query
    // ---------------------------

    $baseQuery = array_filter([
      'minReserveStartTime' => $min,
      'maxReserveStartTime' => $max,
      'statusId' => 6,
    ], static fn($v) => $v !== '' && $v !== 0);

    // ---------------------------
    // Cache Setup
    // ---------------------------

    $useCache = (int) $request->query->get('cache', 1) !== 0;

    $cid = 'usf_ems_proxy:bookings_all:' . sha1(json_encode([
      $baseQuery,
      $pageSize,
      $eventTypeIds,
      $range,
    ]));

    if ($useCache && ($cached = $this->cache->get($cid))) {

      $response = new CacheableJsonResponse($cached->data);

      $response->getCacheableMetadata()->setCacheMaxAge(0);

      $response->headers->set('Cache-Control', 'private, max-age=0');
      $response->headers->set('X-USF-EMS-Proxy-Cache', 'HIT');
      $response->headers->set('X-USF-EMS-Min', $min);
      $response->headers->set('X-USF-EMS-Max', $max);
      $response->headers->set('X-USF-EMS-Range', $range);
      $response->headers->set('X-USF-EMS-EventTypeIds', implode(',', $eventTypeIds));
      $response->headers->set('X-USF-EMS-Cache-Enabled', $useCache ? '1' : '0');

      return $response;

    }

    // ---------------------------
    // Fetch from EMS
    // ---------------------------

    $allById = [];

    try {

      if (empty($eventTypeIds)) {

        $data = $this->emsClient->getBookingsAll($baseQuery, $pageSize);

        foreach (($data['results'] ?? []) as $row) {

          if (isset($row['id'])) {
            $allById[(string) $row['id']] = $row;
          }

        }

      } else {

        foreach ($eventTypeIds as $tid) {

          $data = $this->emsClient->getBookingsAll(
            $baseQuery + ['eventTypeId' => $tid],
            $pageSize
          );

          foreach (($data['results'] ?? []) as $row) {

            if (isset($row['id'])) {
              $allById[(string) $row['id']] = $row;
            }

          }

        }

      }

    }
    catch (\Throwable $e) {

      $response = new CacheableJsonResponse([
        'error' => 'Upstream EMS request failed',
        'message' => $e->getMessage(),
      ], 502);

      $response->getCacheableMetadata()->setCacheMaxAge(0);

      $response->headers->set('Cache-Control', 'private, max-age=0');
      $response->headers->set('X-USF-EMS-Proxy-Cache', 'BYPASS');
      $response->headers->set('X-USF-EMS-Min', $min);
      $response->headers->set('X-USF-EMS-Max', $max);
      $response->headers->set('X-USF-EMS-Range', $range);
      $response->headers->set('X-USF-EMS-EventTypeIds', implode(',', $eventTypeIds));
      $response->headers->set('X-USF-EMS-Cache-Enabled', $useCache ? '1' : '0');

      return $response;

    }

    // ---------------------------
    // Merge + Sort
    // ---------------------------

    $results = array_values($allById);

    usort($results, static function ($a, $b) {
      return strcmp(
        (string) ($a['eventStartTime'] ?? ''),
        (string) ($b['eventStartTime'] ?? '')
      );
    });

    // ---------------------------
    // Final Output
    // ---------------------------

    $out = [
      'page' => 1,
      'pageSize' => $pageSize,
      'pageCount' => 1,
      'resultsCount' => count($results),
      'results' => $results,
    ];

    if ($useCache) {
      $this->cache->set($cid, $out, time() + 300);
    }

    $response = new CacheableJsonResponse($out);

    $response->getCacheableMetadata()->setCacheMaxAge(0);

    $response->headers->set('Cache-Control', 'private, max-age=0');
    $response->headers->set('X-USF-EMS-Proxy-Cache', 'MISS');
    $response->headers->set('X-USF-EMS-Min', $min);
    $response->headers->set('X-USF-EMS-Max', $max);
    $response->headers->set('X-USF-EMS-Range', $range);
    $response->headers->set('X-USF-EMS-EventTypeIds', implode(',', $eventTypeIds));
    $response->headers->set('X-USF-EMS-Cache-Enabled', $useCache ? '1' : '0');

    return $response;

  }

}